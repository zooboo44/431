<?php
$pageTitle = 'Race Detail';
require_once __DIR__ . '/../includes/header_public.php';

$db = getDB();
$raceId = intval($_GET['id'] ?? 0);

if (!$raceId) {
    include __DIR__ . '/../includes/404.php';
    exit;
}

$stmt = $db->prepare("
    SELECT r.*, c.name AS circuit, c.country, c.city, c.length_km, c.number_of_laps,
           s.year AS season_year
    FROM races r
    JOIN circuits c ON c.id = r.circuit_id
    JOIN seasons s ON s.id = r.season_id
    WHERE r.id = ?
");
$stmt->execute([$raceId]);
$race = $stmt->fetch();

if (!$race || $race['status'] !== 'completed') {
    include __DIR__ . '/../includes/404.php';
    exit;
}

$pageTitle = h($race['name']) . ' — Results';

// Race results
$stmt = $db->prepare("
    SELECT rr.finish_position, rr.start_position, rr.points_scored,
           rr.fastest_lap_ms, rr.fastest_lap_bonus, rr.laps_completed,
           rr.total_race_time_ms, rr.status,
           p.first_name, p.last_name, p.racing_number,
           t.name AS team_name, t.short_name
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ?
    ORDER BY
        CASE WHEN rr.finish_position IS NULL THEN 1 ELSE 0 END,
        rr.finish_position ASC
");
$stmt->execute([$raceId]);
$results = $stmt->fetchAll();

// Qualifying results
$stmt = $db->prepare("
    SELECT qr.grid_position, qr.q1_time_ms, qr.q2_time_ms, qr.q3_time_ms, qr.eliminated_in,
           p.first_name, p.last_name, p.racing_number, t.short_name
    FROM qualifying_results qr
    JOIN race_entries re ON re.id = qr.race_entry_id
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    WHERE re.race_id = ?
    ORDER BY qr.grid_position ASC
");
$stmt->execute([$raceId]);
$qualifying = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title"><?= h($race['name']) ?></h1>
            <p class="page-subtitle">
                Round <?= h((string)$race['round_number']) ?> &bull;
                <?= h($race['circuit']) ?>, <?= h($race['city']) ?>, <?= h($race['country']) ?> &bull;
                <?= h(date('d M Y', strtotime($race['race_date']))) ?>
            </p>
        </div>
        <a href="<?= APP_URL ?>/public/results.php" class="btn btn-outline">&larr; All Results</a>
    </div>

    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="stat-card">
            <div class="stat-value"><?= h((string)$race['number_of_laps']) ?></div>
            <div class="stat-label">Total Laps</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= h(number_format($race['length_km'], 3)) ?></div>
            <div class="stat-label">Circuit Length (km)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= h(number_format($race['length_km'] * $race['number_of_laps'], 2)) ?></div>
            <div class="stat-label">Race Distance (km)</div>
        </div>
    </div>

    <!-- Race Results -->
    <div class="card">
        <div class="card-title">&#127937; Race Result</div>
        <?php if (empty($results)): ?>
        <div class="empty-state"><p>No results available.</p></div>
        <?php else: ?>
        <div class="table-container" style="border:0;margin-bottom:0">
            <table class="sortable" id="race-results-table">
                <thead><tr>
                    <th>Pos</th>
                    <th>Driver</th>
                    <th>Team</th>
                    <th>Grid</th>
                    <th>Laps</th>
                    <th>Status</th>
                    <th>Fastest Lap</th>
                    <th>Points</th>
                </tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td>
                        <?php if ($r['finish_position']): ?>
                        <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= h($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--text-muted)">#<?= h((string)$r['racing_number']) ?></div>
                    </td>
                    <td class="text-muted"><?= h($r['short_name']) ?></td>
                    <td class="text-muted"><?= h((string)$r['start_position']) ?></td>
                    <td class="text-muted"><?= h((string)$r['laps_completed']) ?></td>
                    <td>
                        <span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span>
                    </td>
                    <td class="mono">
                        <?php if ($r['fastest_lap_ms']): ?>
                        <?php if ($r['fastest_lap_bonus']): ?>
                        <span class="fl-indicator">&#9889; <?= h(formatLapTime($r['fastest_lap_ms'])) ?></span>
                        <?php else: ?>
                        <?= h(formatLapTime($r['fastest_lap_ms'])) ?>
                        <?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><strong class="text-accent"><?= h((string)$r['points_scored']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:0.75rem">
            <button class="btn btn-outline btn-sm" data-export-csv="race-results-table" data-filename="race_<?= h((string)$raceId) ?>_results.csv">Export CSV</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Qualifying Results -->
    <?php if ($qualifying): ?>
    <div class="card">
        <div class="card-title">&#9201; Qualifying Results</div>
        <div class="table-container" style="border:0;margin-bottom:0">
            <table class="sortable">
                <thead><tr>
                    <th>Grid</th>
                    <th>Driver</th>
                    <th>Team</th>
                    <th>Q1</th>
                    <th>Q2</th>
                    <th>Q3</th>
                    <th>Elim.</th>
                </tr></thead>
                <tbody>
                <?php foreach ($qualifying as $q): ?>
                <tr>
                    <td><span class="position-badge pos-<?= $q['grid_position'] <= 3 ? $q['grid_position'] : 'other' ?>"><?= h((string)$q['grid_position']) ?></span></td>
                    <td><strong><?= h($q['first_name'] . ' ' . $q['last_name']) ?></strong> <span class="text-muted" style="font-size:0.8rem">#<?= h((string)$q['racing_number']) ?></span></td>
                    <td class="text-muted"><?= h($q['short_name']) ?></td>
                    <td class="mono"><?= $q['q1_time_ms'] ? h(formatLapTime($q['q1_time_ms'])) : '—' ?></td>
                    <td class="mono"><?= $q['q2_time_ms'] ? h(formatLapTime($q['q2_time_ms'])) : '—' ?></td>
                    <td class="mono"><?= $q['q3_time_ms'] ? h(formatLapTime($q['q3_time_ms'])) : '—' ?></td>
                    <td><?= $q['eliminated_in'] ? '<span class="status-badge status-dnf">' . h($q['eliminated_in']) . '</span>' : '<span class="status-badge status-finished">Q3</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<footer style="background:var(--bg-surface);border-top:1px solid var(--border);text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.8rem;margin-top:2rem">
    &copy; <?= date('Y') ?> <?= h(APP_NAME) ?>
</footer>

</main>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
