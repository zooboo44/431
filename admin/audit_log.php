<?php
$pageTitle = 'Audit Log';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();

// Filters
$filterAction = strip_tags(trim($_GET['action'] ?? ''));
$filterUser   = intval($_GET['user_id'] ?? 0);
$filterDate   = $_GET['date'] ?? '';
$page         = max(1, intval($_GET['page'] ?? 1));
$perPage      = 25;

$where  = [];
$params = [];

if ($filterAction) {
    $where[]  = 'al.action LIKE ?';
    $params[] = '%' . $filterAction . '%';
}
if ($filterUser) {
    $where[]  = 'al.user_id = ?';
    $params[] = $filterUser;
}
if ($filterDate) {
    $where[]  = 'DATE(al.created_at) = ?';
    $params[] = $filterDate;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log al $whereClause");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT al.*, u.name AS user_name, u.role AS user_role
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $db->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

// Distinct actions for filter dropdown
$actions = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Audit Log</h1>
        <p class="page-subtitle"><?= number_format($total) ?> entr<?= $total == 1 ? 'y' : 'ies' ?></p>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:1.25rem">
    <form method="get" class="d-flex gap-1 align-center" style="flex-wrap:wrap">
        <select name="action" class="form-control" style="width:auto">
            <option value="">All Actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= h($a) ?>"<?= $filterAction === $a ? ' selected' : '' ?>><?= h($a) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="user_id" class="form-control" style="width:auto">
            <option value="">All Users</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= h((string)$u['id']) ?>"<?= $filterUser == $u['id'] ? ' selected' : '' ?>><?= h($u['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date" class="form-control" style="width:auto" value="<?= h($filterDate) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= APP_URL ?>/admin/audit_log.php" class="btn btn-outline btn-sm">Clear</a>
    </form>
</div>

<div class="table-container">
    <table>
        <thead><tr>
            <th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Resource</th><th>Details</th><th>IP</th>
        </tr></thead>
        <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="7" class="text-center" style="padding:2rem;color:var(--text-muted)">No entries match the current filters.</td></tr>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <tr class="clickable-row" data-href="<?= APP_URL ?>/admin/audit_log_detail.php?id=<?= (int)$log['id'] ?>">
            <td class="text-muted" style="white-space:nowrap;font-size:0.8rem"><?= h(date('d M Y H:i:s', strtotime($log['created_at']))) ?></td>
            <td><?= $log['user_name'] ? h($log['user_name']) : '<span class="text-muted">Guest</span>' ?></td>
            <td><?php if ($log['user_role']): ?>
                <span class="role-badge" style="background:<?= h(ROLE_BADGE_COLORS[$log['user_role']] ?? '#555') ?>"><?= h($log['user_role']) ?></span>
            <?php else: ?>—<?php endif; ?></td>
            <td>
                <code style="font-size:0.8rem;color:<?php
                    if (str_contains($log['action'], 'fail') || str_contains($log['action'], 'denied')) echo 'var(--danger)';
                    elseif (str_contains($log['action'], 'success') || str_contains($log['action'], 'created')) echo 'var(--success)';
                    else echo 'var(--text-secondary)';
                ?>"><?= h($log['action']) ?></code>
            </td>
            <td class="text-muted"><?= $log['resource'] ? h($log['resource'] . ($log['resource_id'] ? ' #' . $log['resource_id'] : '')) : '—' ?></td>
            <td style="font-size:0.8rem;max-width:200px;overflow:hidden;text-overflow:ellipsis" class="text-muted">
                <?= $log['details'] ? h(substr($log['details'], 0, 100)) : '—' ?>
            </td>
            <td class="text-muted" style="font-size:0.8rem"><?= h($log['ip_address']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <span>Page <?= $page ?> of <?= $pages ?> (<?= number_format($total) ?> total)</span>
        <div class="pagination-btns">
            <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-outline btn-sm">&larr; Prev</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
