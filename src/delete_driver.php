<?php
session_start();

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

if (!isset($_SESSION['db_user'], $_SESSION['db_pass'])) {
	die("Database session not initalized.");
}

$db = new mysqli("localhost", $_SESSION['db_user'], $_SESSION['db_pass'], "FORMULA_ONE");

if ($db->connect_error) {
		die("Could not connect to database! Please try again later.");
}

if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['id'])) {

	$id = $_POST['id'];

	$query = "DELETE FROM drivers WHERE id = ?";
	try {
		$stmt = $db->prepare($query);
	} catch (mysqli_sql_exception $e) {
		$_SESSION['error'] = "You do not have permission to perform this action.";
		header("Location: drivers.php");
		exit();

	}
	
	if (!$stmt) {
		$_SESSION['error'] = "Database error: " . $db->error;
		header("Location: drivers.php");
		exit();
	}
	$stmt->bind_param("i", $id);
	if (!$stmt->execute()) {
		$_SESSION['error'] = "Unable to delete driver. You may not have permission.";
		header("Location: drivers.php");
		exit();
	}
	$stmt->close();

}

$db->close();
header("Location: drivers.php");
exit();
?>
