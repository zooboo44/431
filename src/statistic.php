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

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$query = "SELECT driver_statistics.*, drivers.first_name, drivers.last_name, circuits.name AS circuit_name
			FROM driver_statistics 
			JOIN drivers ON drivers.id = driver_statistics.driver_id
			JOIN circuits ON circuits.id = driver_statistics.circuit_id
			ORDER BY drivers.last_name, drivers.first_name";
$result = $db->query($query);

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Manage Statistics - F1 Statistics</title>
	<style>
		table { border-collapse: collapse; width: 100%; margin-top: 20px; }
		th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
		th { background-color: #f2f2f2; }
	</style>
</head>
	<body>
		<a href="member.php">Back to Dashboard</a>
		<h1 style="text-align:left;">Manage Statistics</h1>
		<?php if (!empty($error)): ?>
		    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>
		<a href="add_driver_stat.php"><button>Add Statistics</button></a>
		
		<table>
			<thead>
				<tr>
					<th>ID</th>
					<th>Driver</th>
					<th>Circuit</th>
					<th>Position</th>
					<th>Pit Stops</th>
					<th>Laps</th>
					<th>Best Lap Time</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($result && $result->num_rows > 0): ?>
					<?php while ($row = $result->fetch_assoc()): ?>
						<tr>
							<td><?php echo htmlspecialchars($row['id']); ?></td>
							<td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></td>
							<td><?php echo htmlspecialchars($row['circuit_name']); ?></td>
							<td><?php echo htmlspecialchars($row['position']); ?></td>
							<td><?php echo htmlspecialchars($row['pit_stops']); ?></td>
							<td><?php echo htmlspecialchars($row['laps']); ?></td>
							<td><?php echo htmlspecialchars($row['best_lap_time_ms']); ?></td>
							<td>
								<a href="edit_driver_stat.php?id=<?php echo urlencode($row['id']); ?>">Edit</a> | 
								<form action="delete_driver_stat.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this statistic?');">
									<input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
									<button type="submit" style="background:none;border:none;color:blue;text-decoration:underline;cursor:pointer;padding:0;">Delete</button>
								</form>
							</td>
						</tr>
					<?php endwhile; ?>
				<?php else: ?>
					<tr>
						<td colspan="9" style="text-align:center;">No driver statistic found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</body>
</html>
<?php
$db->close();
?>