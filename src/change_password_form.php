<?php
session_start();

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
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
	<button onclick="history.back()">Go Back</button>
	<h1 style="text-align: left;">Changing Password</h1>
	<?php
	session_start();

	if (!empty($_SESSION['error'])) {
		echo '<p style="color:red;">' . htmlspecialchars($_SESSION['error']) . '</p>';
		unset($_SESSION['error']);
	}
	?>
	<form action="change_password.php" method="POST">
		<label>Enter current password:</label>
		<br>
		<input type="password" name="current_password" required>
		<br>
		<label>Enter new password:</label>
		<br>
		<input type="password" name="new_password" required>
		<br>
		<label>Confirm password:</label>
		<br>
		<input type="password" name="confirmed_password" required>
		<br><br>
		<button type="submit">Change password</button>
	</form>
</body>
</html>