<?php
declare(strict_types=1);

require_once __DIR__ . '/../layout.php';

admin_require_login();
admin_require_role('pastor', 'admin');

$pdo = db();
$errors = [];
$values = [
    'username' => '',
    'role'     => 'admin',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);

    $values['username'] = trim((string)($_POST['username'] ?? ''));
    $values['role'] = (string)($_POST['role'] ?? 'admin');
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($values['username'] === '') {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,}$/', $values['username'])) {
        $errors[] = 'Username must be at least 3 characters (letters, numbers, dash, underscore).';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :username');
        $stmt->execute([':username' => $values['username']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'This username is already taken.';
        }
    }

    $roleLabels = admin_role_labels();
    if (!array_key_exists($values['role'], $roleLabels)) {
        $errors[] = 'Invalid role selected.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, role, is_active) VALUES (:username, :hash, :role, 1)');
        $stmt->execute([
            ':username' => $values['username'],
            ':hash'     => password_hash($password, PASSWORD_DEFAULT),
            ':role'     => $values['role'],
        ]);

        admin_flash('success', 'Created new admin user: ' . $values['username']);
        admin_redirect('/php/admin/users/index.php');
    }
}

$roleLabels = admin_role_labels();
admin_page_start('Add User', 'users');
?>
<div class="card account-editor">
    <p class="account-eyebrow">TEAM ACCESS</p><h2>Add an account</h2>
    <p class="account-editor-intro">Enter a username, choose an existing role, and set a password.</p>

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
                <select name="role" required>
                    <?php foreach ($roleLabels as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $values['role'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-grid">
            <label>
                Password
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label>
                Confirm password
                <input type="password" name="password_confirm" required autocomplete="new-password">
            </label>
        </div>
        <div class="account-editor-actions">
            <button class="btn btn-primary" type="submit">Create user</button>
            <a class="btn btn-secondary" href="/php/admin/users/index.php">Cancel</a>
        </div>
    </form>
</div>
<?php
admin_page_end();
