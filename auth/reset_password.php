<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../middleware/auth_check.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

startSecureSession();

$db = getDB();
$rawToken = trim($_GET['token'] ?? '');
$errors   = [];
$success  = false;
$tokenRow = null;

if ($rawToken) {
    $stmt = $db->prepare(
        "SELECT id, reset_token_used_at, reset_token_expires
         FROM users
         WHERE reset_token_hash = SHA2(?, 256) AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$rawToken]);
    $tokenRow = $stmt->fetch();

    if (!$tokenRow) {
        $errors[] = 'Invalid or expired reset token.';
        $tokenRow = null;
    } elseif ($tokenRow['reset_token_used_at'] !== null) {
        $errors[] = 'This reset token has already been used.';
        $tokenRow = null;
    } elseif (strtotime($tokenRow['reset_token_expires']) < time()) {
        $errors[] = 'This reset token has expired.';
        $tokenRow = null;
    }
} else {
    $errors[] = 'No reset token provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenRow) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $passError = validatePassword($newPass);
        if ($passError) {
            $errors[] = $passError;
        } elseif ($newPass !== $confirmPass) {
            $errors[] = 'Passwords do not match.';
        } else {
            $db->beginTransaction();
            try {
                // Re-check token under lock
                $stmt = $db->prepare(
                    "SELECT id, reset_token_used_at, reset_token_expires
                     FROM users
                     WHERE reset_token_hash = SHA2(?, 256) AND is_active = 1
                     FOR UPDATE"
                );
                $stmt->execute([$rawToken]);
                $tokenCheck = $stmt->fetch();

                if (!$tokenCheck || $tokenCheck['reset_token_used_at'] !== null || strtotime($tokenCheck['reset_token_expires']) < time()) {
                    $errors[] = 'Token is no longer valid (possibly used concurrently).';
                    $db->rollBack();
                } else {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->prepare(
                        'UPDATE users SET password_hash=?, must_change_password=0,
                         reset_token_used_at=NOW(),
                         session_token=NULL, session_ip=NULL, session_ua=NULL, session_at=NULL
                         WHERE id=?'
                    )->execute([$hash, $tokenCheck['id']]);

                    $db->commit();
                    logAudit($tokenCheck['id'], 'password_reset_redeemed', 'users', $tokenCheck['id']);
                    $success = true;
                }
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'An error occurred. Please try again.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Reset Password — ' . APP_NAME;

if ($success) {
    header('Location: ' . APP_URL . '/auth/login.php?reset_done=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="public-body">
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <span class="brand-icon">&#128274;</span>
            <h1>Reset Password</h1>
            <p><?= h(APP_NAME) ?></p>
        </div>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= h($err) ?></div>
        <?php endforeach; ?>

        <?php if ($tokenRow && empty($errors)): ?>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <div class="form-group">
                <label class="form-label required" for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required autofocus>
                <div class="form-hint">Min 8 chars, uppercase, lowercase, number, and special character.</div>
            </div>

            <div class="form-group">
                <label class="form-label required" for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Set New Password</button>
        </form>
        <?php elseif (!$tokenRow): ?>
        <p style="text-align:center;margin-top:1rem">
            <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline">Return to Login</a>
        </p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
