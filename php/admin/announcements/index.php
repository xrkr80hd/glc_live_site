<?php
declare(strict_types=1);

require_once __DIR__ . '/../layout.php';

admin_require_login();

$pdo = db();
$message = trim((string) ($_GET['message'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

$categoryFilter=($_GET['category']??'')==='youth'?'youth':'';
$stmt = $pdo->query("
    SELECT id, category, title, body, start_date, end_date, is_published, sort_order, created_at, updated_at
    FROM announcements
    ".($categoryFilter?"WHERE category='youth'":"")."
    ORDER BY is_published DESC, sort_order ASC, created_at DESC, id DESC
");
$announcements = $stmt->fetchAll();

admin_page_start($categoryFilter?'Youth Announcements':'Announcements', 'announcements');
?>

<?php if ($message): ?>
    <div class="flash flash-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="card announcement-card-shell">
    <div class="card-header announcement-list-header">
        <div class="announcement-page-head">
            <h3><?=$categoryFilter?'Youth Announcements':'Manage Announcements'?></h3>
            <p class="announcement-page-copy"><?=$categoryFilter?'Youth-page announcements. Open all announcements to change the website display order.':'Use the arrows to move announcements up or down. New announcements publish straight to the site, and edits save without any sort-order box.'?></p><?php if($categoryFilter): ?><a class="btn btn-secondary" href="index.php">View all announcements</a><?php endif; ?>
        </div>
        <a href="new.php<?=$categoryFilter?'?category=youth':''?>" class="btn btn-primary">+ New Announcement</a>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <p>No announcements yet. Create your first one to get started.</p>
            <a href="new.php<?=$categoryFilter?'?category=youth':''?>" class="btn btn-primary">+ Create Announcement</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table announcement-table">
                <thead>
                    <tr>
                        <th>Announcement</th>
                        <th>Placement</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Move</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $index => $ann): ?>
                        <?php
                        $previous = $announcements[$index - 1] ?? null;
                        $next = $announcements[$index + 1] ?? null;
                        $canMoveUp = !$categoryFilter && $previous && (int) $previous['is_published'] === (int) $ann['is_published'];
                        $canMoveDown = !$categoryFilter && $next && (int) $next['is_published'] === (int) $ann['is_published'];
                        $bodyPreview = trim((string) $ann['body']);
                        if (function_exists('mb_substr')) {
                            $previewText = mb_substr($bodyPreview, 0, 96);
                            $hasMore = mb_strlen($bodyPreview) > 96;
                        } else {
                            $previewText = substr($bodyPreview, 0, 96);
                            $hasMore = strlen($bodyPreview) > 96;
                        }
                        ?>
                        <tr>
                            <td class="announcement-title-cell">
                                <strong><?= htmlspecialchars($ann['title']) ?></strong>
                                <small class="muted announcement-preview"><?= htmlspecialchars($previewText) ?><?= $hasMore ? '…' : '' ?></small>
                            </td>
                            <td>
                                <span class="announcement-meta-label"><?= htmlspecialchars(ucfirst($ann['category'])) ?></span>
                            </td>
                            <td>
                                <small class="announcement-meta-block">
                                    <?php if ($ann['start_date']): ?>
                                        <span>Starts <?= htmlspecialchars((string) $ann['start_date']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($ann['end_date']): ?>
                                        <span>Ends <?= htmlspecialchars((string) $ann['end_date']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!$ann['start_date'] && !$ann['end_date']): ?>
                                        <span>Always visible</span>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <span class="announcement-status <?= $ann['is_published'] ? 'is-live' : 'is-muted' ?>">
                                    <?= $ann['is_published'] ? 'Published' : 'Draft' ?>
                                </span>
                            </td>
                            <td>
                                <div class="announcement-move-cell">
                                    <form method="POST" action="reorder.php" class="move-form">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= (int) $ann['id'] ?>">
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="move-btn" aria-label="Move announcement up" <?= $canMoveUp ? '' : 'disabled' ?>>↑</button>
                                    </form>
                                    <form method="POST" action="reorder.php" class="move-form">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= (int) $ann['id'] ?>">
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="move-btn" aria-label="Move announcement down" <?= $canMoveDown ? '' : 'disabled' ?>>↓</button>
                                    </form>
                                </div>
                            </td>
                            <td class="actions">
                                <a href="edit.php?id=<?= (int) $ann['id'] ?>" class="btn btn-sm">Edit</a>
                                <form method="POST" action="delete.php" onsubmit="return confirm('Delete this announcement?');">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="id" value="<?= (int) $ann['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
admin_page_end();
?>
