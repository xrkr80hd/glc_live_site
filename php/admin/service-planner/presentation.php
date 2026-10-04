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
