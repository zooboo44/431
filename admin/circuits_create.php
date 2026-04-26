<?php
$pageTitle = 'Add Circuit';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();
$errors = [];
$people = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM people WHERE is_active=1 ORDER BY last_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $name        = strip_tags(trim($_POST['name'] ?? ''));
        $country     = strip_tags(trim($_POST['country'] ?? ''));
        $city        = strip_tags(trim($_POST['city'] ?? ''));
        $lengthKm    = floatval($_POST['length_km'] ?? 0);
        $laps        = intval($_POST['number_of_laps'] ?? 0);
        $type        = $_POST['circuit_type'] ?? '';
        $lapRecordS  = trim($_POST['lap_record_s'] ?? '');
        $lapRecordMs = $lapRecordS !== '' ? (int)round(floatval($lapRecordS) * 1000) : null;
        $recordHolder= intval($_POST['lap_record_person_id'] ?? 0) ?: null;

        if (!$name)    $errors[] = 'Circuit name is required.';
        if (!$country) $errors[] = 'Country is required.';
        if (!$city)    $errors[] = 'City is required.';
        if ($lengthKm <= 0) $errors[] = 'Valid circuit length is required.';
        if ($laps <= 0)     $errors[] = 'Number of laps is required.';
        if (!in_array($type, ['permanent','street'])) $errors[] = 'Circuit type must be permanent or street.';

        if (empty($errors)) {
            $chk = $db->prepare('SELECT id FROM circuits WHERE name = ?');
            $chk->execute([$name]);
            if ($chk->fetch()) {
                $errors[] = "A circuit named '{$name}' already exists.";
            } else {
                $db->prepare("INSERT INTO circuits (name,country,city,length_km,number_of_laps,circuit_type,lap_record_ms,lap_record_person_id) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$name,$country,$city,$lengthKm,$laps,$type,$lapRecordMs,$recordHolder]);
                $newId = $db->lastInsertId();
                logAudit($_SESSION['user_id'], 'create', 'circuits', (int)$newId, $name);
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/circuits.php', 'success', "Circuit '{$name}' created.");
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h1 class="page-title">Add Circuit</h1>
    <a href="<?= APP_URL ?>/admin/circuits.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-group">
            <label class="form-label required" for="name">Circuit Name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required maxlength="100">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="country">Country</label>
                <input type="text" id="country" name="country" class="form-control" value="<?= h($_POST['country'] ?? '') ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required" for="city">City</label>
                <input type="text" id="city" name="city" class="form-control" value="<?= h($_POST['city'] ?? '') ?>" required maxlength="50">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="length_km">Length (km)</label>
                <input type="number" id="length_km" name="length_km" class="form-control" value="<?= h($_POST['length_km'] ?? '') ?>" required step="0.001" min="1" max="20">
            </div>
            <div class="form-group">
                <label class="form-label required" for="number_of_laps">Number of Laps</label>
                <input type="number" id="number_of_laps" name="number_of_laps" class="form-control" value="<?= h($_POST['number_of_laps'] ?? '') ?>" required min="1" max="100">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="circuit_type">Circuit Type</label>
                <select id="circuit_type" name="circuit_type" class="form-control">
                    <option value="permanent"<?= ($_POST['circuit_type'] ?? '') === 'permanent' ? ' selected' : '' ?>>Permanent</option>
                    <option value="street"<?= ($_POST['circuit_type'] ?? '') === 'street' ? ' selected' : '' ?>>Street</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="lap_record_s">Lap Record (seconds, e.g. 91.447)</label>
                <input type="number" id="lap_record_s" name="lap_record_s" class="form-control" value="<?= h($_POST['lap_record_s'] ?? '') ?>" step="0.001" min="50">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="lap_record_person_id">Lap Record Holder</label>
            <select id="lap_record_person_id" name="lap_record_person_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach ($people as $p): ?>
                <option value="<?= h((string)$p['id']) ?>"><?= h($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Circuit</button>
            <a href="<?= APP_URL ?>/admin/circuits.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
