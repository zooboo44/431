<?php
session_start();

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$error = "";

$db = new mysqli("localhost", "root", "", "FORMULA_ONE");
if ($db->connect_error) {
	die("Could not connect to database! Please try again later.");
}

// Check if we're submitting the form
if ($_SERVER['REQUEST_METHOD'] === "POST") {

	$id = $_POST['id'] ?? '';
	$driver_id = trim($_POST['driver_id'] ?? '');
	$circuit_id = trim($_POST['circuit_id'] ?? '');
	$pit_stops = trim($_POST['pit_stops'] ?? '');
	$laps = trim($_POST['laps'] ?? '');
	$best_lap_time_ms = trim($_POST['best_lap_time_ms'] ?? '');

	if (empty($driver_id) || empty($circuit_id)) {
		$error = "Driver and circuit Id are required.";
	} else {
		$query = "UPDATE driver_statistics SET driver_id=?, circuit_id=?, pit_stops=?, laps=?, best_lap_time_ms=? WHERE id=?";
		$stmt = $db->prepare($query);
		
		if (!$stmt) {
			$error = "Database error: " . $db->error;
		} else {
			$stmt->bind_param("iiiiii", $driver_id, $circuit_id, $pit_stops, $laps, $best_lap_time_ms, $id);
			
			if ($stmt->execute()) {
				header("Location: statistic.php");
				exit();
			} else {
				$error = "Failed to update statistic: " . $stmt->error;
			}
			$stmt->close();
		}
	}
}

// Load existing data
$id = $_GET['id'] ?? ($_POST['id'] ?? '');
$stat = null;

if (!empty($id)) {
	$query = "SELECT * FROM driver_statistics WHERE id = ?";
	$stmt = $db->prepare($query);
	if ($stmt) {
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$result = $stmt->get_result();
		$stat = $result->fetch_assoc();
		$stmt->close();
	}
}

if (!$stat && empty($error)) {
	die("Statistic not found.");
}

$db->close();
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Edit Statistics - F1 Statistics</title>
		<style>
			.form-group { margin-bottom: 15px; }
			label { display: inline-block; width: 100px; }
		</style>
	</head>
	<body>
		<a href="statistic.php">Back to Statistics</a>
		<h1 style="text-align:left;">Edit Statistics</h1>

		<?php if (!empty($error)): ?>
		    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>

		<form action="edit_driver_stat.php" method="POST">
			<input type="hidden" name="id" value="<?php echo htmlspecialchars($stat['id'] ?? $_POST['id']); ?>">
			<div class="form-group">
				<label>Driver ID:</label>
				<input type="text" name="driver_id" value="<?php echo htmlspecialchars($stat['driver_id'] ?? $_POST['driver_id'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label>Circuit ID:</label>
				<input type="text" name="circuit_id" value="<?php echo htmlspecialchars($stat['circuit_id'] ?? $_POST['circuit_id'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label>Pit Stops:</label>
				<input type="text" name="pit_stops" value="<?php echo htmlspecialchars($stat['pit_stops'] ?? $_POST['pit_stops'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label>Laps:</label>
				<input type="text" name="laps" value="<?php echo htmlspecialchars($stat['laps'] ?? $_POST['laps'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label>Best Lap Time:</label>
				<input type="text" name="best_lap_time_ms" value="<?php echo htmlspecialchars($stat['best_lap_time_ms'] ?? $_POST['best_lap_time_ms'] ?? ''); ?>">
			</div>
			<button type="submit">Update Statistic</button>
		</form>
	</body>
</html>
