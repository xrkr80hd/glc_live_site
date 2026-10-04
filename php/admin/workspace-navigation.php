<?php
declare(strict_types=1);

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
    foreach (['media-management' => ['Media Management', '/php/admin/media/index.php'],
        'service-planner' => ['Service Planner', '/php/admin/service-planner/index.php']] as $key => $item) {
        echo '<a class="resource-nav-home '.($active === $key ? 'active' : '').'" href="'.$item[1].'">'.$item[0].'</a>';
    }
}

function admin_workspace_active(string $active): string
{
    return in_array($active, admin_media_keys(), true) ? 'media-management' : $active;
}
