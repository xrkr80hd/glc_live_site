<?php
declare(strict_types=1);
require_once __DIR__.'/service-planner/access.php';

// Organize existing destinations without changing their permission gates or URLs.
function admin_media_keys(): array
{
    return ['announcements', 'seasonal-features', 'features', 'ministries', 'social-links',
        'youth-scripture', 'youth-banners', 'youth-albums', 'stream', 'sermons',
        'archived-sermons', 'gallery-videos', 'graphics', 'homepage-content'];
}

function admin_workspace_groups(array $groups): array
{
    $remaining = [];
    $media = [];
    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            if (in_array($item['key'], admin_media_keys(), true) && $item['href'] !== '#admin-coming-soon') $media[$item['key']] = $item;
        }
        $group['items'] = array_values(array_filter($group['items'], static fn(array $item): bool =>
            !in_array($item['key'], admin_media_keys(), true) && !($item['key'] === 'service-song-lists' && $item['href'] === '#admin-coming-soon')));
        if ($group['items']) $remaining[] = $group;
    }
    // Preserve additional implemented publishing routes on the live host.
    $GLOBALS['admin_media_destinations'] = $media;
    return $remaining;
}

function admin_workspace_navigation(string $active): void
{
    $isPlanner = $active === 'service-planner';
    $section = $isPlanner ? (string)($_GET['section'] ?? 'feed') : '';
    $id = $isPlanner ? max(0, (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0)) : 0;
    $base = '/php/admin/service-planner/index.php?'.($id ? 'service_id='.$id.'&amp;' : '');
    $selected = $isPlanner ? sp_selected_computers($_GET['computers'] ?? []) : [];
    if (in_array($section, ['computer1','computer2','computer3','computer4'], true)) $selected = [$section];
    ?>
    <section class="nav-group media-nav-group">
        <button type="button" class="nav-group-toggle" aria-expanded="<?=$active==='media-management'?'true':'false'?>"><span>Media Management</span><span class="nav-group-chevron" aria-hidden="true">⌃</span></button>
        <div class="nav-group-items">
            <a class="resource-link <?=$active==='media-management'?'active':''?>" href="/php/admin/media/index.php">Publishing tools</a>
            <a class="resource-link" href="/php/admin/announcements/index.php">Announcements</a>
            <a class="resource-link" href="/php/admin/media/index.php#ministries">Ministries</a>
            <a class="resource-link" href="/php/admin/announcements/index.php?category=youth">Youth announcements</a>
            <a class="resource-link" href="/php/admin/stream/index.php">Livestream</a>
            <?php if(admin_has_role('pastor','admin','media')): ?><a class="resource-link" href="/php/admin/features/index.php">Featured pages</a><?php endif; ?>
            <a class="resource-link" href="/php/admin/youth-albums/index.php">Photos &amp; galleries</a>
        </div>
    </section>
    <section class="nav-group planner-nav-group">
        <button type="button" class="nav-group-toggle" aria-expanded="<?=$isPlanner?'true':'false'?>"><span>Service Planner</span><span class="nav-group-chevron" aria-hidden="true">⌃</span></button>
        <div class="nav-group-items">
            <a class="resource-link <?=$isPlanner && $section==='feed'?'active':''?>" href="<?=$base?>section=feed">Sunday overview</a>
            <?php if(sp_can_edit_sermon()): ?><a class="resource-link <?=$isPlanner && in_array($section,['sermon','announcements'],true)?'active':''?>" href="<?=$base?>section=sermon">Pastor</a><?php endif; ?>
            <?php if(sp_can_edit_worship()): ?><a class="resource-link <?=$isPlanner && $section==='worship'?'active':''?>" href="<?=$base?>section=worship">Music Minister</a><?php endif; ?>
            <?php if(sp_can_operate_media()): ?>
            <details class="planner-station-menu" <?=$isPlanner && ($section==='media' || $selected)?'open':''?>>
                <summary class="resource-link <?=$isPlanner && ($section==='media' || $selected)?'active':''?>">Media Team</summary>
                <form method="get" action="/php/admin/service-planner/index.php">
                    <?php if($id): ?><input type="hidden" name="service_id" value="<?=$id?>"><?php endif; ?>
                    <input type="hidden" name="section" value="media">
                    <p>Select your stations. You can select more than one.</p>
                    <?php foreach(['foh'=>'Front of House · Sanctuary sound','computer1'=>'Computer 1 · Projectors / slides','computer2'=>'Computer 2 · OBS / YouTube','computer3'=>'Computer 3 · Dante / stream audio','computer4'=>'Computer 4 · Stream slides / cameras'] as $key=>$label): ?>
                    <label><input type="checkbox" name="computers[]" value="<?=$key?>" <?=in_array($key,$selected,true)?'checked':''?>> <span><?=$label?></span></label>
                    <?php endforeach; ?>
                    <button class="btn btn-secondary" type="submit">Open selected stations</button>
                </form>
            </details>
            <?php endif; ?>
            <a class="resource-link <?=$isPlanner && $section==='sheet'?'active':''?>" href="<?=$base?>section=sheet">Service Sheet</a>
            <a class="resource-link <?=$isPlanner && $section==='notes'?'active':''?>" href="<?=$base?>section=notes">Service notes</a>
            <?php if(sp_can_operate_media()): ?><a class="resource-link <?=$isPlanner && $section==='guide'?'active':''?>" href="/php/admin/service-planner/guide.php<?=$id?'?service_id='.$id:''?>" data-guide-popup target="liberty-service-guide">Station manual ↗</a><?php endif; ?>
        </div>
    </section>
    <?php
}

function admin_workspace_active(string $active): string
{
    return in_array($active, admin_media_keys(), true) ? 'media-management' : $active;
}
