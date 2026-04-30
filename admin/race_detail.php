<?php
$_action = $_GET['action'] ?? '';
$_titles = ['entries'=>'Race Entries','qualifying'=>'Qualifying Results','results'=>'Race Results Entry','sprint'=>'Sprint Results Entry'];
$pageTitle = $_titles[$_action] ?? 'Race Detail';

require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];
$raceId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT r.*, c.name AS circuit_name, c.city, c.country, c.length_km, c.number_of_laps,
           s.year AS season_year, s.id AS season_id
    FROM races r
    JOIN circuits c ON c.id = r.circuit_id
    JOIN seasons s ON s.id = r.season_id
    WHERE r.id = ?
");
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) { include __DIR__ . '/../includes/404.php'; exit; }

$csrfToken = generateCSRFToken();

// ─── ENTRIES SUB-VIEW ─────────────────────────────────────────────────────────
if ($_action === 'entries') {
    $stmt = $db->prepare("
        SELECT re.id, re.person_id, re.team_season_id,
               p.first_name, p.last_name, p.racing_number, t.name AS team_name
        FROM race_entries re
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE re.race_id = ?
        ORDER BY p.racing_number
    ");
    $stmt->execute([$raceId]);
    $entries = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT ds.person_id, ds.team_season_id,
               p.first_name, p.last_name, p.racing_number, t.name AS team_name
        FROM driver_seasons ds
        JOIN people p ON p.id = ds.person_id
        JOIN team_seasons ts ON ts.id = ds.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE ds.season_id = ?
          AND ds.status = 'active'
          AND ds.person_id NOT IN (SELECT person_id FROM race_entries WHERE race_id = ?)
          AND ds.id = (
              SELECT MAX(ds2.id) FROM driver_seasons ds2
              WHERE ds2.person_id = ds.person_id AND ds2.season_id = ? AND ds2.status = 'active'
          )
        ORDER BY p.racing_number
    ");
    $stmt->execute([$race['season_id'], $raceId, $race['season_id']]);
    $availableDrivers = $stmt->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid token.'; } else {
            $personId    = intval($_POST['person_id'] ?? 0);
            $teamSeasonId= intval($_POST['team_season_id'] ?? 0);
            if (!$personId || !$teamSeasonId) { $errors[] = 'Driver and team are required.'; } else {
                $chk = $db->prepare('SELECT id FROM race_entries WHERE race_id=? AND person_id=?');
                $chk->execute([$raceId, $personId]);
                if ($chk->fetch()) { $errors[] = 'This driver already has an entry for this race.'; } else {
                    $db->prepare("INSERT INTO race_entries (race_id,person_id,team_season_id) VALUES (?,?,?)")->execute([$raceId,$personId,$teamSeasonId]);
                    logAudit($_SESSION['user_id'], 'create', 'race_entries', null, "Race $raceId, person $personId");
                    rotateCSRFToken();
                    redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId . '&action=entries', 'success', 'Driver entry added.');
                }
            }
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_entry'])) {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid token.'; } else {
            $entryId = intval($_POST['entry_id'] ?? 0);
            $db->prepare('DELETE FROM race_entries WHERE id = ?')->execute([$entryId]);
            logAudit($_SESSION['user_id'], 'delete', 'race_entries', $entryId);
            rotateCSRFToken();
            redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId . '&action=entries', 'success', 'Entry removed.');
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_all'])) {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid token.'; } else {
            $added = 0;
            foreach ($availableDrivers as $d) {
                $chk = $db->prepare('SELECT id FROM race_entries WHERE race_id=? AND person_id=?');
                $chk->execute([$raceId, $d['person_id']]);
                if (!$chk->fetch()) {
                    $db->prepare("INSERT INTO race_entries (race_id,person_id,team_season_id) VALUES (?,?,?)")->execute([$raceId,$d['person_id'],$d['team_season_id']]);
                    $added++;
                }
            }
            logAudit($_SESSION['user_id'], 'create', 'race_entries', null, "Bulk added $added drivers for race $raceId");
            rotateCSRFToken();
            redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId . '&action=entries', 'success', "$added driver" . ($added != 1 ? 's' : '') . ' added.');
        }
    }

    renderFlash();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Race Entries</h1>
        <p class="page-subtitle"><?= h($race['name']) ?> — Round <?= h((string)$race['round_number']) ?> — <?= h(date('d M Y', strtotime($race['race_date']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>" class="btn btn-outline">&larr; Race Detail</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-title">Current Entries (<?= count($entries) ?>)</div>
        <?php if (empty($entries)): ?>
        <div class="empty-state"><p>No entries yet.</p></div>
        <?php else: ?>
        <?php foreach ($entries as $e): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--border)">
            <div>
                <strong>#<?= h((string)$e['racing_number']) ?> <?= h($e['first_name'] . ' ' . $e['last_name']) ?></strong>
                <div class="text-muted" style="font-size:0.8rem"><?= h($e['team_name']) ?></div>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                <input type="hidden" name="remove_entry" value="1">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove this entry? This will delete qualifying/race results too.">Remove</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div>
        <?php if ($availableDrivers): ?>
        <div class="card">
            <div class="card-title">Add Driver Entry</div>
            <form method="post" style="margin-bottom:1rem">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <div class="form-group">
                    <label class="form-label">Driver (Season Registered)</label>
                    <select name="person_id" class="form-control" onchange="updateTeam(this)">
                        <?php foreach ($availableDrivers as $d): ?>
                        <option value="<?= h((string)$d['person_id']) ?>" data-team="<?= h((string)$d['team_season_id']) ?>">
                            #<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?> — <?= h($d['team_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="team_season_id" id="team_season_id" value="<?= h((string)($availableDrivers[0]['team_season_id'] ?? '')) ?>">
                <button type="submit" name="add_entry" class="btn btn-primary">Add Entry</button>
            </form>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="add_all" value="1">
                <button type="submit" class="btn btn-secondary" data-confirm="Add all remaining season drivers to this race?">Add All Season Drivers</button>
            </form>
        </div>
        <?php else: ?>
        <div class="notice">All season-registered drivers have been entered for this race.</div>
        <?php endif; ?>

        <div class="card" style="margin-top:1rem">
            <div class="card-title">Next Steps</div>
            <div style="display:flex;flex-direction:column;gap:0.5rem">
                <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=qualifying" class="btn btn-outline">Enter Qualifying Results &rarr;</a>
                <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=results" class="btn btn-primary">Enter Race Results &rarr;</a>
                <?php if ($race['has_sprint']): ?>
                <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=sprint" class="btn btn-outline">Enter Sprint Results &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateTeam(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('team_season_id').value = opt.dataset.team;
}
</script>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── QUALIFYING SUB-VIEW ──────────────────────────────────────────────────────
if ($_action === 'qualifying') {
    $stmt = $db->prepare("
        SELECT re.id AS entry_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name,
               qr.id AS qual_id, qr.q1_time_ms, qr.q2_time_ms, qr.q3_time_ms, qr.grid_position, qr.eliminated_in
        FROM race_entries re
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
        WHERE re.race_id = ?
        ORDER BY COALESCE(qr.grid_position, 99), p.racing_number
    ");
    $stmt->execute([$raceId]);
    $entries = $stmt->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid token.'; } else {
            $db->beginTransaction();
            try {
                foreach ($entries as $e) {
                    $entryId = $e['entry_id'];
                    $gridPos = intval($_POST["grid_{$entryId}"] ?? 0);
                    $q1s     = trim($_POST["q1_{$entryId}"] ?? '');
                    $q2s     = trim($_POST["q2_{$entryId}"] ?? '');
                    $q3s     = trim($_POST["q3_{$entryId}"] ?? '');
                    $elim    = $_POST["elim_{$entryId}"] ?? '';
                    $q1ms    = $q1s !== '' ? (int)round(floatval($q1s) * 1000) : null;
                    $q2ms    = $q2s !== '' ? (int)round(floatval($q2s) * 1000) : null;
                    $q3ms    = $q3s !== '' ? (int)round(floatval($q3s) * 1000) : null;
                    $elimVal = in_array($elim, ['Q1','Q2']) ? $elim : null;
                    if ($gridPos < 1) continue;
                    if ($e['qual_id']) {
                        $db->prepare("UPDATE qualifying_results SET q1_time_ms=?,q2_time_ms=?,q3_time_ms=?,grid_position=?,eliminated_in=? WHERE id=?")
                           ->execute([$q1ms,$q2ms,$q3ms,$gridPos,$elimVal,$e['qual_id']]);
                    } else {
                        $db->prepare("INSERT INTO qualifying_results (race_entry_id,q1_time_ms,q2_time_ms,q3_time_ms,grid_position,eliminated_in) VALUES (?,?,?,?,?,?)")
                           ->execute([$entryId,$q1ms,$q2ms,$q3ms,$gridPos,$elimVal]);
                    }
                }
                $db->commit();
                logAudit($_SESSION['user_id'], 'update', 'qualifying_results', $raceId, "Race {$raceId}");
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId . '&action=qualifying', 'success', 'Qualifying results saved.');
            } catch (Exception $ex) {
                $db->rollBack();
                $errors[] = 'Save failed: ' . $ex->getMessage();
            }
        }
    }

    renderFlash();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Qualifying Results</h1>
        <p class="page-subtitle"><?= h($race['name']) ?> — <?= h(date('d M Y', strtotime($race['race_date']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>" class="btn btn-outline">&larr; Race Detail</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<?php if (empty($entries)): ?>
<div class="card">
    <div class="empty-state"><p>No race entries found. <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=entries">Add entries first.</a></p></div>
</div>
<?php else: ?>
<div class="notice">Enter lap times in seconds (e.g. 89.456). Grid position 1 = pole. Leave time blank if not set (DNS/Q1 out).</div>
<div class="card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="table-container" style="border:0;margin-bottom:0">
            <table>
                <thead><tr>
                    <th>Grid</th><th>Driver</th><th>Team</th>
                    <th>Q1 (s)</th><th>Q2 (s)</th><th>Q3 (s)</th><th>Eliminated In</th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td style="width:70px">
                        <input type="number" name="grid_<?= (int)$e['entry_id'] ?>" class="form-control" style="padding:0.3rem 0.5rem;width:65px"
                               value="<?= h((string)($e['grid_position'] ?? '')) ?>" min="1" max="<?= count($entries) ?>">
                    </td>
                    <td><strong>#<?= h((string)$e['racing_number']) ?> <?= h($e['first_name'] . ' ' . $e['last_name']) ?></strong></td>
                    <td class="text-muted"><?= h($e['team_name']) ?></td>
                    <td><input type="number" name="q1_<?= (int)$e['entry_id'] ?>" class="form-control" style="padding:0.3rem 0.5rem;width:100px"
                               value="<?= $e['q1_time_ms'] ? h((string)round($e['q1_time_ms']/1000, 3)) : '' ?>" step="0.001" min="50" placeholder="e.g. 89.456"></td>
                    <td><input type="number" name="q2_<?= (int)$e['entry_id'] ?>" class="form-control" style="padding:0.3rem 0.5rem;width:100px"
                               value="<?= $e['q2_time_ms'] ? h((string)round($e['q2_time_ms']/1000, 3)) : '' ?>" step="0.001" min="50"></td>
                    <td><input type="number" name="q3_<?= (int)$e['entry_id'] ?>" class="form-control" style="padding:0.3rem 0.5rem;width:100px"
                               value="<?= $e['q3_time_ms'] ? h((string)round($e['q3_time_ms']/1000, 3)) : '' ?>" step="0.001" min="50"></td>
                    <td>
                        <select name="elim_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:80px;padding:0.3rem 0.5rem">
                            <option value="">Q3</option>
                            <option value="Q2"<?= $e['eliminated_in'] === 'Q2' ? ' selected' : '' ?>>Q2</option>
                            <option value="Q1"<?= $e['eliminated_in'] === 'Q1' ? ' selected' : '' ?>>Q1</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Qualifying Results</button>
            <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=results" class="btn btn-outline">Go to Race Results &rarr;</a>
        </div>
    </form>
</div>
<?php endif; ?>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── RESULTS ENTRY SUB-VIEW ───────────────────────────────────────────────────
if ($_action === 'results') {
    $stmt = $db->prepare("
        SELECT re.id AS entry_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name,
               COALESCE(qr.grid_position, 0) AS grid_position,
               rr.id AS result_id, rr.finish_position, rr.start_position, rr.points_scored,
               rr.total_race_time_ms, rr.fastest_lap_ms, rr.fastest_lap_bonus, rr.laps_completed, rr.status
        FROM race_entries re
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
        LEFT JOIN race_results rr ON rr.race_entry_id = re.id AND rr.is_sprint = 0
        WHERE re.race_id = ?
        ORDER BY COALESCE(qr.grid_position, 99), p.racing_number
    ");
    $stmt->execute([$raceId]);
    $entries = $stmt->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid token.'; } else {
            $db->beginTransaction();
            try {
                $fastestLapEntryId = intval($_POST['fastest_lap_entry'] ?? 0) ?: null;
                foreach ($entries as $e) {
                    $entryId   = $e['entry_id'];
                    $finishPos = trim($_POST["finish_{$entryId}"] ?? '');
                    $startPos  = intval($_POST["start_{$entryId}"] ?? ($e['grid_position'] ?: 0));
                    $status    = $_POST["status_{$entryId}"] ?? 'finished';
                    $lapsComp  = intval($_POST["laps_{$entryId}"] ?? 0);
                    $flS       = trim($_POST["fl_{$entryId}"] ?? '');
                    $timeS     = trim($_POST["time_{$entryId}"] ?? '');

                    $finishPosVal = ($finishPos !== '' && $status === 'finished') ? intval($finishPos) : null;
                    $flMs         = $flS !== '' ? (int)round(floatval($flS) * 1000) : null;
                    $timeMs       = $timeS !== '' ? (int)round(floatval($timeS) * 1000) : null;

                    $isFastestLap = ($fastestLapEntryId === $entryId);
                    $inTopTen     = ($finishPosVal !== null && $finishPosVal <= 10);
                    $flBonus      = ($isFastestLap && $inTopTen) ? 1 : 0;

                    $points = 0.0;
                    if ($status === 'finished' && $finishPosVal !== null) {
                        $points = calculateRacePoints($finishPosVal, (bool)$flBonus, $inTopTen);
                    }

                    $validStatuses = ['finished','DNF','DNS','DSQ'];
                    if (!in_array($status, $validStatuses)) $status = 'finished';

                    if ($e['result_id']) {
                        $db->prepare("UPDATE race_results SET start_position=?,finish_position=?,points_scored=?,total_race_time_ms=?,fastest_lap_ms=?,fastest_lap_bonus=?,laps_completed=?,status=? WHERE id=? AND is_sprint=0")
                           ->execute([$startPos,$finishPosVal,$points,$timeMs,$flMs,$flBonus,$lapsComp,$status,$e['result_id']]);
                    } else {
                        $db->prepare("INSERT INTO race_results (race_entry_id,start_position,finish_position,points_scored,total_race_time_ms,fastest_lap_ms,fastest_lap_bonus,laps_completed,status) VALUES (?,?,?,?,?,?,?,?,?)")
                           ->execute([$entryId,$startPos,$finishPosVal,$points,$timeMs,$flMs,$flBonus,$lapsComp,$status]);
                    }
                }
                $db->prepare("UPDATE races SET status='completed' WHERE id=?")->execute([$raceId]);
                $db->commit();
                recalculateStandings($race['season_id']);
                logAudit($_SESSION['user_id'], 'update', 'results', $raceId, "Race {$raceId} results saved");
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId . '&action=results', 'success', 'Race results saved and standings updated.');
            } catch (Exception $ex) {
                $db->rollBack();
                $errors[] = 'Save failed: ' . $ex->getMessage();
            }
        }
    }

    renderFlash();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Race Results Entry</h1>
        <p class="page-subtitle"><?= h($race['name']) ?> — <?= h(date('d M Y', strtotime($race['race_date']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>" class="btn btn-outline">&larr; Race Detail</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<?php if (empty($entries)): ?>
<div class="card"><div class="empty-state"><p>No entries. <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=entries">Add entries first.</a></p></div></div>
<?php else: ?>
<div class="notice">
    Points auto-calculated by finishing position. Select the <strong>FL &#9889;</strong> radio button to designate the fastest lap holder — bonus point (+1) applies only if that driver finishes in the top 10.
    Enter lap times in seconds (e.g. 88.534). Total race time in seconds.
</div>
<div class="card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="table-container" style="border:0;margin-bottom:0;overflow-x:auto">
            <table>
                <thead><tr>
                    <th>Finish</th><th>Driver</th><th>Team</th><th>Start</th>
                    <th>Status</th><th>Laps</th><th>FL &#9889;</th><th>Fastest Lap (s)</th><th>Total Time (s)</th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td style="width:70px">
                        <input type="number" name="finish_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:65px;padding:0.3rem 0.4rem"
                               value="<?= $e['finish_position'] !== null ? h((string)$e['finish_position']) : '' ?>" min="1" max="<?= count($entries) ?>">
                    </td>
                    <td><strong>#<?= h((string)$e['racing_number']) ?> <?= h($e['first_name'] . ' ' . $e['last_name']) ?></strong></td>
                    <td class="text-muted"><?= h($e['team_name']) ?></td>
                    <td style="width:70px">
                        <input type="number" name="start_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:65px;padding:0.3rem 0.4rem"
                               value="<?= h((string)($e['start_position'] ?? $e['grid_position'] ?? '')) ?>" min="1" max="<?= count($entries) ?>">
                    </td>
                    <td>
                        <select name="status_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:90px;padding:0.3rem 0.4rem">
                            <?php foreach (['finished','DNF','DNS','DSQ'] as $s): ?>
                            <option value="<?= $s ?>"<?= ($e['status'] ?? 'finished') === $s ? ' selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="width:70px">
                        <input type="number" name="laps_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:65px;padding:0.3rem 0.4rem"
                               value="<?= h((string)($e['laps_completed'] ?? '')) ?>" min="0">
                    </td>
                    <td style="text-align:center;width:50px">
                        <input type="radio" name="fastest_lap_entry" value="<?= (int)$e['entry_id'] ?>"
                               title="Fastest lap holder"
                               <?= $e['fastest_lap_bonus'] ? 'checked' : '' ?>
                               style="width:18px;height:18px;cursor:pointer">
                    </td>
                    <td>
                        <input type="number" name="fl_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:100px;padding:0.3rem 0.4rem"
                               value="<?= $e['fastest_lap_ms'] ? h((string)round($e['fastest_lap_ms']/1000, 3)) : '' ?>" step="0.001" min="50" placeholder="e.g. 88.534">
                    </td>
                    <td>
                        <input type="number" name="time_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:110px;padding:0.3rem 0.4rem"
                               value="<?= $e['total_race_time_ms'] ? h((string)round($e['total_race_time_ms']/1000, 3)) : '' ?>" step="0.001" min="0">
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Results &amp; Update Standings</button>
            <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=qualifying" class="btn btn-outline">&larr; Qualifying</a>
            <a href="<?= APP_URL ?>/admin/penalties.php?race_id=<?= $raceId ?>" class="btn btn-secondary">Penalties</a>
        </div>
    </form>
</div>
<?php endif; ?>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── SPRINT SUB-VIEW ──────────────────────────────────────────────────────────
if ($_action === 'sprint') {
    if (!$race['has_sprint']) {
        redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'warning', 'That race has no sprint session.');
    }

    $stmt = $db->prepare("
        SELECT re.id AS entry_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name,
               rr.id AS sprint_id, rr.finish_position, rr.points_scored, rr.status
        FROM race_entries re
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        LEFT JOIN race_results rr ON rr.race_entry_id = re.id AND rr.is_sprint = 1
        WHERE re.race_id = ?
        ORDER BY COALESCE(rr.finish_position, 99), p.racing_number
    ");
    $stmt->execute([$raceId]);
    $entries = $stmt->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid token.'; } else {
            $db->beginTransaction();
            try {
                foreach ($entries as $e) {
                    $entryId   = $e['entry_id'];
                    $finishPos = trim($_POST["finish_{$entryId}"] ?? '');
                    $status    = $_POST["status_{$entryId}"] ?? 'finished';
                    $validStatuses = ['finished','DNF','DNS','DSQ'];
                    if (!in_array($status, $validStatuses)) $status = 'finished';
                    $finishPosVal = ($finishPos !== '' && $status === 'finished') ? intval($finishPos) : null;
                    $points = ($finishPosVal !== null) ? calculateSprintPoints($finishPosVal) : 0.0;
                    if ($e['sprint_id']) {
                        $db->prepare("UPDATE race_results SET finish_position=?,points_scored=?,status=? WHERE id=? AND is_sprint=1")
                           ->execute([$finishPosVal,$points,$status,$e['sprint_id']]);
                    } else {
                        $db->prepare("INSERT INTO race_results (race_entry_id,finish_position,points_scored,status,is_sprint,start_position,laps_completed) VALUES (?,?,?,?,1,0,0)")
                           ->execute([$entryId,$finishPosVal,$points,$status]);
                    }
                }
                $db->commit();
                recalculateStandings($race['season_id']);
                logAudit($_SESSION['user_id'], 'update', 'race_results', $raceId, 'Sprint results updated');
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId . '&action=sprint', 'success', 'Sprint results saved and standings updated.');
            } catch (Exception $ex) {
                $db->rollBack();
                $errors[] = 'Save failed: ' . $ex->getMessage();
            }
        }
    }

    renderFlash();
    ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Sprint Results Entry</h1>
        <p class="page-subtitle"><?= h($race['name']) ?> — Sprint</p>
    </div>
    <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>" class="btn btn-outline">&larr; Race Detail</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="notice">
    Sprint points: P1=8, P2=7, P3=6, P4=5, P5=4, P6=3, P7=2, P8=1. Points auto-calculated.
</div>

<div class="card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="table-container" style="border:0;margin-bottom:0">
            <table>
                <thead><tr>
                    <th>Finish Pos</th><th>Driver</th><th>Team</th><th>Status</th><th>Points</th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td style="width:80px">
                        <input type="number" name="finish_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:70px;padding:0.3rem 0.4rem"
                               value="<?= $e['finish_position'] !== null ? h((string)$e['finish_position']) : '' ?>" min="1" max="<?= count($entries) ?>">
                    </td>
                    <td><strong>#<?= h((string)$e['racing_number']) ?> <?= h($e['first_name'] . ' ' . $e['last_name']) ?></strong></td>
                    <td class="text-muted"><?= h($e['team_name']) ?></td>
                    <td>
                        <select name="status_<?= (int)$e['entry_id'] ?>" class="form-control" style="width:90px;padding:0.3rem 0.4rem">
                            <?php foreach (['finished','DNF','DNS','DSQ'] as $s): ?>
                            <option value="<?= $s ?>"<?= ($e['status'] ?? 'finished') === $s ? ' selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="text-accent"><?= $e['points_scored'] !== null ? h((string)$e['points_scored']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Sprint Results</button>
            <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=results" class="btn btn-outline">Race Results</a>
        </div>
    </form>
</div>

    <?php require_once __DIR__ . '/../includes/footer.php'; exit;
}

// ─── DEFAULT: RACE DETAIL VIEW ────────────────────────────────────────────────

// Handle penalty delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_penalty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'danger', 'Invalid token.');
    }
    $penId = intval($_POST['penalty_id'] ?? 0);
    $db->prepare('DELETE FROM penalties WHERE id = ?')->execute([$penId]);
    logAudit($_SESSION['user_id'], 'delete', 'penalties', $penId, "Deleted from race $raceId");
    rotateCSRFToken();
    redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'success', 'Penalty deleted.');
}

