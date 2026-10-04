<?php
if (!isset($service, $statuses)) { http_response_code(404); exit; }
$isSheet=$section==='sheet';
$allReady=!array_diff($statuses,['READY','COMPLETE']);
$announcements=sp_service_announcements($pdo,$service);
?>
<section class="workspace-section sheet-intro"><p class="planner-eyebrow"><?=$isSheet?'SHARED · READ-ONLY':'SHARED REFERENCE · READ-ONLY'?></p><h3><?=$isSheet?'Service Sheet':'Service notes'?></h3>
    <p><?=$isSheet?'The saved Sunday plan and what each team has completed.':'The details and announcement notes needed by the team.'?></p>
    <?php if($isSheet): ?><p class="sheet-readiness" data-sheet-readiness><?=$allReady?'The service plan is ready.':'This service is still being prepared.'?></p><?php endif; ?>
    <div class="actions"><a class="btn btn-secondary" href="<?=sp_e(sp_page_url($id,$isSheet?'notes':'sheet'))?>"><?=$isSheet?'Read service notes':'View Service Sheet'?></a></div>
</section>
<?php if($isSheet): ?>
<section class="workspace-section"><h3>Team readiness</h3><dl class="sheet-readiness-list">
    <div><dt>Pastor · Sermon</dt><dd><?php sp_badge('sermon',$statuses['sermon']); ?></dd></div>
    <div><dt>Music Minister · Worship</dt><dd><?php sp_badge('worship',$statuses['worship']); ?></dd></div>
    <div><dt>Front of House<small>Sound heard inside the sanctuary</small></dt><dd><?php sp_badge('foh',$statuses['foh']); ?></dd></div>
    <?php foreach(sp_stations() as $key=>$station): [$done,$total]=sp_progress($tasks,$completions,$key); ?><div><dt><?=sp_e($station[0])?><small><?=sp_e($station[1])?></small><span data-progress-station="<?=sp_e($key)?>" data-progress-total="<?=$total?>"><?=$done?> of <?=$total?> tasks completed</span></dt><dd><?php sp_badge($key,$statuses[$key]); ?></dd></div><?php endforeach; ?>
</dl></section>
<section class="workspace-section"><p class="planner-eyebrow">SERMON</p><h3 data-sermon-field="title"><?=sp_e($service['sermon']['title']??'Sermon not entered')?></h3>
    <h4>Primary scripture</h4><div class="reference" data-sermon-field="primary_scripture"><?=sp_e($service['sermon']['primary_scripture']??'Not entered')?></div>
    <h4>Additional scriptures</h4><div class="reference" data-sermon-field="additional_scriptures"><?=sp_e($service['sermon']['additional_scriptures']??'Not entered')?></div>
</section>
<section class="workspace-section"><p class="planner-eyebrow">WORSHIP</p><h3>Song order</h3><?php sp_render_worship($service['worship']); ?></section>
<?php else: ?>
<section class="workspace-section"><h3>Notes from Pastor</h3>
<?php foreach(['media_notes'=>'Sermon notes for Media','special_media'=>'Special media requirements','videos'=>'Videos / media references','presentation_instructions'=>'Presentation instructions'] as $key=>$label): ?><h4><?=sp_e($label)?></h4><div class="reference" data-sermon-field="<?=sp_e($key)?>"><?=sp_e($service['sermon'][$key]??'No notes entered.')?></div><?php endforeach; ?>
</div></section>
<section class="workspace-section"><h3>Worship notes</h3><?php sp_render_worship($service['worship']); ?></section>
<?php endif; ?>
<section class="workspace-section"><h3>Announcement notes for Media</h3><div class="reference" data-sermon-field="announcement_notes"><?=sp_e($service['sermon']['announcement_notes']??'No announcement notes entered.')?></div></section>
<section class="workspace-section"><h3>Church announcements &amp; upcoming dates</h3><p><?=$archived?'Saved with this archived Sunday.':'Current and upcoming announcements from the existing website announcement system.'?></p>
<div data-sheet-announcements>
<?php if(!$announcements): ?><p>No current or upcoming announcements.</p><?php endif; ?>
<?php foreach($announcements as $announcement): ?><article class="sheet-announcement"><h4><?=sp_e($announcement['title'])?></h4><p><?=sp_e(sp_announcement_dates($announcement))?></p><div class="reference"><?=sp_e($announcement['body'])?></div></article><?php endforeach; ?>
</div></section>
