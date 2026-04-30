<?php
session_start();
if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	$db = new mysqli("localhost", "root", "", "FORMULA_ONE");
	if ($db->connect_error) {
		die("DB connection failed");
	}
	$user_id = $_SESSION['user_id'];

	$email = $_POST['email'] ?? '';
	$new_role = $_POST['user_role'] ?? '';

	$query = "SELECT user_role FROM accounts where id = ?";
	$stmt = $db->prepare($query);
	if (!$stmt) {
		die("Prepare failed");
	}
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	$user = $result->fetch_assoc();

	if (!$user) {
		$error = "User not found";
	} else {
		$updateQuery = "UPDATE accounts
						SET user_role = ?
						WHERE email = ?
						AND EXISTS (
							SELECT 1 FROM accounts
							WHERE id = ? AND user_role = 4
					)";
		$updateStmt = $db->prepare($updateQuery);
		if (!$updateStmt) {
			die("Prepare failed");
		}
		$updateStmt->bind_param("isi", $new_role, $email, $user_id);
		if ($updateStmt->execute()) {
			$error = "Failed to update role";
		} else {
			$success = "Role successfully changed.";
		}
		$updateStmt->close();
	}
	$stmt->close();
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
	<h1 style="text-align:left;">Changing Role</h1>
	<?php if (!empty($error)): ?>
    	<p><?php echo $error; ?></p>
    	<form action="change_role_form.php" method="GET">
			<button type="submit">Try Again</button>
		</form>
	<?php endif; ?>

	<?php if (!empty($success)): ?>
	    <p><?php echo $success; ?></p>
	    	<form action="member.php" method="GET">
				<button type="submit">Go back to homepage</button>
	</form>
	<?php endif; ?>
</body>
</html>