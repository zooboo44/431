<?php
session_start();
session_unset();
session_destroy();

//setcookie(session_name(), '', time() - 42000, '/');
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>F1 Statistics</title>
</head>
<body>
	<h1 style="text-align: center;">Logged out</h1>
	<form action="login.php" method="GET">
  	  <button type="submit">Return to login</button>
	</form>
</body>
</html>