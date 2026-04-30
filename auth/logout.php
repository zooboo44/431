<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../middleware/auth_check.php';

startSecureSession();

$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
    logAudit($userId, 'logout', 'users', $userId);
    try {
        getDB()->prepare('UPDATE users SET session_token=NULL, session_ip=NULL, session_ua=NULL, session_at=NULL WHERE id=?')
               ->execute([$userId]);
    } catch (Exception $e) {}
}

session_unset();
session_destroy();

header('Location: ' . APP_URL . '/auth/login.php?logged_out=1');
exit;
