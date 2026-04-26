<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';

$db   = getDB();
$user = currentUser();
$badgeColor = ROLE_BADGE_COLORS[$user['role']] ?? '#555';

// Get linked entity
$linkedName = '—';
if ($user['linked_id']) {
    if (in_array($user['role'], ['driver'])) {
        $stmt = $db->prepare('SELECT CONCAT(first_name, " ", last_name) AS n FROM people WHERE id = ?');
        $stmt->execute([$user['linked_id']]);
        $row = $stmt->fetch();
        $linkedName = $row['n'] ?? '—';
    } elseif (in_array($user['role'], ['team_manager', 'engineer'])) {
        $stmt = $db->prepare('SELECT name FROM teams WHERE id = ?');
        $stmt->execute([$user['linked_id']]);
        $row = $stmt->fetch();
        $linkedName = $row['name'] ?? '—';
    }
}

// Last login from DB
$stmt = $db->prepare('SELECT last_login, created_at FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();
?>

<div class="page-header">
    <h1 class="page-title">My Profile</h1>
    <a href="<?= APP_URL ?>/shared/change_password.php" class="btn btn-outline">Change Password</a>
</div>

<div class="card" style="max-width:600px">
    <div class="card-title">Account Information</div>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">Name</div>
            <div class="info-value"><?= h($user['name']) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Email</div>
            <div class="info-value"><?= h($user['email']) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Role</div>
            <div class="info-value">
                <span class="role-badge" style="background:<?= $badgeColor ?>"><?= h(str_replace('_', ' ', $user['role'])) ?></span>
            </div>
        </div>
        <?php if ($user['linked_id']): ?>
        <div class="info-item">
            <div class="info-label">Linked To</div>
            <div class="info-value"><?= h($linkedName) ?></div>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <div class="info-label">Last Login</div>
            <div class="info-value"><?= $userData['last_login'] ? h(date('d M Y H:i', strtotime($userData['last_login']))) : 'N/A' ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Account Created</div>
            <div class="info-value"><?= $userData['created_at'] ? h(date('d M Y', strtotime($userData['created_at']))) : 'N/A' ?></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
