<?php
$pageTitle = 'Circuits';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin', 'team_manager', 'driver');

$db = getDB();
$stmt = $db->prepare("
    SELECT c.*,
           CONCAT(p.first_name, ' ', p.last_name) AS lap_record_holder
    FROM circuits c
    LEFT JOIN people p ON p.id = c.lap_record_person_id
    WHERE c.is_active = 1
    ORDER BY c.country, c.name
");
$stmt->execute();
$circuits = $stmt->fetchAll();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Circuits</h1>
        <p class="page-subtitle"><?= count($circuits) ?> active circuit<?= count($circuits) != 1 ? 's' : '' ?></p>
    </div>
</div>

<?php if (empty($circuits)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">&#9940;</div>
        <p>No circuits available.</p>
    </div>
</div>
<?php else: ?>
<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="circuits-table" placeholder="Search circuits...">
        </div>
    </div>
    <table class="sortable" id="circuits-table">
        <thead><tr>
            <th>Circuit</th><th>Country</th><th>City</th><th>Type</th>
            <th>Length</th><th>Laps</th><th>Lap Record</th><th>Record Holder</th>
        </tr></thead>
        <tbody>
        <?php foreach ($circuits as $c): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/circuit_detail.php?id=<?= (int)$c['id'] ?>">
            <td><strong><?= h($c['name']) ?></strong></td>
            <td><?= h($c['country']) ?></td>
            <td class="text-muted"><?= h($c['city']) ?></td>
            <td>
                <span class="status-badge <?= $c['circuit_type'] === 'street' ? 'status-warning' : 'status-scheduled' ?>">
                    <?= h($c['circuit_type']) ?>
                </span>
            </td>
            <td><?= h(number_format($c['length_km'], 3)) ?> km</td>
            <td><?= h((string)$c['number_of_laps']) ?></td>
            <td class="mono text-success">
                <?= $c['lap_record_ms'] ? h(formatLapTime($c['lap_record_ms'])) : '—' ?>
            </td>
            <td class="text-muted"><?= $c['lap_record_holder'] ? h($c['lap_record_holder']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
