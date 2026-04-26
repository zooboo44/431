<?php
$pageTitle = 'Driver Detail';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db       = getDB();
$personId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM people WHERE id = ?');
$stmt->execute([$personId]);
$person = $stmt->fetch();
if (!$person) { include __DIR__ . '/../includes/404.php'; exit; }

$pageTitle = h($person['first_name'] . ' ' . $person['last_name']);

// Career totals
$stmt = $db->prepare("
    SELECT
        COALESCE(SUM(rr.points_scored),0) AS total_points,
        SUM(CASE WHEN rr.finish_position=1 THEN 1 ELSE 0 END) AS wins,
        SUM(CASE WHEN rr.finish_position<=3 AND rr.finish_position IS NOT NULL THEN 1 ELSE 0 END) AS podiums,
        SUM(CASE WHEN rr.status='DNF' THEN 1 ELSE 0 END) AS dnfs,
        SUM(CASE WHEN rr.fastest_lap_bonus=1 THEN 1 ELSE 0 END) AS fastest_laps,
        COUNT(DISTINCT re.race_id) AS total_races
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id AND re.person_id = ?
");
$stmt->execute([$personId]);
$career = $stmt->fetch();

// Season registrations
$stmt = $db->prepare("
    SELECT ds.*, s.year, t.name AS team_name,
           ds_stand.points, ds_stand.position AS standing_pos, ds_stand.wins
    FROM driver_seasons ds
    JOIN seasons s ON s.id = ds.season_id
    JOIN team_seasons ts ON ts.id = ds.team_season_id
    JOIN teams t ON t.id = ts.team_id
    LEFT JOIN driver_standings ds_stand ON ds_stand.person_id = ds.person_id AND ds_stand.season_id = ds.season_id
    WHERE ds.person_id = ?
    ORDER BY s.year DESC
");
$stmt->execute([$personId]);
$seasons = $stmt->fetchAll();

// Recent race results
$stmt = $db->prepare("
    SELECT rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
           r.name AS race_name, r.round_number, r.id AS race_id, s.year
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id AND re.person_id = ?
    JOIN races r ON r.id = re.race_id
    JOIN seasons s ON s.id = r.season_id
    ORDER BY s.year DESC, r.round_number DESC
    LIMIT 20
");
$stmt->execute([$personId]);
$raceHistory = $stmt->fetchAll();

// Licence points this season
$activeSeason = getActiveSeason();
$licencePoints = 0;
if ($activeSeason) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(licence_points_awarded),0) AS lic_pts
        FROM penalties pen
        JOIN races r ON r.id = pen.race_id
        WHERE pen.person_id = ? AND r.season_id = ?
    ");
    $stmt->execute([$personId, $activeSeason['id']]);
    $licencePoints = (int)$stmt->fetchColumn();
}

// Penalties
$stmt = $db->prepare("
    SELECT pen.*, r.name AS race_name, r.round_number, s.year
    FROM penalties pen
    JOIN races r ON r.id = pen.race_id
    JOIN seasons s ON s.id = r.season_id
    WHERE pen.person_id = ?
    ORDER BY pen.issued_at DESC
");
$stmt->execute([$personId]);
$penalties = $stmt->fetchAll();

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">#<?= h((string)$person['racing_number']) ?> <?= h($person['first_name'] . ' ' . $person['last_name']) ?></h1>
        <p class="page-subtitle"><?= h($person['nationality']) ?> &bull; Born <?= h(date('d M Y', strtotime($person['date_of_birth']))) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/people_edit.php?id=<?= $personId ?>" class="btn btn-outline">Edit</a>
        <a href="<?= APP_URL ?>/admin/people.php" class="btn btn-outline">&larr; Drivers</a>
    </div>
</div>

<?php if ($licencePoints >= 10): ?>
<div class="alert alert-danger">&#9888; This driver has <strong><?= $licencePoints ?> licence points</strong> in the current season — at or above the penalty threshold.</div>
<?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(6,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-value text-accent"><?= h(number_format((float)$career['total_points'],1)) ?></div>
        <div class="stat-label">Career Points</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$career['wins']) ?></div>
        <div class="stat-label">Wins</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$career['podiums']) ?></div>
        <div class="stat-label">Podiums</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$career['fastest_laps']) ?></div>
        <div class="stat-label">Fastest Laps</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$career['dnfs']) ?></div>
        <div class="stat-label">DNFs</div>
    </div>
    <div class="stat-card">
        <?php
        $licColor = $licencePoints >= 10 ? 'var(--danger)' : ($licencePoints >= 6 ? 'var(--warning)' : 'var(--success)');
        ?>
        <div class="stat-value" style="color:<?= $licColor ?>"><?= h((string)$licencePoints) ?></div>
        <div class="stat-label">Licence Pts (<?= $activeSeason ? h((string)$activeSeason['year']) : 'Season' ?>)</div>
    </div>
