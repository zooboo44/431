<?php
session_start();

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

// Check if we're submitting the form
if ($_SERVER['REQUEST_METHOD'] === "POST") {
	$id = $_POST['id'] ?? '';
	$first_name = trim($_POST['first_name'] ?? '');
	$last_name = trim($_POST['last_name'] ?? '');
	$street = trim($_POST['street'] ?? '');
	$city = trim($_POST['city'] ?? '');
	$state = trim($_POST['state'] ?? '');
	$country = trim($_POST['country'] ?? '');
	$zip = trim($_POST['zip'] ?? '');

	if (empty($id) || empty($first_name) || empty($last_name)) {
		$error = "ID, First name and last name are required.";
	} else {
		$query = "UPDATE drivers SET first_name=?, last_name=?, street=?, city=?, state=?, country=?, zip=? WHERE id=?";
		try {
			$stmt = $db->prepare($query);
		} catch (mysqli_sql_exception $e) {
			$error = "You do not have permission to perform this action.";
		}
		
		if (!$stmt) {
			$error = "Database error: You dont have permssion to preform this action";
		} else {
			$p_street = $street === "" ? null : $street;
			$p_city = $city === "" ? null : $city;
			$p_state = $state === "" ? null : $state;
			$p_country = $country === "" ? null : $country;
			$p_zip = $zip === "" ? null : $zip;

			$stmt->bind_param("sssssssi", $first_name, $last_name, $p_street, $p_city, $p_state, $p_country, $p_zip, $id);
			
			if ($stmt->execute()) {
				header("Location: drivers.php");
				exit();
			} else {
				$error = "Failed to update driver: You dont have permission for this action" . $stmt->error;
			}
			$stmt->close();
		}
	}
}

// Load existing data
$id = $_GET['id'] ?? ($_POST['id'] ?? '');
$driver = null;

if (!empty($id)) {
	$query = "SELECT * FROM drivers WHERE id = ?";
	try {
		$stmt = $db->prepare($query);
	} catch (mysqli_sql_exception $e) {
		$error = "You do not have permission to perform this action.";
	}
	if ($stmt) {
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$result = $stmt->get_result();
		$driver = $result->fetch_assoc();
		$stmt->close();
	}
}

if (!$driver && empty($error)) {
	$error = "Driver not found.";
}

$db->close();
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Edit Driver - F1 Statistics</title>
		<style>
			.form-group { margin-bottom: 15px; }
			label { display: inline-block; width: 100px; }
		</style>
	</head>
	<body>
		<a href="drivers.php">Back to Drivers</a>
		<h1 style="text-align:left;">Edit Driver</h1>

		<?php if (!empty($error)): ?>
		    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form action="edit_driver.php" method="POST">
			<input type="hidden" name="id" value="<?php echo htmlspecialchars($driver['id'] ?? $_POST['id']); ?>">
			
			<div class="form-group">
				<label>First Name:</label>
				<input type="text" name="first_name" value="<?php echo htmlspecialchars($driver['first_name'] ?? $_POST['first_name'] ?? ''); ?>" required pattern="[a-zA-Z0-9]+" title="Only alphanumeric characters allowed">
			</div>
			<div class="form-group">
				<label>Last Name:</label>
				<input type="text" name="last_name" value="<?php echo htmlspecialchars($driver['last_name'] ?? $_POST['last_name'] ?? ''); ?>" required pattern="[a-zA-Z0-9]+" title="Only alphanumeric characters allowed">
			</div>
			<div class="form-group">
				<label>Street:</label>
				<input type="text" name="street" value="<?php echo htmlspecialchars($driver['street'] ?? $_POST['street'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label>City:</label>
				<input type="text" name="city" value="<?php echo htmlspecialchars($driver['city'] ?? $_POST['city'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label>State:</label>
				<input type="text" name="state" value="<?php echo htmlspecialchars($driver['state'] ?? $_POST['state'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label>Country:</label>
				<input type="text" name="country" value="<?php echo htmlspecialchars($driver['country'] ?? $_POST['country'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label>ZIP:</label>
				<input type="text" name="zip" value="<?php echo htmlspecialchars($driver['zip'] ?? $_POST['zip'] ?? ''); ?>">
			</div>
			<button type="submit">Update Driver</button>
		</form>
	</body>
</html>
