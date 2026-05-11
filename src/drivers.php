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

$query = "SELECT id, first_name, last_name, street, city, state, country, zip FROM drivers ORDER BY last_name, first_name";
$result = $db->query($query);
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Manage Drivers - F1 Statistics</title>
		<style>
			table { border-collapse: collapse; width: 100%; margin-top: 20px; }
			th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
			th { background-color: #f2f2f2; }
		</style>
	</head>
	<body>
		<a href="member.php">Back to Dashboard</a>
		<h1 style="text-align:left;">Manage Drivers</h1>
		<?php if (!empty($error)): ?>
		    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
		<?php endif; ?>
		<a href="add_driver.php"><button>Add New Driver</button></a>
		
		<table>
			<thead>
				<tr>
					<th>ID</th>
					<th>First Name</th>
					<th>Last Name</th>
					<th>Street</th>
					<th>City</th>
					<th>State</th>
					<th>Country</th>
					<th>ZIP</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($result && $result->num_rows > 0): ?>
					<?php while ($row = $result->fetch_assoc()): ?>
						<tr>
							<td><?php echo htmlspecialchars($row['id']); ?></td>
							<td><?php echo htmlspecialchars($row['first_name']); ?></td>
							<td><?php echo htmlspecialchars($row['last_name']); ?></td>
							<td><?php echo htmlspecialchars($row['street']); ?></td>
							<td><?php echo htmlspecialchars($row['city']); ?></td>
							<td><?php echo htmlspecialchars($row['state']); ?></td>
							<td><?php echo htmlspecialchars($row['country']); ?></td>
							<td><?php echo htmlspecialchars($row['zip']); ?></td>
							<td>
								<a href="edit_driver.php?id=<?php echo urlencode($row['id']); ?>">Edit</a> | 
								<form action="delete_driver.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this driver?');">
									<input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
									<button type="submit" style="background:none;border:none;color:blue;text-decoration:underline;cursor:pointer;padding:0;">Delete</button>
								</form>
							</td>
						</tr>
					<?php endwhile; ?>
				<?php else: ?>
					<tr>
						<td colspan="9" style="text-align:center;">No drivers found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</body>
</html>
<?php
$db->close();
?>
