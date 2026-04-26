<?php
$pageTitle = 'Add Race';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();
$errors = [];

$seasons  = getSeasonList();
$circuits = $db->query("SELECT id, name, country FROM circuits WHERE is_active=1 ORDER BY name")->fetchAll();
// Map circuit id → name for JS auto-populate
$circuitNames = [];
foreach ($circuits as $c) { $circuitNames[$c['id']] = $c['name']; }

$defaultSeasonId = intval($_GET['season_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $seasonId      = intval($_POST['season_id'] ?? 0);
        $circuitId     = intval($_POST['circuit_id'] ?? 0);
        $name          = strip_tags(trim($_POST['name'] ?? ''));
        $roundNumber   = intval($_POST['round_number'] ?? 0);
        $raceDate      = $_POST['race_date'] ?? '';
        $qualDate      = $_POST['qualifying_date'] ?? '';
        $hasSprint     = isset($_POST['has_sprint']) ? 1 : 0;
        $sprintDate    = $_POST['sprint_date'] ?? '';

        if (!$seasonId)    $errors[] = 'Season is required.';
        if (!$circuitId)   $errors[] = 'Circuit is required.';
        if (!$name)        $errors[] = 'Race name is required.';
        if ($roundNumber < 1) $errors[] = 'Round number must be at least 1.';
        if (!$raceDate)    $errors[] = 'Race date is required.';
        if ($qualDate && $raceDate && $raceDate <= $qualDate) {
            $errors[] = 'Race date must be after qualifying date.';
        }

        if (empty($errors)) {
            $chk = $db->prepare('SELECT id FROM races WHERE season_id=? AND round_number=?');
            $chk->execute([$seasonId, $roundNumber]);
            if ($chk->fetch()) {
                $errors[] = "Round $roundNumber already exists for this season.";
            }
            if (empty($errors) && $raceDate) {
                $chkDate = $db->prepare('SELECT id FROM races WHERE season_id=? AND race_date=?');
                $chkDate->execute([$seasonId, $raceDate]);
                if ($chkDate->fetch()) $errors[] = 'Another race in this season already has that date.';
            }
            if (empty($errors)) {
                $db->prepare("INSERT INTO races (season_id,circuit_id,name,round_number,race_date,qualifying_date,has_sprint,sprint_date) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$seasonId,$circuitId,$name,$roundNumber,$raceDate,$qualDate ?: null,$hasSprint,$hasSprint && $sprintDate ? $sprintDate : null]);
                $newId = $db->lastInsertId();
                logAudit($_SESSION['user_id'], 'create', 'races', (int)$newId, $name);
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/races.php?season=' . $seasonId, 'success', "Race '{$name}' created.");
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h1 class="page-title">Add Race</h1>
    <a href="<?= APP_URL ?>/admin/races.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post" id="race-form">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Season</label>
                <select name="season_id" class="form-control">
                    <?php foreach ($seasons as $s): ?>
                    <option value="<?= h((string)$s['id']) ?>"<?= ($s['id'] == ($defaultSeasonId ?: ($seasons[0]['id'] ?? 0))) ? ' selected' : '' ?>><?= h((string)$s['year']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label required">Round Number</label>
                <input type="number" name="round_number" class="form-control" value="<?= h($_POST['round_number'] ?? '') ?>" required min="1" max="30">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label required">Circuit</label>
            <select name="circuit_id" id="circuit_id" class="form-control" onchange="autoFillName(this)">
                <option value="">— Select Circuit —</option>
                <?php foreach ($circuits as $c): ?>
                <option value="<?= h((string)$c['id']) ?>"
                    data-name="<?= h($c['name']) ?>"
                    <?= ($_POST['circuit_id'] ?? '') == $c['id'] ? ' selected' : '' ?>>
                    <?= h($c['name']) ?> (<?= h($c['country']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Race Name</label>
            <input type="text" id="race_name" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required maxlength="100" placeholder="e.g. Bahrain Grand Prix">
            <div class="form-hint">Auto-filled from circuit — edit to override</div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Qualifying Date</label>
                <input type="date" name="qualifying_date" id="qual_date" class="form-control" value="<?= h($_POST['qualifying_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label required">Race Date</label>
                <input type="date" name="race_date" id="race_date" class="form-control" value="<?= h($_POST['race_date'] ?? '') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                <input type="checkbox" name="has_sprint" value="1" id="has_sprint"<?= isset($_POST['has_sprint']) ? ' checked' : '' ?> onchange="toggleSprintDate(this.checked)">
                <span>This weekend has a Sprint Race</span>
            </label>
        </div>
        <div class="form-group" id="sprint-date-group" style="<?= isset($_POST['has_sprint']) ? '' : 'display:none' ?>">
            <label class="form-label">Sprint Date</label>
            <input type="date" name="sprint_date" class="form-control" value="<?= h($_POST['sprint_date'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Race</button>
            <a href="<?= APP_URL ?>/admin/races.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
function autoFillName(sel) {
    const opt = sel.options[sel.selectedIndex];
    const nameField = document.getElementById('race_name');
    if (nameField.value === '' || nameField.dataset.autofilled === '1') {
        if (opt.dataset.name) {
            nameField.value = opt.dataset.name + ' Grand Prix';
            nameField.dataset.autofilled = '1';
        }
    }
}
document.getElementById('race_name').addEventListener('input', function() {
    this.dataset.autofilled = '0';
});
function toggleSprintDate(show) {
    document.getElementById('sprint-date-group').style.display = show ? '' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
