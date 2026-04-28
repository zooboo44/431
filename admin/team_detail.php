<?php
$pageTitle = 'Team Detail';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$teamId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM teams WHERE id = ?');
$stmt->execute([$teamId]);
$team = $stmt->fetch();
if (!$team) { include __DIR__ . '/../includes/404.php'; exit; }

$pageTitle = h($team['name']);

// Season registrations
$stmt = $db->prepare("
    SELECT ts.*, s.year, s.is_active AS season_active,
           cs.position AS constructor_pos, cs.points AS constructor_pts, cs.wins AS constructor_wins
    FROM team_seasons ts
    JOIN seasons s ON s.id = ts.season_id
    LEFT JOIN constructor_standings cs ON cs.team_id = ts.team_id AND cs.season_id = ts.season_id
    WHERE ts.team_id = ?
    ORDER BY s.year DESC
");
$stmt->execute([$teamId]);
$teamSeasons = $stmt->fetchAll();

// Active season details + drivers
$activeSeason = getActiveSeason();
$activeDrivers = [];
$activeTeamSeason = null;
if ($activeSeason) {
    $stmt = $db->prepare("
        SELECT ds.*, p.first_name, p.last_name, p.racing_number,
               ds_stand.points, ds_stand.position AS standing_pos
        FROM driver_seasons ds
        JOIN people p ON p.id = ds.person_id
        JOIN team_seasons ts ON ts.id = ds.team_season_id AND ts.team_id = ?
        LEFT JOIN driver_standings ds_stand ON ds_stand.person_id = p.id AND ds_stand.season_id = ?
        WHERE ds.season_id = ?
        ORDER BY p.racing_number
    ");
    $stmt->execute([$teamId, $activeSeason['id'], $activeSeason['id']]);
    $activeDrivers = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM team_seasons WHERE team_id=? AND season_id=?");
    $stmt->execute([$teamId, $activeSeason['id']]);
    $activeTeamSeason = $stmt->fetch();
}

// Manager user linked to team
$stmt = $db->prepare("SELECT id, name, email FROM users WHERE linked_id=? AND role='team_manager' LIMIT 1");
$stmt->execute([$teamId]);
$manager = $stmt->fetch();

// Career totals
$stmt = $db->prepare("
    SELECT
        COUNT(DISTINCT rr.id) AS total_races,
        COALESCE(SUM(rr.points_scored),0) AS total_points,
        SUM(CASE WHEN rr.finish_position=1 THEN 1 ELSE 0 END) AS wins,
        SUM(CASE WHEN rr.finish_position<=3 AND rr.finish_position IS NOT NULL THEN 1 ELSE 0 END) AS podiums
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id
    JOIN team_seasons ts ON ts.id = re.team_season_id AND ts.team_id = ?
");
$stmt->execute([$teamId]);
$career = $stmt->fetch();

renderFlash();
?>

<?php if (!empty($_GET['prompt_manager'])): ?>
<div class="alert" style="background:rgba(var(--warning-rgb,230,160,20),0.15);border:1px solid var(--warning);color:var(--text-primary);margin-bottom:1rem">
    <strong>&#9888; No team manager yet.</strong>
    Would you like to create a team manager account for this team?
    <a href="<?= APP_URL ?>/admin/users_create.php?role=team_manager" class="btn btn-primary btn-sm" style="margin-left:1rem">Create Team Manager</a>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h($team['name']) ?></h1>
        <p class="page-subtitle"><?= h($team['nationality']) ?><?= $team['founded_year'] ? ' &bull; Founded ' . h((string)$team['founded_year']) : '' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="<?= APP_URL ?>/admin/teams_edit.php?id=<?= $teamId ?>" class="btn btn-outline">Edit Team</a>
        <a href="<?= APP_URL ?>/admin/teams.php" class="btn btn-outline">&larr; Teams</a>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
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
        <div class="stat-value"><?= h((string)$career['total_races']) ?></div>
        <div class="stat-label">Race Entries</div>
    </div>
</div>

<div class="grid-2">
<!-- Active season info -->
<div class="card">
    <div class="card-title">
        <?= $activeSeason ? h((string)$activeSeason['year']) . ' Season' : 'Current Season' ?>
        <?php if (!$activeTeamSeason): ?><span class="text-muted" style="font-size:0.8rem"> (not registered)</span><?php endif; ?>
    </div>
    <?php if ($activeTeamSeason): ?>
    <div class="info-grid">
        <div class="info-item"><div class="info-label">Car</div><div class="info-value"><?= h($activeTeamSeason['car_name']) ?></div></div>
        <div class="info-item"><div class="info-label">Power Unit</div><div class="info-value"><?= h($activeTeamSeason['power_unit']) ?></div></div>
        <div class="info-item"><div class="info-label">Principal</div><div class="info-value"><?= h($activeTeamSeason['principal']) ?></div></div>
        <?php if ($activeTeamSeason['base_location']): ?>
        <div class="info-item"><div class="info-label">Base</div><div class="info-value"><?= h($activeTeamSeason['base_location']) ?></div></div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>Team not registered for active season.</p></div>
    <?php endif; ?>

    <?php if ($manager): ?>
    <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
        <div class="info-label">Team Manager</div>
        <div><?= h($manager['name']) ?> — <?= h($manager['email']) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Active season drivers -->
<div class="card">
    <div class="card-title">Drivers — <?= $activeSeason ? h((string)$activeSeason['year']) : 'Active Season' ?></div>
    <?php if (empty($activeDrivers)): ?>
    <div class="empty-state"><p>No drivers registered.</p></div>
    <?php else: ?>
    <?php foreach ($activeDrivers as $d): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--border)">
        <div>
            <a href="<?= APP_URL ?>/admin/person_detail.php?id=<?= $d['id'] ?>" class="fw-bold">#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></a>
            <div class="text-muted" style="font-size:0.8rem"><span class="status-badge status-<?= h($d['status']) ?>"><?= h($d['status']) ?></span></div>
        </div>
        <div class="text-accent fw-bold"><?= h(number_format((float)($d['points']??0),1)) ?> pts</div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>

<!-- Season history -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Season History</div>
    <?php if (empty($teamSeasons)): ?>
    <div class="empty-state"><p>No season registrations.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Season</th><th>Car</th><th>Principal</th><th>Pos</th><th>Points</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($teamSeasons as $ts): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/season_detail.php?id=<?= (int)$ts['season_id'] ?>">
            <td><strong><?= h((string)$ts['year']) ?></strong><?= $ts['season_active'] ? ' <span class="status-badge status-active" style="font-size:0.7rem">Active</span>' : '' ?></td>
            <td><?= h($ts['car_name']) ?></td>
            <td class="text-muted"><?= h($ts['principal']) ?></td>
            <td><?= $ts['constructor_pos'] ? 'P' . h((string)$ts['constructor_pos']) : '—' ?></td>
            <td class="text-accent"><?= h(number_format((float)($ts['constructor_pts']??0),1)) ?></td>
            <td><?= h((string)($ts['constructor_wins']??0)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
