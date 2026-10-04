<?php
declare(strict_types=1);
require_once __DIR__.'/presentation.php';
admin_require_login();
sp_require_access(sp_can_operate_media());
header('Cache-Control: no-store');
$uploadedPath=null;
$pdo=db();$id=max(0,(int)($_GET['service_id']??0));$service=null;$error='';
$task=null;$stationTasks=[];$number=0;
$station=(string)($_GET['station']??'');$key=(string)($_GET['task']??'');$edit=($_GET['edit']??'')==='1';
try {
    if($id)$service=sp_service($pdo,$id);
    $tasks=$service?sp_tasks($pdo,$service):sp_definitions($pdo);
    $task=null;$number=0;$stationTasks=[];
    foreach($tasks as $candidate)if($candidate['task_key']===$key){$task=$candidate;$station=$candidate['station'];break;}
    if($key && !$task){http_response_code(404);$error='This guide task was not found.';}
    if($station && !isset(sp_stations()[$station])){http_response_code(404);$error='This station was not found.';$station='';}
    if($station)$stationTasks=array_values(array_filter($tasks,static fn($row)=>$row['station']===$station));
    foreach($stationTasks as $i=>$row)if($row['task_key']===$key)$number=$i+1;
    if($edit)sp_require_access($task && (!$service || !$service['is_archived']) && sp_can_save_action('instructions'));
    if($_SERVER['REQUEST_METHOD']==='POST'){
        sp_require_access($edit);
        verify_csrf($_POST['csrf_token']??'');
        $input=$_POST;
        if(isset($_FILES['screenshot']) && $_FILES['screenshot']['error']!==UPLOAD_ERR_NO_FILE){
            $upload=$_FILES['screenshot'];
            if($upload['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('The server rejected this upload. Try a smaller screenshot.');
            if($upload['size']>8*1024*1024)throw new InvalidArgumentException('Use a screenshot up to 8 MB.');
            $info=getimagesize($upload['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if(!$info || !isset($extensions[$info['mime']]))throw new InvalidArgumentException('Use a JPG, PNG, or WebP screenshot.');
            ensure_upload_directory();$dir=UPLOAD_DIR.'/service-guides';
            if(!is_dir($dir) && !mkdir($dir,0755,true))throw new RuntimeException('Screenshot storage is unavailable.');
            $filename=bin2hex(random_bytes(16)).'.'.$extensions[$info['mime']];$uploadedPath=$dir.'/'.$filename;
            if(!move_uploaded_file($upload['tmp_name'],$uploadedPath))throw new RuntimeException('Could not store the screenshot.');
            $input['image']='/uploads/service-guides/'.$filename;
        }
        if($service)sp_mutate($pdo,$input,(string)admin_current_user()['username']);
        else sp_save_instructions($pdo,$input,(string)admin_current_user()['username']);
        admin_redirect(sp_guide_url($id,$station,$key));
    }
}catch(Throwable $e){if($uploadedPath && is_file($uploadedPath))unlink($uploadedPath);$error=$e instanceof InvalidArgumentException || $e->getCode()===409?$e->getMessage():'The guide could not load or save. Try again.';if(!($e instanceof InvalidArgumentException) && $e->getCode()!==409)error_log($e->getMessage());}
$resources=$task?sp_decode($task['resources_json']??'[]'):[];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=sp_e($task?$task['title']:'Station Manual')?> · Liberty Church</title><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/admin-workspace.css?v=3"></head>
<body class="admin-dark guide-body"><main class="workspace guide-workspace">
    <header class="guide-header"><div><p class="planner-eyebrow">LIBERTY CHURCH</p><h1>Station Manual</h1></div><button type="button" class="btn btn-secondary" id="close-guide" hidden>Close guide</button></header>
    <nav class="planner-breadcrumb"><a href="<?=sp_e(sp_guide_url($id))?>">All stations</a><?php if($station): ?><span aria-hidden="true">/</span><a href="<?=sp_e(sp_guide_url($id,$station))?>"><?=sp_e(sp_stations()[$station][0])?></a><?php endif; ?></nav>
    <?php if($service): ?><p><?=sp_e((new DateTimeImmutable($service['service_date']))->format('F j, Y'))?><?=$service['is_archived']?' · Archived instructions':''?></p><?php endif; ?>
    <?php if($error): ?><p class="flash flash-error" role="alert"><?=sp_e($error)?></p><?php endif; ?>
    <?php if(!$error || $edit): ?>
    <?php if($task): ?>
        <section class="workspace-section"><p class="planner-eyebrow"><?=sp_e(sp_stations()[$station][0])?> · TASK <?=$number?></p><h2><?=sp_e($task['title'])?></h2>
        <?php if(!$edit): ?>
            <p class="guide-intro">Follow these steps, then return to your checklist and check the task when it is done.</p>
            <?php $lines=preg_split('/\R/',$task['instructions']);$steps=[];$notes=[];foreach($lines as $line){if(preg_match('/^\s*\d+[.)]\s*(.+)$/u',$line,$match))$steps[]=$match[1];elseif(trim($line))$notes[]=trim($line);} ?>
            <?php if($steps): ?><ol class="guide-steps"><?php foreach($steps as $step): ?><li><?=sp_e($step)?></li><?php endforeach; ?></ol><?php endif; ?>
            <?php if($notes): ?><div class="guide-notes"><h3>Important notes</h3><div class="instruction-text"><?=sp_e(implode("\n",$notes))?></div></div><?php endif; ?>
            <?php if(!$steps && !$notes): ?><p>The regular operator still needs to add instructions for this task.</p><?php endif; ?>
            <?php if($key==='computer2_pre_06'): ?>
            <aside class="guide-notes"><h3>Publish the existing church player</h3><ol class="guide-steps"><li>In YouTube, open the correct Liberty Church service.</li><li>Select Share, then Embed, and copy the embed code.</li><li>Open the existing <a class="guide-link" href="/php/admin/stream/index.php" target="_blank" rel="noopener">website livestream settings ↗</a>.</li><li>Paste the code into its embed box, then use its existing Start Live control when appropriate for the service.</li></ol><p>Your checklist stays open while you work. OBS and YouTube remain under your control.</p></aside>
            <?php endif; ?>
            <?php foreach($resources as $resource): if(!sp_safe_url($resource['url']??''))continue; ?><?php if(($resource['type']??'')==='image'): ?><figure><img src="<?=sp_e($resource['url'])?>" alt="Reference for <?=sp_e($task['title'])?>" loading="lazy" style="max-width:100%;height:auto"></figure><?php else: ?><p><a class="guide-link" href="<?=sp_e($resource['url'])?>" target="_blank" rel="noopener noreferrer">Open reference resource ↗</a></p><?php endif; ?><?php endforeach; ?>
            <nav class="guide-step-navigation" aria-label="Other tasks">
                <?php if($number>1): ?><a class="btn btn-secondary" href="<?=sp_e(sp_guide_url($id,$station,$stationTasks[$number-2]['task_key']))?>">← Previous task</a><?php endif; ?>
                <?php if($number<count($stationTasks)): ?><a class="btn btn-secondary" href="<?=sp_e(sp_guide_url($id,$station,$stationTasks[$number]['task_key']))?>">Next task →</a><?php endif; ?>
            </nav>
            <?php if(!$service || !$service['is_archived']): ?><footer class="guide-edit-link"><a href="<?=sp_e(sp_guide_url($id,$station,$key,true))?>">Update these instructions</a></footer><?php endif; ?>
        <?php else: ?>
            <h3>Edit reusable instructions</h3><p>Use one numbered step per line. Archived Sundays retain their saved instructions.</p>
            <form method="post" enctype="multipart/form-data"><?php sp_hidden($service??['id'=>0,'revision'=>0],'instructions'); ?><input type="hidden" name="task_key" value="<?=sp_e($key)?>"><input type="hidden" name="definition_revision" value="<?=(int)$task['revision']?>">
                <label for="guide-instructions">Steps, warnings, and notes</label><textarea id="guide-instructions" name="instructions" maxlength="30000" rows="12"><?=sp_e($_POST['instructions']??$task['instructions'])?></textarea>
                <label for="guide-links">Reference links — one per line</label><textarea id="guide-links" name="links"><?=sp_e($_POST['links']??implode("\n",array_column(array_filter($resources,static fn($r)=>$r['type']==='link'),'url')))?></textarea>
                <label for="guide-screenshot">Add a reference screenshot</label><input id="guide-screenshot" type="file" name="screenshot" accept="image/jpeg,image/png,image/webp"><p>JPG, PNG, or WebP, up to 8 MB.</p><label for="guide-image">Or use a reference image URL / existing uploaded image path</label><input id="guide-image" name="image" maxlength="2000" value="<?=sp_e($_POST['image']??(array_values(array_filter($resources,static fn($r)=>$r['type']==='image'))[0]['url']??''))?>">
                <div class="actions"><button class="btn btn-primary" type="submit">Save instructions</button><a class="btn btn-secondary" href="<?=sp_e(sp_guide_url($id,$station,$key))?>">Cancel</a></div>
            </form>
        <?php endif; ?></section>
    <?php elseif($station): ?>
        <section class="workspace-section"><p class="planner-eyebrow"><?=sp_e(sp_stations()[$station][0])?></p><h2><?=sp_e(sp_stations()[$station][1])?></h2><p><?=sp_e(sp_operator($station))?></p><p>Choose the task you need help with.</p>
        <?php $number=0;foreach(sp_checklist_groups($station) as $group): ?><section class="manual-process"><h3>Step <?=$group['number']?> · <?=sp_e($group['title'])?></h3><ol class="manual-task-list" start="<?=$number+1?>"><?php foreach($stationTasks as $row): if(!in_array($row['task_key'],$group['task_keys'],true))continue;$number++; ?><li><a href="<?=sp_e(sp_guide_url($id,$station,$row['task_key']))?>"><?=sp_e($row['title'])?></a></li><?php endforeach; ?></ol></section><?php endforeach; ?></section>
    <?php else: ?>
        <section class="workspace-section"><h2>Choose a station</h2><p>Step-by-step instructions for the existing Sunday workflow.</p><?php foreach(sp_stations() as $stationKey=>$stationLabel): ?><a class="planner-area-link" href="<?=sp_e(sp_guide_url($id,$stationKey))?>"><span><strong><?=sp_e($stationLabel[0])?></strong><small><?=sp_e($stationLabel[1])?></small></span><span aria-hidden="true">→</span></a><?php endforeach; ?></section>
    <?php endif; endif; ?>
    <footer class="guide-footer"><a href="<?=sp_e(sp_page_url($id,$station?'media':'feed',$station?[$station]:[]))?>" target="_self">Return to Service Planner →</a></footer>
</main><script src="/assets/js/service-guide.js?v=1" defer></script></body></html>
