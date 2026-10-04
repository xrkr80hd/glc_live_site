<?php
declare(strict_types=1);
require_once __DIR__ . '/../layout.php';
admin_require_login();
admin_page_start('Media Management', 'media-management');
?>
<link rel="stylesheet" href="/assets/admin-workspace.css?v=2">
<div class="workspace">
    <p>Publish and manage website content using the existing tools.</p>
    <section class="workspace-section">
        <h3>Website</h3>
        <details class="tool-detail"><summary>Announcements</summary><p>Manage published announcements, dates, and their display order.</p><a class="btn" href="/php/admin/announcements/index.php">Manage announcements</a><a class="btn btn-secondary" href="/php/admin/announcements/new.php">Add announcement</a></details>
        <?php if (admin_has_role('pastor', 'admin', 'media')): ?>
        <details class="tool-detail"><summary>Featured pages &amp; homepage feature</summary><p>Manage Operation Christmas Child, its page content, sharing graphic, organizers, contact messages, and homepage visibility.</p><a class="btn" href="/php/admin/features/index.php">Edit featured content</a></details>
        <?php endif; ?>
        <details class="tool-detail"><summary>Youth announcements &amp; scripture</summary><p><a class="btn" href="/php/admin/announcements/index.php?category=youth">Manage youth announcements</a></p><p>Edit the scripture displayed on the youth page.</p><a class="btn" href="/php/admin/youth-scripture/index.php">Edit scripture</a></details>
    </section>
    <section class="workspace-section" id="ministries"><h3>Ministries</h3>
        <?php $ministryTool=$GLOBALS['admin_media_destinations']['ministries']??null; if($ministryTool): ?><a class="btn" href="<?=htmlspecialchars($ministryTool['href'],ENT_QUOTES,'UTF-8')?>">Manage ministry content</a><?php else: ?><p>The existing backend does not yet have a ministry content editor. Ministry announcement dates and notes use the existing announcements tool.</p><a class="btn btn-secondary" href="/php/admin/announcements/index.php">Manage announcements</a><?php endif; ?>
    </section>
    <section class="workspace-section">
        <h3>Photos &amp; galleries</h3>
        <details class="tool-detail"><summary>Existing albums, photos &amp; videos</summary><p>Select an album to upload or manage its photos and videos.</p><a class="btn" href="/php/admin/youth-albums/index.php">Manage albums and media</a></details>
        <details class="tool-detail"><summary>Create an album</summary><p>Create an album, then use its existing upload tool.</p><a class="btn" href="/php/admin/youth-albums/new.php">Create album</a></details>
    </section>
    <section class="workspace-section">
        <h3>Sermon / service media</h3>
        <details class="tool-detail"><summary>Livestream publishing settings</summary><p>Use the existing website livestream publishing tool. Sunday operating checklists are in Service Planner.</p><a class="btn" href="/php/admin/stream/index.php">Manage website livestream</a></details>
    </section>
    <?php
    $covered = ['ministries','announcements', 'seasonal-features', 'features', 'youth-scripture', 'youth-albums', 'stream'];
    $additional = array_filter($GLOBALS['admin_media_destinations'] ?? [], static fn($item) => !in_array($item['key'], $covered, true));
    if ($additional): ?>
    <section class="workspace-section"><h3>Other existing website content</h3>
    <?php foreach ($additional as $item): ?>
        <details class="tool-detail"><summary><?=htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')?></summary><a class="btn" href="<?=htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8')?>">Open existing tool</a></details>
    <?php endforeach; ?></section>
    <?php endif; ?>
    <a class="btn btn-secondary" href="/php/admin/service-planner/index.php">Open Service Planner</a>
</div>
<?php admin_page_end(); ?>
