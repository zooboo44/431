<?php
$pageTitle = 'Races';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

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
        SELECT r.*, c.name AS circuit, c.country,
               (SELECT COUNT(*) FROM race_entries re WHERE re.race_id=r.id) AS entry_count
        FROM races r JOIN circuits c ON c.id = r.circuit_id
        WHERE r.season_id = ?
        ORDER BY r.round_number ASC
    ");
    $stmt->execute([$selectedSeasonId]);
    $races = $stmt->fetchAll();
}

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Races</h1>
        <p class="page-subtitle"><?= count($races) ?> race<?= count($races) != 1 ? 's' : '' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center">
        <form method="get" class="season-selector">
            <select name="season" onchange="this.form.submit()">
                <?php foreach ($seasons as $s): ?>
                <option value="<?= h((string)$s['id']) ?>"<?= $s['id'] == $selectedSeasonId ? ' selected' : '' ?>><?= h((string)$s['year']) ?><?= $s['is_active'] ? ' ★' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="<?= APP_URL ?>/admin/races_create.php?season_id=<?= $selectedSeasonId ?>" class="btn btn-primary">+ Add Race</a>
    </div>
</div>

<div class="table-container">
    <table class="sortable">
        <thead><tr>
            <th>Round</th><th>Name</th><th>Circuit</th><th>Date</th><th>Sprint</th><th>Status</th><th>Entries</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($races)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:2rem">No races for this season.</td></tr>
        <?php else: ?>
        <?php foreach ($races as $r): ?>
        <?php $delMsg = "Delete race '{$r['name']}'? This will remove all {$r['entry_count']} entries, results and qualifying data. Cannot be undone."; ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/race_detail.php?id=<?= $r['id'] ?>">
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td class="text-muted"><?= h($r['circuit']) ?>, <?= h($r['country']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
            <td><span class="status-badge status-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
            <td><?= h((string)$r['entry_count']) ?></td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/races_edit.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="race">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/races.php?season=' . $selectedSeasonId) ?>">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="<?= h($delMsg) ?>">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
