<?php
$pageTitle = 'Circuit Detail';
require_once __DIR__ . '/../includes/header.php';
requireRole('team_manager', 'engineer', 'driver', 'media', 'fan', 'race_director', 'admin');

$db        = getDB();
$circuitId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT c.*,
           CONCAT(p.first_name,' ',p.last_name) AS record_holder_name,
           p.id AS record_holder_id
    FROM circuits c
    LEFT JOIN people p ON p.id = c.lap_record_person_id
    WHERE c.id = ? AND c.is_active = 1
");
$stmt->execute([$circuitId]);
$circuit = $stmt->fetch();
if (!$circuit) { include __DIR__ . '/../includes/404.php'; exit; }

$pageTitle = h($circuit['name']);

// All completed races at this circuit
$stmt = $db->prepare("
    SELECT r.id, r.name, r.round_number, r.race_date, r.has_sprint, s.year,
           (SELECT COUNT(*) FROM race_entries re WHERE re.race_id=r.id) AS entry_count
    FROM races r
    JOIN seasons s ON s.id = r.season_id
    WHERE r.circuit_id = ? AND r.status = 'completed'
    ORDER BY s.year DESC, r.round_number DESC
");
$stmt->execute([$circuitId]);
$races = $stmt->fetchAll();

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= h($circuit['name']) ?></h1>
        <p class="page-subtitle"><?= h($circuit['city']) ?>, <?= h($circuit['country']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/shared/circuits.php" class="btn btn-outline">&larr; Circuits</a>
</div>

<div class="grid-2" style="margin-bottom:1.5rem">
    <div class="card">
        <div class="card-title">Circuit Info</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Country</div><div class="info-value"><?= h($circuit['country']) ?></div></div>
            <div class="info-item"><div class="info-label">City</div><div class="info-value"><?= h($circuit['city']) ?></div></div>
            <div class="info-item">
                <div class="info-label">Type</div>
                <div class="info-value"><span class="status-badge <?= $circuit['circuit_type'] === 'street' ? 'status-warning' : 'status-scheduled' ?>"><?= h($circuit['circuit_type']) ?></span></div>
            </div>
            <div class="info-item"><div class="info-label">Length</div><div class="info-value"><?= h(number_format($circuit['length_km'], 3)) ?> km</div></div>
            <div class="info-item"><div class="info-label">Laps</div><div class="info-value"><?= h((string)$circuit['number_of_laps']) ?></div></div>
            <div class="info-item"><div class="info-label">Race Distance</div><div class="info-value"><?= h(number_format($circuit['length_km'] * $circuit['number_of_laps'], 2)) ?> km</div></div>
            <?php if ($circuit['lap_record_ms']): ?>
            <div class="info-item">
                <div class="info-label">Lap Record</div>
                <div class="info-value text-success fw-bold">
                    <?= h(formatLapTime($circuit['lap_record_ms'])) ?>
                    <?php if ($circuit['record_holder_name']): ?>
                    — <a href="<?= APP_URL ?>/shared/driver_detail.php?id=<?= (int)$circuit['record_holder_id'] ?>" style="font-weight:400;font-size:0.85rem"><?= h($circuit['record_holder_name']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Quick Stats</div>
        <div class="stats-grid" style="grid-template-columns:repeat(2,1fr)">
            <div class="stat-card">
                <div class="stat-value"><?= count($races) ?></div>
                <div class="stat-label">Races Held</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count(array_unique(array_column($races, 'year'))) ?></div>
                <div class="stat-label">Seasons</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">Races at <?= h($circuit['name']) ?></div>
    <?php if (empty($races)): ?>
    <div class="empty-state"><p>No completed races held here yet.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Season</th><th>Rd</th><th>Race</th><th>Date</th><th>Sprint</th><th>Entries</th></tr></thead>
        <tbody>
        <?php foreach ($races as $r): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/shared/race_detail.php?id=<?= (int)$r['id'] ?>">
            <td><strong><?= h((string)$r['year']) ?></strong></td>
            <td><span class="round-chip"><?= h((string)$r['round_number']) ?></span></td>
            <td><?= h($r['name']) ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($r['race_date']))) ?></td>
            <td><?= $r['has_sprint'] ? '<span class="status-badge status-in_progress">Yes</span>' : '—' ?></td>
            <td><?= h((string)$r['entry_count']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
