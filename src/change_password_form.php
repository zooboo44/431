<?php
require_once __DIR__ . '/functions/auth_fns.php';

require_login();
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>F1 Statistics</title>
    </head>
    <body>
        <form action="member.php" method="GET">
            <button type="submit">Go Back</button>
        </form>

        <h1 style="text-align: left;">Change Password</h1>
        <form action="change_password.php" method="POST">
            <label>Current password:</label>
            <br>
            <input type="password" name="current_password" required>
            <br>

            <label>New password:</label>
            <br>
            <input type="password" name="new_password" required>
            <br>

            <label>Confirm password:</label>
            <br>
            <input type="password" name="confirmed_password" required>
            <br><br>

            <button type="submit">Change password</button>
        </form>
    </body>
</html>
