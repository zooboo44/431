<?php
$pageTitle = 'Telemetry Data';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();

// Filters
$filterSeasonId = intval($_GET['season_id'] ?? 0);
$filterTeamId   = intval($_GET['team_id'] ?? 0);
$filterPersonId = intval($_GET['person_id'] ?? 0);
$filterRaceId   = intval($_GET['race_id'] ?? 0);

// Load filter options
$seasons = $db->query("SELECT id, year FROM seasons ORDER BY year DESC")->fetchAll();
$teams   = $db->query("SELECT id, name FROM teams WHERE is_active=1 ORDER BY name")->fetchAll();

$raceOpts = [];
$raceQuery = "SELECT r.id, r.name, r.round_number, s.year FROM races r JOIN seasons s ON s.id=r.season_id WHERE r.status='completed'";
$raceParams = [];
if ($filterSeasonId) { $raceQuery .= " AND r.season_id=?"; $raceParams[] = $filterSeasonId; }
$raceQuery .= " ORDER BY s.year DESC, r.round_number";
$raceStmt = $db->prepare($raceQuery);
$raceStmt->execute($raceParams);
$raceOpts = $raceStmt->fetchAll();

$driverOpts = [];
$driverQuery = "SELECT DISTINCT p.id, p.first_name, p.last_name, p.racing_number FROM people p
    JOIN race_entries re ON re.person_id = p.id
    JOIN lap_telemetry lt ON lt.race_entry_id = re.id";
$driverParams = [];
if ($filterTeamId || $filterSeasonId) {
    $driverQuery .= " JOIN team_seasons ts ON ts.id = re.team_season_id";
    if ($filterTeamId)   { $driverQuery .= " AND ts.team_id=?";   $driverParams[] = $filterTeamId; }
    if ($filterSeasonId) { $driverQuery .= " AND ts.season_id=?"; $driverParams[] = $filterSeasonId; }
}
$driverQuery .= " ORDER BY p.last_name";
$driverStmt = $db->prepare($driverQuery);
$driverStmt->execute($driverParams);
$driverOpts = $driverStmt->fetchAll();

// Handle delete
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lap'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        $lapId = intval($_POST['lap_id'] ?? 0);
        $chk = $db->prepare("SELECT id FROM lap_telemetry WHERE id = ?");
        $chk->execute([$lapId]);
        if ($chk->fetch()) {
            $db->prepare('DELETE FROM lap_telemetry WHERE id = ?')->execute([$lapId]);
            logAudit($_SESSION['user_id'], 'delete', 'lap_telemetry', $lapId, 'Deleted by admin');
            rotateCSRFToken();
            $qs = http_build_query(array_filter(['season_id'=>$filterSeasonId,'team_id'=>$filterTeamId,'person_id'=>$filterPersonId,'race_id'=>$filterRaceId]));
            redirectWithMessage(APP_URL . '/admin/telemetry.php' . ($qs ? '?' . $qs : ''), 'success', 'Lap deleted.');
        } else {
            $errors[] = 'Lap not found.';
        }
    }
}

// Build main query
$where  = ['1=1'];
$params = [];
if ($filterSeasonId) { $where[] = 's.id=?';           $params[] = $filterSeasonId; }
if ($filterTeamId)   { $where[] = 'ts.team_id=?';     $params[] = $filterTeamId; }
if ($filterPersonId) { $where[] = 're.person_id=?';   $params[] = $filterPersonId; }
if ($filterRaceId)   { $where[] = 're.race_id=?';     $params[] = $filterRaceId; }
$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT lt.id, lt.lap_number, lt.lap_time_ms, lt.sector1_ms, lt.sector2_ms, lt.sector3_ms,
           lt.speed_trap_kmh, lt.tyre_compound, lt.tyre_age_laps, lt.is_pit_lap,
           p.first_name, p.last_name, p.racing_number, p.id AS person_id,
           t.name AS team_name,
           r.name AS race_name, r.round_number, r.id AS race_id, s.year
    FROM lap_telemetry lt
    JOIN race_entries re ON re.id = lt.race_entry_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    JOIN people p ON p.id = re.person_id
    JOIN races r ON r.id = re.race_id
    JOIN seasons s ON s.id = r.season_id
    WHERE $whereClause
    ORDER BY s.year DESC, r.round_number ASC, p.last_name ASC, lt.lap_number ASC
    LIMIT 500
");
$stmt->execute($params);
$laps = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Telemetry Data</h1>
        <p class="page-subtitle">Lap-by-lap data across all teams</p>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form method="get" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="margin:0;flex:1;min-width:140px">
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
            <a href="<?= APP_URL ?>/admin/telemetry.php" class="btn btn-secondary btn-sm">Clear</a>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <span class="text-muted" style="font-size:0.85rem">
            <?= count($laps) ?> lap<?= count($laps) != 1 ? 's' : '' ?><?= count($laps) === 500 ? ' (limit 500)' : '' ?>
        </span>
    </div>
    <table class="sortable" id="telemetry-table">
        <thead><tr>
            <th>Season</th><th>Race</th><th>Team</th><th>Driver</th><th>Lap</th>
            <th>Lap Time</th><th>S1</th><th>S2</th><th>S3</th>
            <th>Speed Trap</th><th>Tyre</th><th>Age</th><th>Pit</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($laps)): ?>
        <tr><td colspan="14" class="text-center text-muted" style="padding:2rem">No telemetry data found. Use filters above to narrow results.</td></tr>
        <?php else: ?>
        <?php foreach ($laps as $lap): ?>
        <tr>
            <td class="text-muted"><?= h((string)$lap['year']) ?></td>
            <td class="text-muted">Rd <?= h((string)$lap['round_number']) ?></td>
            <td class="text-muted" style="font-size:0.8rem"><?= h($lap['team_name']) ?></td>
            <td><strong>#<?= h((string)$lap['racing_number']) ?> <?= h($lap['last_name']) ?></strong></td>
            <td><span class="round-chip"><?= h((string)$lap['lap_number']) ?></span></td>
            <td class="text-accent fw-bold"><?= h(formatLapTime($lap['lap_time_ms'])) ?></td>
            <td class="text-muted"><?= $lap['sector1_ms'] ? h(formatLapTime($lap['sector1_ms'])) : '—' ?></td>
            <td class="text-muted"><?= $lap['sector2_ms'] ? h(formatLapTime($lap['sector2_ms'])) : '—' ?></td>
            <td class="text-muted"><?= $lap['sector3_ms'] ? h(formatLapTime($lap['sector3_ms'])) : '—' ?></td>
            <td><?= $lap['speed_trap_kmh'] ? h(number_format((float)$lap['speed_trap_kmh'], 1)) . ' km/h' : '—' ?></td>
            <td>
                <?php if ($lap['tyre_compound']): ?>
                <span class="status-badge" style="background:var(--border);color:var(--text-primary)"><?= h($lap['tyre_compound']) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-muted"><?= $lap['tyre_age_laps'] !== null ? h((string)$lap['tyre_age_laps']) . 'L' : '—' ?></td>
            <td><?= $lap['is_pit_lap'] ? '<span class="status-badge status-warning">PIT</span>' : '' ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="lap_id" value="<?= (int)$lap['id'] ?>">
                    <input type="hidden" name="delete_lap" value="1">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete lap <?= (int)$lap['lap_number'] ?> data?">Del</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
