<?php
require_once __DIR__ . '/functions/db_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

$reset_link = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $email = trim($_POST['email'] ?? '');

    if (is_valid_email($email)) {
        $db = db_connect();

        $stmt = $db->prepare("SELECT id FROM accounts WHERE email = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($account) {
                $account_id = (int) $account['id'];
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);

                $expire_old = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE account_id = ? AND used_at IS NULL");
                if ($expire_old) {
                    $expire_old->bind_param("i", $account_id);
                    $expire_old->execute();
                    $expire_old->close();
                }

                $insert = $db->prepare("INSERT INTO password_resets (account_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
                if ($insert) {
                    $insert->bind_param("is", $account_id, $token_hash);
                    $created = $insert->execute();
                    $insert->close();

                    if ($created) {
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $path = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
                        $reset_link = $scheme . '://' . $host . $path . '/reset_password.php?token=' . urlencode($token);
                    }
                }
            }
        }

        $db->close();
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>F1 Statistics - Password Reset</title>
    </head>
    <body>
        <h1>Password Reset</h1>
        <p>If an account exists, a reset link has been created.</p>

        <?php if (!empty($reset_link)): ?>
            <p><strong>Local testing only:</strong> Email is not configured. Use this reset link:</p>
            <p><a href="<?php echo e($reset_link); ?>"><?php echo e($reset_link); ?></a></p>
        <?php endif; ?>

        <form action="login.php" method="GET">
            <button type="submit">Go back to Login</button>
        </form>
    </body>
</html>
