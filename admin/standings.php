<?php
$pageTitle = 'Standings';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'race_director');

$db      = getDB();
$seasons = getSeasonList();

$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}

$selectedYear = '';
foreach ($seasons as $s) { if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; } }

// Handle server-side CSV export
if (isset($_GET['export'])) {
    requireRole('admin', 'race_director');
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . ($type === 'drivers' ? 'driver' : 'constructor') . '_standings_' . $selectedYear . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    $out = fopen('php://output', 'w');
    if ($type === 'drivers') {
        fputcsv($out, ['Pos','Driver','#','Nationality','Team','Points','Wins','Podiums','Fastest Laps','DNFs']);
        $stmt = $db->prepare("
            SELECT ds.position, p.first_name, p.last_name, p.racing_number, p.nationality,
                   t.name AS team_name, ds.points, ds.wins, ds.podiums, ds.fastest_laps, ds.dnfs
            FROM driver_standings ds
            JOIN people p ON p.id = ds.person_id
            JOIN driver_seasons drs ON drs.person_id = p.id AND drs.season_id = ds.season_id
            JOIN team_seasons ts ON ts.id = drs.team_season_id
            JOIN teams t ON t.id = ts.team_id
            WHERE ds.season_id = ? ORDER BY ds.position ASC
        ");
        $stmt->execute([$selectedSeasonId]);
        foreach ($stmt->fetchAll() as $row) {
            fputcsv($out, [$row['position'], $row['first_name'].' '.$row['last_name'], '#'.$row['racing_number'], $row['nationality'], $row['team_name'], $row['points'], $row['wins'], $row['podiums'], $row['fastest_laps'], $row['dnfs']]);
        }
    } else {
        fputcsv($out, ['Pos','Team','Nationality','Points','Wins']);
        $stmt = $db->prepare("
            SELECT cs.position, t.name, t.nationality, cs.points, cs.wins
            FROM constructor_standings cs JOIN teams t ON t.id=cs.team_id
            WHERE cs.season_id = ? ORDER BY cs.position ASC
        ");
        $stmt->execute([$selectedSeasonId]);
        foreach ($stmt->fetchAll() as $row) {
            fputcsv($out, [$row['position'], $row['name'], $row['nationality'], $row['points'], $row['wins']]);
        }
    }
    fclose($out);
    exit;
}

$driverStandings = [];
$constructorStandings = [];

if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT ds.position, ds.points, ds.wins, ds.podiums, ds.dnfs, ds.fastest_laps,
               p.first_name, p.last_name, p.racing_number, p.nationality, p.id AS person_id,
               t.name AS team_name, t.short_name
        FROM driver_standings ds
        JOIN people p ON p.id = ds.person_id
        JOIN driver_seasons drs ON drs.person_id = p.id AND drs.season_id = ds.season_id
        JOIN team_seasons ts ON ts.id = drs.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE ds.season_id = ? ORDER BY ds.position ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $driverStandings = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT cs.position, cs.points, cs.wins, t.name, t.short_name, t.nationality, t.id AS team_id
        FROM constructor_standings cs JOIN teams t ON t.id = cs.team_id
        WHERE cs.season_id = ? ORDER BY cs.position ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $constructorStandings = $stmt->fetchAll();
}

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Championship Standings</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Season' : 'Select a season' ?></p>
    </div>
    <form class="season-selector" method="get">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?>
            <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>><?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="grid-2">
<!-- Driver Championship -->
<div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        <span>Driver Championship</span>
        <a href="?season=<?= $selectedSeasonId ?>&export=drivers" class="btn btn-outline btn-sm">Export CSV</a>
    </div>
    <?php if (empty($driverStandings)): ?>
    <div class="empty-state"><p>No standings yet. Enter race results to generate standings.</p></div>
    <?php else: ?>
    <table class="sortable" id="driver-standings-table">
        <thead><tr>
            <th>Pos</th><th>Driver</th><th>Team</th><th>Pts</th><th>Wins</th><th>Pods</th><th>FL</th><th>DNFs</th>
        </tr></thead>
        <tbody>
        <?php foreach ($driverStandings as $d): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/person_detail.php?id=<?= $d['person_id'] ?>">
            <td><span class="position-badge pos-<?= $d['position'] <= 3 ? $d['position'] : 'other' ?>"><?= h((string)$d['position']) ?></span></td>
            <td>
                <strong>#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></strong>
                <div class="text-muted" style="font-size:0.75rem"><?= h($d['nationality']) ?></div>
            </td>
            <td class="text-muted"><?= h($d['short_name']) ?></td>
            <td><strong class="text-accent"><?= h(number_format((float)$d['points'],1)) ?></strong></td>
            <td><?= h((string)$d['wins']) ?></td>
            <td><?= h((string)$d['podiums']) ?></td>
            <td><?= h((string)$d['fastest_laps']) ?></td>
            <td><?= h((string)$d['dnfs']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Constructor Championship -->
<div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        <span>Constructor Championship</span>
        <a href="?season=<?= $selectedSeasonId ?>&export=constructors" class="btn btn-outline btn-sm">Export CSV</a>
    </div>
    <?php if (empty($constructorStandings)): ?>
    <div class="empty-state"><p>No standings yet.</p></div>
    <?php else: ?>
    <table class="sortable" id="constructor-standings-table">
        <thead><tr><th>Pos</th><th>Constructor</th><th>Pts</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($constructorStandings as $c): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/team_detail.php?id=<?= $c['team_id'] ?>">
            <td><span class="position-badge pos-<?= $c['position'] <= 3 ? $c['position'] : 'other' ?>"><?= h((string)$c['position']) ?></span></td>
            <td>
                <strong><?= h($c['name']) ?></strong>
                <div class="text-muted" style="font-size:0.75rem"><?= h($c['nationality']) ?></div>
            </td>
            <td><strong class="text-accent"><?= h(number_format((float)$c['points'],1)) ?></strong></td>
            <td><?= h((string)$c['wins']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
