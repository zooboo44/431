<?php
$pageTitle = 'My Penalties';
require_once __DIR__ . '/../includes/header.php';
requireRole('driver');

$db       = getDB();
$personId = intval($_SESSION['linked_id'] ?? 0);

if (!$personId) { include __DIR__ . '/../includes/403.php'; exit; }

// IDOR: WHERE pen.person_id = personId — driver can only see own penalties
$stmt = $db->prepare("
    SELECT pen.penalty_type, pen.reason, pen.time_penalty_s, pen.grid_penalty_positions,
           pen.licence_points_awarded, pen.is_dsq, pen.issued_at,
           r.name AS race_name, r.round_number, s.year,
           u.name AS issued_by_name
    FROM penalties pen
    JOIN races r ON r.id = pen.race_id
    JOIN seasons s ON s.id = r.season_id
    JOIN users u ON u.id = pen.issued_by
    WHERE pen.person_id = ?
    ORDER BY pen.issued_at DESC
");
$stmt->execute([$personId]);
$penalties = $stmt->fetchAll();

// Totals
$totalLicencePoints = array_sum(array_column($penalties, 'licence_points_awarded'));
$dsqCount           = count(array_filter($penalties, fn($p) => $p['is_dsq']));
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Penalties</h1>
        <p class="page-subtitle"><?= count($penalties) ?> penalt<?= count($penalties) != 1 ? 'ies' : 'y' ?> on record</p>
    </div>
</div>

<?php if ($totalLicencePoints > 0 || $dsqCount > 0): ?>
<div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:1.5rem;max-width:400px">
    <div class="stat-card">
        <div class="stat-value text-warning"><?= h((string)$totalLicencePoints) ?></div>
        <div class="stat-label">Licence Points (All-time)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h((string)$dsqCount) ?></div>
        <div class="stat-label">Disqualifications</div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($penalties)): ?>
<div class="card">
    <div class="empty-state"><p>No penalties on record. Keep it clean!</p></div>
</div>
<?php else: ?>
<?php foreach ($penalties as $pen): ?>
<div class="card" style="margin-bottom:0.75rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem">
        <div>
            <span class="status-badge status-<?= $pen['is_dsq'] ? 'dsq' : 'warning' ?>" style="font-size:0.85rem">
                <?= h(str_replace('_', ' ', strtoupper($pen['penalty_type']))) ?>
            </span>
            <span class="text-muted" style="margin-left:0.5rem;font-size:0.85rem">
                <?= h((string)$pen['year']) ?> Rd <?= h((string)$pen['round_number']) ?> — <?= h($pen['race_name']) ?>
            </span>
        </div>
        <span class="text-muted" style="font-size:0.8rem"><?= h(date('d M Y H:i', strtotime($pen['issued_at']))) ?></span>
    </div>
    <p style="margin:0.6rem 0 0.4rem;color:var(--text-secondary)"><?= h($pen['reason']) ?></p>
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:0.85rem">
        <?php if ($pen['time_penalty_s']): ?>
        <span><strong>+<?= h((string)$pen['time_penalty_s']) ?>s</strong> time penalty</span>
        <?php endif; ?>
        <?php if ($pen['grid_penalty_positions']): ?>
        <span><strong><?= h((string)$pen['grid_penalty_positions']) ?> place<?= $pen['grid_penalty_positions'] != 1 ? 's' : '' ?></strong> grid drop</span>
        <?php endif; ?>
        <?php if ($pen['licence_points_awarded']): ?>
        <span class="text-warning"><strong><?= h((string)$pen['licence_points_awarded']) ?> licence point<?= $pen['licence_points_awarded'] != 1 ? 's' : '' ?></strong></span>
        <?php endif; ?>
    </div>
    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.4rem">Issued by <?= h($pen['issued_by_name']) ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
