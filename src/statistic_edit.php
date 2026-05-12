<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();

if (!is_league_director() && !is_team_manager()) {
    deny_access('statistic edit');
}

$db = db_connect();
$error = "";
$statistic_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$is_edit = $statistic_id > 0;
$stat = [
    'id' => '',
    'driver_id' => '',
    'race_id' => '',
    'finish_position' => '',
    'points' => '0.00',
    'laps_completed' => '0',
    'pit_stops' => '0',
    'best_lap_time_ms' => '',
    'dnf' => '0'
];

function row_exists($db, $table, $id) {
    $allowed_tables = ['drivers', 'races'];
    if (!in_array($table, $allowed_tables, true)) {
        return false;
    }

    $stmt = $db->prepare("SELECT id FROM " . $table . " WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $id = (int) $id;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();

    return $exists;
}

function optional_positive_int($value, &$output) {
    $value = trim((string) $value);
    if ($value === '') {
        $output = null;
        return true;
    }

    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        return false;
    }

    $output = $id;
    return true;
}

function optional_int_in_range($value, $min, $max, &$output) {
    $value = trim((string) $value);
    if ($value === '') {
        $output = null;
        return true;
    }

    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < $min || $id > $max) {
        return false;
    }

    $output = $id;
    return true;
}

function non_negative_int_value($value, &$output) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < 0) {
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

function non_negative_number_value($value, &$output) {
    if (!is_numeric($value) || (float) $value < 0) {
        return false;
    }

    $output = (float) $value;
    return true;
}

function number_in_range($value, $min, $max, &$output) {
    if (!is_numeric($value)) {
        return false;
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        return false;
    }

    $output = $number;
    return true;
}

function finish_position_conflicts($db, $race_id, $finish_position, $statistic_id) {
    if ($finish_position === null) {
        return false;
    }

    $stmt = $db->prepare("SELECT id FROM driver_statistics
                          WHERE race_id = ? AND finish_position = ? AND id <> ?
                          LIMIT 1");
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param("iii", $race_id, $finish_position, $statistic_id);
    $stmt->execute();
    $conflict = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();

    return $conflict;
}

function driver_race_statistic_exists($db, $driver_id, $race_id, $statistic_id) {
    $stmt = $db->prepare("SELECT id FROM driver_statistics
                          WHERE driver_id = ? AND race_id = ? AND id <> ?
                          LIMIT 1");
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param("iii", $driver_id, $race_id, $statistic_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();

    return $exists;
}

function log_statistic_save($db, $statistic_id, $action, $details = '') {
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

if ($is_edit) {
    $stmt = $db->prepare("SELECT * FROM driver_statistics WHERE id = ?");
    if (!$stmt) {
        $db->close();
        die("Statistic not found.");
    }

    $stmt->bind_param("i", $statistic_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        $db->close();
        die("Statistic not found.");
    }

    if (!is_league_director() && !can_manage_driver($existing['driver_id'])) {
        $db->close();
        deny_access('statistic edit');
    }

    $stat = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $driver_id = (int) ($_POST['driver_id'] ?? 0);
    $race_id = (int) ($_POST['race_id'] ?? 0);
    $finish_position = null;
    $points = 0.0;
    $laps_completed = 0;
    $pit_stops = 0;
    $best_lap_time_ms = null;
    $dnf_raw = $_POST['dnf'] ?? 0;
    $dnf = filter_var($dnf_raw, FILTER_VALIDATE_INT);

    if (!is_positive_id($driver_id) || !row_exists($db, 'drivers', $driver_id)) {
        $error = "Please select a valid driver.";
    } elseif (!is_positive_id($race_id) || !row_exists($db, 'races', $race_id)) {
        $error = "Please select a valid race.";
    } elseif (!is_league_director() && !can_manage_driver($driver_id)) {
        $error = "Team Managers can only save statistics for drivers on their own team.";
    } elseif ($dnf === false || ($dnf !== 0 && $dnf !== 1)) {
        $error = "DNF value is invalid.";
    } elseif (!optional_int_in_range($_POST['finish_position'] ?? '', 1, 99, $finish_position)) {
        $error = "Finish position must be empty or between 1 and 99.";
    } elseif (driver_race_statistic_exists($db, $driver_id, $race_id, $statistic_id)) {
        $error = "A statistic already exists for this driver and race.";
    } elseif (finish_position_conflicts($db, $race_id, $finish_position, $statistic_id)) {
        $error = "That finish position is already used for this race.";
    } elseif (!number_in_range($_POST['points'] ?? '', 0, 100, $points)) {
        $error = "Points must be a number between 0 and 100.";
    } elseif (!int_in_range($_POST['laps_completed'] ?? '', 0, 300, $laps_completed)) {
        $error = "Laps completed must be between 0 and 300.";
    } elseif (!int_in_range($_POST['pit_stops'] ?? '', 0, 20, $pit_stops)) {
        $error = "Pit stops must be between 0 and 20.";
    } elseif (!optional_int_in_range($_POST['best_lap_time_ms'] ?? '', 1, 600000, $best_lap_time_ms)) {
        $error = "Best lap time must be empty or between 1 and 600000 milliseconds.";
    }

    $stat = [
        'id' => $statistic_id,
        'driver_id' => $driver_id,
        'race_id' => $race_id,
        'finish_position' => $finish_position,
        'points' => $points,
        'laps_completed' => $laps_completed,
        'pit_stops' => $pit_stops,
        'best_lap_time_ms' => $best_lap_time_ms,
        'dnf' => $dnf
    ];

    if (empty($error)) {
        if ($is_edit) {
            $stmt = $db->prepare("UPDATE driver_statistics
                                  SET driver_id = ?, race_id = ?, finish_position = ?, points = ?,
                                      laps_completed = ?, pit_stops = ?, best_lap_time_ms = ?, dnf = ?
                                  WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("iiidiiiii", $driver_id, $race_id, $finish_position, $points, $laps_completed, $pit_stops, $best_lap_time_ms, $dnf, $statistic_id);
                if ($stmt->execute()) {
                    log_statistic_save($db, $statistic_id, 'statistic_updated');
                    $stmt->close();
                    $db->close();
                    header("Location: statistic.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Statistic update failed. Please check for duplicate driver/race or finish position values.";
        } else {
            $stmt = $db->prepare("INSERT INTO driver_statistics
                                  (driver_id, race_id, finish_position, points, laps_completed, pit_stops, best_lap_time_ms, dnf)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iiidiiii", $driver_id, $race_id, $finish_position, $points, $laps_completed, $pit_stops, $best_lap_time_ms, $dnf);
                if ($stmt->execute()) {
                    $new_statistic_id = $stmt->insert_id;
                    log_statistic_save($db, $new_statistic_id, 'statistic_created');
                    $stmt->close();
                    $db->close();
                    header("Location: statistic.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Statistic creation failed. Please check for duplicate driver/race or finish position values.";
        }
    }
}

if (is_league_director()) {
    $drivers = $db->query("SELECT drivers.id, drivers.first_name, drivers.last_name, teams.team_name
                           FROM drivers
                           JOIN teams ON teams.id = drivers.team_id
                           ORDER BY teams.team_name, drivers.last_name, drivers.first_name");
} else {
    $team_id = current_team_id();
    $drivers_stmt = $db->prepare("SELECT drivers.id, drivers.first_name, drivers.last_name, teams.team_name
                                  FROM drivers
                                  JOIN teams ON teams.id = drivers.team_id
                                  WHERE drivers.team_id = ?
                                  ORDER BY drivers.last_name, drivers.first_name");
    $drivers_stmt->bind_param("i", $team_id);
    $drivers_stmt->execute();
    $drivers = $drivers_stmt->get_result();
}

$races = $db->query("SELECT races.id, races.race_name, races.race_date, circuits.circuit_name
                     FROM races
                     JOIN circuits ON circuits.id = races.circuit_id
                     ORDER BY races.race_date DESC, races.race_name");
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php echo $is_edit ? 'Edit Statistic' : 'Add Statistic'; ?> - F1 Statistics</title>
        <style>
            .form-group { margin-bottom: 12px; }
            label { display: inline-block; width: 150px; }
        </style>
    </head>
    <body>
        <a href="statistic.php">Back to Statistics</a>
        <h1><?php echo $is_edit ? 'Edit Statistic' : 'Add Statistic'; ?></h1>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo encode_var($error); ?></p>
        <?php endif; ?>

        <form action="statistic_edit.php" method="POST">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo encode_var($statistic_id); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Driver:</label>
                <select name="driver_id" required>
                    <option value="">Select driver</option>
                    <?php while ($driver = $drivers->fetch_assoc()): ?>
                        <option value="<?php echo encode_var($driver['id']); ?>" <?php echo (int) $stat['driver_id'] === (int) $driver['id'] ? 'selected' : ''; ?>>
                            <?php echo encode_var($driver['team_name'] . ' - ' . $driver['first_name'] . ' ' . $driver['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Race:</label>
                <select name="race_id" required>
                    <option value="">Select race</option>
                    <?php while ($race = $races->fetch_assoc()): ?>
                        <option value="<?php echo encode_var($race['id']); ?>" <?php echo (int) $stat['race_id'] === (int) $race['id'] ? 'selected' : ''; ?>>
                            <?php echo encode_var($race['race_name'] . ' - ' . $race['race_date'] . ' - ' . $race['circuit_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Finish position:</label>
                <input type="number" name="finish_position" min="1" max="99" value="<?php echo encode_var($stat['finish_position']); ?>">
            </div>

            <div class="form-group">
                <label>Points:</label>
                <input type="number" name="points" min="0" max="100" step="0.01" value="<?php echo encode_var($stat['points']); ?>" required>
            </div>

            <div class="form-group">
                <label>Laps completed:</label>
                <input type="number" name="laps_completed" min="0" max="300" value="<?php echo encode_var($stat['laps_completed']); ?>" required>
            </div>

            <div class="form-group">
                <label>Pit stops:</label>
                <input type="number" name="pit_stops" min="0" max="20" value="<?php echo encode_var($stat['pit_stops']); ?>" required>
            </div>

            <div class="form-group">
                <label>Best lap time MS:</label>
                <input type="number" name="best_lap_time_ms" min="1" max="600000" value="<?php echo encode_var($stat['best_lap_time_ms']); ?>">
            </div>

            <div class="form-group">
                <label>DNF:</label>
                <input type="checkbox" name="dnf" value="1" <?php echo (int) $stat['dnf'] === 1 ? 'checked' : ''; ?>>
            </div>

            <button type="submit">Save Statistic</button>
        </form>
    </body>
</html>
<?php
if (isset($drivers_stmt)) {
    $drivers_stmt->close();
}
$db->close();
?>
