<?php
$pageTitle = 'Edit Circuit';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db        = getDB();
$errors    = [];
$circuitId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM circuits WHERE id = ?');
$stmt->execute([$circuitId]);
$circuit = $stmt->fetch();
if (!$circuit) { include __DIR__ . '/../includes/404.php'; exit; }

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
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || !$country || !$city || $lengthKm <= 0 || $laps <= 0) $errors[] = 'All required fields must be filled.';

        if ($name) {
            $chk = $db->prepare('SELECT id FROM circuits WHERE name = ? AND id != ?');
            $chk->execute([$name, $circuitId]);
            if ($chk->fetch()) $errors[] = "A circuit named '{$name}' already exists.";
        }

        if (empty($errors)) {
            $db->prepare("UPDATE circuits SET name=?,country=?,city=?,length_km=?,number_of_laps=?,circuit_type=?,lap_record_ms=?,lap_record_person_id=?,is_active=? WHERE id=?")
               ->execute([$name,$country,$city,$lengthKm,$laps,$type,$lapRecordMs,$recordHolder,$isActive,$circuitId]);
            logAudit($_SESSION['user_id'], 'update', 'circuits', $circuitId, $name);
            rotateCSRFToken();
            redirectWithMessage(APP_URL . '/admin/circuits.php', 'success', "Circuit '{$name}' updated.");
        }
    }
}

$csrfToken = generateCSRFToken();
$lapRecordS = $circuit['lap_record_ms'] ? round($circuit['lap_record_ms'] / 1000, 3) : '';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Circuit</h1>
        <p class="page-subtitle"><?= h($circuit['name']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/circuits.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-group">
            <label class="form-label required">Circuit Name</label>
            <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? $circuit['name']) ?>" required maxlength="100">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Country</label>
                <input type="text" name="country" class="form-control" value="<?= h($_POST['country'] ?? $circuit['country']) ?>" required maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label required">City</label>
                <input type="text" name="city" class="form-control" value="<?= h($_POST['city'] ?? $circuit['city']) ?>" required maxlength="50">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Length (km)</label>
                <input type="number" name="length_km" class="form-control" value="<?= h((string)($_POST['length_km'] ?? $circuit['length_km'])) ?>" required step="0.001" min="1">
            </div>
            <div class="form-group">
                <label class="form-label required">Laps</label>
                <input type="number" name="number_of_laps" class="form-control" value="<?= h((string)($_POST['number_of_laps'] ?? $circuit['number_of_laps'])) ?>" required min="1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Circuit Type</label>
                <select name="circuit_type" class="form-control">
                    <option value="permanent"<?= ($circuit['circuit_type'] === 'permanent') ? ' selected' : '' ?>>Permanent</option>
                    <option value="street"<?= ($circuit['circuit_type'] === 'street') ? ' selected' : '' ?>>Street</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Lap Record (seconds)</label>
                <input type="number" name="lap_record_s" class="form-control" value="<?= h((string)($_POST['lap_record_s'] ?? $lapRecordS)) ?>" step="0.001" min="50">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Lap Record Holder</label>
            <select name="lap_record_person_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach ($people as $p): ?>
                <option value="<?= h((string)$p['id']) ?>"<?= $circuit['lap_record_person_id'] == $p['id'] ? ' selected' : '' ?>><?= h($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                <input type="checkbox" name="is_active" value="1"<?= $circuit['is_active'] ? ' checked' : '' ?>>
                <span>Active circuit</span>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/circuits.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
