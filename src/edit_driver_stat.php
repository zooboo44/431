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
	$driver_id = trim($_POST['driver_id'] ?? '');
	$circuit_id = trim($_POST['circuit_id'] ?? '');
	$position = trim($_POST['position'] ?? '');
	$pit_stops = trim($_POST['pit_stops'] ?? '');
	$laps = trim($_POST['laps'] ?? '');
	$best_lap_time_ms = trim($_POST['best_lap_time_ms'] ?? '');

	if (empty($driver_id) || empty($circuit_id)) {
		$error = "Driver and circuit Id are required.";
	} else {
		$query = "UPDATE driver_statistics SET driver_id=?, circuit_id=?, position=?, pit_stops=?, laps=?, best_lap_time_ms=? WHERE id=?";
		try {
			$stmt = $db->prepare($query);
		} catch (mysqli_sql_exception $e) {
			$error = "You do not have permission to perform this action.";
		}
	
		if (!$stmt) {
			$error = "Database error: You do not have permission to perform this action";
		} else {
			$stmt->bind_param("iiiiiii", $driver_id, $circuit_id, $position, $pit_stops, $laps, $best_lap_time_ms, $id);
			
			if ($stmt->execute()) {
				header("Location: statistic.php");
				exit();
			} else {
				$error = "Failed to update statistic: You dont have permission for this action" . $stmt->error;
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
	$error = "Statistic not found.";
}
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
						$selected = ($driver_id == ($stat['driver_id'] ?? '')) ? "selected" : "";
						echo '<option value="' . htmlspecialchars($driver_id) . '" ' . $selected . '>' . htmlspecialchars($lastName . ', ' . $firstName) . '</option>';
					}
					$driverStmt->close();
					?>
				</select>
			</div>
			<div class="form-group">
				<label>Circuit:</label>
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
						$selected = ($circuit_id == ($stat['circuit_id'] ?? '')) ? "selected" : "";
						echo '<option value="' . htmlspecialchars($circuit_id) . '" ' . $selected . '>' . htmlspecialchars($circuitName) . '</option>';
					}
					$circuitStmt->close();
					$db->close();
					?>
				</select>
			</div>
			<div class="form-group">
				<label>Position:</label>
				<input type="text" name="position" value="<?php echo htmlspecialchars($stat['position'] ?? $_POST['position'] ?? ''); ?>">
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
