<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();
require_role('league_director');

$db = db_connect();
$error = "";
$race_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$race = [
    'id' => '',
    'race_name' => '',
    'circuit_id' => '',
    'race_date' => '',
    'scheduled_laps' => ''
];

function circuit_exists_for_race($db, $circuit_id) {
    $stmt = $db->prepare("SELECT id FROM circuits WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $circuit_id = (int) $circuit_id;
    $stmt->bind_param("i", $circuit_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();

    return $exists;
}

function positive_int_value($value, &$output) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        return false;
    }

    $output = $id;
    return true;
}

function int_in_range($value, $min, $max, &$output) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < $min || $id > $max) {
        return false;
    }

    $output = $id;
    return true;
}

function log_race_save($db, $race_id, $action, $details = '') {
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

if ($race_id > 0) {
    $stmt = $db->prepare("SELECT id, race_name, circuit_id, race_date, scheduled_laps FROM races WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $race_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $race = $existing;
        } elseif ($_SERVER['REQUEST_METHOD'] !== "POST") {
            $db->close();
            die("Race not found.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $race_name = trim($_POST['race_name'] ?? '');
    $circuit_id = (int) ($_POST['circuit_id'] ?? 0);
    $race_date = trim($_POST['race_date'] ?? '');
    $scheduled_laps = 0;

    $race = [
        'id' => $race_id,
        'race_name' => $race_name,
        'circuit_id' => $circuit_id,
        'race_date' => $race_date,
        'scheduled_laps' => $_POST['scheduled_laps'] ?? ''
    ];

    if ($race_name === '') {
        $error = "Race name is required.";
    } elseif (!is_positive_id($circuit_id) || !circuit_exists_for_race($db, $circuit_id)) {
        $error = "Please select a valid circuit.";
    } elseif ($race_date === '') {
        $error = "Race date is required.";
    } elseif (!int_in_range($_POST['scheduled_laps'] ?? '', 1, 200, $scheduled_laps)) {
        $error = "Scheduled laps must be between 1 and 200.";
    }

    if (empty($error)) {
        if ($race_id > 0) {
            $stmt = $db->prepare("UPDATE races
                                  SET race_name = ?, circuit_id = ?, race_date = ?, scheduled_laps = ?
                                  WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sisii", $race_name, $circuit_id, $race_date, $scheduled_laps, $race_id);
                if ($stmt->execute()) {
                    log_race_save($db, $race_id, 'race_updated');
                    $stmt->close();
                    $db->close();
                    header("Location: races.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Race update failed.";
        } else {
            $stmt = $db->prepare("INSERT INTO races (race_name, circuit_id, race_date, scheduled_laps)
                                  VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sisi", $race_name, $circuit_id, $race_date, $scheduled_laps);
                if ($stmt->execute()) {
                    $new_race_id = $stmt->insert_id;
                    log_race_save($db, $new_race_id, 'race_created');
                    $stmt->close();
                    $db->close();
                    header("Location: races.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Race creation failed.";
        }
    }
}

$circuits = $db->query("SELECT id, circuit_name, location, country FROM circuits ORDER BY country, circuit_name");
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php echo $race_id > 0 ? 'Edit Race' : 'Add Race'; ?> - F1 Statistics</title>
        <style>
            .form-group { margin-bottom: 12px; }
            label { display: inline-block; width: 130px; }
        </style>
    </head>
    <body>
        <a href="races.php">Back to Races</a>
        <h1><?php echo $race_id > 0 ? 'Edit Race' : 'Add Race'; ?></h1>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo e($error); ?></p>
        <?php endif; ?>

        <form action="race_edit.php" method="POST">
            <?php if ($race_id > 0): ?>
                <input type="hidden" name="id" value="<?php echo e($race_id); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Race name:</label>
                <input type="text" name="race_name" value="<?php echo e($race['race_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Circuit:</label>
                <select name="circuit_id" required>
                    <option value="">Select circuit</option>
                    <?php while ($circuit = $circuits->fetch_assoc()): ?>
                        <option value="<?php echo e($circuit['id']); ?>" <?php echo (int) $race['circuit_id'] === (int) $circuit['id'] ? 'selected' : ''; ?>>
                            <?php echo e($circuit['circuit_name'] . ' - ' . $circuit['location'] . ', ' . $circuit['country']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Race date:</label>
                <input type="date" name="race_date" value="<?php echo e($race['race_date']); ?>" required>
            </div>

            <div class="form-group">
                <label>Scheduled laps:</label>
                <input type="number" name="scheduled_laps" min="1" max="200" value="<?php echo e($race['scheduled_laps']); ?>" required>
            </div>

            <button type="submit">Save Race</button>
        </form>
    </body>
</html>
<?php
$db->close();
?>
