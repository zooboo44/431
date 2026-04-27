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
		<title>
			CPSC 431 Final Project
		</title>
	</head>
	<body>
		<h1 style="text-align:center">F1 Statistics</h1>
		<a href="<?php echo 'logout.php'; ?>">Logout</a>
		<a href="<?php echo 'change_password_form.php';?>">Change password</a>
		<br><br>
		<a href="drivers.php">Manage Drivers</a>
	</body>
</html>
