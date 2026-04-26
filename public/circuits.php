<?php
$pageTitle = 'Circuits';
require_once __DIR__ . '/../includes/header_public.php';

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
?>

<div class="container">
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
                <th data-sort="0">Circuit</th>
                <th data-sort="1">Country</th>
                <th data-sort="2">City</th>
                <th data-sort="3">Type</th>
                <th data-sort="4">Length</th>
                <th data-sort="5">Laps</th>
                <th data-sort="6">Lap Record</th>
                <th data-sort="7">Record Holder</th>
            </tr></thead>
            <tbody>
            <?php foreach ($circuits as $c): ?>
            <tr>
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
</div>

<footer style="background:var(--bg-surface);border-top:1px solid var(--border);text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.8rem;margin-top:2rem">
    &copy; <?= date('Y') ?> <?= h(APP_NAME) ?>
</footer>

</main>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
