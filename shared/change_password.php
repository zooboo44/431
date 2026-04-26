<?php
$pageTitle = 'Change Password';
require_once __DIR__ . '/../includes/header.php';

$db     = getDB();
$errors = [];
$saved  = false;
$forced = !empty($_SESSION['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Fetch current hash
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();

        if (!$forced && !password_verify($current, $row['password_hash'] ?? '')) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $passError = validatePassword($new);
            if ($passError) {
                $errors[] = $passError;
            } elseif ($new !== $confirm) {
                $errors[] = 'New passwords do not match.';
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
                   ->execute([$hash, $_SESSION['user_id']]);

                $_SESSION['must_change_password'] = false;
                logAudit($_SESSION['user_id'], 'password_changed', 'users', $_SESSION['user_id']);
                rotateCSRFToken();

                if ($forced) {
                    $role = $_SESSION['role'] ?? '';
                    $dest = ROLE_DASHBOARDS[$role] ?? APP_URL . '/public/standings.php';
                    redirectWithMessage($dest, 'success', 'Password changed successfully. Welcome!');
                }
                $saved = true;
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Change Password</h1>
        <?php if ($forced): ?>
        <p class="page-subtitle" style="color:var(--warning)">&#9888; You must change your password before continuing.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($saved): ?>
<div class="alert alert-success" id="flash-msg">Password changed successfully.</div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card">
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <?php if (!$forced): ?>
        <div class="form-group">
            <label class="form-label required" for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" class="form-control" required>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label required" for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" class="form-control" required>
            <div class="form-hint">Min 8 chars, uppercase, lowercase, number, and special character.</div>
        </div>

        <div class="form-group">
            <label class="form-label required" for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Password</button>
            <?php if (!$forced): ?>
            <a href="javascript:history.back()" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
