<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../middleware/auth_check.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

startSecureSession();

// Already logged in
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'fan';
    $dest = ROLE_DASHBOARDS[$role] ?? APP_URL . '/public/standings.php';
    header('Location: ' . $dest);
    exit;
}

$errors = [];
$emailVal = '';

// Handle messages from query string
$timeout    = isset($_GET['timeout']);
$displaced  = isset($_GET['displaced']);
$loggedOut  = isset($_GET['logged_out']);
$resetDone  = isset($_GET['reset_done']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = verifyCSRFToken($_POST['csrf_token'] ?? '');
    if (!$csrfOk) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $emailVal = $email;

        // Rate limit check
        if (!checkRateLimit($ip, 'login_failed', LOGIN_MAX_ATTEMPTS, LOGIN_WINDOW_MINUTES)) {
            $errors[] = 'Too many failed login attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
            logAudit(null, 'login_failed', null, null, 'Invalid email: ' . substr($email, 0, 50));
        } else {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($pass, $user['password_hash'])) {
                $errors[] = 'Invalid email or password.';
                logAudit($user['id'] ?? null, 'login_failed', null, null, 'Email: ' . $email);
            } else {
                // Successful login
                session_regenerate_id(true);
                $sessionId = session_id();

                $_SESSION['user_id']              = $user['id'];
                $_SESSION['role']                 = $user['role'];
                $_SESSION['linked_id']            = $user['linked_id'];
                $_SESSION['must_change_password'] = (bool)$user['must_change_password'];
                $_SESSION['last_activity']        = time();

                // Single session enforcement for restricted roles
                if (in_array($user['role'], RESTRICTED_ROLES, true)) {
                    // Insert session row first
                    $db->prepare(
                        'INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity)
                         VALUES (?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE last_activity = NOW()'
                    )->execute([
                        $sessionId,
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
                    ]);

                    // Kill all other sessions
                    enforceSingleSession($user['id']);
                }

                // Update last login
                $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

                logAudit($user['id'], 'login_success', 'users', $user['id']);
                rotateCSRFToken();

                if ($user['must_change_password']) {
                    header('Location: ' . APP_URL . '/shared/change_password.php');
                    exit;
                }

                $redirect = filter_var($_POST['redirect'] ?? '', FILTER_SANITIZE_URL);
                if ($redirect && str_starts_with($redirect, '/f1app/')) {
                    header('Location: http://localhost' . $redirect);
                } else {
                    $dest = ROLE_DASHBOARDS[$user['role']] ?? APP_URL . '/public/standings.php';
                    header('Location: ' . $dest);
                }
                exit;
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$redirect  = htmlspecialchars($_GET['redirect'] ?? '', ENT_QUOTES, 'UTF-8');
$pageTitle = 'Login — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="public-body">
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <span class="brand-icon">&#9872;</span>
            <h1><?= h(APP_NAME) ?></h1>
            <p>Portal Login</p>
        </div>

        <?php if ($timeout): ?>
        <div class="alert alert-warning">Your session expired due to inactivity. Please log in again.</div>
        <?php endif; ?>
        <?php if ($displaced): ?>
        <div class="alert alert-warning">You have been signed in from another location. Please log in again.</div>
        <?php endif; ?>
        <?php if ($loggedOut): ?>
        <div class="alert alert-success" id="flash-msg">You have been successfully logged out.</div>
        <?php endif; ?>
        <?php if ($resetDone): ?>
        <div class="alert alert-success" id="flash-msg">Password reset successful. You can now log in.</div>
        <?php endif; ?>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= h($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="redirect" value="<?= $redirect ?>">

            <div class="form-group">
                <label class="form-label required" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= h($emailVal) ?>" required autocomplete="email" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label required" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Sign In</button>
        </form>

        <p style="margin-top:1.5rem;text-align:center;font-size:0.8rem;color:var(--text-muted)">
            <a href="<?= APP_URL ?>/">← Back to Public Site</a>
        </p>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
