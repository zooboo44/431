<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

$db = db_connect();
$message = "";
$error = "";

function log_driver_action($db, $driver_id, $action, $details = '') {
    $account_id = current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $query = "INSERT INTO audit_logs (account_id, action, entity_type, entity_id, details, ip_address, user_agent)
              VALUES (?, ?, 'driver', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("isisss", $account_id, $action, $driver_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!is_league_director()) {
        $db->close();
        deny_access('driver delete');
    }

    $driver_id = (int) ($_POST['driver_id'] ?? 0);

    if ($driver_id <= 0) {
        $error = "Invalid driver.";
    } else {
        $check = $db->prepare("SELECT
                                  (SELECT COUNT(*) FROM driver_statistics WHERE driver_id = ?) AS stat_count,
                                  (SELECT COUNT(*) FROM accounts WHERE driver_id = ?) AS account_count");
        if ($check) {
            $check->bind_param("ii", $driver_id, $driver_id);
            $check->execute();
            $counts = $check->get_result()->fetch_assoc();
            $check->close();

            if ((int) $counts['stat_count'] > 0 || (int) $counts['account_count'] > 0) {
                $error = "Driver cannot be deleted while statistics or accounts still reference them.";
            } else {
                $delete = $db->prepare("DELETE FROM drivers WHERE id = ?");
                if ($delete) {
                    $delete->bind_param("i", $driver_id);
                    if ($delete->execute() && $delete->affected_rows > 0) {
                        $message = "Driver deleted.";
                        log_driver_action($db, $driver_id, 'driver_deleted');
                    } else {
                        $error = "Driver delete failed.";
                    }
                    $delete->close();
                } else {
                    $error = "Driver delete failed.";
                }
            }
        } else {
            $error = "Driver delete failed.";
        }
    }
}

$query = "SELECT drivers.id, drivers.team_id, drivers.first_name, drivers.last_name,
                 drivers.date_of_birth, drivers.nationality, drivers.racing_number,
                 drivers.street, drivers.city, drivers.state, drivers.country, drivers.zip,
                 teams.team_name
          FROM drivers
          JOIN teams ON teams.id = drivers.team_id
          ORDER BY teams.team_name, drivers.last_name, drivers.first_name";
$result = $db->query($query);
$can_add = is_league_director() || is_team_manager();
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Drivers - F1 Statistics</title>
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <a href="member.php">Back to Dashboard</a>
        <h1>Drivers</h1>

        <?php if (!empty($message)): ?>
            <p style="color:green;"><?php echo encode_var($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo encode_var($error); ?></p>
        <?php endif; ?>

        <?php if ($can_add): ?>
            <a href="driver_edit.php"><button>Add Driver</button></a>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Team</th>
                    <th>Number</th>
                    <th>Nationality</th>
                    <th>Private Profile</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $can_edit = can_edit_own_driver_profile($row['id']);
                            $can_delete = is_league_director();
                            $can_view_private = is_league_director()
                                || (is_team_manager() && can_manage_team($row['team_id']))
                                || (is_driver() && current_driver_id() === (int) $row['id']);
                        ?>
                        <tr>
                            <td><?php echo encode_var($row['id']); ?></td>
                            <td><?php echo encode_var($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo encode_var($row['team_name']); ?></td>
                            <td><?php echo encode_var($row['racing_number']); ?></td>
                            <td><?php echo encode_var($row['nationality']); ?></td>
                            <td>
                                <?php if ($can_view_private): ?>
                                    <?php echo encode_var(trim(($row['street'] ?? '') . ' ' . ($row['city'] ?? '') . ' ' . ($row['state'] ?? '') . ' ' . ($row['country'] ?? '') . ' ' . ($row['zip'] ?? ''))); ?>
                                <?php else: ?>
                                    Private
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($can_edit): ?>
                                    <a href="driver_edit.php?id=<?php echo encode_var($row['id']); ?>">Edit</a>
                                <?php endif; ?>

                                <?php if ($can_delete): ?>
                                    <form action="drivers.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this driver?');">
                                        <input type="hidden" name="driver_id" value="<?php echo encode_var($row['id']); ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No drivers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
</html>
<?php
$db->close();
?>
