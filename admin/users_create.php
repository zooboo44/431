<?php
$pageTitle = 'Create User';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];

// Load people and teams for linked_id dropdowns
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

        $validRoles = ['admin','race_director','team_manager','engineer','driver','media','fan'];

        if (!$name)                                       $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'Valid email is required.';
        if (!in_array($role, $validRoles, true))          $errors[] = 'Invalid role selected.';

        // linked_id required for team_manager, engineer, driver
        if (in_array($role, ['team_manager','engineer']) && !$linkedId) $errors[] = 'Team is required for this role.';
        if ($role === 'driver' && !$linkedId)                           $errors[] = 'Driver person record is required.';

        if (empty($errors)) {
            // Check email unique
            $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'Email address is already in use.';
            } else {
                // Generate a temp password
                $rawPass = bin2hex(random_bytes(8)); // 16-char hex
                $hash    = password_hash($rawPass, PASSWORD_BCRYPT, ['cost' => 12]);

                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password_hash, role, linked_id, is_active, must_change_password, created_by)
                    VALUES (?, ?, ?, ?, ?, 1, 1, ?)
                ");
                $stmt->execute([$name, $email, $hash, $role, $linkedId, $_SESSION['user_id']]);
                $newId = $db->lastInsertId();

                logAudit($_SESSION['user_id'], 'user_created', 'users', (int)$newId, "Role: $role, Email: $email");
                rotateCSRFToken();

                // Store temp password in session to show once
                $_SESSION['new_user_temp_pass'] = $rawPass;
                $_SESSION['new_user_name']      = $name;

                redirectWithMessage(APP_URL . '/admin/users.php', 'success', "User '{$name}' created. Temp password: {$rawPass}");
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create User</h1>
    </div>
    <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">&larr; Back to Users</a>
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
                <input type="text" id="name" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label required" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>" required maxlength="150">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label required" for="role">Role</label>
            <select id="role" name="role" class="form-control" onchange="updateLinkedId(this.value)">
                <option value="">— Select Role —</option>
                <option value="admin"<?= ($_POST['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Admin</option>
                <option value="race_director"<?= ($_POST['role'] ?? '') === 'race_director' ? ' selected' : '' ?>>Race Director</option>
                <option value="team_manager"<?= ($_POST['role'] ?? '') === 'team_manager' ? ' selected' : '' ?>>Team Manager</option>
                <option value="engineer"<?= ($_POST['role'] ?? '') === 'engineer' ? ' selected' : '' ?>>Engineer</option>
                <option value="driver"<?= ($_POST['role'] ?? '') === 'driver' ? ' selected' : '' ?>>Driver</option>
                <option value="media"<?= ($_POST['role'] ?? '') === 'media' ? ' selected' : '' ?>>Media</option>
                <option value="fan"<?= ($_POST['role'] ?? '') === 'fan' ? ' selected' : '' ?>>Fan</option>
            </select>
        </div>

        <div class="form-group" id="linked-team-group" style="display:none">
            <label class="form-label" for="linked_team">Linked Team</label>
            <select id="linked_team" name="linked_id" class="form-control">
                <option value="">— Select Team —</option>
                <?php foreach ($teams as $t): ?>
                <option value="<?= h((string)$t['id']) ?>"><?= h($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="linked-driver-group" style="display:none">
            <label class="form-label" for="linked_driver">Linked Driver Person</label>
            <select id="linked_driver" name="linked_id_driver" class="form-control">
                <option value="">— Select Driver —</option>
                <?php foreach ($people as $p): ?>
                <option value="<?= h((string)$p['id']) ?>"><?= h($p['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="notice">
            A temporary password will be generated automatically and shown once after creating the account.
            The user will be required to change it on first login.
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
function updateLinkedId(role) {
    const teamGroup   = document.getElementById('linked-team-group');
    const driverGroup = document.getElementById('linked-driver-group');
    const linkedTeam  = document.getElementById('linked_team');
    const linkedDriver= document.getElementById('linked_driver');

    teamGroup.style.display   = ['team_manager','engineer'].includes(role) ? '' : 'none';
    driverGroup.style.display = role === 'driver' ? '' : 'none';

    // Sync the correct linked_id into the POST via name attribute manipulation
    if (role === 'driver') {
        linkedDriver.name = 'linked_id';
        linkedTeam.name   = 'linked_id_team_unused';
    } else {
        linkedTeam.name   = 'linked_id';
        linkedDriver.name = 'linked_id_driver_unused';
    }
}

// Init on load
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    if (roleSelect.value) updateLinkedId(roleSelect.value);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
