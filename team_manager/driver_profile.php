<?php
$pageTitle = 'Driver Profile';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager');

$db       = getDB();
$teamId   = intval($_SESSION['linked_id'] ?? 0);
$personId = intval($_GET['person_id'] ?? 0);

// IDOR: verify this driver has raced for the manager's team
$stmt = $db->prepare("
    SELECT p.* FROM people p
    JOIN driver_seasons drs ON drs.person_id = p.id
    JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
    WHERE p.id = ? LIMIT 1
");
$stmt->execute([$teamId, $personId]);
$person = $stmt->fetch();
if (!$person) { include __DIR__ . '/../includes/403.php'; exit; }

// Career stats per season
$stmt = $db->prepare("
    SELECT s.year, t.name AS team_name, drs.status,
           ds.points, ds.wins, ds.podiums, ds.dnfs, ds.fastest_laps, ds.position
    FROM driver_seasons drs
    JOIN seasons s ON s.id = drs.season_id
    JOIN team_seasons ts ON ts.id = drs.team_season_id
    JOIN teams t ON t.id = ts.team_id
    LEFT JOIN driver_standings ds ON ds.person_id = drs.person_id AND ds.season_id = s.id
    WHERE drs.person_id = ?
    ORDER BY s.year DESC
");
$stmt->execute([$personId]);
$careerBySeasons = $stmt->fetchAll();

// Recent race results (for this team's races only)
$stmt = $db->prepare("
    SELECT r.name AS race_name, r.round_number, s.year,
           rr.finish_position, rr.points_scored, rr.status, rr.fastest_lap_bonus,
           qr.grid_position
    FROM race_results rr
    JOIN race_entries re ON re.id = rr.race_entry_id AND re.person_id = ?
    JOIN race_entries re2 ON re2.id = rr.race_entry_id
    JOIN team_seasons ts ON ts.id = re2.team_season_id AND ts.team_id = ?
    JOIN races r ON r.id = re2.race_id
    JOIN seasons s ON s.id = r.season_id
    LEFT JOIN qualifying_results qr ON qr.race_entry_id = re.id
    ORDER BY r.race_date DESC
    LIMIT 20
");
$stmt->execute([$personId, $teamId]);
$recentResults = $stmt->fetchAll();

$pageTitle = h($person['first_name'] . ' ' . $person['last_name']) . ' — Profile';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">#<?= h((string)$person['racing_number']) ?> <?= h($person['first_name'] . ' ' . $person['last_name']) ?></h1>
        <p class="page-subtitle"><?= h($person['nationality']) ?> &bull; Born <?= h(date('d M Y', strtotime($person['date_of_birth']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/team_manager/drivers.php" class="btn btn-outline">&larr; Drivers</a>
</div>

<!-- Career summary -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <?php
    $totalPts  = array_sum(array_column($careerBySeasons, 'points'));
    $totalWins = array_sum(array_column($careerBySeasons, 'wins'));
    $totalPods = array_sum(array_column($careerBySeasons, 'podiums'));
    $totalDnfs = array_sum(array_column($careerBySeasons, 'dnfs'));
    ?>
    <div class="stat-card"><div class="stat-value"><?= h(number_format($totalPts,1)) ?></div><div class="stat-label">Career Points</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalWins) ?></div><div class="stat-label">Wins</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalPods) ?></div><div class="stat-label">Podiums</div></div>
    <div class="stat-card"><div class="stat-value"><?= h((string)$totalDnfs) ?></div><div class="stat-label">DNFs</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">Season-by-Season</div>
        <?php if (empty($careerBySeasons)): ?>
        <div class="empty-state"><p>No season data.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Year</th><th>Team</th><th>Pos</th><th>Pts</th><th>Wins</th><th>Pods</th></tr></thead>
            <tbody>
            <?php foreach ($careerBySeasons as $cs): ?>
            <tr>
                <td><strong><?= h((string)$cs['year']) ?></strong></td>
                <td class="text-muted"><?= h($cs['team_name']) ?></td>
                <td><?= $cs['position'] ? 'P' . h((string)$cs['position']) : '—' ?></td>
                <td class="text-accent fw-bold"><?= h((string)($cs['points'] ?? 0)) ?></td>
                <td><?= h((string)($cs['wins'] ?? 0)) ?></td>
                <td><?= h((string)($cs['podiums'] ?? 0)) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Recent Results (Our Races)</div>
        <?php if (empty($recentResults)): ?>
        <div class="empty-state"><p>No results yet.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Year</th><th>Race</th><th>Grid</th><th>Finish</th><th>Pts</th></tr></thead>
            <tbody>
            <?php foreach ($recentResults as $r): ?>
            <tr>
                <td class="text-muted"><?= h((string)$r['year']) ?></td>
                <td>Rd <?= h((string)$r['round_number']) ?></td>
                <td class="text-muted"><?= $r['grid_position'] ? 'P' . h((string)$r['grid_position']) : '—' ?></td>
                <td>
                    <?php if ($r['finish_position']): ?>
                    <span class="position-badge pos-<?= $r['finish_position'] <= 3 ? $r['finish_position'] : 'other' ?>"><?= h((string)$r['finish_position']) ?></span>
                    <?php else: ?>
                    <span class="status-badge status-dnf"><?= h($r['status']) ?></span>
                    <?php endif; ?>
                    <?= $r['fastest_lap_bonus'] ? ' <span class="fl-indicator">&#9889;</span>' : '' ?>
                </td>
                <td class="text-accent"><?= h((string)$r['points_scored']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
