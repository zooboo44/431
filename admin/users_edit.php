<?php
$pageTitle = 'Edit User';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];
$userId = intval($_GET['id'] ?? 0);

if (!$userId) {
    include __DIR__ . '/../includes/404.php';
    exit;
}

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$editUser = $stmt->fetch();

if (!$editUser) {
    include __DIR__ . '/../includes/404.php';
    exit;
}

$people = $db->query("SELECT id, CONCAT(first_name,' ',last_name,' (#',racing_number,')') AS label FROM people WHERE is_active=1 ORDER BY last_name")->fetchAll();
$teams  = $db->query("SELECT id, name FROM teams WHERE is_active=1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $name     = strip_tags(trim($_POST['name'] ?? ''));
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? '';
        $linkedId = intval($_POST['linked_id'] ?? 0) ?: null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $mustChange = isset($_POST['must_change_password']) ? 1 : 0;

        $validRoles = ['admin','race_director','team_manager','engineer','driver','media','fan'];

        if (!$name)                                     $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (!in_array($role, $validRoles, true))        $errors[] = 'Invalid role.';

        if (empty($errors)) {
            // Check email unique (excluding current user)
            $chk = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $chk->execute([$email, $userId]);
            if ($chk->fetch()) {
                $errors[] = 'Email address is already in use by another account.';
            } else {
                $db->prepare("
                    UPDATE users SET name=?, email=?, role=?, linked_id=?, is_active=?, must_change_password=?
                    WHERE id=?
                ")->execute([$name, $email, $role, $linkedId, $isActive, $mustChange, $userId]);

                $action = $isActive ? 'user_updated' : 'user_deactivated';
                logAudit($_SESSION['user_id'], $action, 'users', $userId, "Role: $role");
                rotateCSRFToken();
                redirectWithMessage(APP_URL . '/admin/users.php', 'success', "User '{$name}' updated successfully.");
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit User</h1>
        <p class="page-subtitle"><?= h($editUser['email']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">&larr; Back</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card">
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= h($_POST['name'] ?? $editUser['name']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label required" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? $editUser['email']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="role">Role</label>
                <select id="role" name="role" class="form-control">
                    <?php foreach (['admin','race_director','team_manager','engineer','driver','media','fan'] as $r): ?>
                    <option value="<?= h($r) ?>"<?= ($editUser['role'] === $r) ? ' selected' : '' ?>><?= h(str_replace('_',' ',$r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="linked_id">Linked Entity (optional)</label>
                <select id="linked_id" name="linked_id" class="form-control">
                    <option value="">— None —</option>
                    <optgroup label="Teams">
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= h((string)$t['id']) ?>"<?= $editUser['linked_id'] == $t['id'] ? ' selected' : '' ?>>[Team] <?= h($t['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Drivers">
                        <?php foreach ($people as $p): ?>
                        <option value="<?= h((string)$p['id']) ?>"<?= $editUser['linked_id'] == $p['id'] ? ' selected' : '' ?>>[Driver] <?= h($p['label']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Account Status</label>
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-top:0.5rem">
                    <input type="checkbox" name="is_active" value="1"<?= ($editUser['is_active'] ? ' checked' : '') ?>>
                    <span>Active account</span>
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">Force Password Change</label>
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-top:0.5rem">
                    <input type="checkbox" name="must_change_password" value="1"<?= ($editUser['must_change_password'] ? ' checked' : '') ?>>
                    <span>Must change password on next login</span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/reset_token.php?user_id=<?= $userId ?>" class="btn btn-secondary">Generate Reset Token</a>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
