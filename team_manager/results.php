<?php
$pageTitle = 'Team Results';
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

// IDOR: always filter by teamId in the query
$stmt = $db->prepare("
    SELECT r.name AS race_name, r.round_number, r.race_date, s.year,
           p.id AS person_id, p.first_name, p.last_name, p.racing_number,
           rr.finish_position, rr.start_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
           rr.laps_completed, qr.grid_position
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id
    JOIN team_seasons ts ON ts.id = re.team_season_id AND ts.team_id = ?
    JOIN races r ON r.id = re.race_id AND r.season_id = ?
    JOIN seasons s ON s.id = r.season_id
    JOIN people p ON p.id = re.person_id
    LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
    ORDER BY r.round_number ASC, rr.finish_position ASC
");
$stmt->execute([$teamId, $selectedSeasonId]);
$results = $stmt->fetchAll();

$selectedYear = '';
foreach ($seasons as $s) { if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; } }
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Race Results</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Season' : '' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <form class="season-selector" method="get">
            <select name="season" onchange="this.form.submit()">
                <?php foreach ($seasons as $s): ?>
                <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>><?= h((string)$s['year']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="<?= APP_URL ?>/team_manager/export.php?type=results&season=<?= $selectedSeasonId ?>" class="btn btn-outline">Export CSV</a>
    </div>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="results-table" placeholder="Search...">
        </div>
    </div>
    <table class="sortable" id="results-table">
        <thead><tr>
            <th>Rd</th><th>Race</th><th>Driver</th><th>Grid</th><th>Start</th>
            <th>Finish</th><th>Status</th><th>Laps</th><th>Points</th>
        </tr></thead>
        <tbody>
        <?php if (empty($results)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:2rem">No results for this season.</td></tr>
        <?php else: ?>
        <?php foreach ($results as $r): ?>
        <tr>
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><?= h($r['race_name']) ?></td>
            <td><a href="<?= APP_URL ?>/team_manager/driver_profile.php?person_id=<?= (int)$r['person_id'] ?>" style="font-weight:600">#<?= h((string)$r['racing_number']) ?> <?= h($r['first_name'] . ' ' . $r['last_name']) ?></a></td>
            <td class="text-muted"><?= $r['grid_position'] ? 'P' . h((string)$r['grid_position']) : '—' ?></td>
            <td class="text-muted"><?= 'P' . h((string)$r['start_position']) ?></td>
            <td>
                <?php if ($r['finish_position']): ?>
                <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                <?php else: ?>—<?php endif; ?>
                <?= $r['fastest_lap_bonus'] ? ' <span class="fl-indicator" title="Fastest Lap">&#9889;</span>' : '' ?>
            </td>
            <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
            <td class="text-muted"><?= h((string)$r['laps_completed']) ?></td>
            <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
