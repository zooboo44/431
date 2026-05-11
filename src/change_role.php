<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$success = "";
$error = "";

if (!isset($_SESSION['db_user'], $_SESSION['db_pass'])) {
	die("Database session not initalized.");
}

$db = new mysqli("localhost", $_SESSION['db_user'], $_SESSION['db_pass'], "FORMULA_ONE");

if ($db->connect_error) {
		die("Could not connect to database! Please try again later.");
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {

	$email = $_POST['email'] ?? '';
	$new_role = $_POST['user_role'] ?? '';

	$updateQuery = "UPDATE accounts SET user_role = ? WHERE email = ?";

	try {
		$updateStmt = $db->prepare($updateQuery);
	} catch (mysqli_sql_exception $e) {
		$_SESSION['error'] = "You do not have permission to perform this action.";
		header("Location: change_role_form.php");
		exit();
	}
	if (!$updateStmt) {
		$_SESSION['error'] = "Prepare failed";
		header("Location: change_role_form.php");
		exit();
	}
		
	$updateStmt->bind_param("is", $new_role, $email);
		
	if (!$updateStmt->execute()) {
		$_SESSION['error'] = "Failed to update role: You dont have permission for this action";
		header("Location: change_role_form.php");
		exit();
	}
	$updateStmt->close();
}
$db->close();

?>