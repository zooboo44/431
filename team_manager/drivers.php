<?php
$pageTitle = 'My Drivers';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager');

$db     = getDB();
$teamId = intval($_SESSION['linked_id'] ?? 0);

// All drivers who have ever raced for this team — IDOR: ownership enforced via team_id=teamId
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.first_name, p.last_name, p.racing_number, p.nationality, p.date_of_birth,
           COUNT(DISTINCT drs.season_id) AS seasons_count,
           SUM(COALESCE(rr.points_scored,0)) AS career_points,
           SUM(CASE WHEN rr.finish_position=1 THEN 1 ELSE 0 END) AS career_wins
    FROM people p
    JOIN driver_seasons drs ON drs.person_id = p.id
    JOIN team_seasons ts ON ts.id = drs.team_season_id AND ts.team_id = ?
    LEFT JOIN race_entries re ON re.person_id = p.id
    LEFT JOIN race_results rr ON rr.race_entry_id = re.id
    GROUP BY p.id
    ORDER BY career_points DESC
");
$stmt->execute([$teamId]);
$drivers = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Drivers</h1>
        <p class="page-subtitle"><?= count($drivers) ?> driver<?= count($drivers) != 1 ? 's' : '' ?> — all seasons</p>
    </div>
    <a href="<?= APP_URL ?>/team_manager/export.php?type=drivers" class="btn btn-outline">Export CSV</a>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="drivers-table" placeholder="Search drivers...">
        </div>
    </div>
    <table class="sortable" id="drivers-table">
        <thead><tr>
            <th>#</th><th>Name</th><th>Nationality</th><th>DOB</th>
            <th>Seasons</th><th>Career Pts</th><th>Career Wins</th>
        </tr></thead>
        <tbody>
        <?php if (empty($drivers)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:2rem">No drivers found.</td></tr>
        <?php else: ?>
        <?php foreach ($drivers as $d): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/team_manager/driver_profile.php?person_id=<?= (int)$d['id'] ?>">
            <td class="text-accent fw-bold"><?= h((string)$d['racing_number']) ?></td>
            <td><a href="<?= APP_URL ?>/team_manager/driver_profile.php?person_id=<?= (int)$d['id'] ?>" style="font-weight:600"><?= h($d['first_name'] . ' ' . $d['last_name']) ?></a></td>
            <td><?= h($d['nationality']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($d['date_of_birth']))) ?></td>
            <td><?= h((string)$d['seasons_count']) ?></td>
            <td class="text-accent fw-bold"><?= h(number_format($d['career_points'], 1)) ?></td>
            <td><?= h((string)$d['career_wins']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
