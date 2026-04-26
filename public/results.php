<?php
$pageTitle = 'Race Results';
require_once __DIR__ . '/../includes/header_public.php';

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
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Race Results</h1>
            <p class="page-subtitle"><?= $selectedYear ? h((string)$selectedYear) . ' Season' : '' ?></p>
        </div>
        <form class="season-selector" method="get">
            <label for="season" style="color:var(--text-secondary);font-size:0.85rem">Season:</label>
            <select name="season" id="season" onchange="this.form.submit()">
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
        </div>
        <table class="sortable" id="results-table">
            <thead><tr>
                <th data-sort="0">Round</th>
                <th data-sort="1">Race</th>
                <th data-sort="2">Circuit</th>
                <th data-sort="3">Country</th>
                <th data-sort="4">Date</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($races as $r): ?>
            <tr class="clickable-row" data-href="<?= APP_URL ?>/public/race_detail.php?id=<?= (int)$r['id'] ?>">
                <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
                <td><strong><?= h($r['name']) ?></strong><?= $r['has_sprint'] ? ' <span class="status-badge status-in_progress" style="font-size:0.7rem">SPRINT</span>' : '' ?></td>
                <td><?= h($r['circuit']) ?></td>
                <td class="text-muted"><?= h($r['country']) ?></td>
                <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
                <td class="no-row-click"><a href="<?= APP_URL ?>/public/race_detail.php?id=<?= h((string)$r['id']) ?>" class="btn btn-outline btn-sm">Results &rarr;</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<footer style="background:var(--bg-surface);border-top:1px solid var(--border);text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.8rem;margin-top:2rem">
    &copy; <?= date('Y') ?> <?= h(APP_NAME) ?>
</footer>

</main>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
