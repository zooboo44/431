<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../middleware/auth_check.php';

startSecureSession();

$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
    logAudit($userId, 'logout', 'users', $userId);

    // Delete DB session row
    try {
        $db = getDB();
        $db->prepare('DELETE FROM sessions WHERE id = ?')->execute([session_id()]);
    } catch (Exception $e) {}
}

session_unset();
session_destroy();

header('Location: ' . APP_URL . '/auth/login.php?logged_out=1');
exit;
