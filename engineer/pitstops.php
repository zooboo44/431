<?php
$pageTitle = 'Pit Stops';
require_once __DIR__ . '/../includes/header.php';
requireRole('engineer');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);

// Races for this team (completed)
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

$filterRaceId   = intval($_GET['race_id'] ?? 0);
$filterPersonId = intval($_GET['person_id'] ?? 0);

// Handle delete
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pitstop'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $psId = intval($_POST['ps_id'] ?? 0);
        $chk = $db->prepare("
            SELECT ps.id FROM pit_stops ps
            JOIN race_entries re ON re.id = ps.race_entry_id
            JOIN team_seasons ts ON ts.id = re.team_season_id AND ts.team_id = ?
            WHERE ps.id = ?
        ");
        $chk->execute([$teamId, $psId]);
        if ($chk->fetch()) {
            $db->prepare('DELETE FROM pit_stops WHERE id = ?')->execute([$psId]);
            logAudit($_SESSION['user_id'], 'delete', 'pit_stops', $psId, 'Deleted by engineer');
            rotateCSRFToken();
            $qs = http_build_query(array_filter(['race_id' => $filterRaceId, 'person_id' => $filterPersonId]));
            redirectWithMessage(APP_URL . '/engineer/pitstops.php' . ($qs ? '?' . $qs : ''), 'success', 'Pit stop deleted.');
        } else {
            $errors[] = 'Pit stop not found or not authorised.';
        }
    }
}

// IDOR: always join through team_seasons.team_id = teamId
$where  = ['ts.team_id = ?'];
$params = [$teamId];
if ($filterRaceId)   { $where[] = 're.race_id = ?';   $params[] = $filterRaceId; }
if ($filterPersonId) { $where[] = 're.person_id = ?'; $params[] = $filterPersonId; }
$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT ps.id, ps.stop_number, ps.lap_number, ps.duration_ms, ps.tyre_in, ps.tyre_out,
           p.first_name, p.last_name, p.racing_number, p.id AS person_id,
           r.name AS race_name, r.round_number, r.id AS race_id
    FROM pit_stops ps
    JOIN race_entries re ON re.id = ps.race_entry_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN races r ON r.id = re.race_id
    JOIN people p ON p.id = re.person_id
    WHERE $whereClause
    ORDER BY r.round_number ASC, p.last_name ASC, ps.stop_number ASC
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
        <p class="page-subtitle">Pit stop data for your team</p>
    </div>
    <a href="<?= APP_URL ?>/engineer/pitstops_add.php" class="btn btn-primary">+ Add Pit Stop</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <form method="get" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="margin:0;flex:1;min-width:160px">
            <label class="form-label">Race</label>
            <select name="race_id" class="form-control">
                <option value="">All Races</option>
                <?php foreach ($races as $r): ?>
                <option value="<?= h((string)$r['id']) ?>"<?= $filterRaceId == $r['id'] ? ' selected' : '' ?>>
                    <?= h((string)$r['year']) ?> Rd <?= h((string)$r['round_number']) ?> — <?= h($r['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:160px">
            <label class="form-label">Driver</label>
            <select name="person_id" class="form-control">
                <option value="">All Drivers</option>
                <?php foreach ($drivers as $d): ?>
                <option value="<?= h((string)$d['id']) ?>"<?= $filterPersonId == $d['id'] ? ' selected' : '' ?>>
                    #<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:0.5rem">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="<?= APP_URL ?>/engineer/pitstops.php" class="btn btn-secondary btn-sm">Clear</a>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <span class="text-muted" style="font-size:0.85rem"><?= count($pitStops) ?> stop<?= count($pitStops) != 1 ? 's' : '' ?></span>
    </div>
    <table class="sortable" id="pitstops-table">
        <thead><tr>
            <th>Rd</th><th>Race</th><th>Driver</th><th>Stop #</th>
            <th>Lap</th><th>Duration</th><th>Tyre In</th><th>Tyre Out</th><th class="no-row-click">Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($pitStops)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:2rem">No pit stop data. Add via the button above or filter by race/driver.</td></tr>
        <?php else: ?>
        <?php foreach ($pitStops as $ps): ?>
        <tr>
            <td><span class="round-chip"><?= h((string)$ps['round_number']) ?></span></td>
            <td><?= h($ps['race_name']) ?></td>
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
            <td class="no-row-click">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="ps_id" value="<?= (int)$ps['id'] ?>">
                    <input type="hidden" name="delete_pitstop" value="1">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete stop #<?= (int)$ps['stop_number'] ?>?">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
