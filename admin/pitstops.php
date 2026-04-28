<?php
$pageTitle = 'Pit Stops';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();

$filterSeasonId = intval($_GET['season_id'] ?? 0);
$filterTeamId   = intval($_GET['team_id'] ?? 0);
$filterPersonId = intval($_GET['person_id'] ?? 0);
$filterRaceId   = intval($_GET['race_id'] ?? 0);

$seasons = $db->query("SELECT id, year FROM seasons ORDER BY year DESC")->fetchAll();
$teams   = $db->query("SELECT id, name FROM teams WHERE is_active=1 ORDER BY name")->fetchAll();

$raceQuery = "SELECT r.id, r.name, r.round_number, s.year FROM races r JOIN seasons s ON s.id=r.season_id WHERE r.status='completed'";
$raceParams = [];
if ($filterSeasonId) { $raceQuery .= " AND r.season_id=?"; $raceParams[] = $filterSeasonId; }
$raceQuery .= " ORDER BY s.year DESC, r.round_number";
$raceStmt = $db->prepare($raceQuery);
$raceStmt->execute($raceParams);
$raceOpts = $raceStmt->fetchAll();

$driverParams = [];
$driverQuery  = "SELECT DISTINCT p.id, p.first_name, p.last_name, p.racing_number FROM people p
    JOIN race_entries re ON re.person_id=p.id
    JOIN pit_stops ps ON ps.race_entry_id=re.id";
if ($filterTeamId || $filterSeasonId) {
    $driverQuery .= " JOIN team_seasons ts ON ts.id=re.team_season_id";
    if ($filterTeamId)   { $driverQuery .= " AND ts.team_id=?";   $driverParams[] = $filterTeamId; }
    if ($filterSeasonId) { $driverQuery .= " AND ts.season_id=?"; $driverParams[] = $filterSeasonId; }
}
$driverQuery .= " ORDER BY p.last_name";
$driverStmt = $db->prepare($driverQuery);
$driverStmt->execute($driverParams);
$driverOpts = $driverStmt->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pitstop'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $psId = intval($_POST['ps_id'] ?? 0);
        $chk  = $db->prepare("SELECT id FROM pit_stops WHERE id=?");
        $chk->execute([$psId]);
        if ($chk->fetch()) {
            $db->prepare('DELETE FROM pit_stops WHERE id=?')->execute([$psId]);
            logAudit($_SESSION['user_id'], 'delete', 'pit_stops', $psId, 'Deleted by admin');
            rotateCSRFToken();
            $qs = http_build_query(array_filter(['season_id'=>$filterSeasonId,'team_id'=>$filterTeamId,'person_id'=>$filterPersonId,'race_id'=>$filterRaceId]));
            redirectWithMessage(APP_URL . '/admin/pitstops.php' . ($qs ? '?' . $qs : ''), 'success', 'Pit stop deleted.');
        } else {
            $errors[] = 'Pit stop not found.';
        }
    }
}

$where  = ['1=1'];
$params = [];
if ($filterSeasonId) { $where[] = 's.id=?';           $params[] = $filterSeasonId; }
if ($filterTeamId)   { $where[] = 'ts.team_id=?';     $params[] = $filterTeamId; }
if ($filterPersonId) { $where[] = 're.person_id=?';   $params[] = $filterPersonId; }
if ($filterRaceId)   { $where[] = 're.race_id=?';     $params[] = $filterRaceId; }
$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT ps.id, ps.stop_number, ps.lap_number, ps.duration_ms, ps.tyre_in, ps.tyre_out,
           p.first_name, p.last_name, p.racing_number, p.id AS person_id,
           t.name AS team_name,
           r.name AS race_name, r.round_number, r.id AS race_id, s.year
    FROM pit_stops ps
    JOIN race_entries re ON re.id = ps.race_entry_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    JOIN races r ON r.id = re.race_id
    JOIN seasons s ON s.id = r.season_id
    JOIN people p ON p.id = re.person_id
    WHERE $whereClause
    ORDER BY s.year DESC, r.round_number ASC, p.last_name ASC, ps.stop_number ASC
