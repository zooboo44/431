<?php
$pageTitle = 'Race Results Entry';
require_once __DIR__ . '/../includes/header.php';
requireRole('race_director', 'admin');

$db     = getDB();
$errors = [];
$raceId = intval($_GET['race_id'] ?? 0);

$stmt = $db->prepare("SELECT r.*, s.id AS season_id, c.name AS circuit FROM races r JOIN circuits c ON c.id=r.circuit_id JOIN seasons s ON s.id=r.season_id WHERE r.id=?");
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) { include __DIR__ . '/../includes/404.php'; exit; }

// Entries with existing race results
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
    LEFT JOIN race_results rr ON rr.race_entry_id = re.id
    WHERE re.race_id = ?
    ORDER BY COALESCE(qr.grid_position, 99), p.racing_number
");
$stmt->execute([$raceId]);
$entries = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $db->beginTransaction();
        try {
            // Explicit fastest lap selection via radio button
            $fastestLapEntryId = intval($_POST['fastest_lap_entry'] ?? 0) ?: null;

            // Save results
            foreach ($entries as $e) {
                $entryId   = $e['entry_id'];
                $finishPos = trim($_POST["finish_{$entryId}"] ?? '');
                $startPos  = intval($_POST["start_{$entryId}"] ?? ($e['grid_position'] ?: 0));
                $status    = $_POST["status_{$entryId}"] ?? 'finished';
                $lapsComp  = intval($_POST["laps_{$entryId}"] ?? 0);
                $flS       = trim($_POST["fl_{$entryId}"] ?? '');
                $timeS     = trim($_POST["time_{$entryId}"] ?? '');

                $finishPosVal = ($finishPos !== '' && $status === 'finished') ? intval($finishPos) : null;
                $flMs        = $flS !== '' ? (int)round(floatval($flS) * 1000) : null;
                $timeMs      = $timeS !== '' ? (int)round(floatval($timeS) * 1000) : null;

                // Is this the fastest lap holder and in top 10?
                $isFastestLap = ($fastestLapEntryId === $entryId);
                $inTopTen     = ($finishPosVal !== null && $finishPosVal <= 10);
                $flBonus      = ($isFastestLap && $inTopTen) ? 1 : 0;

                // Calculate points
                $points = 0.0;
                if ($status === 'finished' && $finishPosVal !== null) {
                    $points = calculateRacePoints($finishPosVal, (bool)$flBonus, $inTopTen);
                }

                $validStatuses = ['finished','DNF','DNS','DSQ'];
                if (!in_array($status, $validStatuses)) $status = 'finished';

                if ($e['result_id']) {
                    $db->prepare("UPDATE race_results SET start_position=?,finish_position=?,points_scored=?,total_race_time_ms=?,fastest_lap_ms=?,fastest_lap_bonus=?,laps_completed=?,status=? WHERE id=?")
                       ->execute([$startPos,$finishPosVal,$points,$timeMs,$flMs,$flBonus,$lapsComp,$status,$e['result_id']]);
                } else {
                    $db->prepare("INSERT INTO race_results (race_entry_id,start_position,finish_position,points_scored,total_race_time_ms,fastest_lap_ms,fastest_lap_bonus,laps_completed,status) VALUES (?,?,?,?,?,?,?,?,?)")
                       ->execute([$entryId,$startPos,$finishPosVal,$points,$timeMs,$flMs,$flBonus,$lapsComp,$status]);
                }
            }

            // Mark race as completed
            $db->prepare("UPDATE races SET status='completed' WHERE id=?")->execute([$raceId]);

            $db->commit();

            // Recalculate standings
            recalculateStandings($race['season_id']);

            logAudit($_SESSION['user_id'], 'update', 'results', $raceId, "Race {$raceId} results saved");
            rotateCSRFToken();
            redirectWithMessage(APP_URL . '/race_director/results.php?race_id=' . $raceId, 'success', 'Race results saved and standings updated.');
        } catch (Exception $ex) {
            $db->rollBack();
            $errors[] = 'Save failed: ' . $ex->getMessage();
        }
    }
}

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Race Results Entry</h1>
        <p class="page-subtitle"><?= h($race['name']) ?> — <?= h(date('d M Y', strtotime($race['race_date']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/race_director/dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<?php if (empty($entries)): ?>
<div class="card"><div class="empty-state"><p>No entries. <a href="<?= APP_URL ?>/race_director/race_entries.php?race_id=<?= (int)$raceId ?>">Add entries first.</a></p></div></div>
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
            <a href="<?= APP_URL ?>/race_director/qualifying.php?race_id=<?= (int)$raceId ?>" class="btn btn-outline">&larr; Qualifying</a>
            <a href="<?= APP_URL ?>/race_director/penalties.php?race_id=<?= (int)$raceId ?>" class="btn btn-secondary">Penalties</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
