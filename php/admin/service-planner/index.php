<?php
declare(strict_types=1);
require_once __DIR__.'/../layout.php';
require_once __DIR__.'/presentation.php';
admin_require_login();
header('Cache-Control: no-store');
$pdo=admin_db_or_setup_page('Service Planner','service-planner');
$error='';$service=null;$tasks=[];$completions=[];
$id=(int)($_GET['service_id']??$_POST['service_id']??0);
$section=(string)($_GET['section']??'feed');
$computers=sp_selected_computers($_GET['computers']??[]);
if(in_array($section,array_keys(sp_stations()),true)){$computers=[$section];$section='media';}
if(!in_array($section,array_merge(['feed','sermon','worship','notes','sheet','media','announcements'],array_keys(sp_stations())),true))$section='feed';
sp_require_access(sp_can_open_section($section));
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        sp_require_access(sp_can_save_action((string)($_POST['action']??'')));
        verify_csrf($_POST['csrf_token']??'');
        sp_mutate($pdo,$_POST,(string)admin_current_user()['username']);
        if(($_POST['action']??'')==='create'){
            $stmt=$pdo->prepare('SELECT id FROM service_plans WHERE service_date=?');$stmt->execute([$_POST['service_date']]);$id=(int)$stmt->fetchColumn();
        }
        admin_redirect(sp_page_url($id,$section,$computers));
    }
    $services=$pdo->query('SELECT id,service_date,is_archived FROM service_plans ORDER BY service_date DESC')->fetchAll(PDO::FETCH_ASSOC);
    if($id){$service=sp_service($pdo,$id);$tasks=sp_tasks($pdo,$service);$completions=sp_completions($pdo,$id);}
} catch(Throwable $e) {
    $known=$e instanceof InvalidArgumentException || $e->getCode()===409;
    $error=$known?$e->getMessage():'Service Planner is unavailable. Ask the site administrator to verify the planner deployment and database setup.';
    if(!$known)error_log('Service Planner: '.$e->getMessage());
}
$popout=($_GET['popout']??'')==='1';
if(!$popout)admin_page_start('Service Planner','service-planner');
else { ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sunday Checklist · Liberty Church</title><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/admin-workspace.css?v=3"></head><body class="admin-dark guide-body checklist-window"><main><?php }
?>
<link rel="stylesheet" href="/assets/admin-workspace.css?v=3">
<div class="workspace planner-page" id="service-planner" data-popout="<?=$popout?'1':'0'?>" data-section="<?=sp_e($section)?>" data-service-id="<?=$id?>" data-revision="<?=(int)($service['revision']??0)?>" data-archived="<?=!empty($service['is_archived'])?'1':'0'?>">
<?php if($popout): ?><header class="guide-header"><h1>Sunday Checklist</h1><button class="btn btn-secondary" type="button" id="close-guide" hidden>Close</button></header><?php if($id): ?><nav class="planner-section-tabs"><a href="<?=sp_e(sp_page_url($id,'media',$computers))?>">Checklists</a><a href="<?=sp_e(sp_page_url($id,'sheet',$computers))?>">Service Sheet</a><a href="<?=sp_e(sp_page_url($id,'notes',$computers))?>">Notes</a></nav><?php endif; endif; ?>
<?php if(!$popout)sp_work_intro($section,(bool)$service,(bool)$computers,(bool)($service['is_archived']??false)); ?>
<?php if($error): ?><p class="flash flash-error" role="alert"><?=sp_e($error)?></p><?php endif; ?>
<?php if(!$service): ?>
    <p class="planner-eyebrow">START HERE</p><h3>1. Choose the Sunday</h3>
    <p>Then open your section from the left menu.</p>
    <?php if(sp_can_operate_media()): ?><a class="guide-link" href="<?=sp_e(sp_guide_url(0))?>" data-guide-popup target="liberty-service-guide">Open station manual ↗</a><?php endif; ?>
    <section class="workspace-section"><h3>Open a Sunday service</h3>
    <?php if(!empty($services)): ?><ul class="service-history"><?php foreach($services as $item): $date=new DateTimeImmutable($item['service_date']); ?>
        <li><a class="feed-link" href="<?=sp_e(sp_page_url((int)$item['id'],$section,$computers))?>"><span>Sunday, <?=sp_e($date->format('F j, Y'))?> · Week <?=sp_e($date->format('W'))?></span><span><?=$item['is_archived']?'Archived':'Open'?></span></a></li>
    <?php endforeach; ?></ul><?php else: ?><p>No Sunday services yet.</p><?php endif; ?>
    </section>
    <?php if(sp_can_manage_services()): ?><section class="workspace-section"><h3>Create or open a Sunday</h3>
    <form method="post"><input type="hidden" name="csrf_token" value="<?=sp_e(csrf_token())?>"><input type="hidden" name="action" value="create">
    <label for="service-date">Sunday date</label><input id="service-date" type="date" name="service_date" required value="<?=sp_e((new DateTimeImmutable('today'))->modify('this sunday')->format('Y-m-d'))?>">
    <div class="actions"><button class="btn" type="submit">Open Sunday service</button></div><p>Existing dates open the same shared record. Previous Sundays remain available.</p></form></section><?php endif; ?>
<?php else:
    $date=new DateTimeImmutable($service['service_date']);$statuses=sp_feed($service,$tasks,$completions);$archived=(bool)$service['is_archived'];
?>
    <div class="planner-breadcrumb"><a href="/php/admin/service-planner/index.php">All Sundays</a><span aria-hidden="true">/</span><a href="<?=sp_e(sp_page_url($id))?>">Sunday overview</a></div>
    <header class="planner-service-heading"><div><p class="planner-eyebrow">SUNDAY SERVICE · WEEK <?=sp_e($date->format('W'))?></p><h3><?=sp_e($date->format('F j, Y'))?></h3></div><?php if($archived): ?><span class="status">ARCHIVED · READ-ONLY</span><?php endif; ?></header>
    <p id="sync-message" class="save-message planner-sync" role="status" aria-live="polite">Shared changes update automatically.</p>
    <div id="information-update" hidden><p>Sunday information was updated on another device. Save or copy any unsaved edits before reloading.</p><a class="btn btn-secondary" href="<?=sp_e(sp_page_url($id,$section,$computers))?>">Reload information</a></div>
    <?php if($section==='feed'): ?>
        <section class="workspace-section"><p class="planner-eyebrow">YOUR NEXT STEP</p><h3>2. Choose your section</h3><p>Enter information or work through the checklist for your station.</p>
        <?php if(sp_can_edit_sermon()): ?><a class="planner-area-link" href="<?=sp_e(sp_page_url($id,'sermon'))?>"><span><strong>Pastor</strong><small>Enter the sermon title, scriptures, and media instructions.</small></span><?php sp_badge('sermon',$statuses['sermon']); ?></a><?php endif; ?>
        <?php if(sp_can_edit_worship()): ?><a class="planner-area-link" href="<?=sp_e(sp_page_url($id,'worship'))?>"><span><strong>Music Minister</strong><small>Enter the songs, keys, leaders, and order.</small></span><?php sp_badge('worship',$statuses['worship']); ?></a><?php endif; ?>
        <?php if(sp_can_operate_media()): ?><a class="planner-area-link" href="<?=sp_e(sp_page_url($id,'media'))?>"><span><strong>Media Team</strong><small>Choose Computer 1, 2, 3, or 4. You can select more than one.</small></span><span aria-hidden="true">→</span></a><?php endif; ?>
        </section>
        <section class="workspace-section"><h3>Read the shared service</h3><p>See the saved plan and what everyone has completed.</p><div class="actions"><a class="btn btn-primary" href="<?=sp_e(sp_page_url($id,'sheet'))?>">View Service Sheet</a><a class="btn btn-secondary" href="<?=sp_e(sp_page_url($id,'notes'))?>">Read service notes</a></div></section>
        <?php if(!$archived && sp_full_access()): ?><details class="tool-detail"><summary>Preserve this completed Sunday</summary><p>Archive when the team has finished. Its service sheet and instructions remain available for reference.</p><form method="post" data-planner-form><?php sp_hidden($service,'archive'); ?><button class="btn btn-secondary" type="submit">Archive this Sunday</button><p class="save-message" role="status"></p></form></details><?php endif; ?>
    <?php elseif(in_array($section,['sheet','notes'],true)): ?>
        <?php require __DIR__.'/service-sheet.php'; ?>
    <?php elseif($section==='announcements'): ?>
        <section class="workspace-section"><p class="planner-eyebrow">PASTOR</p><h3>Church announcements</h3><nav class="planner-section-tabs"><a href="<?=sp_e(sp_page_url($id,'sermon'))?>">Sermon</a><a href="<?=sp_e(sp_page_url($id,'announcements'))?>" aria-current="page">Announcements</a></nav>
        <form method="post" data-planner-form><?php sp_hidden($service,'announcements'); ?><fieldset <?=$archived?'disabled':''?> style="border:0;padding:0"><label for="announcement-notes">Announcement notes for Media</label><p>List dates, reminders, and what Media needs to prepare for this Sunday.</p><textarea id="announcement-notes" name="announcement_notes" maxlength="12000"><?=sp_e($service['sermon']['announcement_notes']??'')?></textarea><?php if(!$archived): ?><div class="actions"><button class="btn btn-primary" type="submit">Save announcement notes</button></div><?php endif; ?></fieldset><p class="save-message" role="status"></p></form>
        </section><section class="workspace-section"><h3>Website announcements &amp; upcoming dates</h3><p>Use the existing announcement editor for wording and dates. The team sees current and upcoming announcements on the Service Sheet.</p><?php if(!$archived): ?><div class="actions"><a class="btn btn-primary" href="/php/admin/announcements/new.php">Add church announcement</a><a class="btn btn-secondary" href="/php/admin/announcements/index.php">Manage church announcements</a></div><?php endif; ?>
        <?php foreach(sp_service_announcements($pdo,$service) as $announcement): ?><article class="sheet-announcement"><h4><?=sp_e($announcement['title'])?></h4><p><?=sp_e(sp_announcement_dates($announcement))?></p><div class="reference"><?=sp_e($announcement['body'])?></div></article><?php endforeach; ?></section>
        <?php sp_media_readiness($statuses); ?>
    <?php elseif($section==='sermon'): ?>
        <section class="workspace-section"><p class="planner-eyebrow">PASTOR</p><div class="editor-heading"><h3>Sermon information</h3><?php sp_badge('sermon',$statuses['sermon']); ?></div><p>Enter the sermon once for the whole team.</p>
        <nav class="planner-section-tabs"><a href="<?=sp_e(sp_page_url($id,'sermon'))?>" aria-current="page">Sermon</a><a href="<?=sp_e(sp_page_url($id,'announcements'))?>">Announcements</a></nav>
        <form method="post" data-planner-form><?php sp_hidden($service,'sermon'); ?><fieldset <?=$archived?'disabled':''?> style="border:0;padding:0;margin:0">
        <?php foreach(['title'=>'Sermon title','primary_scripture'=>'Primary scripture','additional_scriptures'=>'Additional scriptures'] as $key=>$label): ?>
        <label for="sermon-<?=sp_e($key)?>"><?=sp_e($label)?></label><?php if(in_array($key,['title','primary_scripture'],true)): ?><input id="sermon-<?=sp_e($key)?>" name="<?=sp_e($key)?>" maxlength="1000" value="<?=sp_e($service['sermon'][$key]??'')?>"><?php else: ?><textarea id="sermon-<?=sp_e($key)?>" name="<?=sp_e($key)?>" maxlength="12000"><?=sp_e($service['sermon'][$key]??'')?></textarea><?php endif; ?>
        <?php endforeach; ?>
        <details class="editor-detail"><summary>Media notes &amp; instructions</summary>
        <?php foreach(['media_notes'=>'Sermon notes for Media','special_media'=>'Special media requirements','videos'=>'Videos / media links','presentation_instructions'=>'Presentation instructions'] as $key=>$label): ?><label for="sermon-<?=sp_e($key)?>"><?=sp_e($label)?></label><textarea id="sermon-<?=sp_e($key)?>" name="<?=sp_e($key)?>" maxlength="12000" rows="3"><?=sp_e($service['sermon'][$key]??'')?></textarea><?php endforeach; ?>
        </details>
        <label class="ready-control"><input type="checkbox" name="ready" value="1" <?=$service['sermon_ready']?'checked':''?>> Sermon information ready</label>
        <?php if(!$archived): ?><div class="actions"><button class="btn btn-primary" type="submit">Save sermon information</button><a class="guide-link editor-sheet-link" href="<?=sp_e(sp_page_url($id,'sheet'))?>">View Service Sheet →</a></div><?php endif; ?></fieldset><p class="save-message" role="status"></p></form></section>
        <?php sp_media_readiness($statuses); ?>
    <?php elseif($section==='worship'): ?>
        <section class="workspace-section"><p class="planner-eyebrow">MUSIC MINISTER</p><div class="editor-heading"><h3>Worship plan</h3><?php sp_badge('worship',$statuses['worship']); ?></div><p>Enter the songs in service order.</p>
        <form method="post" data-planner-form id="worship-form"><?php sp_hidden($service,'worship'); ?><fieldset <?=$archived?'disabled':''?> style="border:0;padding:0;margin:0">
        <div id="songs">
        <?php foreach($service['worship'] as $i=>$song)sp_song_editor($song,$i,$archived); ?>
        </div>
        <?php if(!$archived): ?><template id="song-template"><?php sp_song_editor([],0,false); ?></template><div class="actions"><button type="button" id="add-song" class="btn btn-secondary">Add song</button></div><?php endif; ?>
        <label class="ready-control"><input type="checkbox" name="ready" value="1" <?=$service['worship_ready']?'checked':''?>> Worship plan ready</label>
        <?php if(!$archived): ?><div class="actions"><button class="btn btn-primary" type="submit">Save worship plan</button><a class="guide-link editor-sheet-link" href="<?=sp_e(sp_page_url($id,'sheet'))?>">View Service Sheet →</a></div><?php endif; ?></fieldset><p class="save-message" role="status"></p></form></section>
        <?php sp_media_readiness($statuses); ?>
    <?php elseif($section==='media'): ?>
        <?php require __DIR__.'/checklists.php'; ?>
    <?php endif; ?>
<?php endif; ?>
</div>
<script src="/assets/js/service-planner.js?v=3" defer></script>
<?php if(!$popout)admin_page_end();else { ?><script src="/assets/js/service-guide.js?v=1" defer></script></main></body></html><?php } ?>
