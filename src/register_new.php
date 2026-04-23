<?php
$email = trim($_POST['email']);
$username = trim($_POST['username']);
$password = trim($_POST['password']);
$confirmed_password = trim($_POST['confirmed_password']);
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>F1 Statistics</title>
</head>
<body>
	<h1 style="text-align: left;">Registering New Account</h1>
	<p>Registered successfully. Please go back to the login page to login to your account.</p>
	<form action="login.php" method="POST">
		<button type="submit">Go back to Login</button>
	</form>
</body>
</html>