<?php
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/functions/db_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';
require_once __DIR__ . '/functions/output_fns.php';
require_once "config.php";
require_once "Mail.php";

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
                $newPassword = bin2hex(random_bytes(6));
                $newPassword = '@' . strtoupper($newPassword[0]) . $newPassword;
                $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);

                $updateQuery = "UPDATE accounts SET password_hash = ? WHERE email = ?";
                $update = $db->prepare($updateQuery);
                if ($update) {
                    $update->bind_param("ss", $password_hash, $email);
                    if ($update->execute()) {
                        $from = User_name;
                        $to = $email;

                        $host = "ssl://smtp.gmail.com";
                        $port = "465";
                        $username = User_name;
                        $password = Pass_word;

                        $subject = "Your new password";
                        $message = "Your password has been reset. \n\n Your new password is $newPassword";
                        $headers = array (
                            'From' => "F1 Statistics Team <$from>",
                            'To' => $to,
                            'Subject' => $subject
                        );

                        $smtp = Mail::factory('smtp', array (
                            'host' => $host,
                            'port' => $port,
                            'auth' => true,
                            'username' => $username,
                            'password' => $password
                        ));

                        $mail = $smtp->send($to, $headers, $message);

                        if (PEAR::isError($mail)) {
                            die($mail->getMessage());
                        }

                    }
                }
                $update->close();
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
        <p>If an account exists, a new password has been created.</p>
        <form action="login.php" method="GET">
            <button type="submit">Go back to Login</button>
        </form>
    </body>
</html>