");
$stmt->execute($params);
$pitStops = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Pit Stops</h1>
        <p class="page-subtitle">Pit stop data across all teams</p>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form method="get" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="margin:0;flex:1;min-width:130px">
            <label class="form-label">Season</label>
            <select name="season_id" class="form-control" onchange="this.form.submit()">
                <option value="">All Seasons</option>
                <?php foreach ($seasons as $s): ?>
                <option value="<?= h((string)$s['id']) ?>"<?= $filterSeasonId == $s['id'] ? ' selected' : '' ?>><?= h((string)$s['year']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:140px">
            <label class="form-label">Team</label>
            <select name="team_id" class="form-control">
                <option value="">All Teams</option>
                <?php foreach ($teams as $t): ?>
                <option value="<?= h((string)$t['id']) ?>"<?= $filterTeamId == $t['id'] ? ' selected' : '' ?>><?= h($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:140px">
            <label class="form-label">Driver</label>
            <select name="person_id" class="form-control">
                <option value="">All Drivers</option>
                <?php foreach ($driverOpts as $d): ?>
                <option value="<?= h((string)$d['id']) ?>"<?= $filterPersonId == $d['id'] ? ' selected' : '' ?>>
                    #<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:160px">
            <label class="form-label">Race</label>
            <select name="race_id" class="form-control">
                <option value="">All Races</option>
                <?php foreach ($raceOpts as $r): ?>
                <option value="<?= h((string)$r['id']) ?>"<?= $filterRaceId == $r['id'] ? ' selected' : '' ?>>
                    <?= h((string)$r['year']) ?> Rd <?= h((string)$r['round_number']) ?> — <?= h($r['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:0.5rem">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="<?= APP_URL ?>/admin/pitstops.php" class="btn btn-secondary btn-sm">Clear</a>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <span class="text-muted" style="font-size:0.85rem"><?= count($pitStops) ?> stop<?= count($pitStops) != 1 ? 's' : '' ?></span>
    </div>
    <table class="sortable" id="pitstops-table">
        <thead><tr>
            <th>Season</th><th>Rd</th><th>Race</th><th>Team</th><th>Driver</th>
            <th>Stop #</th><th>Lap</th><th>Duration</th><th>Tyre In</th><th>Tyre Out</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($pitStops)): ?>
        <tr><td colspan="11" class="text-center text-muted" style="padding:2rem">No pit stop data found.</td></tr>
        <?php else: ?>
        <?php foreach ($pitStops as $ps): ?>
        <tr>
            <td class="text-muted"><?= h((string)$ps['year']) ?></td>
            <td><span class="round-chip"><?= h((string)$ps['round_number']) ?></span></td>
            <td><?= h($ps['race_name']) ?></td>
            <td class="text-muted" style="font-size:0.8rem"><?= h($ps['team_name']) ?></td>
            <td><strong>#<?= h((string)$ps['racing_number']) ?> <?= h($ps['first_name'] . ' ' . $ps['last_name']) ?></strong></td>
            <td class="text-accent fw-bold"><?= h((string)$ps['stop_number']) ?></td>
            <td class="text-muted">Lap <?= h((string)$ps['lap_number']) ?></td>
            <td><?= $ps['duration_ms'] ? h(number_format($ps['duration_ms'] / 1000, 3)) . 's' : '—' ?></td>
            <td>
                <?php if ($ps['tyre_in']): ?>
                <span class="status-badge" style="background:var(--border);color:var(--text-primary)"><?= h($ps['tyre_in']) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <?php if ($ps['tyre_out']): ?>
                <span class="status-badge" style="background:var(--border);color:var(--text-primary)"><?= h($ps['tyre_out']) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="ps_id" value="<?= (int)$ps['id'] ?>">
                    <input type="hidden" name="delete_pitstop" value="1">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete stop #<?= (int)$ps['stop_number'] ?>?">Del</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
