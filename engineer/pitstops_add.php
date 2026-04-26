<?php
$pageTitle = 'Add Pit Stop';
require_once __DIR__ . '/../includes/header.php';
requireRole('engineer');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);
$errors = [];

// Completed races for this team
$raceStmt = $db->prepare("
    SELECT r.id, r.name, r.round_number, s.year
    FROM races r
    JOIN seasons s ON s.id = r.season_id
    JOIN team_seasons ts ON ts.season_id = r.season_id AND ts.team_id = ?
    WHERE r.status = 'completed'
    ORDER BY r.race_date DESC
");
$raceStmt->execute([$teamId]);
$races = $raceStmt->fetchAll();

// Drivers for this team
$driverStmt = $db->prepare("
    SELECT DISTINCT p.id, p.first_name, p.last_name, p.racing_number
    FROM people p
    JOIN driver_seasons drs ON drs.person_id = p.id
    JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
    ORDER BY p.last_name
");
$driverStmt->execute([$teamId]);
$drivers = $driverStmt->fetchAll();

$preRaceId   = intval($_GET['race_id'] ?? 0);
$prePersonId = intval($_GET['person_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pitstop'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $raceId   = intval($_POST['race_id'] ?? 0);
        $personId = intval($_POST['person_id'] ?? 0);
        $stopNum  = intval($_POST['stop_number'] ?? 0);
        $lapNum   = intval($_POST['lap_number'] ?? 0);
        $durS     = trim($_POST['duration_s'] ?? '');
        $tyreIn   = trim($_POST['tyre_in'] ?? '') ?: null;
        $tyreOut  = trim($_POST['tyre_out'] ?? '') ?: null;

        if (!$raceId)  $errors[] = 'Race is required.';
        if (!$personId) $errors[] = 'Driver is required.';
        if ($stopNum < 1) $errors[] = 'Stop number must be at least 1.';
        if ($lapNum < 1)  $errors[] = 'Lap number must be at least 1.';

        if (empty($errors)) {
            // IDOR: verify race_entry belongs to engineer's team
            $check = $db->prepare("
                SELECT re.id FROM race_entries re
                JOIN team_seasons ts ON ts.id = re.team_season_id AND ts.team_id = ?
                WHERE re.race_id = ? AND re.person_id = ?
                LIMIT 1
            ");
            $check->execute([$teamId, $raceId, $personId]);
            $entryRow = $check->fetch();

            if (!$entryRow) {
                $errors[] = 'This driver/race combination does not belong to your team.';
            } else {
                $entryId = $entryRow['id'];
                $durMs   = $durS !== '' && is_numeric($durS) ? (int)round((float)$durS * 1000) : null;

                try {
                    $db->prepare("
                        INSERT INTO pit_stops (race_entry_id, stop_number, lap_number, duration_ms, tyre_in, tyre_out)
                        VALUES (?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE
                            lap_number=VALUES(lap_number), duration_ms=VALUES(duration_ms),
                            tyre_in=VALUES(tyre_in), tyre_out=VALUES(tyre_out)
                    ")->execute([$entryId, $stopNum, $lapNum, $durMs, $tyreIn, $tyreOut]);

                    logAudit($_SESSION['user_id'], 'create', 'pit_stops', null,
                        "Entry: $entryId Stop: $stopNum Lap: $lapNum");
                    rotateCSRFToken();
                    redirectWithMessage(
                        APP_URL . '/engineer/pitstops.php',
                        'success', 'Pit stop saved.'
                    );
                } catch (\PDOException $e) {
                    $errors[] = 'Could not save pit stop. Check for duplicate stop number.';
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Add Pit Stop</h1>
        <p class="page-subtitle">Record a pit stop for a driver</p>
    </div>
    <a href="<?= APP_URL ?>/engineer/pitstops.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="card" style="max-width:540px">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <div class="form-group">
            <label class="form-label required">Race</label>
            <select name="race_id" class="form-control" required>
                <option value="">— Select Race —</option>
                <?php foreach ($races as $r): ?>
                <option value="<?= h((string)$r['id']) ?>"<?= ($preRaceId && $preRaceId == $r['id']) || (!$preRaceId && isset($_POST['race_id']) && $_POST['race_id'] == $r['id']) ? ' selected' : '' ?>>
                    <?= h((string)$r['year']) ?> Rd <?= h((string)$r['round_number']) ?> — <?= h($r['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label required">Driver</label>
            <select name="person_id" class="form-control" required>
                <option value="">— Select Driver —</option>
                <?php foreach ($drivers as $d): ?>
                <option value="<?= h((string)$d['id']) ?>"<?= ($prePersonId && $prePersonId == $d['id']) || (!$prePersonId && isset($_POST['person_id']) && $_POST['person_id'] == $d['id']) ? ' selected' : '' ?>>
                    #<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group">
                <label class="form-label required">Stop Number</label>
                <input type="number" name="stop_number" class="form-control" min="1" max="10" required
                       value="<?= h((string)intval($_POST['stop_number'] ?? 1)) ?>">
            </div>
            <div class="form-group">
                <label class="form-label required">Lap Number</label>
                <input type="number" name="lap_number" class="form-control" min="1" max="100" required
                       value="<?= h((string)intval($_POST['lap_number'] ?? '')) ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Duration (seconds)</label>
            <input type="number" name="duration_s" class="form-control" step="0.001" min="0"
                   placeholder="e.g. 2.485"
                   value="<?= h($_POST['duration_s'] ?? '') ?>">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group">
                <label class="form-label">Tyre In</label>
                <select name="tyre_in" class="form-control">
                    <option value="">— Unknown —</option>
                    <?php foreach (['soft' => 'Soft','medium' => 'Medium','hard' => 'Hard','intermediate' => 'Inter','wet' => 'Wet'] as $val => $lbl): ?>
                    <option value="<?= h($val) ?>"<?= (($_POST['tyre_in'] ?? '') === $val) ? ' selected' : '' ?>><?= h($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tyre Out</label>
                <select name="tyre_out" class="form-control">
                    <option value="">— Unknown —</option>
                    <?php foreach (['soft' => 'Soft','medium' => 'Medium','hard' => 'Hard','intermediate' => 'Inter','wet' => 'Wet'] as $val => $lbl): ?>
                    <option value="<?= h($val) ?>"<?= (($_POST['tyre_out'] ?? '') === $val) ? ' selected' : '' ?>><?= h($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit" name="add_pitstop" class="btn btn-primary">Save Pit Stop</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
