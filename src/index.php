<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/functions/output_fns.php';

if (isset($_SESSION['account_id'])) {
    header("Location: member.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>F1 Racing Management System</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.5; }
            .actions a { margin-right: 12px; }
        </style>
    </head>
    <body>
        <h1>F1 Racing Management System</h1>

        <p>
            A Formula One database application for viewing and managing teams, drivers,
            circuits, races, and driver statistics.
        </p>

        <p class="actions">
            <a href="login.php">Login</a>
            <a href="register_form.php">Register</a>
        </p>

        <p>
            New accounts register as Viewer accounts. Higher roles are assigned by a League Director.
        </p>
    </body>
</html>
