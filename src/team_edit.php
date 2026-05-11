<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

if (!is_league_director() && !is_team_manager()) {
    deny_access('team edit');
}

$db = db_connect();
$error = "";
$team_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$is_director = is_league_director();
$is_manager = is_team_manager();
$team = [
    'id' => '',
    'team_name' => '',
    'base_location' => '',
    'principal_name' => '',
    'engine_supplier' => ''
];

function log_team_save($db, $team_id, $action, $details = '') {
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

if ($is_manager) {
    if ($team_id <= 0 || current_team_id() === null || $team_id !== current_team_id()) {
        $db->close();
        deny_access('team edit');
    }
}

if (!$is_director && $team_id <= 0) {
    $db->close();
    deny_access('team create');
}

if ($team_id > 0) {
    $stmt = $db->prepare("SELECT id, team_name, base_location, principal_name, engine_supplier FROM teams WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $team = $existing;
        } elseif ($_SERVER['REQUEST_METHOD'] !== "POST") {
            $db->close();
            die("Team not found.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $team_name = $is_director ? trim($_POST['team_name'] ?? '') : $team['team_name'];
    $base_location = trim($_POST['base_location'] ?? '');
    $principal_name = trim($_POST['principal_name'] ?? '');
    $engine_supplier = trim($_POST['engine_supplier'] ?? '');

    $team = [
        'id' => $team_id,
        'team_name' => $team_name,
        'base_location' => $base_location,
        'principal_name' => $principal_name,
        'engine_supplier' => $engine_supplier
    ];

    if ($team_name === '') {
        $error = "Team name is required.";
    }

    if (empty($error)) {
        if ($team_id > 0) {
            if ($is_director) {
                $stmt = $db->prepare("UPDATE teams
                                      SET team_name = ?, base_location = ?, principal_name = ?, engine_supplier = ?
                                      WHERE id = ?");
            } else {
                $stmt = $db->prepare("UPDATE teams
                                      SET base_location = ?, principal_name = ?, engine_supplier = ?
                                      WHERE id = ?");
            }
            if ($stmt) {
                if ($is_director) {
                    $stmt->bind_param("ssssi", $team_name, $base_location, $principal_name, $engine_supplier, $team_id);
                } else {
                    $stmt->bind_param("sssi", $base_location, $principal_name, $engine_supplier, $team_id);
                }
                if ($stmt->execute()) {
                    log_team_save($db, $team_id, 'team_updated');
                    $stmt->close();
                    $db->close();
                    header("Location: teams.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Team update failed.";
        } elseif ($is_director) {
            $stmt = $db->prepare("INSERT INTO teams (team_name, base_location, principal_name, engine_supplier)
                                  VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $team_name, $base_location, $principal_name, $engine_supplier);
                if ($stmt->execute()) {
                    $new_team_id = $stmt->insert_id;
                    log_team_save($db, $new_team_id, 'team_created');
                    $stmt->close();
                    $db->close();
                    header("Location: teams.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Team creation failed.";
        } else {
            $error = "Only League Directors can create teams.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php echo $team_id > 0 ? 'Edit Team' : 'Add Team'; ?> - F1 Statistics</title>
        <style>
            .form-group { margin-bottom: 12px; }
            label { display: inline-block; width: 140px; }
        </style>
    </head>
    <body>
        <a href="teams.php">Back to Teams</a>
        <h1><?php echo $team_id > 0 ? 'Edit Team' : 'Add Team'; ?></h1>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo e($error); ?></p>
        <?php endif; ?>

        <form action="team_edit.php" method="POST">
            <?php if ($team_id > 0): ?>
                <input type="hidden" name="id" value="<?php echo e($team_id); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Team name:</label>
                <?php if ($is_director): ?>
                    <input type="text" name="team_name" value="<?php echo e($team['team_name']); ?>" required>
                <?php else: ?>
                    <?php echo e($team['team_name']); ?>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Base location:</label>
                <input type="text" name="base_location" value="<?php echo e($team['base_location']); ?>">
            </div>

            <div class="form-group">
                <label>Principal:</label>
                <input type="text" name="principal_name" value="<?php echo e($team['principal_name']); ?>">
            </div>

            <div class="form-group">
                <label>Engine supplier:</label>
                <input type="text" name="engine_supplier" value="<?php echo e($team['engine_supplier']); ?>">
            </div>

            <button type="submit">Save Team</button>
        </form>
    </body>
</html>
<?php
$db->close();
?>
