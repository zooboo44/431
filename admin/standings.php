<?php
$pageTitle = 'Standings';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

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
    requireRole('admin');
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . ($type === 'drivers' ? 'driver' : 'constructor') . '_standings_' . $selectedYear . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($type === 'drivers') {
        fputcsv($out, ['Pos','Driver','#','Nationality','Team','Points','Wins','Podiums','Fastest Laps','DNFs']);
        $stmt = $db->prepare("
            SELECT RANK() OVER (ORDER BY COALESCE(SUM(rr.points_scored),0) DESC,
                                SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) DESC) AS position,
                   p.first_name, p.last_name, p.racing_number, p.nationality,
                   t.name AS team_name,
                   COALESCE(SUM(rr.points_scored),0) AS points,
                   SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins,
                   SUM(CASE WHEN rr.finish_position<=3 AND rr.finish_position IS NOT NULL AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS podiums,
                   SUM(CASE WHEN rr.fastest_lap_bonus=1 THEN 1 ELSE 0 END) AS fastest_laps,
                   SUM(CASE WHEN rr.status='DNF' AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS dnfs
            FROM race_results rr
            JOIN race_entries re ON re.id = rr.race_entry_id
            JOIN races r ON r.id = re.race_id
            JOIN people p ON p.id = re.person_id
            JOIN team_seasons ts ON ts.id = re.team_season_id
            JOIN teams t ON t.id = ts.team_id
            WHERE r.season_id = ? AND r.status = 'completed'
            GROUP BY p.id, t.id ORDER BY points DESC, wins DESC
        ");
        $stmt->execute([$selectedSeasonId]);
        foreach ($stmt->fetchAll() as $row) {
            fputcsv($out, [$row['position'], $row['first_name'].' '.$row['last_name'], '#'.$row['racing_number'], $row['nationality'], $row['team_name'], $row['points'], $row['wins'], $row['podiums'], $row['fastest_laps'], $row['dnfs']]);
        }
    } else {
        fputcsv($out, ['Pos','Team','Nationality','Points','Wins']);
        $stmt = $db->prepare("
            SELECT RANK() OVER (ORDER BY COALESCE(SUM(rr.points_scored),0) DESC,
                                SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) DESC) AS position,
                   t.name, t.nationality,
                   COALESCE(SUM(rr.points_scored),0) AS points,
                   SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins
            FROM race_results rr
            JOIN race_entries re ON re.id = rr.race_entry_id
            JOIN races r ON r.id = re.race_id
            JOIN team_seasons ts ON ts.id = re.team_season_id
            JOIN teams t ON t.id = ts.team_id
            WHERE r.season_id = ? AND r.status = 'completed'
            GROUP BY t.id ORDER BY points DESC, wins DESC
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
        SELECT RANK() OVER (ORDER BY COALESCE(SUM(rr.points_scored),0) DESC,
                            SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) DESC) AS position,
               COALESCE(SUM(rr.points_scored),0) AS points,
               SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins,
               SUM(CASE WHEN rr.finish_position<=3 AND rr.finish_position IS NOT NULL AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS podiums,
               SUM(CASE WHEN rr.status='DNF' AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS dnfs,
               SUM(CASE WHEN rr.fastest_lap_bonus=1 THEN 1 ELSE 0 END) AS fastest_laps,
               p.id AS person_id, p.first_name, p.last_name, p.racing_number, p.nationality,
               t.name AS team_name, t.short_name
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN races r ON r.id = re.race_id
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE r.season_id = ? AND r.status = 'completed'
        GROUP BY p.id, t.id
        ORDER BY points DESC, wins DESC
    ");
    $stmt->execute([$selectedSeasonId]);
    $driverStandings = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT RANK() OVER (ORDER BY COALESCE(SUM(rr.points_scored),0) DESC,
                            SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) DESC) AS position,
               COALESCE(SUM(rr.points_scored),0) AS points,
               SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins,
               t.id AS team_id, t.name, t.short_name, t.nationality
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN races r ON r.id = re.race_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE r.season_id = ? AND r.status = 'completed'
        GROUP BY t.id
        ORDER BY points DESC, wins DESC
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
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/people.php?id=<?= $d['person_id'] ?>">
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
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/teams.php?id=<?= $c['team_id'] ?>">
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
