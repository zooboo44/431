<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

$db = db_connect();
$message = "";
$error = "";

function log_statistic_action($db, $statistic_id, $action, $details = '') {
    $account_id = current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $query = "INSERT INTO audit_logs (account_id, action, entity_type, entity_id, details, ip_address, user_agent)
              VALUES (?, ?, 'driver_statistics', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("isisss", $account_id, $action, $statistic_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!is_league_director()) {
        $db->close();
        deny_access('statistic delete');
    }

    $statistic_id = (int) ($_POST['statistic_id'] ?? 0);

    if ($statistic_id <= 0) {
        $error = "Invalid statistic.";
    } else {
        $delete = $db->prepare("DELETE FROM driver_statistics WHERE id = ?");
        if ($delete) {
            $delete->bind_param("i", $statistic_id);
            if ($delete->execute() && $delete->affected_rows > 0) {
                $message = "Statistic deleted.";
                log_statistic_action($db, $statistic_id, 'statistic_deleted');
            } else {
                $error = "Statistic delete failed.";
            }
            $delete->close();
        } else {
            $error = "Statistic delete failed.";
        }
    }
}

$query = "SELECT driver_statistics.id, driver_statistics.driver_id, driver_statistics.race_id,
                 driver_statistics.finish_position, driver_statistics.points,
                 driver_statistics.laps_completed, driver_statistics.pit_stops,
                 driver_statistics.best_lap_time_ms, driver_statistics.dnf,
                 drivers.first_name, drivers.last_name, drivers.team_id,
                 teams.team_name, races.race_name, circuits.circuit_name
          FROM driver_statistics
          JOIN drivers ON drivers.id = driver_statistics.driver_id
          JOIN teams ON teams.id = drivers.team_id
          JOIN races ON races.id = driver_statistics.race_id
          JOIN circuits ON circuits.id = races.circuit_id
          ORDER BY races.race_date DESC, teams.team_name, drivers.last_name, drivers.first_name";
$result = $db->query($query);
$can_add = is_league_director() || is_team_manager();
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Statistics - F1 Statistics</title>
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <a href="member.php">Back to Dashboard</a>
        <h1>Statistics</h1>

        <?php if (!empty($message)): ?>
            <p style="color:green;"><?php echo encode_var($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo encode_var($error); ?></p>
        <?php endif; ?>

        <?php if ($can_add): ?>
            <a href="statistic_edit.php"><button>Add Statistic</button></a>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Driver</th>
                    <th>Team</th>
                    <th>Race</th>
                    <th>Circuit</th>
                    <th>Finish</th>
                    <th>Points</th>
                    <th>Laps</th>
                    <th>Pit Stops</th>
                    <th>Best Lap MS</th>
                    <th>DNF</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $can_edit = is_league_director() || (is_team_manager() && can_manage_team($row['team_id']));
                            $can_delete = is_league_director();
                        ?>
                        <tr>
                            <td><?php echo encode_var($row['id']); ?></td>
                            <td><?php echo encode_var($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo encode_var($row['team_name']); ?></td>
                            <td><?php echo encode_var($row['race_name']); ?></td>
                            <td><?php echo encode_var($row['circuit_name']); ?></td>
                            <td><?php echo encode_var($row['finish_position'] ?? ''); ?></td>
                            <td><?php echo encode_var($row['points']); ?></td>
                            <td><?php echo encode_var($row['laps_completed']); ?></td>
                            <td><?php echo encode_var($row['pit_stops']); ?></td>
                            <td><?php echo encode_var($row['best_lap_time_ms'] ?? ''); ?></td>
                            <td><?php echo (int) $row['dnf'] === 1 ? 'Yes' : 'No'; ?></td>
                            <td>
                                <?php if ($can_edit): ?>
                                    <a href="statistic_edit.php?id=<?php echo encode_var($row['id']); ?>">Edit</a>
                                <?php endif; ?>

                                <?php if ($can_delete): ?>
                                    <form action="statistic.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this statistic?');">
                                        <input type="hidden" name="statistic_id" value="<?php echo encode_var($row['id']); ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" style="text-align:center;">No statistics found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
</html>
<?php
$db->close();
?>
