<?php
$pageTitle = 'Team Profile';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'team_manager', 'driver');

$db     = getDB();
$teamId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM teams WHERE id = ? AND is_active = 1");
$stmt->execute([$teamId]);
$team = $stmt->fetch();
if (!$team) { include __DIR__ . '/../includes/404.php'; exit; }

$pageTitle = h($team['name']) . ' — Team Profile';

// Season history for this team
$stmt = $db->prepare("
    SELECT ts.id AS team_season_id, s.year, s.id AS season_id, ts.principal, ts.car_name, ts.power_unit,
           cs.position AS championship_pos, cs.points, cs.wins
    FROM team_seasons ts
    JOIN seasons s ON s.id = ts.season_id
    LEFT JOIN constructor_standings cs ON cs.team_id = ts.team_id AND cs.season_id = s.id
    WHERE ts.team_id = ?
    ORDER BY s.year DESC
");
$stmt->execute([$teamId]);
$seasonHistory = $stmt->fetchAll();

// Current season drivers
$activeSeason = getActiveSeason();
$currentDrivers = [];
if ($activeSeason) {
    $stmt = $db->prepare("
        SELECT p.id AS person_id, p.first_name, p.last_name, p.racing_number, p.nationality,
               drs.status,
               ds.position, ds.points, ds.wins, ds.podiums
        FROM driver_seasons drs
        JOIN people p ON p.id = drs.person_id
        JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
        LEFT JOIN driver_standings ds ON ds.person_id = p.id AND ds.season_id = drs.season_id
        WHERE drs.season_id = ? AND drs.status = 'active'
        ORDER BY p.racing_number
    ");
    $stmt->execute([$teamId, $activeSeason['id']]);
    $currentDrivers = $stmt->fetchAll();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h($team['name']) ?></h1>
        <p class="page-subtitle"><?= h($team['short_name']) ?><?= $team['nationality'] ? ' &bull; ' . h($team['nationality']) : '' ?></p>
    </div>
    <a href="javascript:history.back()" class="btn btn-outline">&larr; Back</a>
</div>

<?php if ($activeSeason && !empty($currentDrivers)): ?>
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-title">&#128100; Current Drivers (<?= h((string)$activeSeason['year']) ?>)</div>
    <div class="stats-grid" style="grid-template-columns:repeat(<?= count($currentDrivers) ?>,1fr)">
        <?php foreach ($currentDrivers as $d): ?>
        <div class="stat-card" style="cursor:pointer" onclick="window.location='<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$d['person_id'] ?>'">
            <div class="stat-value">#<?= h((string)$d['racing_number']) ?></div>
            <div class="stat-label"><a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$d['person_id'] ?>"><?= h($d['first_name'] . ' ' . $d['last_name']) ?></a></div>
            <?php if ($d['points'] !== null): ?>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.3rem"><?= h((string)$d['points']) ?> pts<?= $d['position'] ? ' &bull; P' . h((string)$d['position']) : '' ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Season History</div>
    <?php if (empty($seasonHistory)): ?>
    <div class="empty-state"><p>No season history.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Year</th><th>Principal</th><th>Car</th><th>Power Unit</th><th>Champ. Pos</th><th>Points</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($seasonHistory as $ts): ?>
        <tr>
            <td><strong><?= h((string)$ts['year']) ?></strong></td>
            <td><?= h($ts['principal']) ?></td>
            <td class="text-muted"><?= h($ts['car_name']) ?></td>
            <td class="text-muted"><?= h($ts['power_unit']) ?></td>
            <td><?= $ts['championship_pos'] ? 'P' . h((string)$ts['championship_pos']) : '—' ?></td>
            <td class="text-accent fw-bold"><?= $ts['points'] !== null ? h((string)$ts['points']) : '—' ?></td>
            <td><?= $ts['wins'] !== null ? h((string)$ts['wins']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
