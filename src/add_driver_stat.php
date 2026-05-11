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

if ($_SERVER['REQUEST_METHOD'] === "POST") {

	$driver_id = trim($_POST['driver_id'] ?? '');
	$circuit_id = trim($_POST['circuit_id'] ?? '');
	$position = trim($_POST['position'] ?? '');
	$pit_stops = trim($_POST['pit_stops'] ?? '');
	$laps = trim($_POST['laps'] ?? '');
	$best_lap_time_ms = trim($_POST['best_lap_time_ms'] ?? '');

	if (empty($driver_id) || empty($circuit_id)) {
		$error = "Driver and circuit Id are required.";
	} else {
		$query = "INSERT INTO driver_statistics (driver_id, circuit_id, position, pit_stops, laps, best_lap_time_ms) VALUES (?, ?, ?, ?, ?, ?)";
		try {
			$stmt = $db->prepare($query);
		} catch (mysqli_sql_exception $e) {
			$error = "You do not have permission to perform this action.";
		}
		
		if (!$stmt) {
			$error = "Database error: You do not have permission to perform this action.";
		} else {
			$stmt->bind_param("iiiiii", $driver_id, $circuit_id, $position, $pit_stops, $laps, $best_lap_time_ms);
			
			if ($stmt->execute()) {
				header("Location: statistic.php");
				exit();
			} else {
				$error = "Failed to add statistic: You dont have permission for this action" . $stmt->error;
			}
			$stmt->close();
		}
	}
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Add Statistics - F1 Statistics</title>
		<style>
			.form-group { margin-bottom: 15px; }
			label { display: inline-block; width: 100px; }
		</style>
	</head>
	<body>
		<a href="statistic.php">Back to Statistics</a>
		<h1 style="text-align:left;">Add New Statistic</h1>

		<?php if (!empty($error)): ?>
		    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form action="add_driver_stat.php" method="POST">
			<div class="form-group">
				<label>Driver:</label>
				<select name="driver_id" required>
					<option value="" selected disabled>
						Choose Driver
					</option>
					<?php
					$driverQuery = "SELECT id, first_name, last_name FROM drivers ORDER BY last_name, first_name";
					$driverStmt = $db->prepare($driverQuery);
					$driverStmt->execute();
					$driverStmt->bind_result($driver_id, $firstName, $lastName);

					while ($driverStmt->fetch()) {
						echo '<option value="' . htmlspecialchars($driver_id) . '">' . htmlspecialchars($lastName . ', ' . $firstName) . '</option>';
					}
					$driverStmt->close();
					?>
				</select>
			</div>
			<div class="form-group">
				<label>Circuit ID:</label>
				<select name="circuit_id" required>
					<option value="" selected disabled>
						Choose Circuit
					</option>
					<?php
					$circuitQuery = "SELECT id, name FROM circuits ORDER BY name";
					$circuitStmt = $db->prepare($circuitQuery);
					$circuitStmt->execute();
					$circuitStmt->bind_result($circuit_id, $circuitName);

					while ($circuitStmt->fetch()) {
						echo '<option value="' . htmlspecialchars($circuit_id) . '">' . htmlspecialchars($circuitName) . '</option>';
					}
					$circuitStmt->close();
					?>
				</select>
			</div>
			<div class="form-group">
				<label>Position:</label>
				<input type="text" name="position">
			</div>
			<div class="form-group">
				<label>Pit Stops:</label>
				<input type="text" name="pit_stops">
			</div>
			<div class="form-group">
				<label>Laps:</label>
				<input type="text" name="laps">
			</div>
			<div class="form-group">
				<label>Best Lap Time:</label>
				<input type="text" name="best_lap_time_ms">
			</div>
			<button type="submit">Add Statistic</button>
		</form>
	</body>
</html>
<?php
$db->close();
?>