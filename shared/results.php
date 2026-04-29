<?php
$pageTitle = 'Race Results';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'team_manager', 'driver');

$db = getDB();
$seasons = getSeasonList();

$selectedSeasonId = intval($_GET['season'] ?? 0);
if (!$selectedSeasonId) {
    $active = getActiveSeason();
    $selectedSeasonId = $active['id'] ?? ($seasons[0]['id'] ?? 0);
}

$races = [];
if ($selectedSeasonId) {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.round_number, r.race_date, r.has_sprint,
               c.name AS circuit, c.country
        FROM races r JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ? AND r.status = 'completed'
        ORDER BY r.round_number ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $races = $stmt->fetchAll();
}

$selectedYear = '';
foreach ($seasons as $s) {
    if ($s['id'] == $selectedSeasonId) { $selectedYear = $s['year']; break; }
}
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Race Results</h1>
        <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Season' : '' ?></p>
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

<?php if (empty($races)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">&#127937;</div>
        <p>No completed races for this season yet.</p>
    </div>
</div>
<?php else: ?>
<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="results-table" placeholder="Search races...">
        </div>
        <button class="btn btn-outline btn-sm" data-export-csv="results-table" data-filename="results_<?= h((string)$selectedYear) ?>.csv">Export CSV</button>
    </div>
    <table class="sortable" id="results-table">
        <thead><tr>
            <th>Round</th><th>Race</th><th>Circuit</th><th>Country</th><th>Date</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($races as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/race_detail.php?id=<?= (int)$r['id'] ?>">
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong><?= $r['has_sprint'] ? ' <span class="status-badge status-in_progress" style="font-size:0.7rem">SPRINT</span>' : '' ?></td>
            <td><?= h($r['circuit']) ?></td>
            <td class="text-muted"><?= h($r['country']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td class="no-row-click"><a href="<?= APP_URL ?>/shared/race_detail.php?id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">Results &rarr;</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
