<?php
declare(strict_types=1);

require_once __DIR__ . '/../layout.php';

admin_require_login();
admin_require_role('pastor', 'admin');

$pdo = db();
$userId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    admin_flash('error', 'User not found.');
    admin_redirect('/php/admin/users/index.php');
}

$errors = [];
$currentUser = admin_current_user();
$isSelf = $currentUser && (int)$currentUser['id'] === $userId;
$canChangeRole = admin_is_super_admin() || !$isSelf;
$values = [
    'username' => $user['username'],
    'role'     => $user['role'],
    'is_active'=> (int)$user['is_active'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);

    $values['username'] = trim((string)($_POST['username'] ?? ''));
    $values['role'] = (string)($_POST['role'] ?? $user['role']);
    $values['is_active'] = $isSelf ? 1 : (isset($_POST['is_active']) ? 1 : 0);
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($values['username'] === '') {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,}$/', $values['username'])) {
        $errors[] = 'Username must be at least 3 characters (letters, numbers, dash, underscore).';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :username AND id <> :id');
        $stmt->execute([':username' => $values['username'], ':id' => $userId]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'This username is already taken.';
        }
    }

    $roleLabels = admin_role_labels();
    if (!array_key_exists($values['role'], $roleLabels)) {
        $errors[] = 'Invalid role selected.';
    } elseif (!$canChangeRole && $values['role'] !== $user['role']) {
        $errors[] = 'You do not have permission to change this role.';
        $values['role'] = $user['role'];
    }

    if (!$values['is_active'] && admin_is_last_active_role($user['role'], $userId)) {
        $errors[] = 'At least one ' . ($roleLabels[$user['role']] ?? $user['role']) . ' must remain active.';
        $values['is_active'] = 1;
    }

    if ($password !== '') {
        if ($password !== $passwordConfirm) {
            $errors[] = 'Password confirmation does not match.';
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE admin_users SET username = :username, role = :role, is_active = :active WHERE id = :id');
            $update->execute([
                ':username' => $values['username'],
                ':role'     => $values['role'],
                ':active'   => $values['is_active'],
                ':id'       => $userId,
            ]);

            if ($password !== '') {
                $pdo->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id')
                    ->execute([
                        ':hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':id'   => $userId,
                    ]);
            }

            $pdo->commit();
            admin_flash('success', 'Updated user ' . $values['username'] . '.');
            admin_redirect('/php/admin/users/index.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Failed to update admin user: ' . $e->getMessage());
            $errors[] = 'Unable to save changes. Please try again.';
        }
    }
}

$roleLabels = admin_role_labels();
admin_page_start('Edit User', 'users');
?>
<div class="card account-editor">
    <p class="account-eyebrow">TEAM ACCESS</p><h2>Edit account</h2>
    <p class="account-editor-intro">Update credentials or permissions. Leave password blank to keep the current one.</p>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div class="form-grid">
            <label>
                Username
                <input type="text" name="username" required autocomplete="username" value="<?= htmlspecialchars($values['username']) ?>">
            </label>
            <label>
                Role
                <select name="role" required <?= $canChangeRole ? '' : 'disabled' ?>>
                    <?php foreach ($roleLabels as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $values['role'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$canChangeRole): ?>
                    <small style="color:var(--admin-muted);">Only a pastor can change this role.</small>
                <?php endif; ?>
            </label>
        </div>
        <label style="display:flex;align-items:center;gap:10px;">
            <input type="checkbox" name="is_active" value="1" <?= $values['is_active'] ? 'checked' : '' ?><?= $isSelf ? ' disabled' : '' ?>>
            <span><?= $isSelf ? 'You cannot disable your own account.' : 'Active' ?></span>
        </label>
        <div class="form-grid">
            <label>
                New password (optional)
                <input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
            </label>
            <label>
                Confirm new password
                <input type="password" name="password_confirm" autocomplete="new-password" placeholder="Leave blank to keep current password">
            </label>
        </div>
        <div class="account-editor-actions">
            <button class="btn btn-primary" type="submit">Save changes</button>
            <a class="btn btn-secondary" href="/php/admin/users/index.php">Cancel</a>
        </div>
    </form>
</div>
<?php
admin_page_end();
