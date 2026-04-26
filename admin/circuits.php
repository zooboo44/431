<?php
$pageTitle = 'Circuits';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'race_director');

$db = getDB();
$stmt = $db->query("
    SELECT c.*, CONCAT(p.first_name,' ',p.last_name) AS record_holder,
           COUNT(DISTINCT r.id) AS race_count
    FROM circuits c
    LEFT JOIN people p ON p.id = c.lap_record_person_id
    LEFT JOIN races r ON r.circuit_id = c.id
    GROUP BY c.id
    ORDER BY c.country, c.name
");
$circuits = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Circuits</h1>
        <p class="page-subtitle"><?= count($circuits) ?> circuit<?= count($circuits) != 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/circuits_create.php" class="btn btn-primary">+ Add Circuit</a>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="circuits-table" placeholder="Search circuits...">
        </div>
    </div>
    <table class="sortable" id="circuits-table">
        <thead><tr>
            <th>Name</th><th>Country</th><th>City</th><th>Type</th><th>Length</th><th>Laps</th><th>Races</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($circuits)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:2rem">No circuits found.</td></tr>
        <?php else: ?>
        <?php foreach ($circuits as $c): ?>
        <?php $delMsg = "Delete circuit '{$c['name']}'? This will remove all {$c['race_count']} associated races and their results. This cannot be undone."; ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/circuit_detail.php?id=<?= $c['id'] ?>">
            <td><strong><?= h($c['name']) ?></strong></td>
            <td><?= h($c['country']) ?></td>
            <td class="text-muted"><?= h($c['city']) ?></td>
            <td><span class="status-badge <?= $c['circuit_type'] === 'street' ? 'status-warning' : 'status-scheduled' ?>"><?= h($c['circuit_type']) ?></span></td>
            <td><?= h(number_format($c['length_km'], 3)) ?> km</td>
            <td><?= h((string)$c['number_of_laps']) ?></td>
            <td><?= h((string)$c['race_count']) ?></td>
            <td><span class="status-badge <?= $c['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="no-row-click" style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/circuits_edit.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="circuit">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/circuits.php') ?>">
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
