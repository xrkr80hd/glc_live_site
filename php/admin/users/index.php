<?php
declare(strict_types=1);

require_once __DIR__ . '/../layout.php';

admin_require_login();
admin_require_role('pastor', 'admin');

$pdo = db();
$users = $pdo->query('SELECT id, username, role, is_active, created_at, last_login FROM admin_users ORDER BY username ASC')->fetchAll();
$roleLabels = admin_role_labels();

admin_page_start('Manage Users', 'users');
?>
<section class="card account-management" aria-labelledby="accounts-heading">
    <header class="account-page-header">
        <div><p class="account-eyebrow">TEAM ACCESS</p><h2 id="accounts-heading">Manage accounts</h2>
        <p>View each person’s access. Choose Edit to update their account.</p></div>
        <a class="btn btn-primary" href="/php/admin/users/new.php">Add user</a>
    </header>
    <?php if ($users): ?>
    <ul class="account-list" aria-label="Team accounts">
    <?php foreach ($users as $user): $isSelf = (int)$user['id'] === (admin_current_user()['id'] ?? 0); ?>
        <li class="account-entry">
            <div class="account-identity"><h3><?= htmlspecialchars($user['username']) ?></h3>
                <p><?= htmlspecialchars($roleLabels[$user['role']] ?? ucfirst($user['role'])) ?></p>
                <?php if ($isSelf): ?><small>Your account</small><?php endif; ?>
            </div>
            <span class="account-status <?= (int)$user['is_active'] ? 'is-active' : 'is-disabled' ?>"><?= (int)$user['is_active'] ? 'Active' : 'Disabled' ?></span>
            <dl class="account-metadata">
                <div><dt>Created</dt><dd><?= htmlspecialchars(format_datetime((string)$user['created_at'])) ?></dd></div>
                <div><dt>Last login</dt><dd><?= $user['last_login'] ? htmlspecialchars(format_datetime((string)$user['last_login'])) : 'Never' ?></dd></div>
            </dl>
            <div class="account-actions" role="group" aria-label="Actions for <?= htmlspecialchars($user['username']) ?>">
                <a class="btn btn-secondary" href="/php/admin/users/edit.php?id=<?= (int)$user['id'] ?>">Edit</a>
                <?php if (!$isSelf): ?>
                <form method="post" action="toggle.php" onsubmit="return confirm('Toggle active status for <?= htmlspecialchars($user['username']) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                    <button class="btn <?= (int)$user['is_active'] ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= (int)$user['is_active'] ? 'Disable' : 'Activate' ?></button>
                </form>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php else: ?><div class="empty-state">No admin accounts created yet.</div><?php endif; ?>
</section>
<?php admin_page_end();
