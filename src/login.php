<?php
session_start();

require_once __DIR__ . '/functions/db_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

$error = "";
$success = isset($_GET['registered']) ? "Registration successful. Please log in." : "";
if (isset($_GET['timeout'])) {
    $success = "Your session timed out. Please log in again.";
} elseif (isset($_GET['reset'])) {
    $success = "Password reset successful. Please log in.";
}

function record_failed_login($db, $email, $account_id = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $query = "INSERT INTO failed_logins (email, account_id, ip_address, user_agent) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("siss", $email, $account_id, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $db = db_connect();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $error = "Invalid email or password.";

    $query = "SELECT accounts.id, accounts.email, accounts.username, accounts.password_hash,
                     accounts.role_id, accounts.team_id, accounts.driver_id, accounts.is_active,
                     roles.display_name AS role_display_name,
                     roles.internal_name AS role_internal_name
              FROM accounts
              JOIN roles ON roles.id = accounts.role_id
              WHERE accounts.email = ?";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $account = $result->fetch_assoc();
        $stmt->close();

        if ($account && (int) $account['is_active'] === 1 && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['account_id'] = (int) $account['id'];
            $_SESSION['user_id'] = (int) $account['id'];
            $_SESSION['email'] = $account['email'];
            $_SESSION['username'] = $account['username'];
            $_SESSION['role_id'] = (int) $account['role_id'];
            $_SESSION['role_display_name'] = $account['role_display_name'];
            $_SESSION['role_internal_name'] = $account['role_internal_name'];
            $_SESSION['team_id'] = $account['team_id'] !== null ? (int) $account['team_id'] : null;
            $_SESSION['driver_id'] = $account['driver_id'] !== null ? (int) $account['driver_id'] : null;
            $_SESSION['last_activity_at'] = time();

            $update = $db->prepare("UPDATE accounts SET last_login_at = NOW() WHERE id = ?");
            if ($update) {
                $update->bind_param("i", $_SESSION['account_id']);
                $update->execute();
                $update->close();
            }

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $audit = $db->prepare("INSERT INTO audit_logs (account_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, 'login_success', 'account', ?, ?, ?)");
            if ($audit) {
                $audit->bind_param("iiss", $_SESSION['account_id'], $_SESSION['account_id'], $ip_address, $user_agent);
                $audit->execute();
                $audit->close();
            }

            $db->close();
            header("Location: member.php");
            exit();
        }

        record_failed_login($db, $email, $account ? (int) $account['id'] : null);
    }

    $db->close();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>F1 Statistics</title>
    </head>
    <body>
        <h1 style="text-align:left;">Login</h1>

        <?php if (!empty($success)): ?>
            <p style="color:green;"><?php echo encode_var($success); ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo encode_var($error); ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <a href="register_form.php">Dont have an account?</a>
            <br><br>
            <label>Email: </label>
            <br>
            <input type="email" name="email" required>

            <br>

            <label>Password: </label>
            <br>
            <input type="password" name="password" required>
            <br>
            <a href="forgot_password_form.php">Forgot password?</a>
            <br>
            <br>
            <button type="submit">Login</button>
        </form>
    </body>
</html>
