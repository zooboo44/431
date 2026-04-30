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
    SELECT s.year, s.id AS season_id, t.id AS team_id, t.name AS team_name,
           COALESCE((
               SELECT SUM(rr2.points_scored)
               FROM race_results rr2
               JOIN race_entries re2 ON re2.id = rr2.race_entry_id
               JOIN races r2 ON r2.id = re2.race_id
               WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
           ),0) AS points,
           COALESCE((
               SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
               FROM race_results rr2
               JOIN race_entries re2 ON re2.id = rr2.race_entry_id
               JOIN races r2 ON r2.id = re2.race_id
               WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
           ),0) AS wins,
           COALESCE((
               SELECT SUM(CASE WHEN rr2.finish_position<=3 AND rr2.finish_position IS NOT NULL AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
               FROM race_results rr2
               JOIN race_entries re2 ON re2.id = rr2.race_entry_id
               JOIN races r2 ON r2.id = re2.race_id
               WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
           ),0) AS podiums,
           COALESCE((
               SELECT SUM(CASE WHEN rr2.status='DNF' AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
               FROM race_results rr2
               JOIN race_entries re2 ON re2.id = rr2.race_entry_id
               JOIN races r2 ON r2.id = re2.race_id
               WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
           ),0) AS dnfs,
           COALESCE((
               SELECT SUM(CASE WHEN rr2.fastest_lap_bonus=1 THEN 1 ELSE 0 END)
               FROM race_results rr2
               JOIN race_entries re2 ON re2.id = rr2.race_entry_id
               JOIN races r2 ON r2.id = re2.race_id
               WHERE r2.season_id = s.id AND r2.status='completed' AND re2.person_id = drs.person_id
           ),0) AS fastest_laps,
           NULL AS position
    FROM driver_seasons drs
    JOIN seasons s ON s.id = drs.season_id
    JOIN team_seasons ts ON ts.id = drs.team_season_id
    JOIN teams t ON t.id = ts.team_id
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
        <?php if (count($chartLabels) >= 2): ?>
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
        <?php elseif (count($chartLabels) === 1): ?>
        <div class="empty-state"><p>Not enough race data to display chart. Participate in at least 2 races.</p></div>
        <?php else: ?>
        <div class="empty-state"><p>No race data yet for this season.</p></div>
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
                <td><a href="<?= APP_URL ?>/shared/standings.php?view=team&id=<?= (int)$ss['team_id'] ?>" class="text-muted"><?= h($ss['team_name']) ?></a></td>
                <td><?= $ss['position'] ? 'P' . h((string)$ss['position']) : '—' ?></td>
                <td class="text-accent fw-bold"><?= h(number_format((float)($ss['points'] ?? 0), 1)) ?></td>
                <td><?= h((string)($ss['wins'] ?? 0)) ?></td>
                <td><?= h((string)($ss['podiums'] ?? 0)) ?></td>
                <td><a href="<?= APP_URL ?>/shared/standings.php?season=<?= $ss['season_id'] ?>" class="btn btn-outline btn-sm">View</a></td>
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
        <thead><tr><th>Year</th><th>Race</th><th>Grid</th><th>Finish</th><th>Status</th><th>Points</th></tr></thead>
        <tbody>
        <?php foreach ($recentResults as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/results.php?id=<?= (int)$r['race_id'] ?>">
            <td class="text-muted"><?= h((string)$r['year']) ?></td>
            <td style="font-weight:600">Rd <?= h((string)$r['round_number']) ?> <?= h($r['race_name']) ?></td>
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

<!-- Penalties -->
<?php
$penStmt = $db->prepare("
    SELECT pen.penalty_type, pen.reason, pen.time_penalty_s, pen.grid_penalty_positions,
           pen.licence_points_awarded, pen.is_dsq, pen.issued_at,
           r.name AS race_name, r.round_number, s.year,
           u.name AS issued_by_name
    FROM penalties pen
    JOIN races r ON r.id = pen.race_id
    JOIN seasons s ON s.id = r.season_id
    JOIN users u ON u.id = pen.issued_by
    WHERE pen.person_id = ?
    ORDER BY pen.issued_at DESC
");
$penStmt->execute([$personId]);
$myPenalties = $penStmt->fetchAll();
$totalLicPts = array_sum(array_column($myPenalties, 'licence_points_awarded'));
$dsqCount    = count(array_filter($myPenalties, fn($p) => $p['is_dsq']));
?>
<div class="card" style="margin-top:1.5rem" id="penalties">
    <div class="card-title">&#9888; Penalties (<?= count($myPenalties) ?>)</div>
    <?php if ($totalLicPts > 0 || $dsqCount > 0): ?>
    <div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:1rem;max-width:400px">
        <div class="stat-card"><div class="stat-value text-warning"><?= h((string)$totalLicPts) ?></div><div class="stat-label">Licence Points</div></div>
        <div class="stat-card"><div class="stat-value"><?= h((string)$dsqCount) ?></div><div class="stat-label">Disqualifications</div></div>
    </div>
    <?php endif; ?>
    <?php if (empty($myPenalties)): ?>
    <div class="empty-state"><p>No penalties on record. Keep it clean!</p></div>
    <?php else: ?>
    <?php foreach ($myPenalties as $pen): ?>
    <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem">
            <div>
                <span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>"><?= h(str_replace('_', ' ', strtoupper($pen['penalty_type']))) ?></span>
                <span class="text-muted" style="margin-left:0.5rem;font-size:0.85rem"><?= h((string)$pen['year']) ?> Rd <?= h((string)$pen['round_number']) ?> — <?= h($pen['race_name']) ?></span>
            </div>
            <span class="text-muted" style="font-size:0.8rem"><?= h(date('d M Y', strtotime($pen['issued_at']))) ?></span>
        </div>
        <p style="margin:0.5rem 0 0.3rem;color:var(--text-secondary)"><?= h($pen['reason']) ?></p>
        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:0.85rem">
            <?php if ($pen['time_penalty_s']): ?><span><strong>+<?= h((string)$pen['time_penalty_s']) ?>s</strong> time penalty</span><?php endif; ?>
            <?php if ($pen['grid_penalty_positions']): ?><span><strong><?= h((string)$pen['grid_penalty_positions']) ?> place<?= $pen['grid_penalty_positions'] != 1 ? 's' : '' ?></strong> grid drop</span><?php endif; ?>
            <?php if ($pen['licence_points_awarded']): ?><span class="text-warning"><strong><?= h((string)$pen['licence_points_awarded']) ?> licence point<?= $pen['licence_points_awarded'] != 1 ? 's' : '' ?></strong></span><?php endif; ?>
        </div>
        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem">Issued by <?= h($pen['issued_by_name']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
