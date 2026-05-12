<?php
session_start();

require_once __DIR__ . '/functions/output_fns.php';

$error = $_SESSION['register_error'] ?? "";
$form = $_SESSION['register_form'] ?? [];
unset($_SESSION['register_error'], $_SESSION['register_form']);
?>
<!DOCTYPE html>
<html>
    <head>
        <title>F1 Statistics</title>
    </head>
    <body>
        <form action="login.php" method="GET">
            <button type="submit">Go back</button>
        </form>

        <h1 style="text-align:left;">Registering new account</h1>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo encode_var($error); ?></p>
        <?php endif; ?>

        <form action="register_new.php" method="POST">
            <label>Enter Email: </label>
            <br>
            <input type="email" name="email" value="<?php echo encode_var($form['email'] ?? ''); ?>" required>
            <br>
            <label>Create username: </label>
            <br>
            <input type="text" name="username" value="<?php echo encode_var($form['username'] ?? ''); ?>" required>
            <br>
            <label>Create password: </label>
            <br>
            <input type="password" name="password" required>
            <br>
            <label>Confirm password: </label>
            <br>
            <input type="password" name="confirmed_password" required>
            <br><br>
            <button type="submit">Create account</button>
        </form>
    </body>
</html>
