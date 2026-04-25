<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$error = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	$db = new mysqli("localhost", "root", "", "FORMULA_ONE");

	if ($db->connect_error) {
		die("Could not connect to database");
	}

	$email = $_POST['email'] ?? '';
	$username = $_POST['username'] ?? '';
	$password = $_POST['password'] ?? '';
	$password_confirmation = $_POST['confirmed_password'] ?? '';
	
	if ($password !== $password_confirmation) {
		$error = "Passwords don't match.";
	} else{
		$password_hash = password_hash($password, PASSWORD_DEFAULT);

		$query = "INSERT INTO accounts(username, password_hash, email) VALUES (?, ?, ?)";
		$stmt = $db->prepare($query);

		if (!$stmt) {
		    die("Prepare failed: " . $db->error);
		}
		$stmt->bind_param("sss", $username, $password_hash, $email);
		if (!$stmt->execute()) {
		    die("MYSQL ERROR: " . $stmt->error);
		} else {
			header("Location: login.php");
			exit();
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
	<h1 style="text-align: left;">Registering New Account</h1>
	<p>Registered successfully. Please go back to the login page to login to your account.</p>
	<form action="login.php" method="GET">
  	  <button type="submit">Go back to Login</button>
	</form>
</body>
</html>