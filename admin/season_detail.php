<?php
$pageTitle = 'Season Detail';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db       = getDB();
$seasonId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM seasons WHERE id = ?');
$stmt->execute([$seasonId]);
$season = $stmt->fetch();
if (!$season) { include __DIR__ . '/../includes/404.php'; exit; }

$pageTitle = $season['year'] . ' Season';

// Teams
$stmt = $db->prepare("
    SELECT ts.*, t.name AS team_name, t.nationality,
           cs.position, cs.points, cs.wins
    FROM team_seasons ts
    JOIN teams t ON t.id = ts.team_id
    LEFT JOIN constructor_standings cs ON cs.team_id = ts.team_id AND cs.season_id = ?
    WHERE ts.season_id = ?
    ORDER BY COALESCE(cs.position,99)
");
$stmt->execute([$seasonId, $seasonId]);
$teams = $stmt->fetchAll();

// Drivers
$stmt = $db->prepare("
    SELECT ds.*, p.first_name, p.last_name, p.racing_number, t.name AS team_name,
           dst.position, dst.points, dst.wins
    FROM driver_seasons ds
    JOIN people p ON p.id = ds.person_id
    JOIN team_seasons ts ON ts.id = ds.team_season_id
    JOIN teams t ON t.id = ts.team_id
    LEFT JOIN driver_standings dst ON dst.person_id = p.id AND dst.season_id = ?
    WHERE ds.season_id = ?
    ORDER BY COALESCE(dst.position,99), t.name
");
$stmt->execute([$seasonId, $seasonId]);
$drivers = $stmt->fetchAll();

// Races
$stmt = $db->prepare("
    SELECT r.*, c.name AS circuit_name, c.country
    FROM races r
    JOIN circuits c ON c.id = r.circuit_id
    WHERE r.season_id = ?
    ORDER BY r.round_number ASC
");
$stmt->execute([$seasonId]);
$races = $stmt->fetchAll();

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h((string)$season['year']) ?> Season</h1>
        <p class="page-subtitle"><?= count($races) ?> races &bull; <?= count($teams) ?> teams &bull; <?= count($drivers) ?> drivers</p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <?php if ($season['is_active']): ?>
        <span class="status-badge status-active" style="padding:0.4rem 0.75rem">&#9733; Active Season</span>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/season_registrations.php?season_id=<?= $seasonId ?>" class="btn btn-outline">Registrations</a>
        <a href="<?= APP_URL ?>/admin/seasons.php" class="btn btn-outline">&larr; Seasons</a>
    </div>
</div>

<!-- Standings side by side -->
<div class="grid-2" style="margin-bottom:1.5rem">
<!-- Driver standings -->
<div class="card">
    <div class="card-title">Driver Championship</div>
    <?php if (empty($drivers) || !array_filter($drivers, fn($d) => $d['position'])): ?>
    <div class="empty-state"><p>No standings yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Pos</th><th>Driver</th><th>Team</th><th>Pts</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($drivers as $d): ?>
        <?php if (!$d['position']) continue; ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/person_detail.php?id=<?= $d['id'] ?>">
            <td><span class="position-badge pos-<?= $d['position']<=3?$d['position']:'other' ?>"><?= h((string)$d['position']) ?></span></td>
            <td><strong>#<?= h((string)$d['racing_number']) ?> <?= h($d['first_name'] . ' ' . $d['last_name']) ?></strong></td>
            <td class="text-muted"><?= h($d['team_name']) ?></td>
            <td class="text-accent fw-bold"><?= h(number_format((float)($d['points']??0),1)) ?></td>
            <td><?= h((string)($d['wins']??0)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Constructor standings -->
<div class="card">
    <div class="card-title">Constructor Championship</div>
    <?php if (empty($teams) || !array_filter($teams, fn($t) => $t['position'])): ?>
    <div class="empty-state"><p>No standings yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Pos</th><th>Team</th><th>Pts</th><th>Wins</th></tr></thead>
        <tbody>
        <?php foreach ($teams as $t): ?>
        <?php if (!$t['position']) continue; ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/team_detail.php?id=<?= $t['team_id'] ?>">
            <td><span class="position-badge pos-<?= $t['position']<=3?$t['position']:'other' ?>"><?= h((string)$t['position']) ?></span></td>
            <td><strong><?= h($t['team_name']) ?></strong></td>
            <td class="text-accent fw-bold"><?= h(number_format((float)($t['points']??0),1)) ?></td>
            <td><?= h((string)($t['wins']??0)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>

<!-- Races -->
<div class="card">
    <div class="card-title">Races</div>
    <?php if (empty($races)): ?>
    <div class="empty-state"><p>No races. <a href="<?= APP_URL ?>/admin/races_create.php?season_id=<?= $seasonId ?>">Add race →</a></p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Rd</th><th>Race</th><th>Circuit</th><th>Date</th><th>Sprint</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($races as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $r['id'] ?>">
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td class="text-muted"><?= h($r['circuit_name']) ?>, <?= h($r['country']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></td>
            <td><span class="status-badge status-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- All drivers this season -->
<div class="card" style="margin-top:1.5rem">
    <div class="card-title">Registered Drivers</div>
    <table>
        <thead><tr><th>#</th><th>Driver</th><th>Team</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($drivers as $d): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/person_detail.php?id=<?= $d['id'] ?>">
            <td><strong class="text-accent"><?= h((string)$d['racing_number']) ?></strong></td>
            <td><?= h($d['first_name'] . ' ' . $d['last_name']) ?></td>
            <td class="text-muted"><?= h($d['team_name']) ?></td>
            <td><span class="status-badge status-<?= h($d['status']) ?>"><?= h($d['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