// Handle add penalty inline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_penalty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'danger', 'Invalid token.');
    }
    $personId   = intval($_POST['person_id'] ?? 0);
    $type       = $_POST['penalty_type'] ?? '';
    $reason     = strip_tags(trim($_POST['reason'] ?? ''));
    $timePenS   = intval($_POST['time_penalty_s'] ?? 0) ?: null;
    $gridPen    = intval($_POST['grid_penalty_positions'] ?? 0) ?: null;
    $licPoints  = intval($_POST['licence_points_awarded'] ?? 0) ?: null;
    $isDsq      = ($type === 'dsq') ? 1 : 0;

    if ($personId && $reason && in_array($type, ['time_penalty','grid_penalty','licence_points','dsq','warning'])) {
        $db->prepare("INSERT INTO penalties (race_id,person_id,issued_by,penalty_type,reason,time_penalty_s,grid_penalty_positions,licence_points_awarded,is_dsq) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$raceId,$personId,$_SESSION['user_id'],$type,$reason,$timePenS,$gridPen,$licPoints,$isDsq]);
        if ($isDsq) {
            $db->prepare("
                UPDATE race_results rr
                JOIN race_entries re ON re.id = rr.race_entry_id
                SET rr.status='DSQ', rr.points_scored=0
                WHERE re.race_id=? AND re.person_id=?
            ")->execute([$raceId, $personId]);
            recalculateStandings($race['season_id']);
        }
        logAudit($_SESSION['user_id'], 'create', 'penalties', null, "Race $raceId, person $personId");
        rotateCSRFToken();
        redirectWithMessage(APP_URL . '/admin/race_detail.php?id=' . $raceId, 'success', 'Penalty issued.');
    }
}

// Race entries
$stmt = $db->prepare("
    SELECT re.id, p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name
    FROM race_entries re
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ?
    ORDER BY p.racing_number
");
$stmt->execute([$raceId]);
$entries = $stmt->fetchAll();

// Race results
$stmt = $db->prepare("
    SELECT rr.*, p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ? AND rr.is_sprint = 0
    ORDER BY COALESCE(rr.finish_position,99), p.racing_number
");
$stmt->execute([$raceId]);
$results = $stmt->fetchAll();

// Qualifying results
$stmt = $db->prepare("
    SELECT qr.*, p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.short_name
    FROM qualifying_results qr
    JOIN race_entries re ON re.id = qr.race_entry_id
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ?
    ORDER BY qr.grid_position ASC
");
$stmt->execute([$raceId]);
$qualifying = $stmt->fetchAll();

// Sprint results
$sprintResults = [];
if ($race['has_sprint']) {
    $stmt = $db->prepare("
        SELECT rr.id, rr.finish_position, rr.points_scored, rr.status,
               p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE re.race_id = ? AND rr.is_sprint = 1
        ORDER BY COALESCE(rr.finish_position,99)
    ");
    $stmt->execute([$raceId]);
    $sprintResults = $stmt->fetchAll();
}

// Penalties
$stmt = $db->prepare("
    SELECT pen.*, p.first_name, p.last_name, p.racing_number, u.name AS issued_by_name
    FROM penalties pen
    JOIN people p ON p.id = pen.person_id
    JOIN users u ON u.id = pen.issued_by
    WHERE pen.race_id = ?
    ORDER BY pen.issued_at DESC
");
$stmt->execute([$raceId]);
$penalties = $stmt->fetchAll();

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Rd <?= h((string)$race['round_number']) ?> — <?= h($race['name']) ?></h1>
        <p class="page-subtitle"><?= h($race['circuit_name']) ?> &bull; <?= h($race['city']) ?>, <?= h($race['country']) ?> &bull; <?= h(date('d M Y', strtotime($race['race_date']))) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/races.php?action=edit&id=<?= $raceId ?>" class="btn btn-outline">Edit Race</a>
        <a href="<?= APP_URL ?>/admin/races.php?season=<?= $race['season_id'] ?>" class="btn btn-outline">&larr; Races</a>
    </div>
</div>

<!-- Info bar -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-value"><span class="status-badge status-<?= h($race['status']) ?>"><?= h(ucfirst(str_replace('_',' ',$race['status']))) ?></span></div>
        <div class="stat-label">Status</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$race['season_year']) ?></div>
        <div class="stat-label">Season</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$race['number_of_laps']) ?></div>
        <div class="stat-label">Laps</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($entries) ?></div>
        <div class="stat-label">Entries</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $race['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></div>
        <div class="stat-label">Sprint</div>
    </div>
</div>

<!-- Quick links -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
    <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=entries" class="btn btn-outline">Manage Entries</a>
    <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=qualifying" class="btn btn-outline">Enter Qualifying</a>
    <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=results" class="btn btn-primary">Enter Results</a>
    <?php if ($race['has_sprint']): ?>
    <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=sprint" class="btn btn-outline">Sprint Results</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/shared/results.php?id=<?= $raceId ?>" class="btn btn-secondary" target="_blank">Public View</a>
</div>

<div class="grid-2">
<!-- Race Entries -->
<div class="card">
    <div class="card-title">Race Entries (<?= count($entries) ?>)</div>
    <?php if (empty($entries)): ?>
    <div class="empty-state"><p>No entries yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>#</th><th>Driver</th><th>Team</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($entries as $e): ?>
        <tr>
            <td><strong class="text-accent"><?= h((string)$e['racing_number']) ?></strong></td>
            <td><a href="<?= APP_URL ?>/admin/people.php?id=<?= (int)$e['person_id'] ?>"><?= h($e['first_name'] . ' ' . $e['last_name']) ?></a></td>
            <td class="text-muted"><?= h($e['team_name']) ?></td>
            <td>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="race_entry">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/race_detail.php?id=' . $raceId) ?>">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove entry for <?= h($e['first_name'] . ' ' . $e['last_name']) ?>?">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Qualifying -->
<div class="card">
    <div class="card-title">Qualifying</div>
    <?php if (empty($qualifying)): ?>
    <div class="empty-state"><p>No qualifying data. <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=qualifying">Enter qualifying →</a></p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Grid</th><th>Driver</th><th>Team</th><th>Q1</th><th>Q2</th><th>Q3</th></tr></thead>
        <tbody>
        <?php foreach ($qualifying as $q): ?>
        <tr>
            <td><span class="position-badge pos-<?= $q['grid_position'] <= 3 ? $q['grid_position'] : 'other' ?>"><?= h((string)$q['grid_position']) ?></span></td>
            <td><a href="<?= APP_URL ?>/admin/people.php?id=<?= (int)$q['person_id'] ?>"><?= h($q['first_name'] . ' ' . $q['last_name']) ?></a></td>
            <td class="text-muted"><?= h($q['short_name']) ?></td>
            <td class="mono"><?= $q['q1_time_ms'] ? h(formatLapTime($q['q1_time_ms'])) : '—' ?></td>
            <td class="mono"><?= $q['q2_time_ms'] ? h(formatLapTime($q['q2_time_ms'])) : '—' ?></td>
            <td class="mono"><?= $q['q3_time_ms'] ? h(formatLapTime($q['q3_time_ms'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>

<!-- Race Results -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Race Results</div>
    <?php if (empty($results)): ?>
    <div class="empty-state"><p>No results yet. <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=results">Enter results →</a></p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Grid</th><th>Laps</th><th>Status</th><th>Points</th><th>FL</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
        <tr>
            <td><?= $r['finish_position'] ? '<span class="position-badge pos-'.($r['finish_position']<=3?$r['finish_position']:'other').'">' . h((string)$r['finish_position']) . '</span>' : '—' ?></td>
            <td><a href="<?= APP_URL ?>/admin/people.php?id=<?= (int)$r['person_id'] ?>" style="font-weight:600"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></a></td>
            <td class="text-muted"><?= h($r['team_name']) ?></td>
            <td class="text-muted"><?= h((string)$r['start_position']) ?></td>
            <td class="text-muted"><?= h((string)$r['laps_completed']) ?></td>
            <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
            <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
            <td><?= $r['fastest_lap_bonus'] ? '<span class="fl-indicator" title="Fastest Lap">&#9889;</span>' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if ($race['has_sprint']): ?>
<!-- Sprint Results -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Sprint Results</div>
    <?php if (empty($sprintResults)): ?>
    <div class="empty-state"><p>No sprint results. <a href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $raceId ?>&action=sprint">Enter sprint results →</a></p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Points</th></tr></thead>
        <tbody>
        <?php foreach ($sprintResults as $sr): ?>
        <tr>
            <td><?= $sr['finish_position'] ? '<span class="position-badge pos-'.($sr['finish_position']<=3?$sr['finish_position']:'other').'">' . h((string)$sr['finish_position']) . '</span>' : '—' ?></td>
            <td><a href="<?= APP_URL ?>/admin/people.php?id=<?= (int)$sr['person_id'] ?>" style="font-weight:600"><?= h($sr['first_name'] . ' ' . $sr['last_name']) ?></a></td>
            <td class="text-muted"><?= h($sr['team_name']) ?></td>
            <td class="text-accent fw-bold"><?= h((string)$sr['points_scored']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Penalties -->
<div class="grid-2" style="margin-top:1.5rem">
<div class="card">
    <div class="card-title">Penalties (<?= count($penalties) ?>)</div>
    <?php if (empty($penalties)): ?>
    <div class="empty-state"><p>No penalties for this race.</p></div>
    <?php else: ?>
    <?php foreach ($penalties as $pen): ?>
    <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <a href="<?= APP_URL ?>/admin/people.php?id=<?= (int)$pen['person_id'] ?>" style="font-weight:600"><?= h($pen['first_name'] . ' ' . $pen['last_name']) ?></a>
                <span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>" style="margin-left:0.5rem"><?= h(str_replace('_',' ',$pen['penalty_type'])) ?></span>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="penalty_id" value="<?= $pen['id'] ?>">
                <input type="hidden" name="delete_penalty" value="1">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this penalty?">Delete</button>
            </form>
        </div>
        <p style="margin:0.35rem 0 0;font-size:0.85rem;color:var(--text-secondary)"><?= h($pen['reason']) ?></p>
        <?php if ($pen['time_penalty_s']): ?><div style="font-size:0.8rem">+<?= $pen['time_penalty_s'] ?>s</div><?php endif; ?>
        <?php if ($pen['licence_points_awarded']): ?><div style="font-size:0.8rem"><?= $pen['licence_points_awarded'] ?> licence pts</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Penalty -->
<div class="card">
    <div class="card-title">Issue Penalty</div>
    <?php if (empty($entries)): ?>
    <div class="notice">Add race entries first.</div>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-group">
            <label class="form-label required">Driver</label>
            <select name="person_id" class="form-control">
                <option value="">— Select —</option>
                <?php foreach ($entries as $e): ?>
                <option value="<?= h((string)$e['person_id']) ?>">#<?= h((string)$e['racing_number']) ?> <?= h($e['first_name'] . ' ' . $e['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Type</label>
            <select name="penalty_type" class="form-control" id="pen_type" onchange="togglePenFields(this.value)">
                <option value="time_penalty">Time Penalty</option>
                <option value="grid_penalty">Grid Penalty</option>
                <option value="licence_points">Licence Points</option>
                <option value="dsq">Disqualification</option>
                <option value="warning">Warning</option>
            </select>
        </div>
        <div class="form-group" id="pf-time">
            <label class="form-label">Seconds</label>
            <input type="number" name="time_penalty_s" class="form-control" min="1" max="120">
        </div>
        <div class="form-group" id="pf-grid" style="display:none">
            <label class="form-label">Grid Positions</label>
            <input type="number" name="grid_penalty_positions" class="form-control" min="1" max="30">
        </div>
        <div class="form-group" id="pf-lic" style="display:none">
            <label class="form-label">Licence Points</label>
            <input type="number" name="licence_points_awarded" class="form-control" min="1" max="12">
        </div>
        <div class="form-group">
            <label class="form-label required">Reason</label>
            <textarea name="reason" class="form-control" rows="2" required maxlength="500"></textarea>
        </div>
        <button type="submit" name="issue_penalty" class="btn btn-primary">Issue Penalty</button>
    </form>
    <?php endif; ?>
</div>
</div>

<script>
function togglePenFields(type) {
    document.getElementById('pf-time').style.display = type === 'time_penalty' ? '' : 'none';
    document.getElementById('pf-grid').style.display = type === 'grid_penalty' ? '' : 'none';
    document.getElementById('pf-lic').style.display  = type === 'licence_points' ? '' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
