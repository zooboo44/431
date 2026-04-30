<?php
$pageTitle = 'Team Manager Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager');

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
$stmt = $db->prepare('SELECT * FROM teams WHERE id = ? AND is_active = 1');
$stmt->execute([$teamId]);
$team = $stmt->fetch();

// Team season info
$teamSeason = null;
$constructorStanding = null;
$teamDrivers = [];

if ($team && $seasonId) {
    $stmt = $db->prepare('SELECT * FROM team_seasons WHERE team_id = ? AND season_id = ?');
    $stmt->execute([$teamId, $seasonId]);
    $teamSeason = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(rr.points_scored),0) AS points,
               SUM(CASE WHEN rr.finish_position=1 AND rr.is_sprint=0 THEN 1 ELSE 0 END) AS wins,
               NULL AS position
        FROM race_results rr
        JOIN race_entries re ON re.id = rr.race_entry_id
        JOIN races r ON r.id = re.race_id
        JOIN team_seasons ts ON ts.id = re.team_season_id
        WHERE r.season_id = ? AND r.status = 'completed' AND ts.team_id = ?
    ");
    $stmt->execute([$seasonId, $teamId]);
    $constructorStanding = $stmt->fetch();

    // Team's drivers and their standings
    $stmt = $db->prepare("
        SELECT p.id, p.first_name, p.last_name, p.racing_number,
               COALESCE((
                   SELECT SUM(rr2.points_scored)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = ? AND r2.status='completed' AND re2.person_id = p.id
               ),0) AS points,
               COALESCE((
                   SELECT SUM(CASE WHEN rr2.finish_position=1 AND rr2.is_sprint=0 THEN 1 ELSE 0 END)
                   FROM race_results rr2
                   JOIN race_entries re2 ON re2.id = rr2.race_entry_id
                   JOIN races r2 ON r2.id = re2.race_id
                   WHERE r2.season_id = ? AND r2.status='completed' AND re2.person_id = p.id
               ),0) AS wins,
               NULL AS standing_pos
        FROM driver_seasons drs
        JOIN people p ON p.id = drs.person_id
        WHERE drs.team_season_id = (SELECT id FROM team_seasons WHERE team_id = ? AND season_id = ?)
        AND drs.season_id = ?
        ORDER BY points DESC
    ");
    $stmt->execute([$seasonId, $seasonId, $teamId, $seasonId, $seasonId]);
    $teamDrivers = $stmt->fetchAll();
}

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $team ? h($team['name']) : 'Team Dashboard' ?></h1>
        <p class="page-subtitle"><?= $activeSeason ? h((string)$activeSeason['year']) . ' Season' : '' ?></p>
    </div>
</div>

<?php if ($constructorStanding): ?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="stat-card">
        <div class="stat-icon">&#127942;</div>
        <div class="stat-value"><?= $constructorStanding['position'] !== null ? 'P' . h((string)$constructorStanding['position']) : '—' ?></div>
        <div class="stat-label">Constructor Pos</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#9312;</div>
        <div class="stat-value"><?= h((string)($constructorStanding['points'] ?? 0)) ?></div>
        <div class="stat-label">Championship Points</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#127937;</div>
        <div class="stat-value"><?= h((string)($constructorStanding['wins'] ?? 0)) ?></div>
        <div class="stat-label">Wins</div>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">
    <?php if ($teamSeason): ?>
    <div class="card">
        <div class="card-title">Team Info — <?= h((string)$activeSeason['year']) ?></div>
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

    <div class="card">
        <div class="card-title">Drivers — <?= $activeSeason ? h((string)$activeSeason['year']) : '' ?></div>
        <?php if (empty($teamDrivers)): ?>
        <div class="empty-state"><p>No drivers registered this season.</p></div>
        <?php else: ?>
        <?php foreach ($teamDrivers as $d): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 0;border-bottom:1px solid var(--border)">
            <div>
                <a href="<?= APP_URL ?>/team_manager/drivers.php?person_id=<?= $d['id'] ?>" style="font-weight:600">#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></a>
                <div class="text-muted" style="font-size:0.8rem">
                    <?= $d['standing_pos'] ? 'Championship P' . h((string)$d['standing_pos']) : 'No standing yet' ?>
                </div>
            </div>
            <div style="text-align:right">
                <div class="text-accent fw-bold"><?= h((string)($d['points'] ?? 0)) ?> pts</div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
