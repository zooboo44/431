<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();
require_role('league_director');

$db = db_connect();
$message = "";
$error = "";

function log_user_change($db, $target_id, $action, $details = '') {
    $actor_id = current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $query = "INSERT INTO audit_logs (account_id, action, entity_type, entity_id, details, ip_address, user_agent)
              VALUES (?, ?, 'account', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("isisss", $actor_id, $action, $target_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

function is_last_active_league_director($db, $account_id) {
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

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $account_id = (int) ($_POST['account_id'] ?? 0);
    $new_status = (int) ($_POST['is_active'] ?? 0);

    if ($account_id <= 0 || ($new_status !== 0 && $new_status !== 1)) {
        $error = "Invalid account update.";
    } elseif ($account_id === current_user_id() && $new_status === 0) {
        $error = "You cannot disable your own account.";
    } else {
        $target_stmt = $db->prepare("SELECT role_id, is_active FROM accounts WHERE id = ?");
        if ($target_stmt) {
            $target_stmt->bind_param("i", $account_id);
            $target_stmt->execute();
            $target = $target_stmt->get_result()->fetch_assoc();
            $target_stmt->close();

            if (!$target) {
                $error = "Account not found.";
            } elseif ((int) $target['role_id'] === 1 && (int) $target['is_active'] === 1 && $new_status === 0 && is_last_active_league_director($db, $account_id)) {
                $error = "You cannot disable the last active League Director account.";
            }
        } else {
            $error = "Account update failed.";
        }
    }

    if (empty($error)) {
        $stmt = $db->prepare("UPDATE accounts SET is_active = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $new_status, $account_id);
            if ($stmt->execute()) {
                $message = $new_status === 1 ? "Account enabled." : "Account disabled.";
                log_user_change($db, $account_id, $new_status === 1 ? 'account_enabled' : 'account_disabled');
            } else {
                $error = "Account update failed.";
            }
            $stmt->close();
        } else {
            $error = "Account update failed.";
        }
    }
}

$query = "SELECT accounts.id, accounts.email, accounts.username, accounts.role_id, accounts.team_id,
                 accounts.driver_id, accounts.is_active, roles.display_name AS role_name,
                 roles.internal_name AS role_internal_name, teams.team_name,
                 drivers.first_name, drivers.last_name
          FROM accounts
          JOIN roles ON roles.id = accounts.role_id
          LEFT JOIN teams ON teams.id = accounts.team_id
          LEFT JOIN drivers ON drivers.id = accounts.driver_id
          ORDER BY accounts.id";
$result = $db->query($query);
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Manage Users - F1 Statistics</title>
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <a href="member.php">Back to Dashboard</a>
        <h1>Manage Users</h1>

        <?php if (!empty($message)): ?>
            <p style="color:green;"><?php echo e($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo e($error); ?></p>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Team</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($row['id']); ?></td>
                            <td><?php echo e($row['email']); ?></td>
                            <td><?php echo e($row['username']); ?></td>
                            <td><?php echo e($row['role_name']); ?></td>
                            <td><?php echo e($row['team_name'] ?? ''); ?></td>
                            <td><?php echo e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                            <td><?php echo (int) $row['is_active'] === 1 ? 'Active' : 'Disabled'; ?></td>
                            <td>
                                <a href="user_edit.php?id=<?php echo e($row['id']); ?>">Edit</a>

                                <?php if ((int) $row['id'] !== current_user_id()): ?>
                                    <form action="users.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="account_id" value="<?php echo e($row['id']); ?>">
                                        <input type="hidden" name="is_active" value="<?php echo (int) $row['is_active'] === 1 ? 0 : 1; ?>">
                                        <button type="submit"><?php echo (int) $row['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
</html>
<?php
$db->close();
?>
