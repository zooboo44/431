<?php
$pageTitle = 'Create User';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];

$selectedRole = $_GET['role'] ?? ($_POST['role'] ?? '');
$validRoles   = ['admin','race_director','team_manager','engineer','driver','media','fan'];
if ($selectedRole && !in_array($selectedRole, $validRoles, true)) $selectedRole = '';

// Load data based on selected role
$teams  = $db->query("SELECT id, name FROM teams WHERE is_active=1 ORDER BY name")->fetchAll();

// Unlinked driver people records: active people without a driver user account
$unlinkDrivers = [];
if ($selectedRole === 'driver') {
    $stmt = $db->query("
        SELECT p.id, p.first_name, p.last_name, p.racing_number
        FROM people p
        WHERE p.is_active = 1
          AND p.requested_by_team_id IS NULL
          AND NOT EXISTS (SELECT 1 FROM users u WHERE u.role = 'driver' AND u.linked_id = p.id)
        ORDER BY p.last_name, p.first_name
    ");
    $unlinkDrivers = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $name     = strip_tags(trim($_POST['name'] ?? ''));
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';
        $linkedId = intval($_POST['linked_id'] ?? 0) ?: null;

        if (!$name)                                       $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'Valid email is required.';
        if (!in_array($role, $validRoles, true))          $errors[] = 'Invalid role selected.';

        $passErr = validatePassword($password);
        if ($passErr) $errors[] = $passErr;

        if (in_array($role, ['team_manager','engineer'], true) && !$linkedId)
            $errors[] = 'A team must be selected for this role.';
        if ($role === 'driver' && !$linkedId)
            $errors[] = 'A driver person record must be selected.';

        if (empty($errors)) {
            $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'Email address is already in use.';
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

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <h1 class="page-title">Create User</h1>
    <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">&larr; Back to Users</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card">
    <form method="post" action="<?= APP_URL ?>/admin/users_create.php" id="create-user-form">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= h($_POST['name'] ?? '') ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label required" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= h($_POST['email'] ?? '') ?>" required maxlength="150">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label required" for="role">Role</label>
            <select id="role" name="role" class="form-control" onchange="handleRoleChange(this.value)">
                <option value="">— Select Role —</option>
                <?php foreach ($validRoles as $r): ?>
                <option value="<?= h($r) ?>"<?= ($selectedRole === $r || ($_POST['role'] ?? '') === $r) ? ' selected' : '' ?>>
                    <?= h(ucfirst(str_replace('_', ' ', $r))) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div class="form-hint">After selecting a role, any required profile link will appear below.</div>
        </div>

        <?php if ($selectedRole === 'driver'): ?>
        <div class="form-group" id="linked-driver-group">
            <label class="form-label required" for="linked_id">Driver Person Record</label>
            <?php if (empty($unlinkDrivers)): ?>
            <div class="notice">All active driver records already have user accounts. Add a driver record first via <a href="<?= APP_URL ?>/admin/people_create.php">Add Driver</a>.</div>
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
        <?php elseif (in_array($selectedRole, ['team_manager','engineer'], true)): ?>
        <div class="form-group" id="linked-team-group">
            <label class="form-label required" for="linked_id">Linked Team</label>
            <select id="linked_id" name="linked_id" class="form-control" required>
                <option value="">— Select Team —</option>
                <?php foreach ($teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>"<?= ($_POST['linked_id'] ?? '') == $t['id'] ? ' selected' : '' ?>>
                    <?= h($t['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label required" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       data-pw-validate="pw-feedback" required maxlength="25"
                       placeholder="Set a secure password">
                <div id="pw-feedback" class="pw-feedback"></div>
                <div class="form-hint"><?= h(passwordHint()) ?></div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
function handleRoleChange(role) {
    // Reload page with role param so PHP can filter the correct person list
    const url = new URL(window.location.href);
    url.searchParams.set('role', role);
    window.location.href = url.toString();
}

// Don't submit if role changes — let the onchange handle reload
document.getElementById('role').addEventListener('change', function(e) {
    e.preventDefault();
    handleRoleChange(this.value);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
