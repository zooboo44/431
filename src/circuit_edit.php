<?php
require_once __DIR__ . '/functions/auth_fns.php';
require_once __DIR__ . '/functions/output_fns.php';

require_login();
require_role('league_director');

$db = db_connect();
$error = "";
$circuit_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$circuit = [
    'id' => '',
    'circuit_name' => '',
    'location' => '',
    'country' => '',
    'length_km' => ''
];

function log_circuit_save($db, $circuit_id, $action, $details = '') {
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

if ($circuit_id > 0) {
    $stmt = $db->prepare("SELECT id, circuit_name, location, country, length_km FROM circuits WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $circuit_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $circuit = $existing;
        } elseif ($_SERVER['REQUEST_METHOD'] !== "POST") {
            $db->close();
            die("Circuit not found.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $circuit_name = trim($_POST['circuit_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $length_km_raw = trim($_POST['length_km'] ?? '');
    $length_km = is_numeric($length_km_raw) ? (float) $length_km_raw : 0;

    $circuit = [
        'id' => $circuit_id,
        'circuit_name' => $circuit_name,
        'location' => $location,
        'country' => $country,
        'length_km' => $length_km_raw
    ];

    if ($circuit_name === '') {
        $error = "Circuit name is required.";
    } elseif ($location === '') {
        $error = "Location is required.";
    } elseif ($country === '') {
        $error = "Country is required.";
    } elseif (!is_numeric($length_km_raw) || $length_km <= 0) {
        $error = "Length KM must be a number greater than 0.";
    } elseif ($length_km > 30) {
        $error = "Length KM must be 30 or less.";
    }

    if (empty($error)) {
        if ($circuit_id > 0) {
            $stmt = $db->prepare("UPDATE circuits
                                  SET circuit_name = ?, location = ?, country = ?, length_km = ?
                                  WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sssdi", $circuit_name, $location, $country, $length_km, $circuit_id);
                if ($stmt->execute()) {
                    log_circuit_save($db, $circuit_id, 'circuit_updated');
                    $stmt->close();
                    $db->close();
                    header("Location: circuits.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Circuit update failed.";
        } else {
            $stmt = $db->prepare("INSERT INTO circuits (circuit_name, location, country, length_km)
                                  VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssd", $circuit_name, $location, $country, $length_km);
                if ($stmt->execute()) {
                    $new_circuit_id = $stmt->insert_id;
                    log_circuit_save($db, $new_circuit_id, 'circuit_created');
                    $stmt->close();
                    $db->close();
                    header("Location: circuits.php");
                    exit();
                }
                $stmt->close();
            }
            $error = "Circuit creation failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php echo $circuit_id > 0 ? 'Edit Circuit' : 'Add Circuit'; ?> - F1 Statistics</title>
        <style>
            .form-group { margin-bottom: 12px; }
            label { display: inline-block; width: 130px; }
        </style>
    </head>
    <body>
        <a href="circuits.php">Back to Circuits</a>
        <h1><?php echo $circuit_id > 0 ? 'Edit Circuit' : 'Add Circuit'; ?></h1>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo e($error); ?></p>
        <?php endif; ?>

        <form action="circuit_edit.php" method="POST">
            <?php if ($circuit_id > 0): ?>
                <input type="hidden" name="id" value="<?php echo e($circuit_id); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Circuit name:</label>
                <input type="text" name="circuit_name" value="<?php echo e($circuit['circuit_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Location:</label>
                <input type="text" name="location" value="<?php echo e($circuit['location']); ?>" required>
            </div>

            <div class="form-group">
                <label>Country:</label>
                <input type="text" name="country" value="<?php echo e($circuit['country']); ?>" required>
            </div>

            <div class="form-group">
                <label>Length KM:</label>
                <input type="number" name="length_km" min="0.001" max="30" step="0.001" value="<?php echo e($circuit['length_km']); ?>" required>
            </div>

            <button type="submit">Save Circuit</button>
        </form>
    </body>
</html>
<?php
$db->close();
?>
