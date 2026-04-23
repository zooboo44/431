<!DOCTYPE html>
<html>
	<head>
		<title>
			F1 Statistics
		</title>
	</head>
	<body>

		<h1 style="text-align:left;">Login</h1>
		<form action="member.php" method="POST">
			<a href="<?php echo 'register_form.php'; ?>">Dont have an account?</a>
			<br><br>
			<label>Username: </label>
			<br>
			<input type="text" name="username" required>

			<br>

			<label>Password: </label>
			<br>
			<input type="password" name="password" required>
			<br>
			<a href="<?php echo 'forgot_password_form.php'; ?>">Forgot password?</a>
			<br>
			<br>
			<button type="submit">Login</button>
		</form>
	</body>
</html>