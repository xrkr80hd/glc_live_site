<?php
declare(strict_types=1);
require_once __DIR__.'/model.php';
require_once __DIR__.'/access.php';

function sp_page_url(int $id, string $section = 'feed', array $computers = [], ?bool $popout = null): string
{
    $query = ['section'=>$section];
    if ($id) $query['service_id']=$id;
    if (!$computers && in_array($section,['sheet','notes'],true)) $computers=sp_selected_computers($_GET['computers']??[]);
    if ($computers) $query['computers']=$computers;
    if ($popout ?? (($_GET['popout']??'')==='1')) $query['popout']='1';
    return '/php/admin/service-planner/index.php?'.http_build_query($query);
}
function sp_guide_url(int $id, string $station = '', string $task = '', bool $edit = false): string
{
    $query=[];
    if ($id) $query['service_id']=$id;
    if ($station) $query['station']=$station;
    if ($task) $query['task']=$task;
    if ($edit) $query['edit']='1';
    return '/php/admin/service-planner/guide.php'.($query?'?'.http_build_query($query):'');
}
function sp_hidden(array $service, string $action): void { ?>
    <input type="hidden" name="csrf_token" value="<?=sp_e(csrf_token())?>">
    <input type="hidden" name="service_id" value="<?=(int)$service['id']?>">
    <input type="hidden" name="revision" value="<?=(int)$service['revision']?>">
    <input type="hidden" name="action" value="<?=sp_e($action)?>">
<?php }
function sp_badge(string $key, string $status): void { ?>
    <span class="status" data-status-key="<?=sp_e($key)?>" data-status="<?=sp_e($status)?>"><?=sp_e($status)?></span>
<?php }
function sp_operator(string $station): string
{
    return match ($station) {
        'computer1'=>'Controls lyrics, scriptures, slides, and projector output inside the sanctuary.',
        'computer2'=>'Operates the livestream broadcast in OBS and YouTube.',
        'computer3'=>'Prepares and monitors the dedicated livestream audio mix. FOH handles sanctuary audio.',
        'computer4'=>'Controls cameras, online lyrics, scriptures, and stream presentation.',
        default=>'',
    };
}
function sp_progress(array $tasks, array $completions, string $station): array
{
    $total=0;$done=0;
    foreach ($tasks as $task) {
        if ($task['station']!==$station) continue;
        $total++;$done+=(int)!empty($completions[$task['task_key']]['is_complete']);
    }
    return [$done,$total];
}
function sp_checklist_groups(string $station): array
{
    $ranges=match($station){
        'computer1'=>[
            ['Review this Sunday','pre',1,5],['Build the presentation','pre',6,10],['Check projectors and save','pre',11,14]],
        'computer2'=>[
            ['Review this Sunday','pre',1,3],['Prepare the YouTube service','pre',4,6],['Open OBS and check sources','pre',7,10],
            ['Connect and start the broadcast','pre',11,15],['Verify the service start','start',1,4],['End the broadcast','end',1,4]],
        'computer3'=>[
            ['Review the worship plan','pre',1,4],['Power and verify Flow 8','pre',5,6],['Verify Dante devices and routing','pre',7,9],
            ['Open the template and compare the team','pre',10,13],['Check channels and prepare the mix','pre',14,17]],
        'computer4'=>[
            ['Review the presentation handoff','pre',1,2],['Find the weekly service files','pre',3,6],
            ['Prepare stream songs and scriptures','pre',7,10],['Check cameras and presentation output','pre',11,14]],
        default=>[],
    };
    $groups=[];
    foreach($ranges as $i=>[$title,$phase,$first,$last]){
        $keys=[];foreach(range($first,$last) as $n)$keys[]=sprintf('%s_%s_%02d',$station,$phase,$n);
        $groups[]=['key'=>$station.'-group-'.($i+1),'number'=>$i+1,'title'=>$title,'task_keys'=>$keys];
    }
    return $groups;
}
function sp_station_picker(int $id, array $selected): void { ?>
    <form method="get" class="station-picker">
        <?php if($id): ?><input type="hidden" name="service_id" value="<?=$id?>"><?php endif; ?>
        <input type="hidden" name="section" value="media">
        <?php if(($_GET['popout']??'')==='1'): ?><input type="hidden" name="popout" value="1"><?php endif; ?>
        <fieldset><legend>Which computers are you using?</legend><p>Select one or more. For example, select Computers 2 and 3 if you operate both.</p>
        <?php foreach(sp_station_options() as $key=>$station): ?>
        <label><input type="checkbox" name="computers[]" value="<?=sp_e($key)?>" <?=in_array($key,$selected,true)?'checked':''?>> <span><strong><?=sp_e($station[0])?></strong><small><?=sp_e($station[1])?></small></span></label>
        <?php endforeach; ?></fieldset><button type="submit" class="btn btn-primary">Open my checklists</button>
    </form>
<?php }
function sp_media_readiness(array $statuses): void { $ready=0;foreach(array_keys(sp_station_options()) as $station)if(in_array($statuses[$station],['READY','COMPLETE'],true))$ready++; ?>
    <details class="tool-detail readiness-summary"><summary>Media readiness · <span data-media-ready-count><?=$ready?> of 5 stations ready</span></summary><dl class="sheet-readiness-list"><?php foreach(sp_station_options() as $key=>$station): ?><div><dt><?=sp_e($station[0])?></dt><dd><?php sp_badge($key,$statuses[$key]); ?></dd></div><?php endforeach; ?></dl></details>
<?php }
function sp_render_worship(array $songs): void { ?>
    <ol class="sheet-song-list" data-sheet-songs>
    <?php foreach($songs as $song): ?>
        <li><strong><?=sp_e($song['title'])?></strong><p><?=sp_e(implode(' · ',array_filter(['Key: '.($song['key']?:'Not entered'),'Lead: '.($song['lead']?:'Not entered'),!empty($song['additional'])?'Additional: '.$song['additional']:''])))?></p><?php if(!empty($song['notes'])): ?><div class="reference"><?=sp_e($song['notes'])?></div><?php endif; ?></li>
    <?php endforeach; ?></ol>
    <p data-empty-songs <?=!$songs?'':'hidden'?>>No songs entered yet.</p>
<?php }

