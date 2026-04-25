<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	$db = new mysqli("localhost", "root", "", "FORMULA_ONE");

	if ($db->connect_error) {
		die("DB connection failed");
	}
	$user_id = $_SESSION['user_id'];
	
	$current_password = $_POST['current_password'] ?? '';
	$new_password = $_POST['new_password'] ?? '';
	$confirm_password = $_POST['confirmed_password'] ?? '';

	if ($new_password !== $confirm_password) {
		$error = "New passwords don't match.";
	} else {
		// Get stored password
		$query = "SELECT password_hash FROM accounts WHERE id = ?";
		$stmt = $db->prepare($query);
		if (!$stmt) {
		    die("Prepare failed");
		}
		$stmt->bind_param("i", $user_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$user = $result->fetch_assoc();

		if (!$user || !password_verify($current_password, $user['password_hash'])) {
			$error = "Current password is incorrect";
		} else {
			$new_hash_password = password_hash($new_password, PASSWORD_DEFAULT);
			$updateQuery = "UPDATE accounts SET password_hash = ? WHERE id = ?";
			$updateStmt = $db->prepare($updateQuery);
			if (!$updateStmt) {
			    die("Prepare failed");
			}
			$updateStmt->bind_param("si", $new_hash_password, $user_id);

			if (!$updateStmt->execute()) {
			    $error = "Failed to update password";
			} else {
			    $success = "Password successfully changed.";
			}
			$updateStmt->close();
		}
		$stmt->close();

	}
	$db->close();

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
	<h1 style="text-align: left;">Changing Password</h1>
	<?php if (!empty($error)): ?>
    	<p><?php echo $error; ?></p>
    	<form action="change_password_form.php" method="GET">
			<button type="submit">Try Again</button>
		</form>
	<?php endif; ?>

	<?php if (!empty($success)): ?>
	    <p><?php echo $success; ?></p>
	    	<form action="member.php" method="GET">
				<button type="submit">Go back to homepage</button>
			</form>
	<?php endif; ?>
</body>
</html>