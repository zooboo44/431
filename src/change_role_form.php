<?php
session_start();
if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>F1 Statistics</title>
</head>
<body>
	<a href="member.php">Back to Dashboard</a>
	<h1 style="text-align:left;">Change Role</h1>
	<?php if (!empty($error)): ?>
		<p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
	<?php endif; ?>
	<form action="change_role.php" method="POST">
		<label>Enter Email:</label>
		<br>
		<input type="text" name="email" required>
		<br>
		<label>Enter new role(0-4):</label>
		<br>
		<input type="text" name="user_role" required>
		<br><br>
		<button type="submit">Change user role</button>
	</form>

</body>
</html>