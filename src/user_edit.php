<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();
require_role('league_director');

$db = db_connect();
$error = "";
$message = "";
$account_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($account_id <= 0) {
    $db->close();
    die("User not found.");
}

function id_exists($db, $table, $id) {
    $allowed_tables = ['roles', 'teams', 'drivers'];

    if (!in_array($table, $allowed_tables, true)) {
        return false;
    }

    $query = "SELECT id FROM " . $table . " WHERE id = ? LIMIT 1";
    $stmt = $db->prepare($query);

    if (!$stmt) {
        return false;
    }

    $id = (int) $id;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->fetch_assoc() !== null;
    $stmt->close();

    return $exists;
}

function log_account_edit($db, $target_id, $details = '') {
    $actor_id = current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $query = "INSERT INTO audit_logs (account_id, action, entity_type, entity_id, details, ip_address, user_agent)
              VALUES (?, 'account_updated', 'account', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("iisss", $actor_id, $target_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

function is_last_active_league_director_account($db, $account_id) {
    $stmt = $db->prepare("SELECT COUNT(*) AS total
                          FROM accounts
                          WHERE role_id = 1 AND is_active = 1 AND id <> ?");
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0) === 0;
}

$load_stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$load_stmt->bind_param("i", $account_id);
$load_stmt->execute();
$account = $load_stmt->get_result()->fetch_assoc();
$load_stmt->close();

if (!$account) {
    $db->close();
    die("User not found.");
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role_id = (int) ($_POST['role_id'] ?? 0);
    $team_id = trim($_POST['team_id'] ?? '');
    $driver_id = trim($_POST['driver_id'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!is_valid_email($email)) {
        $error = "Please enter a valid email address.";
    } elseif ($username === '') {
        $error = "Please enter a username.";
    } elseif (!id_exists($db, 'roles', $role_id)) {
        $error = "Please select a valid role.";
    } else {
        $duplicate = $db->prepare("SELECT id FROM accounts WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1");
        if (!$duplicate) {
            $error = "User update failed.";
        } else {
            $duplicate->bind_param("ssi", $email, $username, $account_id);
            $duplicate->execute();
            $duplicate_result = $duplicate->get_result();

            if ($duplicate_result->fetch_assoc()) {
                $error = "An account with that email or username already exists.";
            }

            $duplicate->close();
        }
    }

    if (empty($error)) {
        if ($account_id === current_user_id()) {
            $role_id = (int) $account['role_id'];
            $is_active = (int) $account['is_active'];
            $message = "Role and status changes are blocked on your own account.";
        }

        if ((int) $account['role_id'] === 1
            && (int) $account['is_active'] === 1
            && ($role_id !== 1 || $is_active !== 1)
            && is_last_active_league_director_account($db, $account_id)) {
            $error = "You cannot demote or disable the last active League Director account.";
        }
    }

    if (empty($error)) {
        $team_id_value = null;
        $driver_id_value = null;

        if ($role_id === 1 || $role_id === 4) {
            $team_id_value = null;
            $driver_id_value = null;
        } elseif ($role_id === 2) {
            if (!is_positive_id($team_id) || !id_exists($db, 'teams', (int) $team_id)) {
                $error = "Team Manager accounts must have a valid team.";
            } else {
                $team_id_value = (int) $team_id;
                $driver_id_value = null;
            }
        } elseif ($role_id === 3) {
            if (!is_positive_id($driver_id) || !id_exists($db, 'drivers', (int) $driver_id)) {
                $error = "Driver accounts must have a valid driver.";
            } else {
                $team_id_value = null;
                $driver_id_value = (int) $driver_id;
            }
        } else {
            $error = "Please select a valid role.";
        }
    }

    if (empty($error) && $new_password !== '') {
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (!is_strong_password($new_password)) {
            $error = "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.";
        }
    }

    if (empty($error)) {
        if ($new_password !== '') {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE accounts
                                  SET email = ?, username = ?, role_id = ?, team_id = ?, driver_id = ?, is_active = ?, password_hash = ?
                                  WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE accounts
                                  SET email = ?, username = ?, role_id = ?, team_id = ?, driver_id = ?, is_active = ?
                                  WHERE id = ?");
        }

        if (!$stmt) {
            $error = "User update failed.";
        } elseif ($new_password !== '') {
            $stmt->bind_param("ssiiiisi", $email, $username, $role_id, $team_id_value, $driver_id_value, $is_active, $password_hash, $account_id);
        } else {
            $stmt->bind_param("ssiiiii", $email, $username, $role_id, $team_id_value, $driver_id_value, $is_active, $account_id);
        }

        if (empty($error) && $stmt->execute()) {
            $message = $message ?: "User updated.";
            log_account_edit($db, $account_id);

            $load_stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
            $load_stmt->bind_param("i", $account_id);
            $load_stmt->execute();
            $account = $load_stmt->get_result()->fetch_assoc();
            $load_stmt->close();
        } else {
            $error = "User update failed.";
        }

        if ($stmt) {
            $stmt->close();
        }
    }
}

$roles = $db->query("SELECT id, display_name FROM roles ORDER BY id");
$teams = $db->query("SELECT id, team_name FROM teams ORDER BY team_name");
$drivers = $db->query("SELECT drivers.id, drivers.first_name, drivers.last_name, teams.team_name
                       FROM drivers
                       JOIN teams ON teams.id = drivers.team_id
                       ORDER BY teams.team_name, drivers.last_name, drivers.first_name");
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Edit User - F1 Statistics</title>
        <style>
            .form-group { margin-bottom: 12px; }
            label { display: inline-block; width: 160px; }
        </style>
    </head>
    <body>
        <a href="users.php">Back to Users</a>
        <h1>Edit User</h1>

        <?php if (!empty($message)): ?>
            <p style="color:green;"><?php echo encode_var($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo encode_var($error); ?></p>
        <?php endif; ?>

        <form action="user_edit.php" method="POST">
            <input type="hidden" name="id" value="<?php echo encode_var($account['id']); ?>">

            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo encode_var($account['email']); ?>" required>
            </div>

            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" value="<?php echo encode_var($account['username']); ?>" required>
            </div>

            <div class="form-group">
                <label>Role:</label>
                <select name="role_id">
                    <?php while ($role = $roles->fetch_assoc()): ?>
                        <option value="<?php echo encode_var($role['id']); ?>" <?php echo (int) $account['role_id'] === (int) $role['id'] ? 'selected' : ''; ?>>
                            <?php echo encode_var($role['display_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Team:</label>
                <select name="team_id">
                    <option value="">None</option>
                    <?php while ($team = $teams->fetch_assoc()): ?>
                        <option value="<?php echo encode_var($team['id']); ?>" <?php echo (int) ($account['team_id'] ?? 0) === (int) $team['id'] ? 'selected' : ''; ?>>
                            <?php echo encode_var($team['team_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Driver:</label>
                <select name="driver_id">
                    <option value="">None</option>
                    <?php while ($driver = $drivers->fetch_assoc()): ?>
                        <option value="<?php echo encode_var($driver['id']); ?>" <?php echo (int) ($account['driver_id'] ?? 0) === (int) $driver['id'] ? 'selected' : ''; ?>>
                            <?php echo encode_var($driver['team_name'] . ' - ' . $driver['first_name'] . ' ' . $driver['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Active:</label>
                <input type="checkbox" name="is_active" value="1" <?php echo (int) $account['is_active'] === 1 ? 'checked' : ''; ?>>
            </div>

            <div class="form-group">
                <label>New password:</label>
                <input type="password" name="new_password">
            </div>

            <div class="form-group">
                <label>Confirm new password:</label>
                <input type="password" name="confirm_password">
            </div>

            <button type="submit">Save User</button>
        </form>
    </body>
</html>
<?php
$db->close();
?>
