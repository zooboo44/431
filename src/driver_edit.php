<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

$db = db_connect();
$error = "";
$driver_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$is_edit = $driver_id > 0;
$driver = [
    'id' => '',
    'team_id' => '',
    'first_name' => '',
    'last_name' => '',
    'date_of_birth' => '',
    'nationality' => '',
    'racing_number' => '',
    'street' => '',
    'city' => '',
    'state' => '',
    'country' => '',
    'zip' => ''
];

function team_exists($db, $team_id) {
    $stmt = $db->prepare("SELECT id FROM teams WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $team_id = (int) $team_id;
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();

    return $exists;
}

function clean_empty($value) {
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function log_driver_save($db, $driver_id, $action, $details = '') {
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

if ($is_edit) {
    $stmt = $db->prepare("SELECT * FROM drivers WHERE id = ?");
    if (!$stmt) {
        $db->close();
        die("Driver not found.");
    }

    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        $db->close();
        die("Driver not found.");
    }

    if (!can_edit_own_driver_profile($driver_id)) {
        $db->close();
        deny_access('driver edit');
    }

    $driver = $existing;
} else {
    if (!is_league_director() && !is_team_manager()) {
        $db->close();
        deny_access('driver create');
    }

    if (is_team_manager() && current_team_id() === null) {
        $db->close();
        deny_access('team manager missing team');
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $date_of_birth = clean_empty($_POST['date_of_birth'] ?? '');
    $nationality = clean_empty($_POST['nationality'] ?? '');
    $street = clean_empty($_POST['street'] ?? '');
    $city = clean_empty($_POST['city'] ?? '');
    $state = clean_empty($_POST['state'] ?? '');
    $country = clean_empty($_POST['country'] ?? '');
    $zip = clean_empty($_POST['zip'] ?? '');

    if (is_league_director()) {
        $team_id = (int) ($_POST['team_id'] ?? 0);
    } elseif (is_team_manager()) {
        $team_id = current_team_id();
    } else {
        $team_id = (int) $driver['team_id'];
    }

    if (is_league_director() || is_team_manager()) {
        $racing_number_raw = trim($_POST['racing_number'] ?? '');
        $racing_number = $racing_number_raw === '' ? null : (int) $racing_number_raw;
    } else {
        $racing_number = $driver['racing_number'] !== null ? (int) $driver['racing_number'] : null;
    }

    $driver = [
        'id' => $driver_id,
        'team_id' => $team_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'date_of_birth' => $date_of_birth,
        'nationality' => $nationality,
        'racing_number' => $racing_number,
        'street' => $street,
        'city' => $city,
        'state' => $state,
        'country' => $country,
        'zip' => $zip
    ];

    if ($first_name === '' || $last_name === '') {
        $error = "First name and last name are required.";
    } elseif (!is_positive_id($team_id) || !team_exists($db, $team_id)) {
        $error = "A valid team is required.";
    } elseif (is_team_manager() && (int) $team_id !== current_team_id()) {
        $error = "Team Managers can only save drivers for their own team.";
    } elseif ($racing_number !== null && $racing_number <= 0) {
        $error = "Racing number must be a positive number.";
    }

    if (empty($error)) {
        if ($is_edit) {
            if (is_driver()) {
                $stmt = $db->prepare("UPDATE drivers
                                      SET first_name = ?, last_name = ?, date_of_birth = ?, nationality = ?,
                                          street = ?, city = ?, state = ?, country = ?, zip = ?
                                      WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sssssssssi", $first_name, $last_name, $date_of_birth, $nationality, $street, $city, $state, $country, $zip, $driver_id);
                }
            } else {
                $stmt = $db->prepare("UPDATE drivers
                                      SET team_id = ?, first_name = ?, last_name = ?, date_of_birth = ?, nationality = ?,
                                          racing_number = ?, street = ?, city = ?, state = ?, country = ?, zip = ?
                                      WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("issssisssssi", $team_id, $first_name, $last_name, $date_of_birth, $nationality, $racing_number, $street, $city, $state, $country, $zip, $driver_id);
                }
            }

            if ($stmt && $stmt->execute()) {
                log_driver_save($db, $driver_id, 'driver_updated');
                $stmt->close();
                $db->close();
                header("Location: drivers.php");
                exit();
            }

            if ($stmt) {
                $stmt->close();
            }
            $error = "Driver update failed.";
        } else {
            $stmt = $db->prepare("INSERT INTO drivers
                                  (team_id, first_name, last_name, date_of_birth, nationality, racing_number, street, city, state, country, zip)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issssisssss", $team_id, $first_name, $last_name, $date_of_birth, $nationality, $racing_number, $street, $city, $state, $country, $zip);
                if ($stmt->execute()) {
                    $new_driver_id = $stmt->insert_id;
                    log_driver_save($db, $new_driver_id, 'driver_created');
                    $stmt->close();
                    $db->close();
                    header("Location: drivers.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Driver creation failed.";
        }
    }
}

$teams = $db->query("SELECT id, team_name FROM teams ORDER BY team_name");
$can_edit_team = is_league_director();
$can_edit_racing_number = is_league_director() || is_team_manager();
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php echo $is_edit ? 'Edit Driver' : 'Add Driver'; ?> - F1 Statistics</title>
        <style>
            .form-group { margin-bottom: 12px; }
            label { display: inline-block; width: 140px; }
        </style>
    </head>
    <body>
        <a href="drivers.php">Back to Drivers</a>
        <h1><?php echo $is_edit ? 'Edit Driver' : 'Add Driver'; ?></h1>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo e($error); ?></p>
        <?php endif; ?>

        <form action="driver_edit.php" method="POST">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo e($driver_id); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>First name:</label>
                <input type="text" name="first_name" value="<?php echo e($driver['first_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Last name:</label>
                <input type="text" name="last_name" value="<?php echo e($driver['last_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Team:</label>
                <?php if ($can_edit_team): ?>
                    <select name="team_id" required>
                        <option value="">Select team</option>
                        <?php while ($team = $teams->fetch_assoc()): ?>
                            <option value="<?php echo e($team['id']); ?>" <?php echo (int) $driver['team_id'] === (int) $team['id'] ? 'selected' : ''; ?>>
                                <?php echo e($team['team_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                <?php elseif (is_team_manager()): ?>
                    <input type="hidden" name="team_id" value="<?php echo e(current_team_id()); ?>">
                    Team ID <?php echo e(current_team_id()); ?>
                <?php else: ?>
                    Team ID <?php echo e($driver['team_id']); ?>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Racing number:</label>
                <?php if ($can_edit_racing_number): ?>
                    <input type="number" name="racing_number" min="1" value="<?php echo e($driver['racing_number']); ?>">
                <?php else: ?>
                    <?php echo e($driver['racing_number']); ?>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Date of birth:</label>
                <input type="date" name="date_of_birth" value="<?php echo e($driver['date_of_birth']); ?>">
            </div>

            <div class="form-group">
                <label>Nationality:</label>
                <input type="text" name="nationality" value="<?php echo e($driver['nationality']); ?>">
            </div>

            <div class="form-group">
                <label>Street:</label>
                <input type="text" name="street" value="<?php echo e($driver['street']); ?>">
            </div>

            <div class="form-group">
                <label>City:</label>
                <input type="text" name="city" value="<?php echo e($driver['city']); ?>">
            </div>

            <div class="form-group">
                <label>State:</label>
                <input type="text" name="state" value="<?php echo e($driver['state']); ?>">
            </div>

            <div class="form-group">
                <label>Country:</label>
                <input type="text" name="country" value="<?php echo e($driver['country']); ?>">
            </div>

            <div class="form-group">
                <label>ZIP:</label>
                <input type="text" name="zip" value="<?php echo e($driver['zip']); ?>">
            </div>

            <button type="submit">Save Driver</button>
        </form>
    </body>
</html>
<?php
$db->close();
?>
