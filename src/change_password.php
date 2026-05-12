<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $db = db_connect();
    $account_id = current_user_id();
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirmed_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (!is_strong_password($new_password)) {
        $error = "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.";
    } else {
        $stmt = $db->prepare("SELECT password_hash FROM accounts WHERE id = ? AND is_active = 1");
        if ($stmt) {
            $stmt->bind_param("i", $account_id);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$account || !password_verify($current_password, $account['password_hash'])) {
                $error = "Password change failed.";
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $db->prepare("UPDATE accounts SET password_hash = ? WHERE id = ?");

                if ($update) {
                    $update->bind_param("si", $password_hash, $account_id);
                    if ($update->execute()) {
                        $success = "Password successfully changed.";

                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
                        $audit = $db->prepare("INSERT INTO audit_logs (account_id, action, entity_type, entity_id, ip_address, user_agent)
                                               VALUES (?, 'password_changed', 'account', ?, ?, ?)");
                        if ($audit) {
                            $audit->bind_param("iiss", $account_id, $account_id, $ip_address, $user_agent);
                            $audit->execute();
                            $audit->close();
                        }
                    } else {
                        $error = "Password change failed.";
                    }
                    $update->close();
                } else {
                    $error = "Password change failed.";
                }
            }
        } else {
            $error = "Password change failed.";
        }
    }

    $db->close();
} else {
    header("Location: change_password_form.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>F1 Statistics</title>
    </head>
    <body>
        <h1 style="text-align: left;">Change Password</h1>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo encode_var($error); ?></p>
            <form action="change_password_form.php" method="GET">
                <button type="submit">Try Again</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <p style="color:green;"><?php echo encode_var($success); ?></p>
            <form action="member.php" method="GET">
                <button type="submit">Go back to homepage</button>
            </form>
        <?php endif; ?>
    </body>
</html>
