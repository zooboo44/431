<?php
$pageTitle = 'Race Data';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);

$seasons = getSeasonList();
$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}

$activeTab = in_array($_GET['tab'] ?? '', ['telemetry', 'pitstops']) ? ($_GET['tab']) : 'telemetry';

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

// Telemetry data
$where  = ['ts.team_id = ?'];
$params = [$teamId];
if ($filterRaceId)   { $where[] = 're.race_id = ?';   $params[] = $filterRaceId; }
if ($filterPersonId) { $where[] = 're.person_id = ?'; $params[] = $filterPersonId; }
$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT lt.lap_number, lt.lap_time_ms, lt.sector1_ms, lt.sector2_ms, lt.sector3_ms,
           lt.speed_trap_kmh, lt.tyre_compound, lt.tyre_age_laps, lt.is_pit_lap,
           p.id AS person_id, p.first_name, p.last_name, p.racing_number,
           r.id AS race_id, r.name AS race_name, r.round_number, s.year
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

// Pit stop data with race + driver filters
$psWhere  = ['ts.team_id = ?'];
$psParams = [$teamId];
if ($filterRaceId)   { $psWhere[] = 're.race_id = ?';   $psParams[] = $filterRaceId; }
if ($filterPersonId) { $psWhere[] = 're.person_id = ?'; $psParams[] = $filterPersonId; }
$psWhereClause = implode(' AND ', $psWhere);

$stmt = $db->prepare("
    SELECT ps.stop_number, ps.lap_number, ps.duration_ms, ps.tyre_in, ps.tyre_out,
           p.id AS person_id, p.first_name, p.last_name, p.racing_number,
           r.id AS race_id, r.name AS race_name, r.round_number
    FROM pit_stops ps
    JOIN race_entries re ON re.id = ps.race_entry_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN races r ON r.id = re.race_id
    JOIN people p ON p.id = re.person_id
    WHERE $psWhereClause
    ORDER BY r.round_number ASC, p.last_name ASC, ps.stop_number ASC
");
$stmt->execute($psParams);
$pitStops = $stmt->fetchAll();

$selectedYear = '';
foreach ($seasons as $s) { if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; } }

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Race Data</h1>
        <p class="page-subtitle">Telemetry &amp; pit stop data for your team</p>
    </div>
</div>

<!-- Tab navigation -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:1.5rem">
    <a href="?tab=telemetry<?= $filterRaceId ? '&race_id='.$filterRaceId : '' ?><?= $filterPersonId ? '&person_id='.$filterPersonId : '' ?>"
       style="padding:0.6rem 1.2rem;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $activeTab==='telemetry' ? 'var(--accent)' : 'transparent' ?>;margin-bottom:-2px;color:<?= $activeTab==='telemetry' ? 'var(--accent)' : 'var(--text-secondary)' ?>">
        &#128200; Telemetry
    </a>
    <a href="?tab=pitstops&season=<?= $selectedSeasonId ?>"
       style="padding:0.6rem 1.2rem;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $activeTab==='pitstops' ? 'var(--accent)' : 'transparent' ?>;margin-bottom:-2px;color:<?= $activeTab==='pitstops' ? 'var(--accent)' : 'var(--text-secondary)' ?>">
        &#128295; Pit Stops
    </a>
</div>

<?php if ($activeTab === 'telemetry'): ?>
<!-- Telemetry Tab -->
<div class="card" style="margin-bottom:1rem">
    <form method="get" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="tab" value="telemetry">
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
            <a href="?tab=telemetry" class="btn btn-secondary btn-sm">Clear</a>
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
            <td class="text-muted"><a href="<?= APP_URL ?>/shared/results.php?id=<?= (int)$lap['race_id'] ?>">Rd <?= h((string)$lap['round_number']) ?> <?= h((string)$lap['year']) ?></a></td>
            <td><a href="<?= APP_URL ?>/team_manager/drivers.php?person_id=<?= (int)$lap['person_id'] ?>" style="font-weight:600">#<?= h((string)$lap['racing_number']) ?> <?= h($lap['last_name']) ?></a></td>
            <td><span class="round-chip"><?= h((string)$lap['lap_number']) ?></span></td>
            <td class="text-accent fw-bold"><?= h(formatLapTime($lap['lap_time_ms'])) ?></td>
            <td class="text-muted"><?= $lap['sector1_ms'] ? h(formatLapTime($lap['sector1_ms'])) : '—' ?></td>
            <td class="text-muted"><?= $lap['sector2_ms'] ? h(formatLapTime($lap['sector2_ms'])) : '—' ?></td>
            <td class="text-muted"><?= $lap['sector3_ms'] ? h(formatLapTime($lap['sector3_ms'])) : '—' ?></td>
            <td><?= $lap['speed_trap_kmh'] ? h(number_format((float)$lap['speed_trap_kmh'], 1)) . ' km/h' : '—' ?></td>
            <td><?= $lap['tyre_compound'] ? '<span class="status-badge" style="background:var(--border);color:var(--text-primary)">' . h($lap['tyre_compound']) . '</span>' : '—' ?></td>
            <td class="text-muted"><?= $lap['tyre_age_laps'] !== null ? h((string)$lap['tyre_age_laps']) . 'L' : '—' ?></td>
            <td><?= $lap['is_pit_lap'] ? '<span class="status-badge status-warning">PIT</span>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
<!-- Pit Stops Tab -->
<div class="card" style="margin-bottom:1rem">
    <form method="get" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="tab" value="pitstops">
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
            <a href="?tab=pitstops" class="btn btn-secondary btn-sm">Clear</a>
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
            <th>Lap</th><th>Duration</th><th>Tyre In</th><th>Tyre Out</th>
        </tr></thead>
        <tbody>
        <?php if (empty($pitStops)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:2rem">No pit stop data for this season.</td></tr>
        <?php else: ?>
        <?php foreach ($pitStops as $ps): ?>
        <tr>
            <td><span class="round-chip"><?= h((string)$ps['round_number']) ?></span></td>
            <td><a href="<?= APP_URL ?>/shared/results.php?id=<?= (int)$ps['race_id'] ?>"><?= h($ps['race_name']) ?></a></td>
            <td><a href="<?= APP_URL ?>/team_manager/drivers.php?person_id=<?= (int)$ps['person_id'] ?>" style="font-weight:600">#<?= h((string)$ps['racing_number']) ?> <?= h($ps['first_name'] . ' ' . $ps['last_name']) ?></a></td>
            <td class="text-accent fw-bold"><?= h((string)$ps['stop_number']) ?></td>
            <td class="text-muted">Lap <?= h((string)$ps['lap_number']) ?></td>
            <td><?= $ps['duration_ms'] ? h(number_format($ps['duration_ms'] / 1000, 3)) . 's' : '—' ?></td>
            <td><?= $ps['tyre_in'] ? '<span class="status-badge" style="background:var(--border);color:var(--text-primary)">' . h($ps['tyre_in']) . '</span>' : '—' ?></td>
            <td><?= $ps['tyre_out'] ? '<span class="status-badge" style="background:var(--border);color:var(--text-primary)">' . h($ps['tyre_out']) . '</span>' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
