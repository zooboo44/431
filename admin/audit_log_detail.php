<?php
$pageTitle = 'Audit Log Entry';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db    = getDB();
$logId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT al.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE al.id = ?
");
$stmt->execute([$logId]);
$log = $stmt->fetch();
if (!$log) { include __DIR__ . '/../includes/404.php'; exit; }
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Audit Log Entry #<?= $logId ?></h1>
        <p class="page-subtitle"><?= h(date('d M Y H:i:s', strtotime($log['created_at']))) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/audit_log.php" class="btn btn-outline">&larr; Audit Log</a>
</div>

<div class="card" style="max-width:700px">
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">Log ID</div>
            <div class="info-value"><?= h((string)$log['id']) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Timestamp</div>
            <div class="info-value"><?= h(date('d M Y H:i:s', strtotime($log['created_at']))) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">User</div>
            <div class="info-value">
                <?php if ($log['user_name']): ?>
                <?= h($log['user_name']) ?>
                <?php if ($log['user_role']): ?>
                <span class="role-badge" style="background:<?= h(ROLE_BADGE_COLORS[$log['user_role']] ?? '#555') ?>;margin-left:0.4rem">
                    <?= h($log['user_role']) ?>
                </span>
                <?php endif; ?>
                <?php else: ?><span class="text-muted">Guest / System</span><?php endif; ?>
            </div>
        </div>
        <?php if ($log['user_email']): ?>
        <div class="info-item">
            <div class="info-label">Email</div>
            <div class="info-value"><?= h($log['user_email']) ?></div>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <div class="info-label">Action</div>
            <div class="info-value">
                <code style="font-size:0.9rem;color:<?php
                    if (str_contains($log['action'], 'fail') || str_contains($log['action'], 'denied')) echo 'var(--danger)';
                    elseif (str_contains($log['action'], 'create') || str_contains($log['action'], 'update')) echo 'var(--success)';
                    elseif (str_contains($log['action'], 'delete') || str_contains($log['action'], 'deactivate')) echo 'var(--warning)';
                    else echo 'var(--text-secondary)';
                ?>"><?= h($log['action']) ?></code>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">Resource</div>
            <div class="info-value">
                <?= $log['resource'] ? h($log['resource']) . ($log['resource_id'] ? ' <span class="text-muted">#' . h((string)$log['resource_id']) . '</span>' : '') : '<span class="text-muted">—</span>' ?>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">IP Address</div>
            <div class="info-value"><code><?= h($log['ip_address']) ?></code></div>
        </div>
        <div class="info-item" style="grid-column:1/-1">
            <div class="info-label">Details</div>
            <div class="info-value" style="white-space:pre-wrap;font-family:monospace;background:var(--bg-input);padding:0.75rem;border-radius:var(--radius);font-size:0.9rem">
                <?= $log['details'] ? h($log['details']) : '<span class="text-muted">No additional details recorded.</span>' ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
