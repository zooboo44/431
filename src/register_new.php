<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$error = "";

$db = new mysqli("localhost", "root", "", "FORMULA_ONE");

if ($db->connect_error) {
	die("Could not connect to database");
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	$email = $_POST['email'] ?? '';
	$username = $_POST['username'] ?? '';
	$password = $_POST['password'] ?? '';
	$password_confirmation = $_POST['confirmed_password'] ?? '';
	
	if ($password !== $password_confirmation) {
		$_SESSION['error'] = "Passwords don't match.";
	    header("Location: register_form.php");
	    exit();
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['error'] = "Invalid email format";
	    header("Location: register_form.php");
	    exit();
	} elseif (strlen($username) < 4 || strlen($username) > 64) {
		$_SESSION['error'] = "Username needs to be 4-64 characters long.";
	    header("Location: register_form.php");
	    exit();
	} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    	$_SESSION['error'] = "Username can only contain letters, numbers, and underscores.";
	    header("Location: register_form.php");
	    exit();
	} elseif (strlen($password) < 4 || strlen($password) > 64) {
		$_SESSION['error'] = "Password needs to be 4-64 characters long";
	    header("Location: register_form.php");
	    exit();

	} else {
		// Check for duplicate email
		$checkEmail = "SELECT id FROM accounts WHERE email = ?";
		$stmt = $db->prepare($checkEmail);
		$stmt->bind_param("s", $email);
		$stmt->execute();
		$stmt->store_result();

		if ($stmt->num_rows > 0) {
		    $_SESSION['error'] = "Email is already registered.";
		    header("Location: register_form.php");
		    exit();
		}
		$stmt->close();

		// Check for duplicate username
		$checkUsername = "SELECT id FROM accounts WHERE username = ?";
		$stmt = $db->prepare($checkUsername);
		$stmt->bind_param("s", $username);
		$stmt->execute();
		$stmt->store_result();

		if ($stmt->num_rows > 0) {
		    $_SESSION['error'] = "Username is already taken.";
		    header("Location: register_form.php");
		    exit();
		}
		$stmt->close();
		$password_hash = password_hash($password, PASSWORD_DEFAULT);

		$query = "INSERT INTO accounts(username, password_hash, email) VALUES (?, ?, ?)";
		$stmt = $db->prepare($query);

		if (!$stmt) {
		    $_SESSION['error'] = "Prepare failed: " . $db->error;
		}
		$stmt->bind_param("sss", $username, $password_hash, $email);
		if (!$stmt->execute()) {
		    $_SESSION['error'] = "MYSQL ERROR: " . $stmt->error;
		} else {
			header("Location: login.php");
			exit();
		}
		$stmt->close();
	}
}

$db->close();

?>