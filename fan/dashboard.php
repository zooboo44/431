<?php
$pageTitle = 'Fan Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('fan');

$db = getDB();
$seasons = getSeasonList();
$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
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

// Recent completed races
$recentRaces = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, c.name AS circuit_name, c.country
        FROM races r
        JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status = 'completed'
        ORDER BY r.round_number DESC
        LIMIT 5
    ");
    $stmt->execute([$selectedSeasonId]);
    $recentRaces = $stmt->fetchAll();
}

// Upcoming races
$upcoming = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.has_sprint,
               c.name AS circuit_name, c.country
        FROM races r
        JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status IN ('scheduled','in_progress')
        ORDER BY r.round_number ASC
        LIMIT 5
    ");
    $stmt->execute([$selectedSeasonId]);
    $upcoming = $stmt->fetchAll();
}

// Circuits
$circuits = $db->query("SELECT id, name, city, country, length_km, number_of_laps FROM circuits ORDER BY country ASC")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Fan Dashboard</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Season' : 'F1 Racing Management' ?></p>
    </div>
    <form class="season-selector" method="get">
        <label for="season" style="color:var(--text-secondary);font-size:0.85rem">Season:</label>
        <select name="season" id="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?>
            <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>>
                <?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Standings -->
<div class="grid-2" style="margin-bottom:1.5rem">
    <div class="card">
        <div class="card-title">&#127942; Driver Championship</div>
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
                    <?php else: ?><span class="text-muted">P<?= h((string)$ds['position']) ?></span><?php endif; ?>
                </td>
                <td><strong>#<?= h((string)$ds['racing_number']) ?> <?= h($ds['first_name'] . ' ' . $ds['last_name']) ?></strong></td>
                <td class="text-muted"><?= h($ds['team_name']) ?></td>
                <td class="text-accent fw-bold"><?= h(number_format((float)$ds['points'], 1)) ?></td>
                <td><?= h((string)$ds['wins']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">&#127937; Constructor Championship</div>
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
                    <?php else: ?><span class="text-muted">P<?= h((string)$cs['position']) ?></span><?php endif; ?>
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

<!-- Recent Results -->
<?php if (!empty($recentRaces)): ?>
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-title">&#9989; Recent Results</div>
    <table>
        <thead><tr><th>Rd</th><th>Race</th><th>Circuit</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentRaces as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/public/race_detail.php?id=<?= (int)$r['id'] ?>">
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td class="text-muted"><?= h($r['circuit_name']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td class="no-row-click"><a href="<?= APP_URL ?>/public/race_detail.php?id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">View &rarr;</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Upcoming Races -->
<?php if (!empty($upcoming)): ?>
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-title">&#128197; Upcoming Races</div>
    <table>
        <thead><tr><th>Rd</th><th>Race</th><th>Circuit</th><th>Date</th><th>Sprint</th></tr></thead>
        <tbody>
        <?php foreach ($upcoming as $r): ?>
        <tr>
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td class="text-muted"><?= h($r['circuit_name']) ?>, <?= h($r['country']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Circuits -->
<div class="card">
    <div class="card-title">&#9940; Circuits</div>
    <div class="table-container" style="border:0;margin:0">
        <div class="table-toolbar">
            <div class="table-search">
                <input type="text" class="table-search-input" data-table="circuits-table" placeholder="Search circuits...">
            </div>
        </div>
        <table class="sortable" id="circuits-table">
            <thead><tr>
                <th>Circuit</th><th>City</th><th>Country</th><th>Length (km)</th><th>Laps</th>
            </tr></thead>
            <tbody>
            <?php if (empty($circuits)): ?>
            <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">No circuits found.</td></tr>
            <?php else: ?>
            <?php foreach ($circuits as $c): ?>
            <tr>
                <td><strong><?= h($c['name']) ?></strong></td>
                <td class="text-muted"><?= h($c['city']) ?></td>
                <td class="text-muted"><?= h($c['country']) ?></td>
                <td><?= $c['length_km'] ? h(number_format((float)$c['length_km'], 3)) . ' km' : '—' ?></td>
                <td><?= $c['number_of_laps'] ? h((string)$c['number_of_laps']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
