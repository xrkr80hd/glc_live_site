<?php
declare(strict_types=1);
require_once __DIR__.'/../php/admin/service-planner/model.php';
require_once __DIR__.'/../php/admin/workspace-navigation.php';
function check(bool $value,string $message): void {if(!$value)throw new RuntimeException($message);}
function rejected(callable $action,int $code=0): void {try{$action();}catch(Throwable $e){check(!$code || $e->getCode()===$code,'Wrong rejection status');return;}throw new RuntimeException('Expected rejection');}
$dsn=getenv('PLANNER_TEST_DSN');
if(!$dsn || !str_contains($dsn,'_test'))throw new RuntimeException('Use an isolated _test database via PLANNER_TEST_DSN. Never run against production.');
if(!str_contains($dsn,'charset='))$dsn.=';charset=utf8mb4';
$pdo=new PDO($dsn,getenv('PLANNER_TEST_USER')?:'root',getenv('PLANNER_TEST_PASSWORD')?:'',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
sp_schema($pdo);sp_schema($pdo);
$expected=['computer1'=>14,'computer2'=>23,'computer3'=>17,'computer4'=>14];
$tasks=sp_definitions($pdo);check(count($tasks)===68,'All supplied checklist tasks must exist exactly once');
foreach($expected as $station=>$count)check(count(array_filter($tasks,fn($t)=>$t['station']===$station))===$count,'Station task count');
rejected(fn()=>sp_sunday('2026-10-05'));rejected(fn()=>sp_sunday('2026-02-30'));
check(!sp_safe_url('javascript:alert(1)') && !sp_safe_url('//evil.test') && sp_safe_url('/uploads/reference.png'),'Safe resources');
$groups=admin_workspace_groups([['label'=>'Main','items'=>[['key'=>'announcements','href'=>'/existing','label'=>'A'],['key'=>'visits','href'=>'/visits','label'=>'V']]]]);
check($groups[0]['items'][0]['key']==='visits','Unrelated navigation remains');check(admin_workspace_active('seasonal-features')==='media-management','Featured pages belong to MMS');
$date='2099-10-04'; // Dedicated test Sunday, never a production record.
sp_sunday($date);sp_mutate($pdo,['action'=>'create','service_date'=>$date],'test-one');sp_mutate($pdo,['action'=>'create','service_date'=>$date],'test-two');
$stmt=$pdo->prepare('SELECT id FROM service_plans WHERE service_date=?');$stmt->execute([$date]);$id=(int)$stmt->fetchColumn();
try {
    $service=sp_service($pdo,$id);check((int)$service['revision']===0,'Fresh service');
    $base=['service_id'=>$id,'revision'=>0];
    sp_mutate($pdo,$base+['action'=>'sermon','title'=>'Shared title','primary_scripture'=>'John 3:16','ready'=>'1'],'test-one');
    check(sp_service($pdo,$id)['sermon']['title']==='Shared title','Sermon persists');
    rejected(fn()=>sp_mutate($pdo,$base+['action'=>'sermon','title'=>'Stale overwrite'],'test-two'),409);
    sp_mutate($pdo,['service_id'=>$id,'revision'=>1,'action'=>'worship','ready'=>'1','songs'=>[
        ['title'=>'First','key'=>'G','lead'=>'Erin','additional'=>'Team','notes'=>'Acoustic guitar'],['title'=>'Second','key'=>'D','lead'=>'Team']]],'test-two');
    check(sp_service($pdo,$id)['worship'][1]['title']==='Second','Worship order persists');
    $key='computer1_pre_01';
    sp_mutate($pdo,['service_id'=>$id,'revision'=>0,'action'=>'task','task_key'=>$key,'checked'=>'1'],'test-one');
    // Independent PDO connection represents another authorized device.
    $other=new PDO($dsn,getenv('PLANNER_TEST_USER')?:'root',getenv('PLANNER_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    check((int)sp_completions($other,$id)[$key]['is_complete']===1,'Second device sees saved completion');
    rejected(fn()=>sp_mutate($pdo,['service_id'=>$id,'revision'=>0,'action'=>'task','task_key'=>$key,'checked'=>'0'],'test-two'),409);
    rejected(fn()=>sp_mutate($pdo,['service_id'=>$id,'revision'=>0,'action'=>'task','task_key'=>'computer1_pre_14','checked'=>'1'],'test-one'));
    check(sp_status($tasks,sp_completions($pdo,$id),'computer1')==='IN PROGRESS','Readiness derives from completion');
    foreach($tasks as $task)if($task['station']==='computer1' && $task['task_key']!==$key)sp_mutate($pdo,['service_id'=>$id,'revision'=>0,'action'=>'task','task_key'=>$task['task_key'],'checked'=>'1'],'test-one');
    check(sp_status($tasks,sp_completions($pdo,$id),'computer1')==='READY','Pre-service readiness');
    foreach($tasks as $task)if($task['station']==='computer2')sp_mutate($pdo,['service_id'=>$id,'revision'=>0,'action'=>'task','task_key'=>$task['task_key'],'checked'=>'1'],'test-two');
    check(sp_status($tasks,sp_completions($pdo,$id),'computer2')==='COMPLETE','Broadcast completion');
    sp_mutate($pdo,['service_id'=>$id,'revision'=>2,'action'=>'archive'],'test-one');
    $archived=sp_service($pdo,$id);check((bool)$archived['is_archived'],'Archive persists');
    rejected(fn()=>sp_mutate($pdo,['service_id'=>$id,'revision'=>3,'action'=>'sermon'],'test-two'));
    $before=sp_tasks($pdo,$archived)[0]['instructions'];
    $stmt=$pdo->prepare('UPDATE service_task_definitions SET instructions=? WHERE task_key=?');$stmt->execute(['Changed later',$tasks[0]['task_key']]);
    check(sp_tasks($pdo,sp_service($pdo,$id))[0]['instructions']===$before,'Archived instructions do not change');
    $stmt->execute([$before,$tasks[0]['task_key']]);
    sp_mutate($pdo,['action'=>'create','service_date'=>'2099-10-11'],'test-one');
    $next=(int)$pdo->query("SELECT id FROM service_plans WHERE service_date='2099-10-11'")->fetchColumn();
    check($next!==$id && !sp_completions($pdo,$next),'Next Sunday is independent');
    check(sp_service($pdo,$id)['sermon']['title']==='Shared title','Previous Sunday preserved');
    $pdo->prepare('DELETE FROM service_plans WHERE id=?')->execute([$next]);
    echo "Service planner integration checks passed (isolated MariaDB).\n";
} finally {
    $pdo->prepare('DELETE FROM service_task_completions WHERE service_id=?')->execute([$id]);
    $pdo->prepare('DELETE FROM service_plans WHERE id=?')->execute([$id]);
}
