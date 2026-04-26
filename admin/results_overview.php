<?php
$pageTitle = 'Results Overview';
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

// CSV export — all race results for the season
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="results_' . $selectedYear . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Season','Round','Race','Driver','#','Team','Start','Finish','Status','Points','FL Bonus','Laps','Fastest Lap (s)','Total Time (s)']);
    $stmt = $db->prepare("
        SELECT s.year, r.round_number, r.name AS race_name,
               p.first_name, p.last_name, p.racing_number,
               t.name AS team_name,
               rr.start_position, rr.finish_position, rr.status, rr.points_scored,
               rr.fastest_lap_bonus, rr.laps_completed, rr.fastest_lap_ms, rr.total_race_time_ms
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN races r ON r.id = re.race_id
        JOIN seasons s ON s.id = r.season_id
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE s.id = ?
        ORDER BY r.round_number ASC, rr.finish_position ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            $row['year'], $row['round_number'], $row['race_name'],
            $row['first_name'] . ' ' . $row['last_name'], '#' . $row['racing_number'],
            $row['team_name'], $row['start_position'], $row['finish_position'] ?? '',
            $row['status'], number_format((float)$row['points_scored'], 1),
            $row['fastest_lap_bonus'] ? 'Yes' : '',
            $row['laps_completed'],
            $row['fastest_lap_ms'] ? round($row['fastest_lap_ms'] / 1000, 3) : '',
            $row['total_race_time_ms'] ? round($row['total_race_time_ms'] / 1000, 3) : '',
        ]);
    }
    fclose($out);
    exit;
}

// Races for the selected season with result counts
$races = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.has_sprint, r.status,
               c.name AS circuit_name, c.country,
               (SELECT COUNT(*) FROM race_entries re WHERE re.race_id = r.id) AS entry_count,
               (SELECT COUNT(*) FROM qualifying_results qr JOIN race_entries re2 ON re2.id = qr.race_entry_id WHERE re2.race_id = r.id) AS qual_count,
               (SELECT COUNT(*) FROM race_results rr JOIN race_entries re3 ON re3.id = rr.race_entry_id WHERE re3.race_id = r.id) AS result_count,
               (SELECT CONCAT(p.first_name,' ',p.last_name)
                FROM race_results rr2
                JOIN race_entries re4 ON re4.id = rr2.race_entry_id
                JOIN people p ON p.id = re4.person_id
                WHERE re4.race_id = r.id AND rr2.finish_position = 1 LIMIT 1) AS winner
        FROM races r
        JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ?
        ORDER BY r.round_number ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $races = $stmt->fetchAll();
}

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Results Overview</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Season' : 'Select a season' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <form method="get">
            <select name="season" class="form-control" style="width:auto" onchange="this.form.submit()">
                <?php foreach ($seasons as $s): ?>
                <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>><?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="?season=<?= $selectedSeasonId ?>&export=1" class="btn btn-outline">Export CSV</a>
    </div>
</div>

<div class="card">
    <div class="card-title">Race Results — <?= $selectedYear ? h((string)$selectedYear) : '' ?> (<?= count($races) ?> races)</div>
    <?php if (empty($races)): ?>
    <div class="empty-state"><p>No races found for this season.</p></div>
    <?php else: ?>
    <table class="sortable" id="results-overview-table">
        <thead><tr>
            <th data-sort="0">Rd</th>
            <th data-sort="1">Race</th>
            <th data-sort="2">Circuit</th>
            <th data-sort="3">Date</th>
            <th data-sort="4">Status</th>
            <th>Sprint</th>
            <th data-sort="6">Entries</th>
            <th data-sort="7">Qual</th>
            <th data-sort="8">Results</th>
            <th>Winner</th>
        </tr></thead>
        <tbody>
        <?php foreach ($races as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $r['id'] ?>">
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td class="text-muted"><?= h($r['circuit_name']) ?>, <?= h($r['country']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><span class="status-badge status-<?= h($r['status']) ?>"><?= h(str_replace('_', ' ', $r['status'])) ?></span></td>
            <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></td>
            <td><?= h((string)$r['entry_count']) ?></td>
            <td><?= $r['qual_count'] > 0 ? '<span class="text-success">' . h((string)$r['qual_count']) . '</span>' : '<span class="text-muted">0</span>' ?></td>
            <td><?= $r['result_count'] > 0 ? '<span class="text-success">' . h((string)$r['result_count']) . '</span>' : '<span class="text-muted">0</span>' ?></td>
            <td class="text-muted" style="font-size:0.85rem"><?= $r['winner'] ? h($r['winner']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
