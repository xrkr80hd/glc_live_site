<?php
declare(strict_types=1);

function sp_json($value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function sp_decode(string $value): array { $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR); return is_array($data) ? $data : []; }
function sp_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function sp_stations(): array {
    return ['computer1' => ['Computer 1', 'In-house presentation · Elle-Belle'],
        'computer2' => ['Computer 2', 'OBS / YouTube broadcast'],
        'computer3' => ['Computer 3', 'Stream audio · Flow 8 / Dante / Fender Studio'],
        'computer4' => ['Computer 4', 'Stream presentation / cameras · Trav']];
}
function sp_schema(PDO $pdo): void {
    $sql = file_get_contents(__DIR__ . '/../../../database/service_planner.sql');
    if ($sql === false) throw new RuntimeException('Planner schema file is missing.');
    foreach (explode(';', $sql) as $statement) if (trim($statement)) $pdo->exec($statement);
    $defaults = sp_decode(file_get_contents(__DIR__.'/default-tasks.json'));
    $stmt = $pdo->prepare('INSERT IGNORE INTO service_task_definitions (task_key,station,phase,sort_order,title,instructions,resources_json) VALUES (?,?,?,?,?,?,?)');
    foreach ($defaults as $task) $stmt->execute([$task['task_key'],$task['station'],$task['phase'],$task['sort_order'],$task['title'],$task['instructions'],sp_json($task['resources'])]);
}
function sp_definitions(PDO $pdo): array {
    return $pdo->query('SELECT * FROM service_task_definitions ORDER BY station, FIELD(phase,\'pre\',\'start\',\'end\'), sort_order')->fetchAll(PDO::FETCH_ASSOC);
}
function sp_service(PDO $pdo, int $id, bool $lock = false): array {
    $stmt=$pdo->prepare('SELECT * FROM service_plans WHERE id=?'.($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id]); $service=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) throw new InvalidArgumentException('Sunday service was not found.');
    $service['sermon']=sp_decode($service['sermon_json']);
    $service['worship']=sp_decode($service['worship_json']);
    return $service;
}
function sp_tasks(PDO $pdo,array $service): array {
    return (int)$service['is_archived'] ? sp_decode($service['definition_snapshot']) : sp_definitions($pdo);
}
function sp_completions(PDO $pdo,int $id): array {
    $stmt=$pdo->prepare('SELECT * FROM service_task_completions WHERE service_id=?');$stmt->execute([$id]);
    $out=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$row['task_key']]=$row;return $out;
}
function sp_status(array $tasks,array $completions,string $station): string {
    $all=0;$done=0;$pre=0;$preDone=0;$hasEnd=false;
    foreach($tasks as $task){
        if($task['station']!==$station)continue;
        $complete=!empty($completions[$task['task_key']]['is_complete']);
        $all++;$done+=(int)$complete;
        if($task['phase']==='pre'){$pre++;$preDone+=(int)$complete;}
        if($task['phase']==='end')$hasEnd=true;
    }
    if($all && $done===$all && $hasEnd)return 'COMPLETE';
    if($pre && $preDone===$pre)return 'READY';
    return $done ? 'IN PROGRESS' : 'NOT STARTED';
}
function sp_feed(array $service,array $tasks,array $completions): array {
    $statuses=['sermon'=>!empty($service['sermon_ready'])?'READY':(array_filter($service['sermon'])?'IN PROGRESS':'NOT STARTED'),
        'worship'=>!empty($service['worship_ready'])?'READY':($service['worship']?'IN PROGRESS':'NOT STARTED')];
    foreach(sp_stations() as $key=>$station)$statuses[$key]=sp_status($tasks,$completions,$key);
    return $statuses;
}
function sp_sunday(string $date): string {
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$parsed || $parsed->format('Y-m-d')!==$date || $parsed->format('w')!=='0')throw new InvalidArgumentException('Choose a valid Sunday date.');
    return $date;
}
function sp_text($value,int $limit=12000): string {
    if(!is_string($value) || strlen($value)>$limit)throw new InvalidArgumentException('A field is missing or too long.');return trim($value);
}
function sp_safe_url(string $url): bool {
    return (bool)preg_match('~^/(?!/)[^\s]*$~',$url) || (filter_var($url,FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['https','http'],true));
}
function sp_resources(string $links,string $image): array {
    $resources=[];
    foreach(preg_split('/\R/',trim($links)) as $url){$url=trim($url);if(!$url)continue;if(!sp_safe_url($url))throw new InvalidArgumentException('Reference links must be website URLs or internal paths.');$resources[]=['type'=>'link','url'=>$url];}
    if($image!==''){if(!sp_safe_url($image))throw new InvalidArgumentException('Reference image must be a website URL or internal path.');$resources[]=['type'=>'image','url'=>$image];}
    return $resources;
}

