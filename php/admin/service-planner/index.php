<?php
declare(strict_types=1);
require_once __DIR__.'/../layout.php';
require_once __DIR__.'/model.php';
admin_require_login();
header('Cache-Control: no-store');
$pdo=admin_db_or_setup_page('Service Planner','service-planner');
$error='';$service=null;$tasks=[];$completions=[];
$id=(int)($_GET['service_id']??$_POST['service_id']??0);
$section=(string)($_GET['section']??'feed');
if(!in_array($section,array_merge(['feed','sermon','worship'],array_keys(sp_stations())),true))$section='feed';
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf($_POST['csrf_token']??'');
        sp_mutate($pdo,$_POST,(string)admin_current_user()['username']);
        if(($_POST['action']??'')==='create'){
            $stmt=$pdo->prepare('SELECT id FROM service_plans WHERE service_date=?');$stmt->execute([$_POST['service_date']]);$id=(int)$stmt->fetchColumn();
        }
        admin_redirect('/php/admin/service-planner/index.php?service_id='.$id.'&section='.rawurlencode($section));
    }
    $services=$pdo->query('SELECT id,service_date,is_archived FROM service_plans ORDER BY service_date DESC')->fetchAll(PDO::FETCH_ASSOC);
    if($id){$service=sp_service($pdo,$id);$tasks=sp_tasks($pdo,$service);$completions=sp_completions($pdo,$id);}
} catch(Throwable $e) {
    $known=$e instanceof InvalidArgumentException || $e->getCode()===409;
    $error=$known?$e->getMessage():'Service Planner is unavailable. Ask the site administrator to verify the planner deployment and database setup.';
    if(!$known)error_log('Service Planner: '.$e->getMessage());
}
admin_page_start('Service Planner','service-planner');
function sp_hidden(array $service,string $action): void { ?>
    <input type="hidden" name="csrf_token" value="<?=sp_e(csrf_token())?>">
    <input type="hidden" name="service_id" value="<?=(int)$service['id']?>">
    <input type="hidden" name="revision" value="<?=(int)$service['revision']?>">
    <input type="hidden" name="action" value="<?=sp_e($action)?>">
<?php }
function sp_badge(string $key,string $status): void { ?><span class="status" data-status-key="<?=sp_e($key)?>" data-status="<?=sp_e($status)?>"><?=sp_e($status)?></span><?php }
?>
<link rel="stylesheet" href="/assets/admin-workspace.css?v=1">
<div class="workspace" id="service-planner" data-service-id="<?=$id?>" data-revision="<?=(int)($service['revision']??0)?>" data-archived="<?=!empty($service['is_archived'])?'1':'0'?>">
<?php if($error): ?><p class="flash flash-error" role="alert"><?=sp_e($error)?></p><?php endif; ?>
<?php if(!$service): ?>
    <p>One shared Sunday plan: sermon, worship, station checklists, and instructions.</p>
    <section class="workspace-section"><h3>Open a Sunday service</h3>
    <?php if(!empty($services)): ?><ul class="service-history"><?php foreach($services as $item): $date=new DateTimeImmutable($item['service_date']); ?>
        <li><a class="feed-link" href="?service_id=<?=(int)$item['id']?>"><span>Sunday, <?=sp_e($date->format('F j, Y'))?> · Week <?=sp_e($date->format('W'))?></span><span><?=$item['is_archived']?'Archived':'Open'?></span></a></li>
    <?php endforeach; ?></ul><?php else: ?><p>No Sunday services yet.</p><?php endif; ?>
    </section>
    <section class="workspace-section"><h3>Create or open a Sunday</h3>
    <form method="post"><input type="hidden" name="csrf_token" value="<?=sp_e(csrf_token())?>"><input type="hidden" name="action" value="create">
    <label for="service-date">Sunday date</label><input id="service-date" type="date" name="service_date" required value="<?=sp_e((new DateTimeImmutable('today'))->modify('this sunday')->format('Y-m-d'))?>">
    <div class="actions"><button class="btn" type="submit">Open Sunday service</button></div><p>Existing dates open the same shared record. Previous Sundays remain available.</p></form></section>
