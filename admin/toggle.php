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
$activate = intval($_POST['activate'] ?? 0); // 1 = activate, 0 = deactivate
$returnTo = $_POST['return_to'] ?? APP_URL . '/admin/dashboard.php';

if (!$id) {
    redirectWithMessage($returnTo, 'danger', 'Invalid ID.');
}

rotateCSRFToken();

switch ($entity) {

    case 'team':
        if ($activate) {
            // Check active season team count
            $active = getActiveSeason();
            if ($active) {
                $cnt = $db->prepare('SELECT COUNT(*) FROM team_seasons ts JOIN teams t ON t.id=ts.team_id WHERE ts.season_id=? AND t.is_active=1');
                $cnt->execute([$active['id']]);
                if ((int)$cnt->fetchColumn() >= 10) {
                    redirectWithMessage($returnTo, 'danger', 'Maximum 10 active teams per season. Deactivate another team first.');
                }
            }
        }
        $stmt = $db->prepare('SELECT name FROM teams WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'Team not found.'); }
        $db->prepare('UPDATE teams SET is_active=? WHERE id=?')->execute([$activate, $id]);
        if (!$activate) {
            // Also mark their driver_seasons inactive for active season
            $active = getActiveSeason();
            if ($active) {
                $db->prepare("
                    UPDATE driver_seasons ds
                    JOIN team_seasons ts ON ts.id = ds.team_season_id
                    SET ds.status='inactive'
                    WHERE ts.team_id=? AND ds.season_id=?
                ")->execute([$id, $active['id']]);
            }
        }
        logAudit($_SESSION['user_id'], $activate ? 'activate' : 'deactivate', 'teams', $id, $row['name']);
        redirectWithMessage($returnTo, 'success', "Team '{$row['name']}' " . ($activate ? 'activated' : 'deactivated') . '.');

    case 'person':
        $stmt = $db->prepare('SELECT CONCAT(first_name," ",last_name) AS name FROM people WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'Driver not found.'); }
        if ($activate) {
            $active = getActiveSeason();
            if ($active) {
                // Max 20 active drivers total across the active season
                $cnt = $db->prepare('SELECT COUNT(*) FROM driver_seasons ds JOIN people p ON p.id=ds.person_id WHERE ds.season_id=? AND p.is_active=1');
                $cnt->execute([$active['id']]);
                if ((int)$cnt->fetchColumn() >= 20) {
                    redirectWithMessage($returnTo, 'danger', 'Maximum 20 active drivers per season. Deactivate one first.');
                }
                // Max 2 active drivers per team in the active season
                $teamCnt = $db->prepare("
                    SELECT COUNT(*) FROM driver_seasons ds2
                    JOIN people p2 ON p2.id = ds2.person_id
                    JOIN driver_seasons ds_target ON ds_target.person_id = ? AND ds_target.season_id = ?
                    WHERE ds2.team_season_id = ds_target.team_season_id
                      AND ds2.season_id = ?
                      AND p2.is_active = 1
                ");
                $teamCnt->execute([$id, $active['id'], $active['id']]);
                if ((int)$teamCnt->fetchColumn() >= 2) {
                    redirectWithMessage($returnTo, 'danger', 'This team already has 2 active drivers for the current season. Deactivate one first.');
                }
            }
        }
        $db->prepare('UPDATE people SET is_active=? WHERE id=?')->execute([$activate, $id]);
        logAudit($_SESSION['user_id'], $activate ? 'activate' : 'deactivate', 'people', $id, $row['name']);
        redirectWithMessage($returnTo, 'success', "Driver '{$row['name']}' " . ($activate ? 'activated' : 'deactivated') . '.');

    case 'user':
        if ($id === 1 && !$activate) {
            redirectWithMessage($returnTo, 'danger', 'Cannot deactivate the default admin account.');
        }
        $stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { redirectWithMessage($returnTo, 'danger', 'User not found.'); }
        $db->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([$activate, $id]);
        logAudit($_SESSION['user_id'], $activate ? 'activate' : 'deactivate', 'users', $id, $row['name']);
        redirectWithMessage($returnTo, 'success', "User '{$row['name']}' " . ($activate ? 'activated' : 'deactivated') . '.');

    default:
        redirectWithMessage($returnTo, 'danger', 'Unknown entity type.');
}

redirectWithMessage($returnTo, 'danger', 'Toggle failed.');
