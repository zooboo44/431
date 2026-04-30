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

	$driver_id = trim($_POST['driver_id'] ?? '');
	$circuit_id = trim($_POST['circuit_id'] ?? '');
	$pit_stops = trim($_POST['pit_stops'] ?? '');
	$laps = trim($_POST['laps'] ?? '');
	$best_lap_time_ms = trim($_POST['best_lap_time_ms'] ?? '');

	if (empty($driver_id) || empty($circuit_id)) {
		$error = "Driver and circuit Id are required.";
	} else {
		$query = "INSERT INTO driver_statistics (driver_id, circuit_id, pit_stops, laps, best_lap_time_ms) VALUES (?, ?, ?, ?, ?)";
		$stmt = $db->prepare($query);
		
		if (!$stmt) {
			$error = "Database error: " . $db->error;
		} else {
			$stmt->bind_param("iiiii", $driver_id, $circuit_id, $pit_stops, $laps, $best_lap_time_ms);
			
			if ($stmt->execute()) {
				header("Location: statistic.php");
				exit();
			} else {
				$error = "Failed to add statistic: " . $stmt->error;
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
				<label>Driver ID:</label>
				<input type="text" name="driver_id">
			</div>
			<div class="form-group">
				<label>Circuit ID:</label>
				<input type="text" name="circuit_id">
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
