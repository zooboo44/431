<?php
$pageTitle = 'Telemetry';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);

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

$filterRaceId   = intval($_GET['race_id'] ?? 0);
$filterPersonId = intval($_GET['person_id'] ?? 0);

$driverStmt = $db->prepare("
    SELECT DISTINCT p.id, p.first_name, p.last_name, p.racing_number
    FROM people p
    JOIN driver_seasons drs ON drs.person_id = p.id
    JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
    ORDER BY p.last_name
");
$driverStmt->execute([$teamId]);
$drivers = $driverStmt->fetchAll();

// IDOR: always filter through team_seasons.team_id = teamId
$where  = ['ts.team_id = ?'];
$params = [$teamId];
if ($filterRaceId)   { $where[] = 're.race_id = ?';   $params[] = $filterRaceId; }
if ($filterPersonId) { $where[] = 're.person_id = ?'; $params[] = $filterPersonId; }
$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT lt.lap_number, lt.lap_time_ms, lt.sector1_ms, lt.sector2_ms, lt.sector3_ms,
           lt.speed_trap_kmh, lt.tyre_compound, lt.tyre_age_laps, lt.is_pit_lap,
           p.id AS person_id, p.first_name, p.last_name, p.racing_number,
           r.name AS race_name, r.round_number, s.year
    FROM lap_telemetry lt
    JOIN race_entries re ON re.id = lt.race_entry_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN people p ON p.id = re.person_id
    JOIN races r ON r.id = re.race_id
    JOIN seasons s ON s.id = r.season_id
    WHERE $whereClause
    ORDER BY r.race_date DESC, p.last_name ASC, lt.lap_number ASC
    LIMIT 500
");
$stmt->execute($params);
$laps = $stmt->fetchAll();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Telemetry Data</h1>
        <p class="page-subtitle">Lap-by-lap data for your drivers</p>
    </div>
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
            <a href="<?= APP_URL ?>/team_manager/telemetry.php" class="btn btn-secondary btn-sm">Clear</a>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="telemetry-table" placeholder="Search...">
        </div>
        <span class="text-muted" style="font-size:0.85rem">
            <?= count($laps) ?> lap<?= count($laps) != 1 ? 's' : '' ?><?= count($laps) === 500 ? ' (limit 500)' : '' ?>
        </span>
    </div>
    <table class="sortable" id="telemetry-table">
        <thead><tr>
            <th>Race</th><th>Driver</th><th>Lap</th><th>Lap Time</th>
            <th>S1</th><th>S2</th><th>S3</th>
            <th>Speed Trap</th><th>Tyre</th><th>Age</th><th>Pit</th>
        </tr></thead>
        <tbody>
        <?php if (empty($laps)): ?>
        <tr><td colspan="11" class="text-center text-muted" style="padding:2rem">No telemetry data. Use filters to select a race or driver.</td></tr>
        <?php else: ?>
        <?php foreach ($laps as $lap): ?>
        <tr>
            <td class="text-muted">Rd <?= h((string)$lap['round_number']) ?> <?= h((string)$lap['year']) ?></td>
            <td><a href="<?= APP_URL ?>/team_manager/driver_profile.php?person_id=<?= (int)$lap['person_id'] ?>" style="font-weight:600">#<?= h((string)$lap['racing_number']) ?> <?= h($lap['last_name']) ?></a></td>
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
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
