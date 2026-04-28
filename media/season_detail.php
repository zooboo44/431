<?php
$pageTitle = 'Season Overview';
require_once __DIR__ . '/../includes/header.php';
requireRole('media');

$db = getDB();
$seasons = getSeasonList();

$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}

$season = null;
foreach ($seasons as $s) {
    if ($s['id'] == $selectedSeasonId) { $season = $s; break; }
}

$races = [];
$driverChampion = null;
$constructorChampion = null;
$totalRaces = 0;
$completedRaces = 0;

if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.status, r.has_sprint,
               c.name AS circuit, c.country,
               p.first_name AS winner_first, p.last_name AS winner_last, p.id AS winner_id
        FROM races r
        JOIN circuits c ON c.id = r.circuit_id
        LEFT JOIN race_results rr ON rr.finish_position = 1
            AND rr.race_entry_id IN (SELECT id FROM race_entries WHERE race_id = r.id)
        LEFT JOIN race_entries re_win ON re_win.id = rr.race_entry_id
        LEFT JOIN people p ON p.id = re_win.person_id
        WHERE r.season_id = ?
        ORDER BY r.round_number ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $races = $stmt->fetchAll();

    $totalRaces = count($races);
    $completedRaces = count(array_filter($races, fn($r) => $r['status'] === 'completed'));

    // Driver champion (position 1)
    $stmt = $db->prepare("
        SELECT p.id, p.first_name, p.last_name, p.racing_number, ds.points
        FROM driver_standings ds
        JOIN people p ON p.id = ds.person_id
        WHERE ds.season_id = ? AND ds.position = 1
        LIMIT 1
    ");
    $stmt->execute([$selectedSeasonId]);
    $driverChampion = $stmt->fetch();

    // Constructor champion
    $stmt = $db->prepare("
        SELECT t.id, t.name, cs.points
        FROM constructor_standings cs
        JOIN teams t ON t.id = cs.team_id
        WHERE cs.season_id = ? AND cs.position = 1
        LIMIT 1
    ");
    $stmt->execute([$selectedSeasonId]);
    $constructorChampion = $stmt->fetch();
}
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $season ? h((string)$season['year']) : '' ?> Season Overview</h1>
        <p class="page-subtitle"><?= $completedRaces ?>/<?= $totalRaces ?> races completed</p>
    </div>
    <form class="season-selector" method="get">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons as $s): ?>
            <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>>
                <?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Season summary cards -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-icon">&#128197;</div>
        <div class="stat-value"><?= h((string)$totalRaces) ?></div>
        <div class="stat-label">Total Races</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#9989;</div>
        <div class="stat-value"><?= h((string)$completedRaces) ?></div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128100;</div>
        <div class="stat-value">
            <?php if ($driverChampion): ?>
            <a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$driverChampion['id'] ?>" style="font-size:0.9rem;font-weight:700"><?= h($driverChampion['first_name'] . ' ' . $driverChampion['last_name']) ?></a>
            <?php else: ?>—<?php endif; ?>
        </div>
        <div class="stat-label">Driver Champion</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#127937;</div>
        <div class="stat-value">
            <?php if ($constructorChampion): ?>
            <a href="<?= APP_URL ?>/shared/team_detail.php?id=<?= (int)$constructorChampion['id'] ?>" style="font-size:0.9rem;font-weight:700"><?= h($constructorChampion['name']) ?></a>
            <?php else: ?>—<?php endif; ?>
        </div>
        <div class="stat-label">Constructor Champion</div>
    </div>
</div>

<!-- Race calendar -->
<div class="card">
    <div class="card-title">Race Calendar</div>
    <?php if (empty($races)): ?>
    <div class="empty-state"><p>No races scheduled for this season.</p></div>
    <?php else: ?>
    <div class="table-container" style="border:0;margin:0">
        <div class="table-toolbar">
            <button class="btn btn-outline btn-sm" data-export-csv="season-races-table" data-filename="season_<?= h((string)($season['year'] ?? '')) ?>_races.csv">Export CSV</button>
        </div>
        <table class="sortable" id="season-races-table">
            <thead><tr>
                <th>Rd</th><th>Race</th><th>Circuit</th><th>Country</th><th>Date</th><th>Sprint</th><th>Status</th><th>Winner</th>
            </tr></thead>
            <tbody>
            <?php foreach ($races as $r): ?>
            <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/race_detail.php?id=<?= (int)$r['id'] ?>">
                <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
                <td><strong><?= h($r['name']) ?></strong></td>
                <td><?= h($r['circuit']) ?></td>
                <td class="text-muted"><?= h($r['country']) ?></td>
                <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
                <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress" style="font-size:0.7rem">SPRINT</span>' : '—' ?></td>
                <td><span class="status-badge status-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
                <td class="no-row-click">
                    <?php if ($r['winner_id']): ?>
                    <a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$r['winner_id'] ?>"><?= h($r['winner_first'] . ' ' . $r['winner_last']) ?></a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
