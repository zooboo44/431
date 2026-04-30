<?php
session_start();

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['id'])) {
	$db = new mysqli("localhost", "root", "", "FORMULA_ONE");
	if ($db->connect_error) {
		die("Could not connect to database! Please try again later.");
	}

	$id = $_POST['id'];

	$query = "DELETE FROM driver_statistics WHERE id = ?";
	$stmt = $db->prepare($query);
	
	if ($stmt) {
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$stmt->close();
	}

	$db->close();
}

header("Location: statistic.php");
exit();
?>
