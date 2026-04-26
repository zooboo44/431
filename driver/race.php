<?php
$pageTitle = 'Race Detail';
require_once __DIR__ . '/../includes/header.php';
requireRole('driver');

$db       = getDB();
$personId = intval($_SESSION['linked_id'] ?? 0);
$raceId   = intval($_GET['race_id'] ?? 0);

if (!$personId || !$raceId) { include __DIR__ . '/../includes/403.php'; exit; }

// IDOR: verify driver has a race entry in this race
$stmt = $db->prepare("
    SELECT re.id AS entry_id, r.name AS race_name, r.round_number, r.race_date,
           c.name AS circuit_name, c.city AS location, c.country, s.year
    FROM race_entries re
    JOIN races r ON r.id = re.race_id AND r.id = ? AND r.status = 'completed'
    JOIN circuits c ON c.id = r.circuit_id
    JOIN seasons s ON s.id = r.season_id
    WHERE re.person_id = ?
    LIMIT 1
");
$stmt->execute([$raceId, $personId]);
$meta = $stmt->fetch();
if (!$meta) { include __DIR__ . '/../includes/403.php'; exit; }

$entryId = $meta['entry_id'];

// Own race result
$stmt = $db->prepare("
    SELECT rr.finish_position, rr.start_position, rr.points_scored, rr.status,
           rr.fastest_lap_bonus, rr.laps_completed
    FROM race_results rr WHERE rr.race_entry_id = ?
");
$stmt->execute([$entryId]);
$result = $stmt->fetch();

// Qualifying result
$stmt = $db->prepare("
    SELECT grid_position, q1_time_ms, q2_time_ms, q3_time_ms, eliminated_in
    FROM qualifying_results WHERE race_entry_id = ?
");
$stmt->execute([$entryId]);
$qualifying = $stmt->fetch();

// Pit stops
$stmt = $db->prepare("
    SELECT stop_number, lap_number, duration_ms, tyre_in, tyre_out
    FROM pit_stops WHERE race_entry_id = ? ORDER BY stop_number ASC
");
$stmt->execute([$entryId]);
$pitStops = $stmt->fetchAll();

// Lap telemetry
$stmt = $db->prepare("
    SELECT lap_number, lap_time_ms, sector1_ms, sector2_ms, sector3_ms,
           speed_trap_kmh, tyre_compound, tyre_age_laps, is_pit_lap
    FROM lap_telemetry WHERE race_entry_id = ? ORDER BY lap_number ASC
");
$stmt->execute([$entryId]);
$telemetry = $stmt->fetchAll();

// Find fastest lap for this driver
$fastestLap = null;
if (!empty($telemetry)) {
    $min = PHP_INT_MAX;
    foreach ($telemetry as $t) { if ($t['lap_time_ms'] < $min) { $min = $t['lap_time_ms']; $fastestLap = $t; } }
}

// Full race result table (all drivers)
$stmt = $db->prepare("
    SELECT rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
           p.first_name, p.last_name, p.racing_number, p.id AS person_id,
           t.name AS team_name,
           qr.grid_position
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id AND re.race_id = ?
    JOIN people p ON p.id = re.person_id
    JOIN team_seasons ts ON ts.id = re.team_season_id
    JOIN teams t ON t.id = ts.team_id
    LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
    ORDER BY COALESCE(rr.finish_position, 99), p.racing_number
");
$stmt->execute([$raceId]);
$allResults = $stmt->fetchAll();

// Penalties for this race related to this driver
$stmt = $db->prepare("
    SELECT pen.penalty_type, pen.reason, pen.time_penalty_s, pen.grid_penalty_positions,
           pen.licence_points_awarded, pen.is_dsq, pen.issued_at
    FROM penalties pen WHERE pen.race_id = ? AND pen.person_id = ?
    ORDER BY pen.issued_at DESC
");
$stmt->execute([$raceId, $personId]);
$racePenalties = $stmt->fetchAll();

$pageTitle = 'Rd ' . $meta['round_number'] . ' ' . $meta['race_name'] . ' ' . $meta['year'];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Rd <?= h((string)$meta['round_number']) ?> — <?= h($meta['race_name']) ?></h1>
        <p class="page-subtitle"><?= h($meta['circuit_name']) ?> &bull; <?= h(date('d M Y', strtotime($meta['race_date']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/driver/dashboard.php" class="btn btn-outline">&larr; Dashboard</a>
</div>

<!-- Result summary -->
<?php if ($result): ?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-value">
            <?php if ($result['finish_position']): ?>
            <span class="position-badge pos-<?= $result['finish_position'] <= 3 ? $result['finish_position'] : 'other' ?>" style="font-size:1.5rem"><?= h((string)$result['finish_position']) ?></span>
            <?php else: ?>—<?php endif; ?>
        </div>
        <div class="stat-label">Finish</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $qualifying ? 'P' . h((string)$qualifying['grid_position']) : '—' ?></div>
        <div class="stat-label">Grid Position</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-accent"><?= h((string)$result['points_scored']) ?></div>
        <div class="stat-label">Points<?= $result['fastest_lap_bonus'] ? ' <span class="fl-indicator">&#9889;</span>' : '' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)($result['laps_completed'] ?? '—')) ?></div>
        <div class="stat-label">Laps Completed</div>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">
    <!-- Qualifying -->
    <div class="card">
        <div class="card-title">Qualifying</div>
        <?php if ($qualifying): ?>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Grid Position</div>
                <div class="info-value">P<?= h((string)$qualifying['grid_position']) ?></div>
            </div>
            <?php if ($qualifying['q1_time_ms']): ?>
            <div class="info-item">
                <div class="info-label">Q1</div>
                <div class="info-value"><?= h(formatLapTime($qualifying['q1_time_ms'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($qualifying['q2_time_ms']): ?>
            <div class="info-item">
                <div class="info-label">Q2</div>
                <div class="info-value"><?= h(formatLapTime($qualifying['q2_time_ms'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($qualifying['q3_time_ms']): ?>
            <div class="info-item">
                <div class="info-label">Q3</div>
                <div class="info-value"><?= h(formatLapTime($qualifying['q3_time_ms'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($qualifying['eliminated_in']): ?>
            <div class="info-item">
                <div class="info-label">Eliminated In</div>
                <div class="info-value"><?= h($qualifying['eliminated_in']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><p>No qualifying data.</p></div>
        <?php endif; ?>
    </div>

    <!-- Pit stops -->
    <div class="card">
        <div class="card-title">Pit Stops (<?= count($pitStops) ?>)</div>
        <?php if (empty($pitStops)): ?>
        <div class="empty-state"><p>No pit stop data.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Stop</th><th>Lap</th><th>Duration</th><th>Tyre In</th><th>Tyre Out</th></tr></thead>
            <tbody>
            <?php foreach ($pitStops as $ps): ?>
            <tr>
                <td class="fw-bold"><?= h((string)$ps['stop_number']) ?></td>
                <td class="text-muted">Lap <?= h((string)$ps['lap_number']) ?></td>
                <td><?= $ps['duration_ms'] ? h(number_format($ps['duration_ms'] / 1000, 3)) . 's' : '—' ?></td>
                <td><?= $ps['tyre_in'] ? h($ps['tyre_in']) : '—' ?></td>
                <td><?= $ps['tyre_out'] ? h($ps['tyre_out']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Race Penalties -->
<?php if (!empty($racePenalties)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">&#9888; Penalties Received</div>
    <?php foreach ($racePenalties as $pen): ?>
    <div style="border:1px solid var(--border);border-radius:var(--radius);padding:0.75rem;margin-bottom:0.5rem">
        <span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>"><?= h(str_replace('_', ' ', $pen['penalty_type'])) ?></span>
        <p style="margin:0.4rem 0 0.3rem;font-size:0.9rem"><?= h($pen['reason']) ?></p>
        <div style="font-size:0.8rem;color:var(--text-muted)">
            <?php if ($pen['time_penalty_s']): ?>+<?= h((string)$pen['time_penalty_s']) ?>s &bull; <?php endif; ?>
            <?php if ($pen['grid_penalty_positions']): ?><?= h((string)$pen['grid_penalty_positions']) ?> place grid drop &bull; <?php endif; ?>
            <?php if ($pen['licence_points_awarded']): ?><?= h((string)$pen['licence_points_awarded']) ?> licence pt<?= $pen['licence_points_awarded'] != 1 ? 's' : '' ?> &bull; <?php endif; ?>
            <?= h(date('d M Y H:i', strtotime($pen['issued_at']))) ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Full Race Results -->
<?php if (!empty($allResults)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Full Race Results</div>
    <table>
        <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Grid</th><th>Status</th><th>Points</th><th>FL</th></tr></thead>
        <tbody>
        <?php foreach ($allResults as $ar): ?>
        <tr<?= $ar['person_id'] == $personId ? ' style="background:rgba(0,210,190,0.07)"' : '' ?>>
            <td>
                <?php if ($ar['finish_position'] && $ar['finish_position'] <= 3): ?>
                <span class="position-badge pos-<?= $ar['finish_position'] ?>"><?= h((string)$ar['finish_position']) ?></span>
                <?php elseif ($ar['finish_position']): ?>
                <span class="text-muted">P<?= h((string)$ar['finish_position']) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <strong><?= h($ar['first_name'] . ' ' . $ar['last_name']) ?></strong>
                <?php if ($ar['person_id'] == $personId): ?> <span class="status-badge status-active" style="font-size:0.65rem">YOU</span><?php endif; ?>
            </td>
            <td class="text-muted"><?= h($ar['team_name']) ?></td>
            <td class="text-muted"><?= $ar['grid_position'] ? 'P' . h((string)$ar['grid_position']) : '—' ?></td>
            <td><span class="status-badge status-<?= h(strtolower($ar['status'])) ?>"><?= h($ar['status']) ?></span></td>
            <td class="text-accent fw-bold"><?= h((string)$ar['points_scored']) ?></td>
            <td><?= $ar['fastest_lap_bonus'] ? '<span class="fl-indicator" title="Fastest Lap">&#9889;</span>' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Lap telemetry -->
<?php if (!empty($telemetry)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">
        Lap Telemetry
        <?php if ($fastestLap): ?>
        <span class="text-muted" style="font-size:0.8rem;font-weight:400;margin-left:0.5rem">
            Best: <span class="text-accent"><?= h(formatLapTime($fastestLap['lap_time_ms'])) ?></span> (Lap <?= h((string)$fastestLap['lap_number']) ?>)
        </span>
        <?php endif; ?>
    </div>
    <div class="table-container" style="border:none;padding:0">
        <div class="table-toolbar">
            <div class="table-search">
                <input type="text" class="table-search-input" data-table="telemetry-table" placeholder="Search laps...">
            </div>
        </div>
        <table class="sortable" id="telemetry-table">
            <thead><tr>
                <th>Lap</th><th>Lap Time</th><th>S1</th><th>S2</th><th>S3</th>
                <th>Speed Trap</th><th>Tyre</th><th>Age</th><th>Pit</th>
            </tr></thead>
            <tbody>
            <?php foreach ($telemetry as $lap): ?>
            <?php $isBest = $fastestLap && $lap['lap_number'] === $fastestLap['lap_number']; ?>
            <tr<?= $isBest ? ' style="background:rgba(0,210,190,0.06)"' : '' ?>>
                <td>
                    <span class="round-chip"><?= h((string)$lap['lap_number']) ?></span>
                    <?= $isBest ? ' <span class="fl-indicator" title="Personal Best">&#9889;</span>' : '' ?>
                </td>
                <td class="<?= $isBest ? 'text-success fw-bold' : 'text-accent fw-bold' ?>"><?= h(formatLapTime($lap['lap_time_ms'])) ?></td>
                <td class="text-muted"><?= $lap['sector1_ms'] ? h(formatLapTime($lap['sector1_ms'])) : '—' ?></td>
                <td class="text-muted"><?= $lap['sector2_ms'] ? h(formatLapTime($lap['sector2_ms'])) : '—' ?></td>
                <td class="text-muted"><?= $lap['sector3_ms'] ? h(formatLapTime($lap['sector3_ms'])) : '—' ?></td>
                <td><?= $lap['speed_trap_kmh'] ? h(number_format((float)$lap['speed_trap_kmh'], 1)) . ' km/h' : '—' ?></td>
                <td>
                    <?php if ($lap['tyre_compound']): ?>
                    <span class="status-badge" style="background:var(--border);color:var(--text-primary)"><?= h($lap['tyre_compound']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="text-muted"><?= $lap['tyre_age_laps'] !== null ? h((string)$lap['tyre_age_laps']) . 'L' : '—' ?></td>
                <td><?= $lap['is_pit_lap'] ? '<span class="status-badge status-warning">PIT</span>' : '' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
