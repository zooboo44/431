<?php
$pageTitle = 'Championship Standings';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager', 'engineer', 'driver', 'media', 'fan', 'race_director', 'admin');

$db = getDB();
$seasons = getSeasonList();

$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}

$driverStandings = [];
$constructorStandings = [];

if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT ds.position, ds.points, ds.wins, ds.podiums, ds.dnfs, ds.fastest_laps,
               p.id AS person_id, p.first_name, p.last_name, p.racing_number, p.nationality,
               t.name AS team_name, t.short_name
        FROM driver_standings ds
        JOIN people p ON p.id = ds.person_id
        JOIN driver_seasons drs ON drs.person_id = p.id AND drs.season_id = ?
        JOIN team_seasons ts ON ts.id = drs.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE ds.season_id = ?
        ORDER BY ds.position ASC
    ");
    $stmt->execute([$selectedSeasonId, $selectedSeasonId]);
    $driverStandings = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT cs.position, cs.points, cs.wins, t.id AS team_id, t.name, t.short_name, t.nationality
        FROM constructor_standings cs
        JOIN teams t ON t.id = cs.team_id
        WHERE cs.season_id = ?
        ORDER BY cs.position ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $constructorStandings = $stmt->fetchAll();
}

$selectedYear = '';
foreach ($seasons as $s) {
    if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; }
}
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Championship Standings</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Formula 1 World Championship' : '' ?></p>
    </div>
    <form class="season-selector" method="get">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?>
            <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>>
                <?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">&#128100; Driver Championship</div>
        <?php if (empty($driverStandings)): ?>
        <div class="empty-state"><p>No standings data yet.</p></div>
        <?php else: ?>
        <div class="table-container" style="border:0;margin-bottom:0">
            <table class="sortable" id="driver-standings-table">
                <thead><tr>
                    <th>Pos</th><th>Driver</th><th>Team</th><th>Pts</th><th>Wins</th><th>Pods</th><th>FL</th>
                </tr></thead>
                <tbody>
                <?php foreach ($driverStandings as $d): ?>
                <tr>
                    <td><span class="position-badge pos-<?= $d['position'] <= 3 ? $d['position'] : 'other' ?>"><?= h((string)$d['position']) ?></span></td>
                    <td>
                        <a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$d['person_id'] ?>" style="font-weight:600"><?= h($d['first_name'] . ' ' . $d['last_name']) ?></a>
                        <div style="font-size:0.75rem;color:var(--text-muted)">#<?= h((string)$d['racing_number']) ?> &bull; <?= h($d['nationality']) ?></div>
                    </td>
                    <td class="text-muted"><?= h($d['short_name']) ?></td>
                    <td><strong class="text-accent"><?= h((string)$d['points']) ?></strong></td>
                    <td><?= h((string)$d['wins']) ?></td>
                    <td><?= h((string)$d['podiums']) ?></td>
                    <td><?= h((string)$d['fastest_laps']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (($_SESSION['role'] ?? '') !== 'fan'): ?>
        <div style="margin-top:0.75rem">
            <button class="btn btn-outline btn-sm" data-export-csv="driver-standings-table" data-filename="driver_standings_<?= h((string)$selectedYear) ?>.csv">Export CSV</button>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">&#127937; Constructor Championship</div>
        <?php if (empty($constructorStandings)): ?>
        <div class="empty-state"><p>No standings data yet.</p></div>
        <?php else: ?>
        <div class="table-container" style="border:0;margin-bottom:0">
            <table class="sortable" id="constructor-standings-table">
                <thead><tr>
                    <th>Pos</th><th>Constructor</th><th>Pts</th><th>Wins</th>
                </tr></thead>
                <tbody>
                <?php foreach ($constructorStandings as $c): ?>
                <tr>
                    <td><span class="position-badge pos-<?= $c['position'] <= 3 ? $c['position'] : 'other' ?>"><?= h((string)$c['position']) ?></span></td>
                    <td>
                        <a href="<?= APP_URL ?>/shared/team_detail.php?id=<?= (int)$c['team_id'] ?>" style="font-weight:600"><?= h($c['name']) ?></a>
                        <div style="font-size:0.75rem;color:var(--text-muted)"><?= h($c['nationality']) ?></div>
                    </td>
                    <td><strong class="text-accent"><?= h((string)$c['points']) ?></strong></td>
                    <td><?= h((string)$c['wins']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (($_SESSION['role'] ?? '') !== 'fan'): ?>
        <div style="margin-top:0.75rem">
            <button class="btn btn-outline btn-sm" data-export-csv="constructor-standings-table" data-filename="constructor_standings_<?= h((string)$selectedYear) ?>.csv">Export CSV</button>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
