<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

$db = db_connect();
$message = "";
$error = "";
$can_manage = is_league_director();

function log_race_action($db, $race_id, $action, $details = '') {
    $account_id = current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $query = "INSERT INTO audit_logs (account_id, action, entity_type, entity_id, details, ip_address, user_agent)
              VALUES (?, ?, 'race', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("isisss", $account_id, $action, $race_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!$can_manage) {
        $db->close();
        deny_access('race delete');
    }

    $race_id = (int) ($_POST['race_id'] ?? 0);

    if ($race_id <= 0) {
        $error = "Invalid race.";
    } else {
        $check = $db->prepare("SELECT COUNT(*) AS stat_count FROM driver_statistics WHERE race_id = ?");
        if ($check) {
            $check->bind_param("i", $race_id);
            $check->execute();
            $counts = $check->get_result()->fetch_assoc();
            $check->close();

            if ((int) $counts['stat_count'] > 0) {
                $error = "Race cannot be deleted while statistics still reference it.";
            } else {
                $delete = $db->prepare("DELETE FROM races WHERE id = ?");
                if ($delete) {
                    $delete->bind_param("i", $race_id);
                    if ($delete->execute() && $delete->affected_rows > 0) {
                        $message = "Race deleted.";
                        log_race_action($db, $race_id, 'race_deleted');
                    } else {
                        $error = "Race delete failed.";
                    }
                    $delete->close();
                } else {
                    $error = "Race delete failed.";
                }
            }
        } else {
            $error = "Race delete failed.";
        }
    }
}

$result = $db->query("SELECT races.id, races.race_name, races.race_date, races.scheduled_laps,
                             circuits.circuit_name, circuits.location, circuits.country
                      FROM races
                      JOIN circuits ON circuits.id = races.circuit_id
                      ORDER BY races.race_date DESC, races.race_name");
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Races - F1 Statistics</title>
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <a href="member.php">Back to Dashboard</a>
        <h1>Races</h1>

        <?php if (!empty($message)): ?>
            <p style="color:green;"><?php echo encode_var($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo encode_var($error); ?></p>
        <?php endif; ?>

        <?php if ($can_manage): ?>
            <a href="race_edit.php"><button>Add Race</button></a>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Race Name</th>
                    <th>Race Date</th>
                    <th>Scheduled Laps</th>
                    <th>Circuit</th>
                    <th>Location</th>
                    <th>Country</th>
                    <?php if ($can_manage): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo encode_var($row['race_name']); ?></td>
                            <td><?php echo encode_var($row['race_date']); ?></td>
                            <td><?php echo encode_var($row['scheduled_laps']); ?></td>
                            <td><?php echo encode_var($row['circuit_name']); ?></td>
                            <td><?php echo encode_var($row['location']); ?></td>
                            <td><?php echo encode_var($row['country']); ?></td>
                            <?php if ($can_manage): ?>
                                <td>
                                    <a href="race_edit.php?id=<?php echo encode_var($row['id']); ?>">Edit</a>
                                    <form action="races.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this race?');">
                                        <input type="hidden" name="race_id" value="<?php echo encode_var($row['id']); ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $can_manage ? 7 : 6; ?>" style="text-align:center;">No races found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
</html>
<?php
$db->close();
?>
