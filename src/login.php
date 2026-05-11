<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "config.php";

session_start();
$error = "";
if ($_SERVER['REQUEST_METHOD'] === "POST" &&
	isset($_POST['username'], $_POST['password'])) {

	$db = new mysqli("localhost", "root", '', "FORMULA_ONE");
	if ($db->connect_error) {
		die("Could not connect to database! Please try again later.");
	}

	$username = $_POST['username'] ?? '';
	$password = $_POST['password'] ?? '';
	if (empty($username) || empty($password)) {
		$error = "Username and password required";
	}

	$query = "SELECT accounts.id, accounts.username, accounts.password_hash, accounts.user_role FROM accounts WHERE username = ?";
	$stmt = $db->prepare($query);

	if (!$stmt) {
	    $error = "Query failed";
	}
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$result = $stmt->get_result();

	if ($user = $result->fetch_assoc()) {
		if (password_verify($password, $user['password_hash'])) {
			session_regenerate_id(true);

			$_SESSION['user_id'] = $user['id'];
			$_SESSION['username'] = $user['username'];

			switch ($user['user_role']) {
				case 4:
					$_SESSION['db_user'] = access_admin;
					$_SESSION['db_pass'] = admin_pass;
					break;
				case 3:
					$_SESSION['db_user'] = access_coach;
					$_SESSION['db_pass'] = coach_pass;
					break;
				case 2:
					$_SESSION['db_user'] = access_driver;
					$_SESSION['db_pass'] = driver_pass;
					break;
				case 1:
					$_SESSION['db_user'] = access_visitor;
					$_SESSION['db_pass'] = visitor_pass;
					break;
				case 0:
					$_SESSION['db_user'] = access_observer;
					$_SESSION['db_pass'] = observer_pass;
					break;
				default:
					$error = "Invalid role";
			}

			header("Location: member.php");
			exit();
		} else {
			$error = "Invalid username or password. Please try again!";
		}
	} else {
		$error = "Invalid username or password. Please try again!";
	}
	$stmt->close();
	$db->close();

}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>
			F1 Statistics
		</title>
	</head>
	<body>

		<h1 style="text-align:left;">Login</h1>

		<?php if (!empty($error)): ?>
		    <p style="color:red;"><?php echo $error; ?></p>
		<?php endif; ?>
		<form action="login.php" method="POST">
			<a href="<?php echo 'register_form.php'; ?>">Dont have an account?</a>
			<br><br>
			<label>Username: </label>
			<br>
			<input type="text" name="username" required>

			<br>

			<label>Password: </label>
			<br>
			<input type="password" name="password" required>
			<br>
			<a href="<?php echo 'forgot_password_form.php'; ?>">Forgot password?</a>
			<br>
			<br>
			<button type="submit">Login</button>
		</form>
	</body>
</html>