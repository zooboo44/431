<?php
$pageTitle = 'Generate Reset Token';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db     = getDB();
$errors = [];
$tokenInfo = null;

$targetUserId = intval($_GET['user_id'] ?? 0);
if (!$targetUserId) {
    include __DIR__ . '/../includes/404.php';
    exit;
}

$stmt = $db->prepare('SELECT id, name, email FROM users WHERE id = ? AND is_active = 1');
$stmt->execute([$targetUserId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    include __DIR__ . '/../includes/404.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limit: max 3 per user per hour
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM audit_log
            WHERE action = 'password_reset_requested' AND resource_id = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)
        ");
        $stmt->execute([$targetUserId]);
        $recentCount = (int)$stmt->fetchColumn();

        if ($recentCount >= RESET_TOKEN_MAX_REQUESTS) {
            $errors[] = 'Rate limit reached: maximum ' . RESET_TOKEN_MAX_REQUESTS . ' reset tokens per hour per user.';
        } else {
            // Invalidate old unused tokens
            $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
               ->execute([$targetUserId]);

            // Generate token
            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + (RESET_TOKEN_EXPIRY_HOURS * 3600));

            $db->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
               ->execute([$targetUserId, $tokenHash, $expiresAt]);

            logAudit($_SESSION['user_id'], 'password_reset_requested', 'users', $targetUserId,
                "Reset token generated for user #{$targetUserId}");
            rotateCSRFToken();

            $tokenInfo = [
                'raw'     => $rawToken,
                'url'     => APP_URL . '/auth/reset_password.php?token=' . urlencode($rawToken),
                'expires' => $expiresAt,
            ];
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Generate Reset Token</h1>
        <p class="page-subtitle">For: <?= h($targetUser['name']) ?> (<?= h($targetUser['email']) ?>)</p>
    </div>
    <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">&larr; Back to Users</a>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><?= h($err) ?></div>
<?php endforeach; ?>

<?php if ($tokenInfo): ?>
<div class="card" style="border-color:var(--success)">
    <div class="card-title" style="color:var(--success)">&#9989; Reset Token Generated</div>
    <p class="mb-1"><strong>IMPORTANT:</strong> This token is shown <strong>once only</strong>. Copy it now and give it to the user manually.</p>
    <p class="text-muted mb-1" style="font-size:0.85rem">Expires: <?= h($tokenInfo['expires']) ?></p>

    <div class="form-group">
        <label class="form-label">Raw Token</label>
        <div class="token-box"><?= h($tokenInfo['raw']) ?></div>
    </div>

    <div class="form-group">
        <label class="form-label">Full Reset URL</label>
        <div class="token-box"><?= h($tokenInfo['url']) ?></div>
    </div>

    <p class="text-muted" style="font-size:0.85rem">The user visits this URL, enters a new password, and the token is consumed. It cannot be used again.</p>
</div>
<?php else: ?>
<div class="card" style="max-width:500px">
    <div class="card-title">Confirm Token Generation</div>
    <p class="mb-2" style="color:var(--text-secondary)">
        This will invalidate any existing unused tokens for <strong><?= h($targetUser['name']) ?></strong>
        and generate a new one-time reset link.
    </p>
    <div class="notice">Rate limit: maximum <?= RESET_TOKEN_MAX_REQUESTS ?> tokens per user per hour.</div>

    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-actions" style="margin-top:1rem;padding-top:0;border-top:none">
            <button type="submit" class="btn btn-primary">Generate Token</button>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
