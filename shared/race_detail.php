<?php
$pageTitle = 'Race Detail';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'team_manager', 'driver');

$db     = getDB();
$raceId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT r.*, c.name AS circuit, c.country, c.city, c.length_km, c.number_of_laps, c.id AS circuit_id,
           s.year AS season_year
    FROM races r
    JOIN circuits c ON c.id = r.circuit_id
    JOIN seasons s ON s.id = r.season_id
    WHERE r.id = ?
");
$stmt->execute([$raceId]);
$race = $stmt->fetch();

if (!$race || $race['status'] !== 'completed') {
    include __DIR__ . '/../includes/404.php'; exit;
}

$pageTitle = h($race['name']) . ' — Results';

// Race results
$stmt = $db->prepare("
    SELECT rr.finish_position, rr.start_position, rr.points_scored,
           rr.fastest_lap_ms, rr.fastest_lap_bonus, rr.laps_completed,
           rr.total_race_time_ms, rr.status,
           p.id AS person_id, p.first_name, p.last_name, p.racing_number,
           t.name AS team_name, t.short_name, t.id AS team_id
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
           p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.short_name, t.id AS team_id
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

// Sprint results
$sprintResults = [];
if ($race['has_sprint']) {
    $stmt = $db->prepare("
        SELECT sr.finish_position, sr.points_scored, sr.status,
               p.id AS person_id, p.first_name, p.last_name, p.racing_number, t.short_name
        FROM sprint_results sr
        JOIN race_entries re ON re.id = sr.race_entry_id
        JOIN people p ON p.id = re.person_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        JOIN teams t ON t.id = ts.team_id
        WHERE re.race_id = ?
        ORDER BY CASE WHEN sr.finish_position IS NULL THEN 1 ELSE 0 END, sr.finish_position ASC
    ");
    $stmt->execute([$raceId]);
    $sprintResults = $stmt->fetchAll();
}

// Penalties
$stmt = $db->prepare("
    SELECT pen.penalty_type, pen.reason, pen.time_penalty_s, pen.grid_penalty_positions,
           pen.licence_points_awarded, pen.is_dsq,
           p.id AS person_id, p.first_name, p.last_name, p.racing_number
    FROM penalties pen
    JOIN people p ON p.id = pen.person_id
    WHERE pen.race_id = ?
    ORDER BY pen.issued_at DESC
");
$stmt->execute([$raceId]);
$penalties = $stmt->fetchAll();

$role = $_SESSION['role'] ?? '';
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h($race['name']) ?></h1>
        <p class="page-subtitle">
            Round <?= h((string)$race['round_number']) ?> &bull;
            <?= h($race['circuit']) ?>, <?= h($race['city']) ?>, <?= h($race['country']) ?> &bull;
            <?= h(date('d M Y', strtotime($race['race_date']))) ?>
            <?= $race['season_year'] ? ' &bull; ' . h((string)$race['season_year']) . ' Season' : '' ?>
        </p>
    </div>
    <a href="<?= APP_URL ?>/shared/results.php" class="btn btn-outline">&larr; All Results</a>
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
                <th>Pos</th><th>Driver</th><th>Team</th><th>Grid</th><th>Laps</th><th>Status</th><th>Fastest Lap</th><th>Points</th>
            </tr></thead>
            <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td>
                    <?php if ($r['finish_position']): ?>
                    <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$r['person_id'] ?>" style="font-weight:600"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></a>
                    <div style="font-size:0.75rem;color:var(--text-muted)">#<?= h((string)$r['racing_number']) ?></div>
                </td>
                <td><a href="<?= APP_URL ?>/shared/team_detail.php?id=<?= (int)$r['team_id'] ?>" class="text-muted"><?= h($r['short_name']) ?></a></td>
                <td class="text-muted"><?= h((string)$r['start_position']) ?></td>
                <td class="text-muted"><?= h((string)$r['laps_completed']) ?></td>
                <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
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

<!-- Qualifying -->
<?php if ($qualifying): ?>
<div class="card">
    <div class="card-title">&#9201; Qualifying Results</div>
    <div class="table-container" style="border:0;margin-bottom:0">
        <table class="sortable">
            <thead><tr>
                <th>Grid</th><th>Driver</th><th>Team</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Elim.</th>
            </tr></thead>
            <tbody>
            <?php foreach ($qualifying as $q): ?>
            <tr>
                <td><span class="position-badge pos-<?= $q['grid_position'] <= 3 ? $q['grid_position'] : 'other' ?>"><?= h((string)$q['grid_position']) ?></span></td>
                <td>
                    <a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$q['person_id'] ?>" style="font-weight:600"><?= h($q['first_name'] . ' ' . $q['last_name']) ?></a>
                    <span class="text-muted" style="font-size:0.8rem"> #<?= h((string)$q['racing_number']) ?></span>
                </td>
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

<!-- Sprint Results -->
<?php if (!empty($sprintResults)): ?>
<div class="card">
    <div class="card-title">&#9889; Sprint Results</div>
    <div class="table-container" style="border:0;margin-bottom:0">
        <table>
            <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Status</th><th>Points</th></tr></thead>
            <tbody>
            <?php foreach ($sprintResults as $sr): ?>
            <tr>
                <td>
                    <?php if ($sr['finish_position']): ?>
                    <span class="position-badge pos-<?= $sr['finish_position'] <= 3 ? $sr['finish_position'] : 'other' ?>"><?= h((string)$sr['finish_position']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$sr['person_id'] ?>" style="font-weight:600"><?= h($sr['first_name'] . ' ' . $sr['last_name']) ?></a>
                    <span class="text-muted" style="font-size:0.8rem"> #<?= h((string)$sr['racing_number']) ?></span>
                </td>
                <td class="text-muted"><?= h($sr['short_name']) ?></td>
                <td><span class="status-badge status-<?= h(strtolower($sr['status'])) ?>"><?= h($sr['status']) ?></span></td>
                <td><strong class="text-accent"><?= h((string)$sr['points_scored']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Penalties -->
<?php if (!empty($penalties)): ?>
<div class="card">
    <div class="card-title">&#9888; Penalties</div>
    <?php foreach ($penalties as $pen): ?>
    <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$pen['person_id'] ?>" style="font-weight:600"><?= h($pen['first_name'] . ' ' . $pen['last_name']) ?></a>
                <span class="text-muted" style="font-size:0.8rem"> #<?= h((string)$pen['racing_number']) ?></span>
                <span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>" style="margin-left:0.5rem"><?= h(str_replace('_', ' ', $pen['penalty_type'])) ?></span>
            </div>
        </div>
        <p style="margin-top:0.4rem;font-size:0.85rem;color:var(--text-secondary)"><?= h($pen['reason']) ?></p>
        <?php if ($pen['time_penalty_s']): ?><div style="font-size:0.8rem"><strong>+<?= h((string)$pen['time_penalty_s']) ?>s</strong> time penalty</div><?php endif; ?>
        <?php if ($pen['grid_penalty_positions']): ?><div style="font-size:0.8rem"><strong><?= h((string)$pen['grid_penalty_positions']) ?> place</strong> grid drop</div><?php endif; ?>
        <?php if ($pen['licence_points_awarded']): ?><div style="font-size:0.8rem"><strong><?= h((string)$pen['licence_points_awarded']) ?> licence point<?= $pen['licence_points_awarded'] != 1 ? 's' : '' ?></strong></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
