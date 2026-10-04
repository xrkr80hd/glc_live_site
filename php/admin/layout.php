<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_db_or_setup_page(string $title, string $active): PDO
{
    try {
        return db();
    } catch (Throwable $e) {
        $drivers = PDO::getAvailableDrivers();
        admin_page_start($title, $active);
        ?>
        <section class="card">
            <h3>Local Database Setup Needed</h3>
            <p class="muted">
                The admin shell is available for conversion work, but this tool needs a MySQL connection before records can load or save.
            </p>
            <div class="flash flash-error">MySQL is unavailable in this local PHP runtime or the configured credentials are not reachable from here.</div>
            <table class="table">
                <tbody>
                    <tr>
                        <th>Configured host</th>
                        <td><?= htmlspecialchars(DB_HOST) ?></td>
                    </tr>
                    <tr>
                        <th>Configured database</th>
                        <td><?= htmlspecialchars(DB_NAME) ?></td>
                    </tr>
                    <tr>
                        <th>Configured user</th>
                        <td><?= htmlspecialchars(DB_USER) ?></td>
                    </tr>
                    <tr>
                        <th>Available PDO drivers</th>
                        <td><?= htmlspecialchars($drivers ? implode(', ', $drivers) : 'none') ?></td>
                    </tr>
                </tbody>
            </table>
            <p>
                On cPanel, run <code>database/glc_cpanel_full_schema.sql</code> in MySQL/MariaDB and keep <code>php/config.php</code> pointed at that database.
            </p>
            <p>
                On this local Windows PHP server, enable the <code>pdo_mysql</code> extension to make the admin tools connect locally.
            </p>
        </section>
        <?php
        admin_page_end();
        exit;
    }
}

