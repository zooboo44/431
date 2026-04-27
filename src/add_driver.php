<?php
session_start();

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
	$db = new mysqli("localhost", "root", "", "FORMULA_ONE");
	if ($db->connect_error) {
		die("Could not connect to database! Please try again later.");
	}

	$first_name = trim($_POST['first_name'] ?? '');
	$last_name = trim($_POST['last_name'] ?? '');
	$street = trim($_POST['street'] ?? '');
	$city = trim($_POST['city'] ?? '');
	$state = trim($_POST['state'] ?? '');
	$country = trim($_POST['country'] ?? '');
	$zip = trim($_POST['zip'] ?? '');

	if (empty($first_name) || empty($last_name)) {
		$error = "First name and last name are required.";
	} else {
		$query = "INSERT INTO drivers (first_name, last_name, street, city, state, country, zip) VALUES (?, ?, ?, ?, ?, ?, ?)";
		$stmt = $db->prepare($query);
		
		if (!$stmt) {
			$error = "Database error: " . $db->error;
		} else {
			// Convert empty strings to null for optional fields to avoid regex constraint failures on empty strings if applicable
			$p_street = $street === "" ? null : $street;
			$p_city = $city === "" ? null : $city;
			$p_state = $state === "" ? null : $state;
			$p_country = $country === "" ? null : $country;
			$p_zip = $zip === "" ? null : $zip;

			$stmt->bind_param("sssssss", $first_name, $last_name, $p_street, $p_city, $p_state, $p_country, $p_zip);
			
			if ($stmt->execute()) {
				header("Location: drivers.php");
				exit();
			} else {
				$error = "Failed to add driver: " . $stmt->error;
			}
			$stmt->close();
		}
	}
	$db->close();
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Add Driver - F1 Statistics</title>
		<style>
			.form-group { margin-bottom: 15px; }
			label { display: inline-block; width: 100px; }
		</style>
	</head>
	<body>
		<a href="drivers.php">Back to Drivers</a>
		<h1 style="text-align:left;">Add New Driver</h1>

		<?php if (!empty($error)): ?>
		    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form action="add_driver.php" method="POST">
			<div class="form-group">
				<label>First Name:</label>
				<input type="text" name="first_name" required pattern="[a-zA-Z0-9]+" title="Only alphanumeric characters allowed">
			</div>
			<div class="form-group">
				<label>Last Name:</label>
				<input type="text" name="last_name" required pattern="[a-zA-Z0-9]+" title="Only alphanumeric characters allowed">
			</div>
			<div class="form-group">
				<label>Street:</label>
				<input type="text" name="street">
			</div>
			<div class="form-group">
				<label>City:</label>
				<input type="text" name="city">
			</div>
			<div class="form-group">
				<label>State:</label>
				<input type="text" name="state">
			</div>
			<div class="form-group">
				<label>Country:</label>
				<input type="text" name="country">
			</div>
			<div class="form-group">
				<label>ZIP:</label>
				<input type="text" name="zip">
			</div>
			<button type="submit">Add Driver</button>
		</form>
	</body>
</html>
