<?php

require_once __DIR__ . '/db_fns.php';
require_once __DIR__ . '/url_fns.php';

define('SESSION_IDLE_TIMEOUT_SECONDS', 1800);

function ensure_session_started() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function clear_session() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    session_destroy();
}

function require_login() {
    ensure_session_started();

    if (!isset($_SESSION['account_id'])) {
        redirect_to('login.php');
    }

    $now = time();
    if (isset($_SESSION['last_activity_at']) && ($now - (int) $_SESSION['last_activity_at']) > SESSION_IDLE_TIMEOUT_SECONDS) {
        clear_session();
        redirect_to('login.php?timeout=1');
    }

    $_SESSION['last_activity_at'] = $now;
}

function current_user_id() {
    ensure_session_started();
    return isset($_SESSION['account_id']) ? (int) $_SESSION['account_id'] : null;
}

function current_role_internal_name() {
    ensure_session_started();
    return $_SESSION['role_internal_name'] ?? null;
}

function current_team_id() {
    ensure_session_started();
    return isset($_SESSION['team_id']) && $_SESSION['team_id'] !== null ? (int) $_SESSION['team_id'] : null;
}

function current_driver_id() {
    ensure_session_started();
    return isset($_SESSION['driver_id']) && $_SESSION['driver_id'] !== null ? (int) $_SESSION['driver_id'] : null;
}

function has_role($roles) {
    $roles = is_array($roles) ? $roles : [$roles];
    return in_array(current_role_internal_name(), $roles, true);
}

function require_role($roles) {
    require_login();

    if (!has_role($roles)) {
        deny_access();
    }
}

function is_league_director() {
    return current_role_internal_name() === 'league_director';
}

function is_team_manager() {
    return current_role_internal_name() === 'team_manager';
}

function is_driver() {
    return current_role_internal_name() === 'driver';
}

function is_viewer() {
    return current_role_internal_name() === 'viewer';
}

function can_manage_all() {
    return is_league_director();
}

function can_manage_team($team_id) {
    if (can_manage_all()) {
        return true;
    }

    return is_team_manager()
        && current_team_id() !== null
        && (int) $team_id === current_team_id();
}

function get_driver_team_id($driver_id) {
    $db = db_connect();
    $query = "SELECT team_id FROM drivers WHERE id = ?";
    $stmt = $db->prepare($query);

    if (!$stmt) {
        $db->close();
        return null;
    }

    $driver_id = (int) $driver_id;
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();
    $stmt->close();
    $db->close();

    return $driver ? (int) $driver['team_id'] : null;
}

function can_manage_driver($driver_id) {
    if (can_manage_all()) {
        return true;
    }

    if (!is_team_manager()) {
        return false;
    }

    $driver_team_id = get_driver_team_id($driver_id);
    return $driver_team_id !== null && can_manage_team($driver_team_id);
}

function can_edit_own_driver_profile($driver_id) {
    if (can_manage_driver($driver_id)) {
        return true;
    }

    return is_driver()
        && current_driver_id() !== null
        && (int) $driver_id === current_driver_id();
}

function can_view_audit_log() {
    return is_league_director();
}

function log_unauthorized_access_attempt($details = '') {
    $db = db_connect();
    $account_id = current_user_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $path = $_SERVER['REQUEST_URI'] ?? '';
    $full_details = trim($path . ' ' . $details);

    $query = "INSERT INTO audit_logs (account_id, action, details, ip_address, user_agent)
              VALUES (?, 'unauthorized_access_attempt', ?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt) {
        $stmt->bind_param("isss", $account_id, $full_details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }

    $db->close();
}

function deny_access($details = '') {
    log_unauthorized_access_attempt($details);
    http_response_code(403);
    echo "Unauthorized access";
    exit();
}

?>