function admin_page_start(string $title, string $active = ''): void
{
    $user = admin_current_user();
    $flashes = admin_consume_flashes();
    $roleLabels = admin_role_labels();
    $roleLabel = $user ? ($roleLabels[$user['role']] ?? ucfirst($user['role'])) : '';
    $cssPath = __DIR__ . '/../../assets/admin.css';
    $cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';

    $resourceGroups = [
        [
            'label' => 'Main Page',
            'items' => [
                ['key' => 'announcements', 'label' => 'Announcements', 'href' => '/php/admin/announcements/index.php'],
                ['key' => 'ministries', 'label' => 'Ministries', 'href' => '#admin-coming-soon'],
                ['key' => 'seasonal-features', 'label' => 'Feature', 'href' => '/php/admin/features/index.php'],
                ['key' => 'social-links', 'label' => 'Social Links', 'href' => '#admin-coming-soon'],
            ],
        ],
        [
            'label' => 'Youth Page',
            'items' => [
                ['key' => 'youth-scripture', 'label' => 'Scripture OTW', 'href' => '/php/admin/youth-scripture/index.php'],
                ['key' => 'youth-banners', 'label' => 'Youth Ticker', 'href' => '#admin-coming-soon'],
                ['key' => 'youth-albums', 'label' => 'Youth Albums', 'href' => '/php/admin/youth-albums/index.php'],
            ],
        ],
        [
            'label' => 'Live Stream / Sermons',
            'items' => [
                ['key' => 'stream', 'label' => 'Livestream', 'href' => '/php/admin/stream/index.php'],
                ['key' => 'service-song-lists', 'label' => 'Service Song Lists', 'href' => '#admin-coming-soon'],
                ['key' => 'sermons', 'label' => 'Sermons', 'href' => '#admin-coming-soon'],
                ['key' => 'archived-sermons', 'label' => 'Archived Sermons', 'href' => '#admin-coming-soon'],
            ],
        ],
        [
            'label' => 'Team & Access',
            'items' => [
                ['key' => 'users', 'label' => 'Team Members', 'href' => '/php/admin/users/index.php'],
                ['key' => 'team-roles', 'label' => 'Team Roles', 'href' => '#admin-coming-soon'],
                ['key' => 'team-member-roles', 'label' => 'Role Assignments', 'href' => '#admin-coming-soon'],
            ],
        ],
        [
            'label' => 'Operations',
            'items' => [
                ['key' => 'ministry-order-requests', 'label' => 'Ministry Orders', 'href' => '#admin-coming-soon'],
                ['key' => 'bookkeeping-reports', 'label' => 'Bookkeeping', 'href' => '#admin-coming-soon'],
            ],
        ],
        [
            'label' => 'Requests',
            'items' => [
                ['key' => 'prayers', 'label' => 'Prayer Requests', 'href' => '/php/admin/prayers/index.php'],
                ['key' => 'visits', 'label' => 'Visit Requests', 'href' => '/php/admin/visits/index.php'],
            ],
        ],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title><?= htmlspecialchars($title) ?> · Liberty Church Admin</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/assets/admin.css?v=<?= rawurlencode($cssVersion) ?>">
    </head>
    <body class="admin-dark">
    <div class="admin-shell">
        <header class="admin-topbar">
            <div class="admin-brand-block">
                <h1>Liberty Church Admin</h1>
                <p>Signed in: <?= htmlspecialchars($user['username'] ?? 'local-admin') ?><?= $roleLabel ? ' · ' . htmlspecialchars($roleLabel) : '' ?></p>
            </div>
            <div class="admin-top-actions">
                <a href="/php/admin/dashboard.php" class="admin-top-link">Refresh</a>
                <a href="/" target="_blank" rel="noreferrer" class="admin-top-link">Visit Site</a>
                <a href="/php/admin/logout.php" class="admin-top-link">Log Out</a>
            </div>
        </header>

        <section class="admin-manager-panel">
            <button type="button" class="resource-nav-launcher" aria-label="Open content menu" aria-expanded="false" aria-controls="adminResourceMenu">
                <span class="launcher-open" aria-hidden="true">☰</span>
                <span class="launcher-close" aria-hidden="true">×</span>
            </button>
            <button type="button" class="resource-nav-backdrop" aria-label="Close content menu" hidden></button>

            <aside id="adminResourceMenu" class="resource-nav" aria-label="Admin resources">
                <a href="/php/admin/dashboard.php" class="resource-nav-home <?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <?php foreach ($resourceGroups as $group): ?>
                    <section class="nav-group">
                        <button type="button" class="nav-group-toggle" aria-expanded="true">
                            <span><?= htmlspecialchars($group['label']) ?></span>
                            <span class="nav-group-chevron" aria-hidden="true">⌃</span>
                        </button>
                        <div class="nav-group-items">
                            <?php foreach ($group['items'] as $item): ?>
                                <?php
                                if ($item['key'] === 'visits' && !admin_can_view_visits()) {
                                    continue;
                                }
                                if ($item['key'] === 'prayers' && !admin_can_view_prayers()) {
                                    continue;
                                }
                                if ($item['key'] === 'seasonal-features' && !admin_has_role('pastor', 'admin', 'media')) {
                                    continue;
                                }
                                if ($item['key'] === 'users' && !admin_can_manage_users()) {
                                    continue;
                                }
                                $isComingSoon = $item['href'] === '#admin-coming-soon';
                                ?>
                                <a
                                    href="<?= htmlspecialchars($item['href']) ?>"
                                    class="resource-link <?= $active === $item['key'] ? 'active' : '' ?> <?= $isComingSoon ? 'is-disabled' : '' ?>"
                                    <?= $isComingSoon ? 'aria-disabled="true"' : '' ?>
                                >
                                    <span class="resource-link-dot" aria-hidden="true"></span>
                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </aside>

            <main class="admin-content">
                <div class="admin-mobile-row">
                    <button type="button" class="btn btn-secondary resource-menu-inline" aria-expanded="false" aria-controls="adminResourceMenu">Content Menu</button>
                </div>
                <header class="admin-content-head">
                    <div>
                        <h2><?= htmlspecialchars($title) ?></h2>
                        <p>Manage records for this section.</p>
                    </div>
                </header>
            <?php if ($flashes): ?>
                <div class="flash-stack">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
                            <?= htmlspecialchars($flash['message']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
    <?php
}

function admin_page_end(): void
{
    ?>
            </main>
        </section>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const launcher = document.querySelector('.resource-nav-launcher');
        const inlineLauncher = document.querySelector('.resource-menu-inline');
        const drawer = document.getElementById('adminResourceMenu');
        const backdrop = document.querySelector('.resource-nav-backdrop');
        const groupToggles = document.querySelectorAll('.nav-group-toggle');
        const disabledLinks = document.querySelectorAll('.resource-link.is-disabled');

        function setDrawer(open) {
            if (!drawer || !launcher || !backdrop) return;
            drawer.classList.toggle('open', open);
            launcher.classList.toggle('open', open);
            launcher.setAttribute('aria-expanded', String(open));
            if (inlineLauncher) inlineLauncher.setAttribute('aria-expanded', String(open));
            backdrop.hidden = !open;
        }

        function setGroup(toggle, open) {
            const group = toggle.closest('.nav-group');
            const items = toggle.nextElementSibling;

            toggle.setAttribute('aria-expanded', String(open));
            toggle.classList.toggle('collapsed', !open);
            if (group) group.classList.toggle('is-collapsed', !open);
            if (items) items.hidden = !open;
        }

        if (launcher) launcher.addEventListener('click', () => setDrawer(!launcher.classList.contains('open')));
        if (inlineLauncher) inlineLauncher.addEventListener('click', () => setDrawer(!(launcher && launcher.classList.contains('open'))));
        if (backdrop) backdrop.addEventListener('click', () => setDrawer(false));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setDrawer(false);
        });

        groupToggles.forEach((toggle) => {
            const group = toggle.closest('.nav-group');
            const hasActiveLink = group ? Boolean(group.querySelector('.resource-link.active')) : false;
            setGroup(toggle, hasActiveLink);

            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                const nextOpen = toggle.getAttribute('aria-expanded') !== 'true';

                if (nextOpen) {
                    groupToggles.forEach((otherToggle) => {
                        if (otherToggle !== toggle) setGroup(otherToggle, false);
                    });
                }

                setGroup(toggle, nextOpen);
            });
        });

        disabledLinks.forEach((link) => {
            link.addEventListener('click', (event) => event.preventDefault());
        });
    });
    </script>
    </body>
    </html>
    <?php
}
