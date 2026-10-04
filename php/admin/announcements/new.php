<?php
declare(strict_types=1);

require_once __DIR__ . '/../layout.php';

admin_require_login();

$pdo = db();
$error = trim((string) ($_GET['error'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $category = trim((string) ($_POST['category'] ?? 'main'));
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $endDate = trim((string) ($_POST['end_date'] ?? ''));

    if ($title === '' || $body === '') {
        header('Location: new.php?error=' . urlencode('Title and body are required.'));
        exit;
    }

    $nextSortStmt = $pdo->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM announcements WHERE is_published = 1");
    $nextSortOrder = (int) $nextSortStmt->fetchColumn();

    $sql = "INSERT INTO announcements (category, title, body, start_date, end_date, sort_order, is_published)
            VALUES (:category, :title, :body, :start_date, :end_date, :sort_order, :is_published)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':category' => $category,
        ':title' => $title,
        ':body' => $body,
        ':start_date' => $startDate ?: null,
        ':end_date' => $endDate ?: null,
        ':sort_order' => $nextSortOrder,
        ':is_published' => 1,
    ]);

    header('Location: index.php?message=' . urlencode('Announcement published to the site.'));
    exit;
}

admin_page_start('New Announcement', 'announcements');
?>

<?php if ($error): ?>
    <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="card announcement-card-shell">
    <div class="announcement-page-head">
        <h3>New Announcement</h3>
        <p class="announcement-page-copy">Write the announcement once and publish it straight to the site. Ordering is handled from the announcements list with arrows, not by typing numbers.</p>
    </div>

    <form method="POST" class="announcement-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <section class="announcement-section">
            <div class="announcement-section-head">
                <h4>Announcement Details</h4>
                <p>Choose where it shows up, give it a title, and write the message people need to see.</p>
            </div>

            <div class="announcement-grid announcement-grid-single">
                <div class="form-group">
                    <label for="category">Show On</label>
                    <select id="category" name="category" required>
                        <option value="main">Main (Homepage)</option>
                        <option value="youth" <?=($_GET['category']??'')==='youth'?'selected':''?>>Youth Page</option>
                        <option value="event">Event</option>
                        <option value="global">Global (All Pages)</option>
                    </select>
                </div>
            </div>

            <div class="announcement-grid announcement-grid-single">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required maxlength="255" placeholder="Special Service This Sunday">
                </div>
            </div>

            <div class="announcement-grid announcement-grid-single">
                <div class="form-group">
                    <label for="body">Announcement</label>
                    <textarea id="body" name="body" required rows="8" placeholder="Share the full announcement here..."></textarea>
                </div>
            </div>
        </section>

        <section class="announcement-section">
            <div class="announcement-section-head">
                <h4>Scheduling</h4>
                <p>Leave the dates blank if this announcement should stay visible until you replace or remove it.</p>
            </div>

            <div class="announcement-grid">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date">
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date">
                </div>
            </div>
        </section>

        <div class="form-actions announcement-actions">
            <button type="submit" class="btn btn-primary">Publish to Site</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</section>

<?php
admin_page_end();
?>
