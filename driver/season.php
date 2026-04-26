<?php
$pageTitle = 'Season Detail';
require_once __DIR__ . '/../includes/header.php';
requireRole('driver');

$db       = getDB();
$personId = intval($_SESSION['linked_id'] ?? 0);
$seasonId = intval($_GET['season_id'] ?? 0);

if (!$personId || !$seasonId) {
    include __DIR__ . '/../includes/403.php'; exit;
}

// IDOR: verify this driver participated in this season
$stmt = $db->prepare("
    SELECT drs.id, s.year, t.name AS team_name, ts.car_name, ts.power_unit,
           ds.points, ds.wins, ds.podiums, ds.dnfs, ds.fastest_laps, ds.position
    FROM driver_seasons drs
    JOIN seasons s ON s.id = drs.season_id AND s.id = ?
    JOIN team_seasons ts ON ts.id = drs.team_season_id
    JOIN teams t ON t.id = ts.team_id
    LEFT JOIN driver_standings ds ON ds.person_id = drs.person_id AND ds.season_id = s.id
    WHERE drs.person_id = ?
    LIMIT 1
");
$stmt->execute([$seasonId, $personId]);
$season = $stmt->fetch();
if (!$season) { include __DIR__ . '/../includes/403.php'; exit; }

// All race results this season (own data only)
$stmt = $db->prepare("
    SELECT r.id AS race_id, r.name AS race_name, r.round_number, r.race_date,
           rr.finish_position, rr.start_position, rr.points_scored, rr.status,
           rr.fastest_lap_bonus, rr.laps_completed,
           qr.grid_position,
           (SELECT COUNT(*) FROM pit_stops ps JOIN race_entries re2 ON re2.id = ps.race_entry_id
            WHERE re2.race_id = r.id AND re2.person_id = ?) AS pit_count
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id AND re.person_id = ?
    JOIN races r ON r.id = re.race_id AND r.season_id = ?
    LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
    ORDER BY r.round_number ASC
");
$stmt->execute([$personId, $personId, $seasonId]);
$results = $stmt->fetchAll();

$pageTitle = h((string)$season['year']) . ' Season — ' . h($season['team_name']);
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h((string)$season['year']) ?> Season</h1>
        <p class="page-subtitle"><?= h($season['team_name']) ?> &bull; <?= h($season['car_name']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/driver/dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
</div>

<!-- Season summary stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-value"><?= $season['position'] ? 'P' . h((string)$season['position']) : '—' ?></div>
        <div class="stat-label">Championship</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-accent"><?= h(number_format((float)($season['points'] ?? 0), 1)) ?></div>
        <div class="stat-label">Points</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)($season['wins'] ?? 0)) ?></div>
        <div class="stat-label">Wins</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)($season['podiums'] ?? 0)) ?></div>
        <div class="stat-label">Podiums</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)($season['fastest_laps'] ?? 0)) ?></div>
        <div class="stat-label">Fastest Laps</div>
    </div>
</div>

<!-- Race-by-race results -->
<div class="table-container">
    <div class="card-title" style="padding:0 0 1rem">Race Results</div>
    <table class="sortable">
        <thead><tr>
            <th>Rd</th><th>Race</th><th>Date</th><th>Grid</th><th>Start</th>
            <th>Finish</th><th>Status</th><th>Laps</th><th>Stops</th><th>Points</th><th></th>
        </tr></thead>
        <tbody>
        <?php if (empty($results)): ?>
        <tr><td colspan="11" class="text-center text-muted" style="padding:2rem">No race results for this season.</td></tr>
        <?php else: ?>
        <?php foreach ($results as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/driver/race.php?race_id=<?= (int)$r['race_id'] ?>">
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><a href="<?= APP_URL ?>/driver/race.php?race_id=<?= (int)$r['race_id'] ?>" style="font-weight:600"><?= h($r['race_name']) ?></a></td>
            <td class="text-muted"><?= h(date('d M', strtotime($r['race_date']))) ?></td>
            <td class="text-muted"><?= $r['grid_position'] ? 'P' . h((string)$r['grid_position']) : '—' ?></td>
            <td class="text-muted"><?= $r['start_position'] ? 'P' . h((string)$r['start_position']) : '—' ?></td>
            <td>
                <?php if ($r['finish_position']): ?>
                <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                <?php else: ?>—<?php endif; ?>
                <?= $r['fastest_lap_bonus'] ? ' <span class="fl-indicator" title="Fastest Lap">&#9889;</span>' : '' ?>
            </td>
            <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
            <td class="text-muted"><?= h((string)($r['laps_completed'] ?? '—')) ?></td>
            <td class="text-muted"><?= h((string)$r['pit_count']) ?></td>
            <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
