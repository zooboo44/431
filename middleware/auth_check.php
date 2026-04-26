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
        // Probabilistic cleanup of expired DB session rows (1-in-100 requests)
        if (mt_rand(1, 100) === 1) {
            try {
                getDB()->prepare('DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)')
                       ->execute();
            } catch (Exception $e) {}
        }
    }
}

function checkSessionTimeout(): void {
    if (!isset($_SESSION['user_id'])) return;

    $lastActivity = $_SESSION['last_activity'] ?? 0;
    if (time() - $lastActivity > SESSION_TIMEOUT) {
        $sessionId = session_id();
        $db = getDB();
        $db->prepare('DELETE FROM sessions WHERE id = ?')->execute([$sessionId]);
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

function enforceSingleSession(int $userId): void {
    $db = getDB();
    $currentSessionId = session_id();

    // Log all displaced sessions
    $stmt = $db->prepare('SELECT id FROM sessions WHERE user_id = ? AND id != ?');
    $stmt->execute([$userId, $currentSessionId]);
    $displaced = $stmt->fetchAll();

    if ($displaced) {
        logAudit($userId, 'session_displaced', 'users', $userId,
            count($displaced) . ' prior session(s) terminated');
    }

    // Kill all prior sessions for this user
    $stmt = $db->prepare('DELETE FROM sessions WHERE user_id = ? AND id != ?');
    $stmt->execute([$userId, $currentSessionId]);
}

function checkDisplacedSession(): void {
    if (empty($_SESSION['user_id'])) return;

    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, RESTRICTED_ROLES, true)) return;

    $db = getDB();
    $sessionId = session_id();
    $stmt = $db->prepare('SELECT id FROM sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $_SESSION['user_id']]);

    if (!$stmt->fetch()) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/auth/login.php?displaced=1');
        exit;
    }
}