</div>

<div class="grid-2">
<!-- Personal info -->
<div class="card">
    <div class="card-title">Profile</div>
    <div class="info-grid">
        <div class="info-item"><div class="info-label">Racing Number</div><div class="info-value">#<?= h((string)$person['racing_number']) ?></div></div>
        <div class="info-item"><div class="info-label">Nationality</div><div class="info-value"><?= h($person['nationality']) ?></div></div>
        <div class="info-item"><div class="info-label">Date of Birth</div><div class="info-value"><?= h(date('d M Y', strtotime($person['date_of_birth']))) ?></div></div>
        <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="status-badge <?= $person['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $person['is_active'] ? 'Active' : 'Inactive' ?></span></div></div>
    </div>
    <?php if ($person['bio']): ?>
    <p style="margin-top:0.75rem;font-size:0.9rem;color:var(--text-secondary)"><?= h($person['bio']) ?></p>
    <?php endif; ?>
</div>

<!-- Season history -->
<div class="card">
    <div class="card-title">Season Registrations</div>
    <?php if (empty($seasons)): ?>
    <div class="empty-state"><p>No season registrations.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Season</th><th>Team</th><th>Status</th><th>Pos</th><th>Points</th></tr></thead>
        <tbody>
        <?php foreach ($seasons as $s): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/season_detail.php?id=<?= (int)$s['season_id'] ?>">
            <td><strong><?= h((string)$s['year']) ?></strong></td>
            <td><?= h($s['team_name']) ?></td>
            <td><span class="status-badge status-<?= h($s['status']) ?>"><?= h($s['status']) ?></span></td>
            <td><?= $s['standing_pos'] ? 'P' . h((string)$s['standing_pos']) : '—' ?></td>
            <td class="text-accent"><?= h(number_format((float)($s['points']??0),1)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>

<!-- Race history -->
<?php if (!empty($raceHistory)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Race History (last 20)</div>
    <table>
        <thead><tr><th>Season</th><th>Rd</th><th>Race</th><th>Finish</th><th>Status</th><th>Points</th><th>FL</th></tr></thead>
        <tbody>
        <?php foreach ($raceHistory as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $r['race_id'] ?>">
            <td><?= h((string)$r['year']) ?></td>
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><?= h($r['race_name']) ?></td>
            <td><?= $r['finish_position'] ? '<span class="position-badge pos-'.($r['finish_position']<=3?$r['finish_position']:'other').'">' . h((string)$r['finish_position']) . '</span>' : '—' ?></td>
            <td><span class="status-badge status-<?= h(strtolower($r['status'])) ?>"><?= h($r['status']) ?></span></td>
            <td class="text-accent fw-bold"><?= h((string)$r['points_scored']) ?></td>
            <td><?= $r['fastest_lap_bonus'] ? '<span class="fl-indicator">&#9889;</span>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Penalties -->
<?php if (!empty($penalties)): ?>
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Penalty History</div>
    <table>
        <thead><tr><th>Season</th><th>Race</th><th>Type</th><th>Reason</th><th>Lic. Pts</th></tr></thead>
        <tbody>
        <?php
        $runningLicPts = 0;
        foreach ($penalties as $pen):
            $runningLicPts += (int)($pen['licence_points_awarded'] ?? 0);
        ?>
        <tr>
            <td><?= h((string)$pen['year']) ?></td>
            <td>Rd <?= h((string)$pen['round_number']) ?></td>
            <td><span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>"><?= h(str_replace('_',' ',$pen['penalty_type'])) ?></span></td>
            <td style="font-size:0.85rem"><?= h($pen['reason']) ?></td>
            <td><?= $pen['licence_points_awarded'] ? h((string)$pen['licence_points_awarded']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
