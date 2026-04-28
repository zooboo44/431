<?php
$pageTitle = 'Sprint Results Entry';
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
        $rlStmt = $db->prepare("SELECT r.id, r.name, r.round_number, r.race_date, r.status FROM races r WHERE r.season_id = ? AND r.has_sprint = 1 ORDER BY r.round_number ASC");
        $rlStmt->execute([$seasonId]);
        $raceList = $rlStmt->fetchAll();
    }
    $pageTitle = 'Sprint Results — Select Race';
    renderFlash();
    echo '<div class="page-header"><div><h1 class="page-title">Sprint Results Entry</h1><p class="page-subtitle">Select a sprint-eligible race</p></div></div>';
    echo '<div class="card"><div class="card-title">&#9889; ' . h((string)($activeSeason['year'] ?? '')) . ' Sprint Races</div>';
    if (empty($raceList)) { echo '<div class="empty-state"><p>No sprint races found for the active season.</p></div>'; }
    else {
        echo '<div class="table-container" style="border:0;margin:0"><table><thead><tr><th>Rd</th><th>Race</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($raceList as $rl) {
            echo '<tr><td><span class="round-chip">' . h((string)$rl['round_number']) . '</span></td>'
               . '<td><strong>' . h($rl['name']) . '</strong></td>'
               . '<td class="text-muted">' . h(date('d M Y', strtotime($rl['race_date']))) . '</td>'
               . '<td><span class="status-badge status-' . h($rl['status']) . '">' . h($rl['status']) . '</span></td>'
               . '<td><a href="' . APP_URL . '/race_director/sprint.php?race_id=' . (int)$rl['id'] . '" class="btn btn-primary btn-sm">Manage Sprint</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $db->prepare("SELECT r.*, s.id AS season_id, c.name AS circuit FROM races r JOIN circuits c ON c.id=r.circuit_id JOIN seasons s ON s.id=r.season_id WHERE r.id=? AND r.has_sprint=1");
$stmt->execute([$raceId]);
$race = $stmt->fetch();
if (!$race) {
    redirectWithMessage(APP_URL . '/race_director/dashboard.php', 'warning', 'That race has no sprint session.');
}

$stmt = $db->prepare("
    SELECT re.id AS entry_id, p.first_name, p.last_name, p.racing_number, t.name AS team_name,
           sr.id AS sprint_id, sr.finish_position, sr.points_scored, sr.status
    FROM race_entries re
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    LEFT JOIN sprint_results sr ON sr.race_entry_id = re.id
    WHERE re.race_id = ?
    ORDER BY COALESCE(sr.finish_position, 99), p.racing_number
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
                $entryId   = $e['entry_id'];
                $finishPos = trim($_POST["finish_{$entryId}"] ?? '');
                $status    = $_POST["status_{$entryId}"] ?? 'finished';

                $validStatuses = ['finished','DNF','DNS','DSQ'];
                if (!in_array($status, $validStatuses)) $status = 'finished';

                $finishPosVal = ($finishPos !== '' && $status === 'finished') ? intval($finishPos) : null;
                $points = ($finishPosVal !== null) ? calculateSprintPoints($finishPosVal) : 0.0;

                if ($e['sprint_id']) {
                    $db->prepare("UPDATE sprint_results SET finish_position=?,points_scored=?,status=? WHERE id=?")
                       ->execute([$finishPosVal,$points,$status,$e['sprint_id']]);
                } else {
                    $db->prepare("INSERT INTO sprint_results (race_entry_id,finish_position,points_scored,status) VALUES (?,?,?,?)")
                       ->execute([$entryId,$finishPosVal,$points,$status]);
                }
            }
            $db->commit();
            recalculateStandings($race['season_id']);
            logAudit($_SESSION['user_id'], 'update', 'sprint_results', $raceId);
            rotateCSRFToken();
            redirectWithMessage(APP_URL . '/race_director/sprint.php?race_id=' . $raceId, 'success', 'Sprint results saved and standings updated.');
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
        <h1 class="page-title">Sprint Results Entry</h1>
        <p class="page-subtitle"><?= h($race['name']) ?> — Sprint</p>
    </div>
    <a href="<?= APP_URL ?>/race_director/dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
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
            <a href="<?= APP_URL ?>/race_director/results.php?race_id=<?= (int)$raceId ?>" class="btn btn-outline">Race Results</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
