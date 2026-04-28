<?php
$pageTitle = 'Add Telemetry';
require_once __DIR__ . '/../includes/header.php';
requireRole('engineer');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);
$errors = [];

// Races for this team
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_telemetry'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $raceId   = intval($_POST['race_id'] ?? 0);
        $personId = intval($_POST['person_id'] ?? 0);
        $lapNum   = intval($_POST['lap_number'] ?? 0);
        $lapTimeS = trim($_POST['lap_time_s'] ?? '');
        $s1       = trim($_POST['sector1_s'] ?? '');
        $s2       = trim($_POST['sector2_s'] ?? '');
        $s3       = trim($_POST['sector3_s'] ?? '');
        $speed    = trim($_POST['speed_trap_kmh'] ?? '');
        $tyre     = trim($_POST['tyre_compound'] ?? '');
        $tyreAge  = trim($_POST['tyre_age_laps'] ?? '');
        $isPit    = isset($_POST['is_pit_lap']) ? 1 : 0;

        if (!$raceId)    $errors[] = 'Race is required.';
        if (!$personId)  $errors[] = 'Driver is required.';
        if ($lapNum < 1) $errors[] = 'Lap number must be at least 1.';
        if ($lapTimeS === '' || !is_numeric($lapTimeS) || (float)$lapTimeS <= 0) {
            $errors[] = 'Valid lap time (seconds) is required.';
        }

        if (empty($errors)) {
            // IDOR: verify this race_entry belongs to engineer's team
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
                $entryId    = $entryRow['id'];
                $lapTimeMs  = (int)round((float)$lapTimeS * 1000);
                $s1Ms       = $s1 !== '' && is_numeric($s1)     ? (int)round((float)$s1 * 1000)       : null;
                $s2Ms       = $s2 !== '' && is_numeric($s2)     ? (int)round((float)$s2 * 1000)       : null;
                $s3Ms       = $s3 !== '' && is_numeric($s3)     ? (int)round((float)$s3 * 1000)       : null;
                $speedVal   = $speed !== '' && is_numeric($speed) ? (float)$speed                      : null;
                $tyreVal    = $tyre !== '' ? $tyre               : null;
                $tyreAgeVal = $tyreAge !== '' && is_numeric($tyreAge) ? intval($tyreAge)               : null;

                $db->prepare("
                    INSERT INTO lap_telemetry
                        (race_entry_id, lap_number, lap_time_ms, sector1_ms, sector2_ms, sector3_ms,
                         speed_trap_kmh, tyre_compound, tyre_age_laps, is_pit_lap)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        lap_time_ms=VALUES(lap_time_ms), sector1_ms=VALUES(sector1_ms),
                        sector2_ms=VALUES(sector2_ms),   sector3_ms=VALUES(sector3_ms),
                        speed_trap_kmh=VALUES(speed_trap_kmh), tyre_compound=VALUES(tyre_compound),
                        tyre_age_laps=VALUES(tyre_age_laps),   is_pit_lap=VALUES(is_pit_lap)
                ")->execute([$entryId, $lapNum, $lapTimeMs, $s1Ms, $s2Ms, $s3Ms, $speedVal, $tyreVal, $tyreAgeVal, $isPit]);

                logAudit($_SESSION['user_id'], 'create', 'lap_telemetry', null,
                    "Entry: $entryId Lap: $lapNum");
                rotateCSRFToken();
                redirectWithMessage(
                    APP_URL . '/engineer/telemetry.php',
                    'success', 'Lap telemetry saved.'
                );
            }
        }
    }
}

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Add Lap Telemetry</h1>
        <p class="page-subtitle">Record lap data for your drivers</p>
    </div>
    <a href="<?= APP_URL ?>/engineer/telemetry.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="card" style="max-width:640px">
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
                <label class="form-label required">Lap Number</label>
                <input type="number" name="lap_number" class="form-control" min="1" max="100" required
                       value="<?= h((string)intval($_POST['lap_number'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label required">Lap Time (seconds)</label>
                <input type="number" name="lap_time_s" class="form-control" step="0.001" min="0.001" required
                       placeholder="e.g. 83.456"
                       value="<?= h($_POST['lap_time_s'] ?? '') ?>">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
            <div class="form-group">
                <label class="form-label">Sector 1 (s)</label>
                <input type="number" name="sector1_s" class="form-control" step="0.001" min="0"
                       placeholder="e.g. 27.1"
                       value="<?= h($_POST['sector1_s'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Sector 2 (s)</label>
                <input type="number" name="sector2_s" class="form-control" step="0.001" min="0"
                       placeholder="e.g. 28.9"
                       value="<?= h($_POST['sector2_s'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Sector 3 (s)</label>
                <input type="number" name="sector3_s" class="form-control" step="0.001" min="0"
                       placeholder="e.g. 27.4"
                       value="<?= h($_POST['sector3_s'] ?? '') ?>">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group">
                <label class="form-label">Speed Trap (km/h)</label>
                <input type="number" name="speed_trap_kmh" class="form-control" step="0.1" min="0" max="400"
                       placeholder="e.g. 315.2"
                       value="<?= h($_POST['speed_trap_kmh'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Tyre Age (laps)</label>
                <input type="number" name="tyre_age_laps" class="form-control" min="0" max="100"
                       placeholder="e.g. 12"
                       value="<?= h($_POST['tyre_age_laps'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Tyre Compound</label>
            <select name="tyre_compound" class="form-control">
                <option value="">— Unknown —</option>
                <?php foreach (['soft' => 'Soft','medium' => 'Medium','hard' => 'Hard','intermediate' => 'Inter','wet' => 'Wet'] as $val => $lbl): ?>
                <option value="<?= h($val) ?>"<?= (($_POST['tyre_compound'] ?? '') === $val) ? ' selected' : '' ?>><?= h($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                <input type="checkbox" name="is_pit_lap" value="1"<?= isset($_POST['is_pit_lap']) ? ' checked' : '' ?>>
                <span>Pit stop on this lap</span>
            </label>
        </div>

        <button type="submit" name="add_telemetry" class="btn btn-primary">Save Lap Data</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
