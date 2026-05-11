<!DOCTYPE html>
<html>
    <head>
        <title>F1 Statistics - Forgot Password</title>
    </head>
    <body>
        <form action="login.php" method="GET">
            <button type="submit">Go back</button>
        </form>

        <h1 style="text-align:left;">Forgot Password</h1>
        <form action="forgot_password.php" method="POST">
            <p>Enter your email address to get a new password</p>

            <label>Email: </label>
            <br>
            <input type="email" name="email" required>
            <br><br>
            <button type="submit">Create temporary password</button>
        </form>
    </body>
</html>
