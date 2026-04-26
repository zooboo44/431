<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();

$stmt = $db->prepare("
    SELECT u.*, cb.name AS created_by_name
    FROM users u
    LEFT JOIN users cb ON cb.id = u.created_by
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
renderFlash();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Users</h1>
        <p class="page-subtitle"><?= count($users) ?> account<?= count($users) != 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/users_create.php" class="btn btn-primary">+ Add User</a>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="table-search">
            <input type="text" class="table-search-input" data-table="users-table" placeholder="Search users...">
        </div>
    </div>
    <table class="sortable" id="users-table">
        <thead><tr>
            <th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Created</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:2rem">No users found.</td></tr>
        <?php else: ?>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><strong><?= h($u['name']) ?></strong></td>
            <td><?= h($u['email']) ?></td>
            <td>
                <span class="role-badge" style="background:<?= h(ROLE_BADGE_COLORS[$u['role']] ?? '#555') ?>">
                    <?= h(str_replace('_', ' ', $u['role'])) ?>
                </span>
            </td>
            <td>
                <span class="status-badge <?= $u['is_active'] ? 'status-active' : 'status-inactive' ?>">
                    <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </td>
            <td class="text-muted"><?= $u['last_login'] ? h(date('d M Y H:i', strtotime($u['last_login']))) : 'Never' ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($u['created_at']))) ?></td>
            <td style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/users_edit.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                <a href="<?= APP_URL ?>/admin/reset_token.php?user_id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Reset</a>
                <?php if ($u['id'] != 1): ?>
                <form method="post" action="<?= APP_URL ?>/admin/toggle.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="user">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="activate" value="<?= $u['is_active'] ? 0 : 1 ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/users.php') ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" action="<?= APP_URL ?>/admin/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="entity" value="user">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(APP_URL . '/admin/users.php') ?>">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete user '<?= h($u['name']) ?>'? This cannot be undone.">Delete</button>
                </form>
                <?php else: ?>
                <span class="text-muted" style="font-size:0.75rem">Protected</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
