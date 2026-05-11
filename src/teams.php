<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

$db = db_connect();
$message = "";
$error = "";
$can_manage = is_league_director();
$can_edit_own_team = is_team_manager() && current_team_id() !== null;

function log_team_action($db, $team_id, $action, $details = '') {
    $account_id = current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $query = "INSERT INTO audit_logs (account_id, action, entity_type, entity_id, details, ip_address, user_agent)
              VALUES (?, ?, 'team', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("isisss", $account_id, $action, $team_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!$can_manage) {
        $db->close();
        deny_access('team delete');
    }

    $team_id = (int) ($_POST['team_id'] ?? 0);

    if ($team_id <= 0) {
        $error = "Invalid team.";
    } else {
        $check = $db->prepare("SELECT
                                  (SELECT COUNT(*) FROM drivers WHERE team_id = ?) AS driver_count,
                                  (SELECT COUNT(*) FROM accounts WHERE team_id = ?) AS account_count");
        if ($check) {
            $check->bind_param("ii", $team_id, $team_id);
            $check->execute();
            $counts = $check->get_result()->fetch_assoc();
            $check->close();

            if ((int) $counts['driver_count'] > 0 || (int) $counts['account_count'] > 0) {
                $error = "Team cannot be deleted while drivers or accounts still reference it.";
            } else {
                $delete = $db->prepare("DELETE FROM teams WHERE id = ?");
                if ($delete) {
                    $delete->bind_param("i", $team_id);
                    if ($delete->execute() && $delete->affected_rows > 0) {
                        $message = "Team deleted.";
                        log_team_action($db, $team_id, 'team_deleted');
                    } else {
                        $error = "Team delete failed.";
                    }
                    $delete->close();
                } else {
                    $error = "Team delete failed.";
                }
            }
        } else {
            $error = "Team delete failed.";
        }
    }
}

$result = $db->query("SELECT id, team_name, base_location, principal_name, engine_supplier FROM teams ORDER BY team_name");
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Teams - F1 Statistics</title>
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <a href="member.php">Back to Dashboard</a>
        <h1>Teams</h1>

        <?php if (!empty($message)): ?>
            <p style="color:green;"><?php echo e($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo e($error); ?></p>
        <?php endif; ?>

        <?php if ($can_manage): ?>
            <a href="team_edit.php"><button>Add Team</button></a>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Team Name</th>
                    <th>Base Location</th>
                    <th>Principal</th>
                    <th>Engine Supplier</th>
                    <?php if ($can_manage || $can_edit_own_team): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($row['id']); ?></td>
                            <td><?php echo e($row['team_name']); ?></td>
                            <td><?php echo e($row['base_location']); ?></td>
                            <td><?php echo e($row['principal_name']); ?></td>
                            <td><?php echo e($row['engine_supplier']); ?></td>
                            <?php if ($can_manage || ($can_edit_own_team && (int) $row['id'] === current_team_id())): ?>
                                <td>
                                    <a href="team_edit.php?id=<?php echo e($row['id']); ?>">Edit</a>
                                    <?php if ($can_manage): ?>
                                        <form action="teams.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this team?');">
                                            <input type="hidden" name="team_id" value="<?php echo e($row['id']); ?>">
                                            <button type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($can_manage || $can_edit_own_team): ?>
                                <td></td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo ($can_manage || $can_edit_own_team) ? 6 : 5; ?>" style="text-align:center;">No teams found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
</html>
<?php
$db->close();
?>
