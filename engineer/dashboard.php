<?php
$pageTitle = 'Engineer Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('engineer');

$db       = getDB();
$teamId   = intval($_SESSION['linked_id'] ?? 0);
$activeSeason = getActiveSeason();
$seasonId = $activeSeason['id'] ?? null;

if (!$teamId) {
    echo '<div class="alert alert-danger">No team linked to your account. Contact admin.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Team info
$stmt = $db->prepare('SELECT * FROM teams WHERE id = ?');
$stmt->execute([$teamId]);
$team = $stmt->fetch();

$teamSeason = null;
$teamDrivers = [];
$lastRace = null;
$lastRaceResults = [];

if ($seasonId) {
    $stmt = $db->prepare('SELECT * FROM team_seasons WHERE team_id = ? AND season_id = ?');
    $stmt->execute([$teamId, $seasonId]);
    $teamSeason = $stmt->fetch();

    // Drivers this season
    $stmt = $db->prepare("
        SELECT p.id, p.first_name, p.last_name, p.racing_number,
               ds.points, ds.position AS standing_pos, ds.wins
        FROM driver_seasons drs
        JOIN people p ON p.id = drs.person_id
        LEFT JOIN driver_standings ds ON ds.person_id = p.id AND ds.season_id = ?
        WHERE drs.team_season_id = (SELECT id FROM team_seasons WHERE team_id = ? AND season_id = ? LIMIT 1)
          AND drs.season_id = ?
        ORDER BY COALESCE(ds.position, 99)
    ");
    $stmt->execute([$seasonId, $teamId, $seasonId, $seasonId]);
    $teamDrivers = $stmt->fetchAll();

    // Last completed race for this team
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date
        FROM races r
        JOIN team_seasons ts ON ts.season_id = r.season_id AND ts.team_id = ?
        WHERE r.season_id = ? AND r.status = 'completed'
        ORDER BY r.round_number DESC
        LIMIT 1
    ");
    $stmt->execute([$teamId, $seasonId]);
    $lastRace = $stmt->fetch();

    if ($lastRace) {
        $stmt = $db->prepare("
            SELECT p.first_name, p.last_name, p.racing_number, p.id AS person_id,
                   rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
                   qr.grid_position,
                   (SELECT COUNT(*) FROM lap_telemetry lt JOIN race_entries re2 ON re2.id = lt.race_entry_id
                    WHERE re2.race_id = ? AND re2.person_id = p.id) AS telemetry_laps,
                   (SELECT COUNT(*) FROM pit_stops ps JOIN race_entries re3 ON re3.id = ps.race_entry_id
                    WHERE re3.race_id = ? AND re3.person_id = p.id) AS pit_count
            FROM race_results rr
            JOIN race_entries re ON re.id = rr.race_entry_id AND re.race_id = ?
            JOIN team_seasons ts ON ts.id = re.team_season_id AND ts.team_id = ?
            JOIN people p ON p.id = re.person_id
            LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
            ORDER BY COALESCE(rr.finish_position, 99)
        ");
        $stmt->execute([$lastRace['id'], $lastRace['id'], $lastRace['id'], $teamId]);
        $lastRaceResults = $stmt->fetchAll();
    }
}

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $team ? h($team['name']) : 'Engineer Dashboard' ?></h1>
        <p class="page-subtitle"><?= $activeSeason ? h((string)$activeSeason['year']) . ' Season' : '' ?></p>
    </div>
</div>

<div class="grid-2">
    <!-- Team info -->
    <?php if ($teamSeason): ?>
    <div class="card">
        <div class="card-title">Team Configuration</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Car</div>
                <div class="info-value"><?= h($teamSeason['car_name']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Power Unit</div>
                <div class="info-value"><?= h($teamSeason['power_unit']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Team Principal</div>
                <div class="info-value"><?= h($teamSeason['principal']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Base</div>
                <div class="info-value"><?= h($teamSeason['base_location'] ?? '—') ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Drivers -->
    <div class="card">
        <div class="card-title">Drivers — <?= $activeSeason ? h((string)$activeSeason['year']) : '' ?></div>
        <?php if (empty($teamDrivers)): ?>
        <div class="empty-state"><p>No drivers registered this season.</p></div>
        <?php else: ?>
        <?php foreach ($teamDrivers as $d): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 0;border-bottom:1px solid var(--border)">
            <div>
                <strong>#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></strong>
                <div class="text-muted" style="font-size:0.8rem">
                    <?= $d['standing_pos'] ? 'Championship P' . h((string)$d['standing_pos']) : 'No standing yet' ?>
                </div>
            </div>
            <div class="text-accent fw-bold"><?= h((string)($d['points'] ?? 0)) ?> pts</div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Last race -->
<?php if ($lastRace): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">
        Last Race — Rd <?= h((string)$lastRace['round_number']) ?> <?= h($lastRace['name']) ?>
        <span class="text-muted" style="font-size:0.8rem;font-weight:400;margin-left:0.5rem"><?= h(date('d M Y', strtotime($lastRace['race_date']))) ?></span>
    </div>
    <?php if (empty($lastRaceResults)): ?>
    <div class="empty-state"><p>No results recorded.</p></div>
    <?php else: ?>
    <table>
        <thead><tr>
            <th>Driver</th><th>Grid</th><th>Finish</th><th>Status</th>
            <th>Points</th><th>Telemetry Laps</th><th>Pit Stops</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($lastRaceResults as $r): ?>
        <tr>
            <td><strong>#<?= h((string)$r['racing_number']) ?> <?= h($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
            <td class="text-muted"><?= $r['grid_position'] ? 'P' . h((string)$r['grid_position']) : '—' ?></td>
            <td>
                <?php if ($r['finish_position']): ?>
                <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                <?php else: ?>—<?php endif; ?>
                <?= $r['fastest_lap_bonus'] ? ' <span class="fl-indicator" title="Fastest Lap">&#9889;</span>' : '' ?>
            </td>
            <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
            <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
            <td class="text-muted"><?= h((string)$r['telemetry_laps']) ?> laps</td>
            <td class="text-muted"><?= h((string)$r['pit_count']) ?> stops</td>
            <td style="display:flex;gap:0.3rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/engineer/telemetry_add.php?race_id=<?= $lastRace['id'] ?>&person_id=<?= $r['person_id'] ?>" class="btn btn-outline btn-sm">+ Telemetry</a>
                <a href="<?= APP_URL ?>/engineer/pitstops_add.php?race_id=<?= $lastRace['id'] ?>&person_id=<?= $r['person_id'] ?>" class="btn btn-outline btn-sm">+ Pit Stop</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
