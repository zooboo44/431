<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();
$errors = [];
$validRoles = ['admin', 'team_manager', 'driver'];

// ── Create / Edit form ────────────────────────────────────────────────────────
$isEdit = isset($_GET['id']);
$isForm = $isEdit || ($_GET['action'] ?? '') === 'create';

if ($isForm) {
    $userId   = $isEdit ? intval($_GET['id']) : 0;
    $editUser = null;

    if ($isEdit) {
        if (!$userId) { include __DIR__ . '/../includes/404.php'; exit; }
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $editUser = $stmt->fetch();
        if (!$editUser) { include __DIR__ . '/../includes/404.php'; exit; }
    }

    $teams = $db->query("SELECT id, name FROM teams WHERE is_active=1 ORDER BY name")->fetchAll();
    $selectedRole  = '';
    $unlinkDrivers = [];

    if (!$isEdit) {
        $selectedRole = $_GET['role'] ?? ($_POST['role'] ?? '');
        if ($selectedRole && !in_array($selectedRole, $validRoles, true)) $selectedRole = '';

        if ($selectedRole === 'driver') {
            $stmt = $db->query("
                SELECT p.id, p.first_name, p.last_name, p.racing_number
                FROM people p
                WHERE p.is_active = 1
                  AND NOT EXISTS (SELECT 1 FROM users u WHERE u.role = 'driver' AND u.linked_id = p.id)
                ORDER BY p.last_name, p.first_name
            ");
            $unlinkDrivers = $stmt->fetchAll();
        }
    } else {
        $people = $db->query("SELECT id, CONCAT(first_name,' ',last_name,' (#',racing_number,')') AS label FROM people WHERE is_active=1 ORDER BY last_name")->fetchAll();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid request token.';
        } else {
            $name     = strip_tags(trim($_POST['name'] ?? ''));
            $email    = trim($_POST['email'] ?? '');
            $role     = $_POST['role'] ?? '';
            $linkedId = intval($_POST['linked_id'] ?? 0) ?: null;

            if (!$name)                                      $errors[] = 'Full name is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Valid email is required.';
            if (!in_array($role, $validRoles, true))         $errors[] = 'Invalid role selected.';

            if ($isEdit) {
                $isActive   = isset($_POST['is_active']) ? 1 : 0;
                $mustChange = isset($_POST['must_change_password']) ? 1 : 0;
            } else {
                $password = $_POST['password'] ?? '';
                $passErr  = validatePassword($password);
                if ($passErr) $errors[] = $passErr;
                if ($role === 'team_manager' && !$linkedId) $errors[] = 'A team must be selected for team manager role.';
                if ($role === 'driver' && !$linkedId)       $errors[] = 'A driver person record must be selected.';
            }

            if (empty($errors)) {
                $chk = $db->prepare('SELECT id FROM users WHERE email = ?' . ($isEdit ? ' AND id != ?' : ''));
                $chk->execute($isEdit ? [$email, $userId] : [$email]);
                if ($chk->fetch()) {
                    $errors[] = 'Email address is already in use' . ($isEdit ? ' by another account.' : '.');
                } else {
                    if ($isEdit) {
                        $db->prepare("UPDATE users SET name=?, email=?, role=?, linked_id=?, is_active=?, must_change_password=? WHERE id=?")
                           ->execute([$name, $email, $role, $linkedId, $isActive, $mustChange, $userId]);
                        logAudit($_SESSION['user_id'], 'user_updated', 'users', $userId, "Role: $role");
                        rotateCSRFToken();
                        redirectWithMessage(APP_URL . '/admin/users.php', 'success', "User '{$name}' updated successfully.");
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $db->prepare("INSERT INTO users (name, email, password_hash, role, linked_id, is_active, must_change_password, created_by) VALUES (?,?,?,?,?,1,0,?)")
                           ->execute([$name, $email, $hash, $role, $linkedId, $_SESSION['user_id']]);
                        $newId = $db->lastInsertId();
                        logAudit($_SESSION['user_id'], 'user_created', 'users', (int)$newId, "Role: $role, Email: $email");
                        rotateCSRFToken();
                        redirectWithMessage(APP_URL . '/admin/users.php', 'success', "User '{$name}' created successfully.");
                    }
                }
            }
        }
    }

    $csrfToken = generateCSRFToken();
    ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isEdit ? 'Edit User' : 'Create User' ?></h1>
        <?php if ($isEdit && $editUser): ?><p class="page-subtitle"><?= h($editUser['email']) ?></p><?php endif; ?>
    </div>
    <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">&larr; Back to Users</a>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= h($_POST['name'] ?? ($editUser['name'] ?? '')) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label required" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? ($editUser['email'] ?? '')) ?>" required maxlength="150">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label required" for="role">Role</label>
            <select id="role" name="role" class="form-control"<?= !$isEdit ? ' onchange="handleRoleChange(this.value)"' : '' ?>>
                <?php if (!$isEdit): ?><option value="">— Select Role —</option><?php endif; ?>
                <?php foreach ($validRoles as $r): ?>
                <option value="<?= h($r) ?>"<?= (($isEdit ? ($editUser['role'] ?? '') : ($selectedRole ?: ($_POST['role'] ?? ''))) === $r) ? ' selected' : '' ?>>
                    <?= h(ucfirst(str_replace('_', ' ', $r))) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$isEdit): ?><div class="form-hint">After selecting a role, any required profile link will appear below.</div><?php endif; ?>
        </div>

        <?php if (!$isEdit): ?>
        <?php if ($selectedRole === 'driver'): ?>
        <div class="form-group">
            <label class="form-label required" for="linked_id">Driver Person Record</label>
            <?php if (empty($unlinkDrivers)): ?>
            <div class="notice">All active driver records already have user accounts. <a href="<?= APP_URL ?>/admin/people.php?action=create">Add a driver record</a> first.</div>
            <input type="hidden" name="linked_id" value="">
            <?php else: ?>
            <select id="linked_id" name="linked_id" class="form-control" required>
                <option value="">— Select Driver —</option>
                <?php foreach ($unlinkDrivers as $p): ?>
                <option value="<?= (int)$p['id'] ?>"<?= ($_POST['linked_id'] ?? '') == $p['id'] ? ' selected' : '' ?>>
                    #<?= h((string)$p['racing_number']) ?> <?= h($p['first_name'] . ' ' . $p['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div class="form-hint">Only drivers without an existing account are shown.</div>
            <?php endif; ?>
        </div>
        <?php elseif ($selectedRole === 'team_manager'): ?>
        <div class="form-group">
            <label class="form-label required" for="linked_id">Linked Team</label>
            <select id="linked_id" name="linked_id" class="form-control" required>
                <option value="">— Select Team —</option>
                <?php foreach ($teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>"<?= ($_POST['linked_id'] ?? '') == $t['id'] ? ' selected' : '' ?>><?= h($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" data-pw-validate="pw-feedback" required maxlength="25" placeholder="Set a secure password">
                <div id="pw-feedback" class="pw-feedback"></div>
                <div class="form-hint"><?= h(passwordHint()) ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="linked_id">Linked Entity (optional)</label>
                <select id="linked_id" name="linked_id" class="form-control">
                    <option value="">— None —</option>
                    <optgroup label="Teams">
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= h((string)$t['id']) ?>"<?= ($editUser['linked_id'] ?? '') == $t['id'] ? ' selected' : '' ?>>[Team] <?= h($t['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Drivers">
                        <?php foreach ($people as $p): ?>
                        <option value="<?= h((string)$p['id']) ?>"<?= ($editUser['linked_id'] ?? '') == $p['id'] ? ' selected' : '' ?>>[Driver] <?= h($p['label']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Account Status</label>
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-top:0.5rem">
                    <input type="checkbox" name="is_active" value="1"<?= ($editUser['is_active'] ?? 1) ? ' checked' : '' ?>>
                    <span>Active account</span>
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">Force Password Change</label>
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-top:0.5rem">
                    <input type="checkbox" name="must_change_password" value="1"<?= ($editUser['must_change_password'] ?? 0) ? ' checked' : '' ?>>
                    <span>Must change password on next login</span>
                </label>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
            <?php if ($isEdit): ?>
            <a href="<?= APP_URL ?>/admin/reset_token.php?user_id=<?= $userId ?>" class="btn btn-secondary">Generate Reset Token</a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php if (!$isEdit): ?>
<script>
function handleRoleChange(role) {
    const url = new URL(window.location.href);
    url.searchParams.set('role', role);
    window.location.href = url.toString();
}
</script>
<?php endif; ?>

<?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── List view ─────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT u.*,
           cb.name AS created_by_name,
           p.first_name AS person_first, p.last_name AS person_last,
           p.racing_number,
           t.name AS team_name
    FROM users u
    LEFT JOIN users cb ON cb.id = u.created_by
    LEFT JOIN people p ON p.id = u.linked_id AND u.role = 'driver'
    LEFT JOIN teams t ON t.id = u.linked_id AND u.role = 'team_manager'
    ORDER BY FIELD(u.role,'admin','team_manager','driver'), u.name ASC
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
    <a href="<?= APP_URL ?>/admin/users.php?action=create" class="btn btn-primary">+ Add User</a>
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
            <td><span class="role-badge" style="background:<?= h(ROLE_BADGE_COLORS[$u['role']] ?? '#555') ?>"><?= h(str_replace('_', ' ', $u['role'])) ?></span></td>
            <td><span class="status-badge <?= $u['is_active'] ? 'status-active' : 'status-inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="text-muted"><?= $u['last_login'] ? h(date('d M Y H:i', strtotime($u['last_login']))) : 'Never' ?></td>
            <td class="text-muted"><?= h(date('d M Y', strtotime($u['created_at']))) ?></td>
            <td style="display:flex;gap:0.35rem;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/users.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
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
