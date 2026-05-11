<?php
require_once __DIR__ . '/functions/db_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = "";

if ($token === '') {
    $error = "Password reset link is invalid or expired.";
}

if ($_SERVER['REQUEST_METHOD'] === "POST" && empty($error)) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirmed_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!is_strong_password($new_password)) {
        $error = "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.";
    } else {
        $db = db_connect();
        $token_hash = hash('sha256', $token);

        $stmt = $db->prepare("SELECT password_resets.id, password_resets.account_id
                              FROM password_resets
                              JOIN accounts ON accounts.id = password_resets.account_id
                              WHERE password_resets.token_hash = ?
                                AND password_resets.used_at IS NULL
                                AND password_resets.expires_at > NOW()
                                AND accounts.is_active = 1
                              LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $token_hash);
            $stmt->execute();
            $reset = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($reset) {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $account_id = (int) $reset['account_id'];
                $reset_id = (int) $reset['id'];

                $update = $db->prepare("UPDATE accounts SET password_hash = ? WHERE id = ?");
                if ($update) {
                    $update->bind_param("si", $password_hash, $account_id);
                    $updated = $update->execute();
                    $update->close();

                    if ($updated) {
                        $mark_used = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
                        if ($mark_used) {
                            $mark_used->bind_param("i", $reset_id);
                            $mark_used->execute();
                            $mark_used->close();
                        }

                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
                        $audit = $db->prepare("INSERT INTO audit_logs (account_id, action, entity_type, entity_id, ip_address, user_agent)
                                               VALUES (?, 'password_reset_completed', 'account', ?, ?, ?)");
                        if ($audit) {
                            $audit->bind_param("iiss", $account_id, $account_id, $ip_address, $user_agent);
                            $audit->execute();
                            $audit->close();
                        }

                        $db->close();
                        header("Location: login.php?reset=1");
                        exit();
                    }
                }

                $db->close();
                $error = "Password reset failed. Please request a new reset link.";
            }
        }

        if (empty($error)) {
            $db->close();
            $error = "Password reset link is invalid or expired.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>F1 Statistics - Reset Password</title>
    </head>
    <body>
        <h1>Reset Password</h1>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo e($error); ?></p>
        <?php endif; ?>

        <?php if ($token !== ''): ?>
            <form action="reset_password.php" method="POST">
                <input type="hidden" name="token" value="<?php echo e($token); ?>">

                <label>New password:</label>
                <br>
                <input type="password" name="new_password" required>
                <br>

                <label>Confirm password:</label>
                <br>
                <input type="password" name="confirmed_password" required>
                <br><br>

                <button type="submit">Reset password</button>
            </form>
        <?php endif; ?>

        <form action="login.php" method="GET">
            <button type="submit">Go back to Login</button>
        </form>
    </body>
</html>
