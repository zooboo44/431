<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

$db = db_connect();
$message = "";
$error = "";
$can_manage = is_league_director();

function log_circuit_action($db, $circuit_id, $action, $details = '') {
    $account_id = current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $query = "INSERT INTO audit_logs (account_id, action, entity_type, entity_id, details, ip_address, user_agent)
              VALUES (?, ?, 'circuit', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("isisss", $account_id, $action, $circuit_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!$can_manage) {
        $db->close();
        deny_access('circuit delete');
    }

    $circuit_id = (int) ($_POST['circuit_id'] ?? 0);

    if ($circuit_id <= 0) {
        $error = "Invalid circuit.";
    } else {
        $check = $db->prepare("SELECT COUNT(*) AS race_count FROM races WHERE circuit_id = ?");
        if ($check) {
            $check->bind_param("i", $circuit_id);
            $check->execute();
            $counts = $check->get_result()->fetch_assoc();
            $check->close();

            if ((int) $counts['race_count'] > 0) {
                $error = "Circuit cannot be deleted while races still reference it.";
            } else {
                $delete = $db->prepare("DELETE FROM circuits WHERE id = ?");
                if ($delete) {
                    $delete->bind_param("i", $circuit_id);
                    if ($delete->execute() && $delete->affected_rows > 0) {
                        $message = "Circuit deleted.";
                        log_circuit_action($db, $circuit_id, 'circuit_deleted');
                    } else {
                        $error = "Circuit delete failed.";
                    }
                    $delete->close();
                } else {
                    $error = "Circuit delete failed.";
                }
            }
        } else {
            $error = "Circuit delete failed.";
        }
    }
}

$result = $db->query("SELECT id, circuit_name, location, country, length_km
                      FROM circuits
                      ORDER BY country, circuit_name");
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Circuits - F1 Statistics</title>
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <a href="member.php">Back to Dashboard</a>
        <h1>Circuits</h1>

        <?php if (!empty($message)): ?>
            <p style="color:green;"><?php echo e($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo e($error); ?></p>
        <?php endif; ?>

        <?php if ($can_manage): ?>
            <a href="circuit_edit.php"><button>Add Circuit</button></a>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Circuit Name</th>
                    <th>Location</th>
                    <th>Country</th>
                    <th>Length KM</th>
                    <?php if ($can_manage): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($row['circuit_name']); ?></td>
                            <td><?php echo e($row['location']); ?></td>
                            <td><?php echo e($row['country']); ?></td>
                            <td><?php echo e($row['length_km']); ?></td>
                            <?php if ($can_manage): ?>
                                <td>
                                    <a href="circuit_edit.php?id=<?php echo e($row['id']); ?>">Edit</a>
                                    <form action="circuits.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this circuit?');">
                                        <input type="hidden" name="circuit_id" value="<?php echo e($row['id']); ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $can_manage ? 5 : 4; ?>" style="text-align:center;">No circuits found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
</html>
<?php
$db->close();
?>