// One row lock serializes edits to the shared Sunday. Optimistic revisions prevent
// stale forms or checkbox actions overwriting a newer edit from another device.
function sp_mutate(PDO $pdo,array $input,string $actor): void {
    $action=$input['action']??'';
    if($action==='create'){
        $date=sp_sunday(sp_text($input['service_date']??'',10));
        $stmt=$pdo->prepare('INSERT IGNORE INTO service_plans (service_date,sermon_json,worship_json,definition_snapshot,created_by,updated_by) VALUES (?,\'{}\',\'[]\',?,?,?)');
        $stmt->execute([$date,sp_json(sp_definitions($pdo)),$actor,$actor]);return;
    }
    $pdo->beginTransaction();
    try{
        $service=sp_service($pdo,(int)($input['service_id']??0),true);
        if($service['is_archived'])throw new InvalidArgumentException('This service is archived and read-only.');
        if($action==='task'){
            $key=sp_text($input['task_key']??'',80);$task=null;
            $tasks=sp_tasks($pdo,$service);
            foreach($tasks as $row)if($row['task_key']===$key)$task=$row;
            if(!$task)throw new InvalidArgumentException('Checklist task was not found.');
            $completions=sp_completions($pdo,(int)$service['id']);
            $old=$completions[$key]??['revision'=>0];
            if((int)$old['revision']!==(int)($input['revision']??-1))throw new RuntimeException('This task changed on another device. Refresh and try again.',409);
            $checked=($input['checked']??'')==='1';
            if($checked && str_starts_with($task['title'],'Mark ')){
                foreach($tasks as $other){
                    if($other['station']!==$task['station'] || $other['task_key']===$key)continue;
                    $required=$task['phase']==='end' || $other['phase']==='pre';
                    if($required && empty($completions[$other['task_key']]['is_complete']))throw new InvalidArgumentException('Complete the preceding station tasks before marking it ready or complete.');
                }
            }
            $stmt=$pdo->prepare('INSERT INTO service_task_completions (service_id,task_key,is_complete,revision,updated_by) VALUES (?,?,?,1,?) ON DUPLICATE KEY UPDATE is_complete=VALUES(is_complete),revision=revision+1,updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
            $stmt->execute([$service['id'],$key,(int)$checked,$actor]);
            $stmt=$pdo->prepare('UPDATE service_plans SET updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');$stmt->execute([$actor,$service['id']]);
        }else{
            if((int)$service['revision']!==(int)($input['revision']??-1))throw new RuntimeException('Sunday information changed on another device. Reload before saving; your text has not been overwritten.',409);
            if($action==='sermon'){
                $sermon=[];foreach(['title','primary_scripture','additional_scriptures','media_notes','special_media','videos','presentation_instructions'] as $key)$sermon[$key]=sp_text($input[$key]??'');
                $ready=!empty($input['ready']);
                if($ready && (!$sermon['title'] || !$sermon['primary_scripture']))throw new InvalidArgumentException('Enter the sermon title and primary scripture before marking it ready.');
                $stmt=$pdo->prepare('UPDATE service_plans SET sermon_json=?,sermon_ready=?,revision=revision+1,updated_by=? WHERE id=?');$stmt->execute([sp_json($sermon),(int)$ready,$actor,$service['id']]);
            }elseif($action==='worship'){
                $songs=$input['songs']??[];if(!is_array($songs) || count($songs)>60)throw new InvalidArgumentException('Use up to 60 songs per service.');$clean=[];
                foreach($songs as $song){if(!is_array($song))throw new InvalidArgumentException('Invalid song.');$row=[];foreach(['title','key','lead','additional','notes'] as $field)$row[$field]=sp_text($song[$field]??'');if(!$row['title'])throw new InvalidArgumentException('Each song needs a title.');$clean[]=$row;}
                if(!empty($input['ready']) && !$clean)throw new InvalidArgumentException('Add a song before marking worship ready.');
                $stmt=$pdo->prepare('UPDATE service_plans SET worship_json=?,worship_ready=?,revision=revision+1,updated_by=? WHERE id=?');$stmt->execute([sp_json($clean),(int)!empty($input['ready']),$actor,$service['id']]);
            }elseif($action==='archive'){
                $stmt=$pdo->prepare('UPDATE service_plans SET is_archived=1,definition_snapshot=?,revision=revision+1,updated_by=? WHERE id=?');$stmt->execute([sp_json(sp_definitions($pdo)),$actor,$service['id']]);
            }elseif($action==='instructions'){
                $key=sp_text($input['task_key']??'',80);$instructions=sp_text($input['instructions']??'',30000);
                $resources=sp_resources(sp_text($input['links']??''),sp_text($input['image']??'',2000));
                $stmt=$pdo->prepare('UPDATE service_task_definitions SET instructions=?,resources_json=?,revision=revision+1,updated_by=? WHERE task_key=? AND revision=?');
                $stmt->execute([$instructions,sp_json($resources),$actor,$key,(int)($input['definition_revision']??-1)]);
                if($stmt->rowCount()!==1)throw new RuntimeException('Instructions changed on another device. Reload before saving.',409);
            }else throw new InvalidArgumentException('Unknown planner action.');
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