function sp_song_editor(array $song, int $index, bool $archived): void { ?>
    <div class="song">
        <div class="song-head"><h4>Song <span class="song-number"><?=$index+1?></span></h4></div>
        <label class="song-title-field">Song title<input data-song-field="title" name="songs[<?=$index?>][title]" value="<?=sp_e($song['title']??'')?>" maxlength="12000" required></label>
        <div class="song-main-fields">
            <label>Key<input data-song-field="key" name="songs[<?=$index?>][key]" value="<?=sp_e($song['key']??'')?>" maxlength="12000" placeholder="e.g. G"></label>
            <label>Lead vocalist<input data-song-field="lead" name="songs[<?=$index?>][lead]" value="<?=sp_e($song['lead']??'')?>" maxlength="12000"></label>
        </div>
        <details class="song-extra-fields">
            <summary>Additional vocals &amp; notes</summary>
            <label>Additional vocalists<input data-song-field="additional" name="songs[<?=$index?>][additional]" value="<?=sp_e($song['additional']??'')?>" maxlength="12000"></label>
            <label>Notes / instruments<textarea data-song-field="notes" name="songs[<?=$index?>][notes]" maxlength="12000" rows="3"><?=sp_e($song['notes']??'')?></textarea></label>
        </details>
        <?php if(!$archived): ?><div class="song-actions" role="group" aria-label="Song order controls">
            <button class="btn btn-secondary" type="button" data-song-action="up" aria-label="Move song up">↑ Up</button>
            <button class="btn btn-secondary" type="button" data-song-action="down" aria-label="Move song down">↓ Down</button>
            <button class="btn btn-danger" type="button" data-song-action="remove">Remove</button>
        </div><?php endif; ?>
    </div>
<?php }

function sp_work_intro(string $section, bool $hasSunday, bool $hasStations, bool $archived): void
{
    $intro=match($section){
        'sermon'=>['Pastor','Prepare Sunday’s sermon','Start with the title and scriptures. Add Media instructions if needed, then save.'],
        'announcements'=>['Pastor','Prepare church announcements','Add the notes and dates Media needs for this Sunday, then save.'],
        'worship'=>['Music Minister','Build Sunday’s worship plan','Add the songs in order. Set each key and lead vocalist, then save the plan.'],
        'media'=>['Media Team',$hasStations?'Work through your station checklist':'Choose where you’re working',$hasStations?'Check each task when done. Open Guide whenever you need instructions.':'Select your stations below. You can work on more than one computer.'],
        default=>null,
    };
    if(!$intro)return;
    if(!$hasSunday)$intro[2]='First, open the Sunday service below. Then complete your section.';
    if($archived){$intro[1]='Saved Sunday · '.$intro[0];$intro[2]='This Sunday is archived. You can read its saved information and completion state.';}
    $startLabel=$archived?'View saved information':(!$hasSunday?'Choose a Sunday':match($section){'sermon'=>'Start with sermon information','announcements'=>'Start with announcement notes','worship'=>'Start with the song list',default=>$hasStations?'Open my checklists':'Choose my stations'});
    ?><header class="planner-work-header"><p class="planner-eyebrow"><?=sp_e($intro[0])?> workspace</p><h2><?=sp_e($intro[1])?></h2><p class="planner-first-step"><?=sp_e($intro[2])?></p><a class="btn planner-start-button" href="#planner-work-start"><?=sp_e($startLabel)?> <span aria-hidden="true">↓</span></a></header><?php
}
