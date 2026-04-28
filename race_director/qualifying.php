<?php
$pageTitle = 'Qualifying Results';
require_once __DIR__ . '/../includes/header.php';
requireRole('race_director', 'admin');

$db     = getDB();
$errors = [];
$raceId = intval($_GET['race_id'] ?? 0);

if (!$raceId) {
    $activeSeason = getActiveSeason();
    $seasonId = $activeSeason['id'] ?? null;
    $raceList = [];
    if ($seasonId) {
        $rlStmt = $db->prepare("SELECT r.id, r.name, r.round_number, r.race_date, r.status FROM races r WHERE r.season_id = ? ORDER BY r.round_number ASC");
        $rlStmt->execute([$seasonId]);
        $raceList = $rlStmt->fetchAll();
    }
    $pageTitle = 'Qualifying — Select Race';
    renderFlash();
    echo '<div class="page-header"><div><h1 class="page-title">Qualifying Results</h1><p class="page-subtitle">Select a race to manage qualifying</p></div></div>';
    echo '<div class="card"><div class="card-title">&#9201; ' . h((string)($activeSeason['year'] ?? '')) . ' Races</div>';
    if (empty($raceList)) { echo '<div class="empty-state"><p>No races found for the active season.</p></div>'; }
    else {
        echo '<div class="table-container" style="border:0;margin:0"><table><thead><tr><th>Rd</th><th>Race</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($raceList as $rl) {
            echo '<tr><td><span class="round-chip">' . h((string)$rl['round_number']) . '</span></td>'
               . '<td><strong>' . h($rl['name']) . '</strong></td>'
               . '<td class="text-muted">' . h(date('d M Y', strtotime($rl['race_date']))) . '</td>'
               . '<td><span class="status-badge status-' . h($rl['status']) . '">' . h($rl['status']) . '</span></td>'
               . '<td><a href="' . APP_URL . '/race_director/qualifying.php?race_id=' . (int)$rl['id'] . '" class="btn btn-primary btn-sm">Manage Qualifying</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $db->prepare("SELECT r.*, c.name AS circuit FROM races r JOIN circuits c ON c.id=r.circuit_id WHERE r.id=?");
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) { include __DIR__ . '/../includes/404.php'; exit; }

// Entries with existing qualifying results
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
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $db->beginTransaction();
        try {
            foreach ($entries as $e) {
                $entryId  = $e['entry_id'];
                $gridPos  = intval($_POST["grid_{$entryId}"] ?? 0);
                $q1s      = trim($_POST["q1_{$entryId}"] ?? '');
                $q2s      = trim($_POST["q2_{$entryId}"] ?? '');
                $q3s      = trim($_POST["q3_{$entryId}"] ?? '');
                $elim     = $_POST["elim_{$entryId}"] ?? '';

                $q1ms = $q1s !== '' ? (int)round(floatval($q1s) * 1000) : null;
                $q2ms = $q2s !== '' ? (int)round(floatval($q2s) * 1000) : null;
                $q3ms = $q3s !== '' ? (int)round(floatval($q3s) * 1000) : null;
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
            redirectWithMessage(APP_URL . '/race_director/qualifying.php?race_id=' . $raceId, 'success', 'Qualifying results saved.');
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
        <h1 class="page-title">Qualifying Results</h1>
        <p class="page-subtitle"><?= h($race['name']) ?> — <?= h(date('d M Y', strtotime($race['race_date']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/race_director/dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<?php if (empty($entries)): ?>
<div class="card">
    <div class="empty-state"><p>No race entries found. <a href="<?= APP_URL ?>/race_director/race_entries.php?race_id=<?= (int)$raceId ?>">Add entries first.</a></p></div>
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
                    <td>
                        <input type="number" name="q1_<?= (int)$e['entry_id'] ?>" class="form-control" style="padding:0.3rem 0.5rem;width:100px"
                               value="<?= $e['q1_time_ms'] ? h((string)round($e['q1_time_ms']/1000, 3)) : '' ?>" step="0.001" min="50" placeholder="e.g. 89.456">
                    </td>
                    <td>
                        <input type="number" name="q2_<?= (int)$e['entry_id'] ?>" class="form-control" style="padding:0.3rem 0.5rem;width:100px"
                               value="<?= $e['q2_time_ms'] ? h((string)round($e['q2_time_ms']/1000, 3)) : '' ?>" step="0.001" min="50">
                    </td>
                    <td>
                        <input type="number" name="q3_<?= (int)$e['entry_id'] ?>" class="form-control" style="padding:0.3rem 0.5rem;width:100px"
                               value="<?= $e['q3_time_ms'] ? h((string)round($e['q3_time_ms']/1000, 3)) : '' ?>" step="0.001" min="50">
                    </td>
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
            <a href="<?= APP_URL ?>/race_director/results.php?race_id=<?= (int)$raceId ?>" class="btn btn-outline">Go to Race Results &rarr;</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
