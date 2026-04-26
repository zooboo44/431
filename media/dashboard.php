<?php
$pageTitle = 'Media Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('media');

$db = getDB();
$activeSeason = getActiveSeason();
$seasonId     = $activeSeason['id'] ?? null;

$seasons = getSeasonList();
$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $selectedSeasonId = $activeSeason['id'] ?? ($seasons[0]['id'] ?? 0);
}
$selectedYear = '';
foreach ($seasons as $s) { if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; } }

// Driver standings
$driverStandings = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT ds.position, p.racing_number, p.first_name, p.last_name, p.nationality,
               t.name AS team_name, ds.points, ds.wins, ds.podiums, ds.fastest_laps, ds.dnfs
        FROM driver_standings ds
        JOIN people p ON p.id = ds.person_id
        JOIN driver_seasons drs ON drs.person_id = p.id AND drs.season_id = ds.season_id
        JOIN team_seasons ts ON ts.id = drs.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE ds.season_id = ?
        ORDER BY ds.position ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $driverStandings = $stmt->fetchAll();
}

// Constructor standings
$constructorStandings = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT cs.position, t.name AS team_name, cs.points, cs.wins
        FROM constructor_standings cs
        JOIN teams t ON t.id = cs.team_id
        WHERE cs.season_id = ?
        ORDER BY cs.position ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $constructorStandings = $stmt->fetchAll();
}

// Recent race results (last 3 completed races)
$recentRaces = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, c.name AS circuit_name
        FROM races r
        JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status = 'completed'
        ORDER BY r.round_number DESC
        LIMIT 3
    ");
    $stmt->execute([$selectedSeasonId]);
    $recentRaces = $stmt->fetchAll();
}

// For each recent race, top 5 finishers
$raceTopFive = [];
foreach ($recentRaces as $race) {
    $stmt = $db->prepare("
        SELECT p.racing_number, p.first_name, p.last_name, t.name AS team_name,
               rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id AND re.race_id = ?
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        ORDER BY COALESCE(rr.finish_position, 99) ASC
        LIMIT 5
    ");
    $stmt->execute([$race['id']]);
    $raceTopFive[$race['id']] = $stmt->fetchAll();
}

// Upcoming races
$upcoming = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.has_sprint,
               c.name AS circuit_name, c.city AS location, c.country
        FROM races r
        JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status IN ('scheduled','in_progress')
        ORDER BY r.round_number ASC
        LIMIT 5
    ");
    $stmt->execute([$selectedSeasonId]);
    $upcoming = $stmt->fetchAll();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Media Centre</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Season' : 'F1 Racing Management' ?></p>
    </div>
    <form class="season-selector" method="get">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?>
            <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>><?= h((string)$s['year']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Standings overview -->
<div class="grid-2" style="margin-bottom:1.5rem">
    <!-- Driver standings -->
    <div class="card">
        <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
            <span>Driver Championship</span>
            <a href="<?= APP_URL ?>/public/standings.php?season=<?= $selectedSeasonId ?>" class="btn btn-outline btn-sm">Full Standings</a>
        </div>
        <?php if (empty($driverStandings)): ?>
        <div class="empty-state"><p>No standings yet.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Pts</th><th>Wins</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($driverStandings, 0, 10) as $ds): ?>
            <tr>
                <td>
                    <?php if ($ds['position'] <= 3): ?>
                    <span class="position-badge pos-<?= $ds['position'] ?>"><?= h((string)$ds['position']) ?></span>
                    <?php else: ?>
                    <span class="text-muted">P<?= h((string)$ds['position']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong>#<?= h((string)$ds['racing_number']) ?> <?= h($ds['first_name'] . ' ' . $ds['last_name']) ?></strong>
                    <div class="text-muted" style="font-size:0.75rem"><?= h($ds['nationality']) ?></div>
                </td>
                <td class="text-muted"><?= h($ds['team_name']) ?></td>
                <td class="text-accent fw-bold"><?= h(number_format((float)$ds['points'], 1)) ?></td>
                <td><?= h((string)$ds['wins']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Constructor standings -->
    <div class="card">
        <div class="card-title">Constructor Championship</div>
        <?php if (empty($constructorStandings)): ?>
        <div class="empty-state"><p>No standings yet.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Pos</th><th>Team</th><th>Points</th><th>Wins</th></tr></thead>
            <tbody>
            <?php foreach ($constructorStandings as $cs): ?>
            <tr>
                <td>
                    <?php if ($cs['position'] <= 3): ?>
                    <span class="position-badge pos-<?= $cs['position'] ?>"><?= h((string)$cs['position']) ?></span>
                    <?php else: ?>
                    <span class="text-muted">P<?= h((string)$cs['position']) ?></span>
                    <?php endif; ?>
                </td>
                <td><strong><?= h($cs['team_name']) ?></strong></td>
                <td class="text-accent fw-bold"><?= h(number_format((float)$cs['points'], 1)) ?></td>
                <td><?= h((string)$cs['wins']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Recent race results -->
<?php if (!empty($recentRaces)): ?>
<h2 style="font-size:1.1rem;font-weight:600;margin:0 0 1rem;color:var(--text-primary)">Recent Results</h2>
<?php foreach ($recentRaces as $race): ?>
<div class="card" style="margin-bottom:1rem">
    <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        <span>Rd <?= h((string)$race['round_number']) ?> — <?= h($race['name']) ?></span>
        <div style="display:flex;gap:0.5rem;align-items:center">
            <span class="text-muted" style="font-size:0.8rem"><?= h(date('d M Y', strtotime($race['race_date']))) ?></span>
            <a href="<?= APP_URL ?>/public/race_detail.php?id=<?= h((string)$race['id']) ?>" class="btn btn-outline btn-sm">Full Results</a>
        </div>
    </div>
    <?php if (!empty($raceTopFive[$race['id']])): ?>
    <table>
        <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Status</th><th>Points</th></tr></thead>
        <tbody>
        <?php foreach ($raceTopFive[$race['id']] as $r): ?>
        <tr>
            <td>
                <?php if ($r['finish_position'] && $r['finish_position'] <= 3): ?>
                <span class="position-badge pos-<?= $r['finish_position'] ?>"><?= h((string)$r['finish_position']) ?></span>
                <?php elseif ($r['finish_position']): ?>
                P<?= h((string)$r['finish_position']) ?>
                <?php else: ?>—<?php endif; ?>
                <?= $r['fastest_lap_bonus'] ? ' <span class="fl-indicator">&#9889;</span>' : '' ?>
            </td>
            <td><strong>#<?= h((string)$r['racing_number']) ?> <?= h($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
            <td class="text-muted"><?= h($r['team_name']) ?></td>
            <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
            <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state" style="padding:1rem 0"><p>No results recorded.</p></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Upcoming races -->
<?php if (!empty($upcoming)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Upcoming Races</div>
    <table>
        <thead><tr><th>Rd</th><th>Race</th><th>Circuit</th><th>Date</th><th>Type</th></tr></thead>
        <tbody>
        <?php foreach ($upcoming as $r): ?>
        <tr>
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td class="text-muted"><?= h($r['circuit_name']) ?>, <?= h($r['country']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td>
                <?php if ($r['has_sprint']): ?>
                <span class="status-badge" style="background:var(--warning);color:#000">Sprint</span>
                <?php else: ?>
                <span class="status-badge status-scheduled">Race</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
