<?php
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    include __DIR__ . '/../includes/404.php'; exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage(APP_URL . '/admin/dashboard.php', 'danger', 'Invalid request token.');
}

$entity   = $_POST['entity'] ?? '';
$id       = intval($_POST['id'] ?? 0);
$returnTo = $_POST['return_to'] ?? APP_URL . '/admin/dashboard.php';

if (!$id) {
    redirectWithMessage($returnTo, 'danger', 'Invalid ID.');
}

rotateCSRFToken();

switch ($entity) {

    case 'team':
        if ($id <= 0) break;
        $stmt = $db->prepare('SELECT name FROM teams WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'Team not found.'); }
        $db->prepare('UPDATE teams SET is_active=0 WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'deactivate', 'teams', $id, $row['name']);
        redirectWithMessage($returnTo, 'success', "Team '{$row['name']}' deactivated (historical data preserved).");

    case 'person':
        if ($id <= 0) break;
        $stmt = $db->prepare('SELECT CONCAT(first_name," ",last_name) AS name FROM people WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'Driver not found.'); }
        $db->prepare('UPDATE people SET is_active=0 WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'deactivate', 'people', $id, $row['name']);
        redirectWithMessage($returnTo, 'success', "Driver '{$row['name']}' deactivated (historical data preserved).");

    case 'circuit':
        if ($id <= 0) break;
        $stmt = $db->prepare('SELECT name FROM circuits WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'Circuit not found.'); }
        $db->prepare('DELETE FROM circuits WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'circuits', $id, $row['name']);
        redirectWithMessage($returnTo, 'success', "Circuit '{$row['name']}' deleted.");

    case 'season':
        if ($id <= 0) break;
        $stmt = $db->prepare('SELECT year, is_active FROM seasons WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'Season not found.'); }
        if ($row['is_active']) { redirectWithMessage($returnTo, 'danger', 'Cannot delete the active season.'); }
        $db->prepare('DELETE FROM seasons WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'seasons', $id, "Year: {$row['year']}");
        redirectWithMessage($returnTo, 'success', "Season {$row['year']} deleted.");

    case 'race':
        if ($id <= 0) break;
        $stmt = $db->prepare('SELECT name FROM races WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'Race not found.'); }
        $db->prepare('DELETE FROM races WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'races', $id, $row['name']);
        redirectWithMessage($returnTo, 'success', "Race '{$row['name']}' deleted.");

    case 'user':
        if ($id === 1) { redirectWithMessage($returnTo, 'danger', 'The default admin account cannot be deleted.'); }
        if ($id === intval($_SESSION['user_id'] ?? 0)) { redirectWithMessage($returnTo, 'danger', 'You cannot delete your own account.'); }
        $stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'User not found.'); }
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'users', $id, $row['name']);
        redirectWithMessage($returnTo, 'success', "User '{$row['name']}' deleted.");

    case 'penalty':
        if ($id <= 0) break;
        $stmt = $db->prepare('SELECT id FROM penalties WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) { redirectWithMessage($returnTo, 'danger', 'Penalty not found.'); }
        $db->prepare('DELETE FROM penalties WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'penalties', $id, 'Penalty deleted');
        redirectWithMessage($returnTo, 'success', 'Penalty deleted.');

    case 'race_result':
        if ($id <= 0) break;
        $db->prepare('DELETE FROM race_results WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'race_results', $id, 'Result deleted');
        redirectWithMessage($returnTo, 'success', 'Race result deleted.');

    case 'qualifying_result':
        if ($id <= 0) break;
        $db->prepare('DELETE FROM qualifying_results WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'qualifying_results', $id, 'Qualifying result deleted');
        redirectWithMessage($returnTo, 'success', 'Qualifying result deleted.');

    case 'sprint_result':
        if ($id <= 0) break;
        $db->prepare('DELETE FROM sprint_results WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'sprint_results', $id, 'Sprint result deleted');
        redirectWithMessage($returnTo, 'success', 'Sprint result deleted.');

    case 'race_entry':
        if ($id <= 0) break;
        $db->prepare('DELETE FROM race_entries WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'race_entries', $id, 'Entry deleted');
        redirectWithMessage($returnTo, 'success', 'Race entry deleted.');

    case 'team_season':
        if ($id <= 0) break;
        $db->prepare('DELETE FROM team_seasons WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'team_seasons', $id, 'Team season deleted');
        redirectWithMessage($returnTo, 'success', 'Team season registration deleted.');

    case 'driver_season':
        if ($id <= 0) break;
        $db->prepare('DELETE FROM driver_seasons WHERE id = ?')->execute([$id]);
        logAudit($_SESSION['user_id'], 'delete', 'driver_seasons', $id, 'Driver season deleted');
        redirectWithMessage($returnTo, 'success', 'Driver season registration deleted.');

    default:
        redirectWithMessage($returnTo, 'danger', 'Unknown entity type.');
}

redirectWithMessage($returnTo, 'danger', 'Delete failed: unknown error.');
