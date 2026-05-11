<?php
session_start();

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit();
}

$db = new mysqli("localhost", "root", "", "FORMULA_ONE");
if ($db->connect_error) {
	die("Could not connect to database! Please try again later.");
}

// User
$query = "SELECT accounts.username, roles.display_name
		  FROM accounts
		  JOIN roles ON accounts.user_role = roles.id
		  WHERE accounts.id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

// Circuit
$selectedCircuit = $_GET['circuit_id'] ?? null;

$circuitQuery = "
	SELECT drivers.first_name, drivers.last_name, driver_statistics.position, driver_statistics.laps, driver_statistics.pit_stops, driver_statistics.best_lap_time_ms
	FROM driver_statistics
	JOIN drivers
	ON driver_statistics.driver_id = drivers.id
	WHERE driver_statistics.circuit_id = ?
	ORDER BY driver_statistics.position ASC";

$circuitResult = null;

if ($selectedCircuit) {
	$stmtCircuit = $db->prepare($circuitQuery);
	$stmtCircuit->bind_param("i", $selectedCircuit);
	$stmtCircuit->execute();
	$circuitResult = $stmtCircuit->get_result();
}

// Driver averages
$driverQuery = "
	SELECT drivers.first_name, drivers.last_name, ROUND(AVG(driver_statistics.position), 2) AS avg_position, ROUND(AVG(driver_statistics.laps), 2) AS avg_laps, ROUND(AVG(driver_statistics.pit_stops), 2) AS avg_pit_stops, MIN(driver_statistics.best_lap_time_ms) AS best_lap
	FROM drivers
	LEFT JOIN driver_statistics ON drivers.id = driver_statistics.driver_id
	GROUP BY drivers.id
	HAVING 
		AVG(driver_statistics.position) IS NOT NULL
		AND AVG(driver_statistics.position) != 0
	ORDER BY drivers.last_name ASC";

$driverResult = $db->query($driverQuery);
?>

<!DOCTYPE html>
<html>
	<head>
		<title>
			CPSC 431 Final Project
		</title>
		<style>
			table { border-collapse: collapse; width: 100%; margin-top: 20px; }
			th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
			th { background-color: #f2f2f2; }
		</style>
	</head>
	<body>
		<h1 style="text-align:center">F1 Statistics</h1>
		<?php
		echo "Hello " . htmlspecialchars($row['username']) . ", (Role: " . htmlspecialchars($row['display_name']) . ")";
		?>
		<a href="logout.php" style="float: right;">Logout</a>
		<br><br>
		<form method="GET">
			<label><strong>Circuit info for: </strong></label>

			<select name="circuit_id" onchange="this.form.submit()">
				<option value="" disabled selected>
					Choose Circuit
				</option>

				<?php
				$circuitDropdown = "SELECT id, name FROM circuits ORDER BY name";
				$circuitStmt = $db->prepare($circuitDropdown);
				$circuitStmt->execute();
				$circuitStmt->bind_result($circuit_id, $circuitName);

				while ($circuitStmt->fetch()) {
					$selected = ($selectedCircuit == $circuit_id) ? 'selected' : '';
					echo "
						<option value='$circuit_id' $selected>
							" . htmlspecialchars($circuitName) . "
						</option>
					";
				}
				$circuitStmt->close();
				?>

			</select>
		</form>
		<table>
			<thead>
				<tr>
					<th>Driver</th>
					<th>Position</th>
					<th>Pit Stops</th>
					<th>Laps</th>
					<th>Best Lap Time</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($circuitResult && $circuitResult->num_rows > 0): ?>
					<?php while ($row = $circuitResult->fetch_assoc()): ?>
						<tr>
							<td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></td>
							<td><?php echo htmlspecialchars($row['position']); ?></td>
							<td><?php echo htmlspecialchars($row['pit_stops']); ?></td>
							<td><?php echo htmlspecialchars($row['laps']); ?></td>
							<td><?php echo htmlspecialchars($row['best_lap_time_ms']); ?></td>
						</tr>
					<?php endwhile; ?>
				<?php else: ?>
					<tr>

						<td colspan="5" style="text-align:center;">No circuit data found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<br><br>

		<table>
			<label><strong>Average driver data</strong></label>
			<thead>
				<tr>
					<th>Driver</th>
					<th>Average Position</th>
					<th>Average Pit stops</th>
					<th>Average Laps</th>
					<th>Best Lap Time Ever</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($driverResult && $driverResult->num_rows > 0): ?>
					<?php while ($row = $driverResult->fetch_assoc()): ?>
						<tr>
							<td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></td>
							<td><?php echo htmlspecialchars($row['avg_position']); ?></td>
							<td><?php echo htmlspecialchars($row['avg_pit_stops']); ?></td>
							<td><?php echo htmlspecialchars($row['avg_laps']); ?></td>
							<td><?php echo htmlspecialchars($row['best_lap']); ?></td>
						</tr>
					<?php endwhile; ?>
				<?php else: ?>
					<tr>
						<td colspan="5" style="text-align:center;">No driver statistics found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<br>
		<div style="text-align: center;">
			<div style="display: inline-flex; gap: 40px;">
				<a href="drivers.php">Manage Drivers</a>
				<a href="statistic.php">Manage Statistics</a>
				<a href="change_role_form.php">Manage Roles</a>
				<a href="change_password_form.php">Change password</a>
			</div>
		</div>

	</body>
</html>
<?php
$db->close();
?>
