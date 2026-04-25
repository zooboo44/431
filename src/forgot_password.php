<?php
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once "Mail.php";
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $db = new mysqli("localhost", "root", "", "FORMULA_ONE");

  if ($db->connect_error) {
    die("Database connection failed");
  }
  $email = $_POST['email'] ?? '';

  // Check if email exists
  $query = "SELECT id FROM accounts where email = ?";
  $stmt = $db->prepare($query);
  if (!$stmt) {
    die("Prepare failed: " . $db->error);
  }
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    die("No account found.");
  }

  $newPassword = bin2hex(random_bytes(4));
  $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);

  $update = "UPDATE accounts SET password_hash = ? WHERE email = ?";
  $updatedStmt = $db->prepare($update);
  if (!$updatedStmt) {
    die("Prepare failed");
  }
  $updatedStmt->bind_param("ss", $password_hash, $email);

  if (!$updatedStmt->execute()) {
    die("Update failed");
  }

  // Email setup
  $from = User_name;
  $to = $email;

  $host = "ssl://smtp.gmail.com";
  $port = "465";
  $username = User_name;
  $password = Pass_word;

  $subject = "Your new password for F1 Statistics";
  $message = "Your password has been reset. \n\n Your new password is $newPassword";
  $headers = array (
    'From' => "FORMULA_ONE1 Statistics <$from>",
    'To' => $to,
    'Subject' => $subject
  
  );

  $smtp = Mail::factory('smtp', array(
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

  $stmt->close();
  $updatedStmt->close();
  $db->close();
}
?>

<!DOCTYPE html>
<html>
  <head>
    <title>F1 Statistics - Feedback Submitted</title>
  </head>
  <body>
    <h1>Change password Request submitted</h1>
    <p>Your new generated password has been sent.</p>

    <form action="login.php" method="GET">
      <button type="submit">Go back to Login</button>
    </form>
  </body>
</html>
