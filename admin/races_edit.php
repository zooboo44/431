<?php
$pageTitle = 'Edit Race';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];
$raceId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM races WHERE id = ?');
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) { include __DIR__ . '/../includes/404.php'; exit; }

$circuits = $db->query("SELECT id, name, country FROM circuits WHERE is_active=1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $circuitId   = intval($_POST['circuit_id'] ?? 0);
        $name        = strip_tags(trim($_POST['name'] ?? ''));
        $roundNumber = intval($_POST['round_number'] ?? 0);
        $raceDate    = $_POST['race_date'] ?? '';
        $qualDate    = $_POST['qualifying_date'] ?? '';
        $hasSprint   = isset($_POST['has_sprint']) ? 1 : 0;
        $sprintDate  = $_POST['sprint_date'] ?? '';
        $status      = $_POST['status'] ?? 'scheduled';

        $validStatuses = ['scheduled','in_progress','completed','cancelled'];
        if (!in_array($status, $validStatuses)) $status = 'scheduled';

        if (!$name || !$circuitId || !$raceDate || $roundNumber < 1) $errors[] = 'Required fields missing.';
        if ($qualDate && $raceDate && $raceDate <= $qualDate) $errors[] = 'Race date must be after qualifying date.';

        if (empty($errors) && $raceDate) {
            $chkDate = $db->prepare('SELECT id FROM races WHERE season_id=? AND race_date=? AND id!=?');
            $chkDate->execute([$race['season_id'], $raceDate, $raceId]);
            if ($chkDate->fetch()) $errors[] = 'Another race in this season already has that date.';
        }
        if (empty($errors)) {
            $db->prepare("UPDATE races SET circuit_id=?,name=?,round_number=?,race_date=?,qualifying_date=?,has_sprint=?,sprint_date=?,status=? WHERE id=?")
               ->execute([$circuitId,$name,$roundNumber,$raceDate,$qualDate ?: null,$hasSprint,$hasSprint && $sprintDate ? $sprintDate : null,$status,$raceId]);
            logAudit($_SESSION['user_id'], 'update', 'races', $raceId, $name);
            rotateCSRFToken();
            redirectWithMessage(APP_URL . '/admin/races.php?season=' . $race['season_id'], 'success', "Race '{$name}' updated.");
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Race</h1>
        <p class="page-subtitle"><?= h($race['name']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/races.php?season=<?= $race['season_id'] ?>" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Race Name</label>
                <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? $race['name']) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label required">Round Number</label>
                <input type="number" name="round_number" class="form-control" value="<?= h((string)($_POST['round_number'] ?? $race['round_number'])) ?>" required min="1" max="30">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label required">Circuit</label>
            <select name="circuit_id" class="form-control">
                <?php foreach ($circuits as $c): ?>
                <option value="<?= h((string)$c['id']) ?>"<?= $race['circuit_id'] == $c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?> (<?= h($c['country']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Race Date</label>
                <input type="date" name="race_date" class="form-control" value="<?= h($_POST['race_date'] ?? $race['race_date']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Qualifying Date</label>
                <input type="date" name="qualifying_date" class="form-control" value="<?= h($_POST['qualifying_date'] ?? ($race['qualifying_date'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <?php foreach (['scheduled','in_progress','completed','cancelled'] as $s): ?>
                    <option value="<?= h($s) ?>"<?= $race['status'] === $s ? ' selected' : '' ?>><?= h(ucfirst(str_replace('_',' ',$s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Sprint</label>
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-top:0.5rem">
                    <input type="checkbox" name="has_sprint" value="1" id="has_sprint"<?= $race['has_sprint'] ? ' checked' : '' ?> onchange="toggleSprintDate(this.checked)">
                    <span>Has Sprint Race</span>
                </label>
                <div id="sprint-date-group" style="<?= $race['has_sprint'] ? '' : 'display:none' ?>;margin-top:0.5rem">
                    <input type="date" name="sprint_date" class="form-control" value="<?= h($_POST['sprint_date'] ?? ($race['sprint_date'] ?? '')) ?>">
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/races.php?season=<?= $race['season_id'] ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
function toggleSprintDate(show) {
    document.getElementById('sprint-date-group').style.display = show ? '' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
