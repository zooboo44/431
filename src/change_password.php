<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$error = "";

if (!isset($_SESSION['db_user'], $_SESSION['db_pass'])) {
	die("Database session not initalized.");
}

$db = new mysqli("localhost", $_SESSION['db_user'], $_SESSION['db_pass'], "FORMULA_ONE");

if ($db->connect_error) {
		die("Could not connect to database! Please try again later.");
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	$user_id = $_SESSION['user_id'];
	
	$current_password = $_POST['current_password'] ?? '';
	$new_password = $_POST['new_password'] ?? '';
	$confirm_password = $_POST['confirmed_password'] ?? '';

	if ($new_password !== $confirm_password) {
		$_SESSION['error'] = "New passwords don't match.";
		header("Location: change_password_form.php");
	    exit();
	} elseif (strlen($password) < 4 || strlen($password) > 64) {
		$_SESSION['error'] = "Password needs to be 8-64 characters long";
	    header("Location: change_password_form.php");
	    exit();
	} else {
		// Get stored password
		$query = "SELECT password_hash FROM accounts WHERE id = ?";
		$stmt = $db->prepare($query);
		if (!$stmt) {
		    $_SESSION['error'] = "Prepare failed";
		}
		$stmt->bind_param("i", $user_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$user = $result->fetch_assoc();

		if (!$user || !password_verify($current_password, $user['password_hash'])) {
			$_SESSION['error'] = "Current password is incorrect";
		} else {
			$new_hash_password = password_hash($new_password, PASSWORD_DEFAULT);
			$updateQuery = "UPDATE accounts SET password_hash = ? WHERE id = ?";
			$updateStmt = $db->prepare($updateQuery);
			if (!$updateStmt) {
			    $_SESSION['error'] = "Prepare failed";
			}
			$updateStmt->bind_param("si", $new_hash_password, $user_id);

			if (!$updateStmt->execute()) {
			    $_SESSION['error'] = "Failed to update password";
			}
			$updateStmt->close();
		}
		$stmt->close();

	}
}
$db->close();
?>