<?php else:
    $date=new DateTimeImmutable($service['service_date']);$statuses=sp_feed($service,$tasks,$completions);$archived=(bool)$service['is_archived'];
?>
    <div class="actions"><a class="btn btn-secondary" href="/php/admin/service-planner/index.php">All Sundays</a><?php if($section!=='feed'): ?><a class="btn btn-secondary" href="?service_id=<?=$id?>">Main Service Feed</a><?php endif; ?></div>
    <h3>Sunday service — Week <?=sp_e($date->format('W'))?></h3><p><?=sp_e($date->format('F j, Y'))?><?=$archived?' · Archived · Read-only':''?></p>
    <p id="sync-message" class="save-message" role="status" aria-live="polite">Shared checklist updates refresh every 8 seconds.</p>
    <div id="information-update" hidden><p>Sunday information was updated on another device. Reload to read the latest information. Save or copy any unsaved edits first.</p><a class="btn btn-secondary" href="<?=sp_e('?service_id='.$id.'&section='.$section)?>">Reload information</a></div>
    <?php if($section==='feed'): ?>
        <section class="workspace-section"><h3>Main Service Feed</h3>
        <a class="feed-link" href="?service_id=<?=$id?>&amp;section=sermon"><span>Sermon<small data-feed-sermon><?=sp_e($service['sermon']['title']??'Title, scriptures, media, and presentation instructions')?></small></span><?php sp_badge('sermon',$statuses['sermon']); ?></a>
        <a class="feed-link" href="?service_id=<?=$id?>&amp;section=worship"><span>Worship<small data-feed-worship><?=count($service['worship'])?> songs · keys · leaders · notes</small></span><?php sp_badge('worship',$statuses['worship']); ?></a>
        <?php foreach(sp_stations() as $key=>$station): ?><a class="feed-link" href="?service_id=<?=$id?>&amp;section=<?=sp_e($key)?>"><span><?=sp_e($station[0])?><small><?=sp_e($station[1])?></small></span><?php sp_badge($key,$statuses[$key]); ?></a><?php endforeach; ?>
        </section>
        <section class="workspace-section"><h3>Sunday reference</h3><p><strong>Primary scripture:</strong> <span data-feed-scripture><?=sp_e($service['sermon']['primary_scripture']??'Not entered')?></span></p>
        <p>FOH handles audio inside the sanctuary. Computer 3 handles the dedicated livestream mix. All equipment continues to be operated using the current Sunday procedures.</p></section>
        <?php if(!$archived): ?><details class="tool-detail"><summary>Preserve this completed Sunday</summary><p>Archive once the team has finished the service. This preserves its checklist and instructions as a read-only historical record.</p><form method="post" data-planner-form><?php sp_hidden($service,'archive'); ?><button class="btn btn-secondary" type="submit">Archive this Sunday</button><p class="save-message" role="status"></p></form></details><?php endif; ?>
    <?php elseif($section==='sermon'): ?>
        <section class="workspace-section"><h3>Sermon information</h3><p>Pastor Andrew’s shared reference for Media and the service team. Enter once for this Sunday.</p><?php sp_badge('sermon',$statuses['sermon']); ?>
        <form method="post" data-planner-form><?php sp_hidden($service,'sermon'); ?><fieldset <?=$archived?'disabled':''?> style="border:0;padding:0;margin:0">
        <?php foreach(['title'=>'Sermon title','primary_scripture'=>'Primary scripture','additional_scriptures'=>'Additional scriptures','media_notes'=>'Sermon notes needed by Media','special_media'=>'Special media requirements','videos'=>'Videos / existing media links','presentation_instructions'=>'Presentation instructions'] as $key=>$label): ?>
        <label for="sermon-<?=sp_e($key)?>"><?=sp_e($label)?></label><?php if(in_array($key,['title','primary_scripture'],true)): ?><input id="sermon-<?=sp_e($key)?>" name="<?=sp_e($key)?>" maxlength="1000" value="<?=sp_e($service['sermon'][$key]??'')?>"><?php else: ?><textarea id="sermon-<?=sp_e($key)?>" name="<?=sp_e($key)?>" maxlength="12000"><?=sp_e($service['sermon'][$key]??'')?></textarea><?php endif; ?>
        <?php endforeach; ?><label><input type="checkbox" name="ready" value="1" <?=$service['sermon_ready']?'checked':''?>> Sermon information ready</label>
        <?php if(!$archived): ?><div class="actions"><button class="btn" type="submit">Save sermon information</button></div><?php endif; ?></fieldset><p class="save-message" role="status"></p></form></section>
    <?php elseif($section==='worship'): ?>
        <section class="workspace-section"><h3>Worship plan</h3><p>Erin’s song order, keys, leaders, and notes, shared with every station and FOH.</p><?php sp_badge('worship',$statuses['worship']); ?>
        <form method="post" data-planner-form id="worship-form"><?php sp_hidden($service,'worship'); ?><fieldset <?=$archived?'disabled':''?> style="border:0;padding:0;margin:0">
        <div id="songs">
        <?php foreach($service['worship'] as $i=>$song): ?><div class="song"><div class="song-head"><h4>Song <span class="song-number"><?=$i+1?></span></h4><?php if(!$archived): ?><div class="actions"><button type="button" data-song-action="up" aria-label="Move song up">↑ Up</button><button type="button" data-song-action="down" aria-label="Move song down">↓ Down</button><button type="button" data-song-action="remove">Remove</button></div><?php endif; ?></div>
        <?php foreach(['title'=>'Song title','key'=>'Key','lead'=>'Lead vocalist','additional'=>'Additional vocalists','notes'=>'Special notes / instruments'] as $key=>$label): ?><label><?=sp_e($label)?><input data-song-field="<?=sp_e($key)?>" name="songs[<?=$i?>][<?=sp_e($key)?>]" value="<?=sp_e($song[$key]??'')?>" maxlength="12000" <?=$key==='title'?'required':''?>></label><?php endforeach; ?></div><?php endforeach; ?>
        </div>
        <?php if(!$archived): ?><div class="actions"><button type="button" id="add-song" class="btn btn-secondary">Add song</button></div><?php endif; ?>
        <label><input type="checkbox" name="ready" value="1" <?=$service['worship_ready']?'checked':''?>> Worship plan ready</label>
        <?php if(!$archived): ?><div class="actions"><button class="btn" type="submit">Save worship plan</button></div><?php endif; ?></fieldset><p class="save-message" role="status"></p></form></section>
    <?php else: $station=sp_stations()[$section]; ?>
        <section class="workspace-section"><h3><?=sp_e($station[0])?> — <?=sp_e($station[1])?></h3><?php sp_badge($section,$statuses[$section]); ?>
        <?php if($section==='computer3'): ?><p>This station mixes audio for the livestream. FOH mixes audio heard in the sanctuary.</p><?php endif; ?>
        <details class="tool-detail"><summary>This Sunday’s sermon &amp; worship reference</summary><p><strong><?=sp_e($service['sermon']['title']??'Sermon not entered')?></strong></p><div class="reference"><?=sp_e(($service['sermon']['primary_scripture']??'')."\n".($service['sermon']['additional_scriptures']??''))?></div>
        <ol><?php foreach($service['worship'] as $song): ?><li><?=sp_e($song['title'])?> · <?=sp_e($song['key'])?> · <?=sp_e($song['lead'])?><?php if(!empty($song['additional'])): ?> · <?=sp_e($song['additional'])?><?php endif; ?><?php if(!empty($song['notes'])): ?><div class="reference"><?=sp_e($song['notes'])?></div><?php endif; ?></li><?php endforeach; ?></ol>
        <a href="?service_id=<?=$id?>&amp;section=sermon">Full sermon information</a> · <a href="?service_id=<?=$id?>&amp;section=worship">Full worship plan</a></details>
        <input type="hidden" id="planner-csrf" value="<?=sp_e(csrf_token())?>">
        <?php foreach(['pre'=>'Pre-service','start'=>'Service start','end'=>'Service end'] as $phase=>$phaseLabel): $phaseTasks=array_filter($tasks,static fn($task)=>$task['station']===$section && $task['phase']===$phase);if(!$phaseTasks)continue; ?>
        <h4><?=sp_e($phaseLabel)?></h4>
        <?php foreach($phaseTasks as $task): $completion=$completions[$task['task_key']]??[];$resources=sp_decode($task['resources_json']??'[]'); ?>
        <div class="task"><label class="task-label"><input type="checkbox" data-task-key="<?=sp_e($task['task_key'])?>" data-revision="<?=(int)($completion['revision']??0)?>" <?=!empty($completion['is_complete'])?'checked':''?> <?=$archived?'disabled':''?>> <span><?=sp_e($task['title'])?></span></label>
        <details class="howto"><summary>HOW DO I DO THIS?</summary><div class="instruction-text"><?=sp_e($task['instructions'])?></div>
        <?php foreach($resources as $resource): if(!sp_safe_url($resource['url']??''))continue; ?><?php if(($resource['type']??'')==='image'): ?><img src="<?=sp_e($resource['url'])?>" alt="Reference for <?=sp_e($task['title'])?>" loading="lazy" style="max-width:100%;height:auto"><?php else: ?><p><a href="<?=sp_e($resource['url'])?>" target="_blank" rel="noopener noreferrer">Open reference resource</a></p><?php endif; ?><?php endforeach; ?>
        <?php if(!$archived): ?><details class="tool-detail"><summary>Edit reusable instructions</summary><form method="post" data-planner-form><?php sp_hidden($service,'instructions'); ?><input type="hidden" name="task_key" value="<?=sp_e($task['task_key'])?>"><input type="hidden" name="definition_revision" value="<?=(int)$task['revision']?>">
        <label>Steps, notes, warnings<textarea name="instructions" maxlength="30000"><?=sp_e($task['instructions'])?></textarea></label>
        <label>Reference links — one per line<textarea name="links"><?=sp_e(implode("\n",array_column(array_filter($resources,static fn($r)=>$r['type']==='link'),'url')))?></textarea></label>
        <label>Reference image URL or existing uploaded image path<input name="image" maxlength="2000" value="<?=sp_e(array_values(array_filter($resources,static fn($r)=>$r['type']==='image'))[0]['url']??'')?>"></label>
        <p>These instructions apply to open and future Sundays. Archived services preserve their saved instructions.</p><button class="btn" type="submit">Save instructions</button><p class="save-message" role="status"></p></form></details><?php endif; ?>
        </details><small data-task-credit="<?=sp_e($task['task_key'])?>"><?=!empty($completion['updated_by'])?'Updated by '.sp_e($completion['updated_by']).' · '.sp_e($completion['updated_at']):''?></small></div>
        <?php endforeach; endforeach; ?>
        <?php if(in_array($section,['computer1','computer4'],true)): ?><h4>During service</h4><p><?=$section==='computer1'?'Continue the current in-house projector, lyric, scripture, slide, video, announcement, and graphic flow.':'Continue the current camera changes, online lyrics, scriptures, stream presentation, and visual synchronization.'?></p><?php endif; ?>
        <noscript><p>Enable JavaScript for shared checkbox updates. Information forms remain available without it.</p></noscript></section>
    <?php endif; ?>
<?php endif; ?>
</div>
<script src="/assets/js/service-planner.js?v=1" defer></script>
<?php admin_page_end(); ?>
