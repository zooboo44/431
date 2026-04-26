<?php
$pageTitle   = 'Driver Dashboard';
$useChartJs  = true;
require_once __DIR__ . '/../includes/header.php';
requireRole('driver');

$db       = getDB();
$personId = intval($_SESSION['linked_id'] ?? 0);

if (!$personId) {
    echo '<div class="alert alert-danger">No driver profile linked to your account. Contact admin.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Driver info
$stmt = $db->prepare('SELECT * FROM people WHERE id = ?');
$stmt->execute([$personId]);
$driver = $stmt->fetch();
if (!$driver) {
    echo '<div class="alert alert-danger">Driver profile not found.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Career season stats (all seasons, for Chart.js)
$stmt = $db->prepare("
    SELECT s.year, s.id AS season_id, t.name AS team_name,
           ds_stand.points, ds_stand.wins, ds_stand.podiums, ds_stand.dnfs,
           ds_stand.fastest_laps, ds_stand.position
    FROM driver_seasons drs
    JOIN seasons s ON s.id = drs.season_id
    JOIN team_seasons ts ON ts.id = drs.team_season_id
    JOIN teams t ON t.id = ts.team_id
    LEFT JOIN driver_standings ds_stand ON ds_stand.person_id = drs.person_id AND ds_stand.season_id = s.id
    WHERE drs.person_id = ?
    ORDER BY s.year ASC
");
$stmt->execute([$personId]);
$seasonStats = $stmt->fetchAll();

// Active season info
$activeSeason = getActiveSeason();
$activeSeasonId = $activeSeason['id'] ?? null;
$currentSeason = null;
foreach ($seasonStats as $ss) {
    if ($ss['season_id'] == $activeSeasonId) { $currentSeason = $ss; break; }
}

// Last 5 race results (own data only — IDOR: WHERE re.person_id = personId)
$stmt = $db->prepare("
    SELECT r.name AS race_name, r.round_number, s.year, r.id AS race_id,
           rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
           qr.grid_position
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id AND re.person_id = ?
    JOIN races r ON r.id = re.race_id
    JOIN seasons s ON s.id = r.season_id
    LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
    ORDER BY r.race_date DESC
    LIMIT 5
");
$stmt->execute([$personId]);
$recentResults = $stmt->fetchAll();

// Totals
$totalPts  = array_sum(array_column($seasonStats, 'points'));
$totalWins = array_sum(array_column($seasonStats, 'wins'));
$totalPods = array_sum(array_column($seasonStats, 'podiums'));
$totalFl   = array_sum(array_column($seasonStats, 'fastest_laps'));

// Chart data: cumulative points per race for active season
$chartLabels = [];
$chartPoints = [];
if ($activeSeasonId) {
    $cpStmt = $db->prepare("
        SELECT r.round_number, r.name AS race_name,
               COALESCE(rr.points_scored, 0) AS pts
        FROM races r
        JOIN race_entries re ON re.race_id = r.id AND re.person_id = ?
        LEFT JOIN race_results rr ON rr.race_entry_id = re.id
        WHERE r.season_id = ? AND r.status = 'completed'
        ORDER BY r.round_number ASC
    ");
    $cpStmt->execute([$personId, $activeSeasonId]);
    $cumulative = 0;
    foreach ($cpStmt->fetchAll() as $row) {
        $cumulative += (float)$row['pts'];
        $chartLabels[] = 'Rd ' . $row['round_number'];
        $chartPoints[] = round($cumulative, 1);
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">#<?= h((string)$driver['racing_number']) ?> <?= h($driver['first_name'] . ' ' . $driver['last_name']) ?></h1>
        <p class="page-subtitle"><?= h($driver['nationality']) ?> &bull; Born <?= h(date('d M Y', strtotime($driver['date_of_birth']))) ?></p>
    </div>
</div>

<!-- Career stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-icon">&#127942;</div>
        <div class="stat-value"><?= h(number_format((float)$totalPts, 1)) ?></div>
        <div class="stat-label">Career Points</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#127937;</div>
        <div class="stat-value"><?= h((string)$totalWins) ?></div>
        <div class="stat-label">Wins</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#9654;</div>
        <div class="stat-value"><?= h((string)$totalPods) ?></div>
        <div class="stat-label">Podiums</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#9889;</div>
        <div class="stat-value"><?= h((string)$totalFl) ?></div>
        <div class="stat-label">Fastest Laps</div>
    </div>
</div>

<div class="grid-2">
    <!-- Points progression chart -->
    <div class="card">
        <div class="card-title">Points Progression</div>
        <?php if (count($chartLabels) > 0): ?>
        <canvas id="pointsChart" height="220"></canvas>
        <script>
        (function() {
            const ctx = document.getElementById('pointsChart');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chartLabels) ?>,
                    datasets: [{
                        label: 'Cumulative Points',
                        data: <?= json_encode($chartPoints) ?>,
                        borderColor: '#e10600',
                        backgroundColor: 'rgba(225,6,0,0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#e10600',
                        pointRadius: 5,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => ctx.parsed.y + ' pts' } }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8a8a8a' } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8a8a8a' }, beginAtZero: true }
                    }
                }
            });
        })();
        </script>
        <?php else: ?>
        <div class="empty-state"><p>No season data yet.</p></div>
        <?php endif; ?>
    </div>

    <!-- Season-by-season table -->
    <div class="card">
        <div class="card-title">Season History</div>
        <?php if (empty($seasonStats)): ?>
        <div class="empty-state"><p>No season data.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Year</th><th>Team</th><th>Pos</th><th>Pts</th><th>Wins</th><th>Pods</th><th></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($seasonStats) as $ss): ?>
            <tr>
                <td><strong><?= h((string)$ss['year']) ?></strong></td>
                <td class="text-muted"><?= h($ss['team_name']) ?></td>
                <td><?= $ss['position'] ? 'P' . h((string)$ss['position']) : '—' ?></td>
                <td class="text-accent fw-bold"><?= h(number_format((float)($ss['points'] ?? 0), 1)) ?></td>
                <td><?= h((string)($ss['wins'] ?? 0)) ?></td>
                <td><?= h((string)($ss['podiums'] ?? 0)) ?></td>
                <td><a href="<?= APP_URL ?>/driver/season.php?season_id=<?= $ss['season_id'] ?>" class="btn btn-outline btn-sm">View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Recent results -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Recent Races</div>
    <?php if (empty($recentResults)): ?>
    <div class="empty-state"><p>No results yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Year</th><th>Race</th><th>Grid</th><th>Finish</th><th>Status</th><th>Points</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentResults as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/driver/race.php?race_id=<?= (int)$r['race_id'] ?>">
            <td class="text-muted"><?= h((string)$r['year']) ?></td>
            <td><a href="<?= APP_URL ?>/driver/race.php?race_id=<?= (int)$r['race_id'] ?>" style="font-weight:600">Rd <?= h((string)$r['round_number']) ?> <?= h($r['race_name']) ?></a></td>
            <td class="text-muted"><?= $r['grid_position'] ? 'P' . h((string)$r['grid_position']) : '—' ?></td>
            <td>
                <?php if ($r['finish_position']): ?>
                <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                <?php else: ?>—<?php endif; ?>
                <?= $r['fastest_lap_bonus'] ? ' <span class="fl-indicator" title="Fastest Lap">&#9889;</span>' : '' ?>
            </td>
            <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
            <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
