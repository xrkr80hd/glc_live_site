<?php
// Included by the authenticated planner page. Not a standalone route.
if (!isset($service, $tasks, $computers)) { http_response_code(404); exit; }
?>
<section class="workspace-section checklist-controls" id="planner-work-start" tabindex="-1"><p class="planner-eyebrow">MEDIA TEAM</p><h3><?=$computers?'Your station checklists':'3. Choose your computers'?></h3>
<?php if(!$computers): ?>
    <?php sp_station_picker($id,[]); ?>
<?php else: ?>
    <p>Check each task when done. Need help? Open Guide.</p>
    <div class="actions"><?php if(!$popout): ?><a class="btn btn-primary desktop-popout" href="<?=sp_e(sp_page_url($id,'media',$computers,true))?>" data-checklist-popup target="liberty-service-checklist">Pop Out Checklist ↗</a><?php endif; ?><a class="btn btn-secondary checklist-reference-link" href="<?=sp_e(sp_page_url($id,'sheet'))?>">View Service Sheet</a><a class="btn btn-secondary checklist-reference-link" href="<?=sp_e(sp_page_url($id,'notes'))?>">Read service notes</a><a class="btn btn-secondary" href="<?=sp_e(sp_page_url($id,'media'))?>">Change computers</a></div>
    <?php if(count($computers)>1): ?><nav class="station-jumps" aria-label="Your selected computers"><?php foreach($computers as $key): ?><a href="#<?=sp_e($key)?>" data-station-switch="<?=sp_e($key)?>"><?=sp_e(sp_station_options()[$key][0])?></a><?php endforeach; ?></nav><?php endif; ?>
<?php endif; ?></section>
<?php if($computers): ?>
<input type="hidden" id="planner-csrf" value="<?=sp_e(csrf_token())?>">
<?php foreach($computers as $stationKey): if($stationKey==='foh'){ require __DIR__.'/foh.php';continue; } $station=sp_stations()[$stationKey];$number=0;[$done,$total]=sp_progress($tasks,$completions,$stationKey); ?>
<section class="station-checklist" id="<?=sp_e($stationKey)?>" data-station="<?=sp_e($stationKey)?>">
    <header class="station-heading"><div><p class="planner-eyebrow"><?=sp_e($station[0])?></p><h3><?=sp_e(explode(' · ',$station[1])[0])?></h3><p><?=sp_e(sp_operator($stationKey))?></p></div><?php sp_badge($stationKey,$statuses[$stationKey]); ?></header>
    <p class="station-progress" data-progress-station="<?=sp_e($stationKey)?>" data-progress-total="<?=$total?>"><?=$done?> of <?=$total?> tasks completed</p>
    <?php $opened=false;foreach(sp_checklist_groups($stationKey) as $group): $groupTasks=array_values(array_filter($tasks,static fn($task)=>in_array($task['task_key'],$group['task_keys'],true)));if(!$groupTasks)continue;$groupDone=0;foreach($groupTasks as $groupTask)$groupDone+=(int)!empty($completions[$groupTask['task_key']]['is_complete']);$open=!$opened && $groupDone<count($groupTasks);if($open)$opened=true; ?>
    <details class="process-group" data-process-group="<?=sp_e($group['key'])?>" <?=$open?'open':''?>>
    <summary><span class="process-number"><?=$group['number']?></span><span><strong><?=sp_e($group['title'])?></strong><small data-group-progress><?=$groupDone?> of <?=count($groupTasks)?> tasks completed</small></span><span class="process-chevron" aria-hidden="true">⌄</span></summary>
    <ol class="numbered-checklist" start="<?=$number+1?>">
    <?php foreach($groupTasks as $task): $number++;$completion=$completions[$task['task_key']]??[]; ?>
        <li class="task-row <?=!empty($completion['is_complete'])?'is-complete':''?>">
            <label class="task-label"><input type="checkbox" data-task-key="<?=sp_e($task['task_key'])?>" data-task-station="<?=sp_e($stationKey)?>" data-revision="<?=(int)($completion['revision']??0)?>" <?=!empty($completion['is_complete'])?'checked':''?> <?=$archived?'disabled':''?>>
                <span class="task-number" aria-hidden="true"><?=$number?></span><span class="task-copy"><span class="task-title"><?=sp_e($task['title'])?></span><span class="task-screen-reader">Task <?=$number?></span></span>
            </label>
            <a class="guide-link" href="<?=sp_e(sp_guide_url($id,$stationKey,$task['task_key']))?>" data-guide-popup target="liberty-service-guide" aria-label="Open guide for task <?=$number?>: <?=sp_e($task['title'])?>">Open Guide ↗</a>
        </li>
    <?php endforeach; ?></ol>
    </details><?php endforeach; ?>
</section>
<?php endforeach; ?>
<noscript><p>Enable JavaScript to save checklist checks. You can open every guide without it.</p></noscript>
<?php endif; ?>
