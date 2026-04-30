<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        session_start();
    }
}

function checkSessionTimeout(): void {
    if (!isset($_SESSION['user_id'])) return;

    $lastActivity = $_SESSION['last_activity'] ?? 0;
    if (time() - $lastActivity > SESSION_TIMEOUT) {
        $userId = $_SESSION['user_id'];
        try {
            getDB()->prepare('UPDATE users SET session_token=NULL, session_ip=NULL, session_ua=NULL, session_expires=NULL WHERE id=?')
                   ->execute([$userId]);
        } catch (Exception $e) {}
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/auth/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . APP_URL . '/auth/login.php?redirect=' . $redirect);
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $roles, true)) {
        logAudit($_SESSION['user_id'], 'permission_denied', null, null,
            'Attempted to access ' . ($_SERVER['REQUEST_URI'] ?? ''));
        include __DIR__ . '/../includes/403.php';
        exit;
    }
}

function enforcePasswordChange(): void {
    if (!empty($_SESSION['must_change_password'])) {
        $currentPath = $_SERVER['PHP_SELF'] ?? '';
        $allowed = ['/f1app/shared/change_password.php', '/f1app/auth/logout.php'];
        foreach ($allowed as $path) {
            if (str_ends_with($currentPath, ltrim($path, '/'))) return;
        }
        header('Location: ' . APP_URL . '/shared/change_password.php');
        exit;
    }
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, name, email, role, linked_id, must_change_password FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    $stored = $_SESSION['csrf_token'] ?? '';
    return hash_equals($stored, $token);
}

function rotateCSRFToken(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function checkDisplacedSession(): void {
    if (empty($_SESSION['user_id'])) return;

    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, RESTRICTED_ROLES, true)) return;

    $db = getDB();
    $stmt = $db->prepare('SELECT session_token FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || $row['session_token'] !== session_id()) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/auth/login.php?displaced=1');
        exit;
    }
}
