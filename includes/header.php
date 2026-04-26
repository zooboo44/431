<?php
ob_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../middleware/auth_check.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

startSecureSession();
requireLogin();
checkSessionTimeout();
checkDisplacedSession();
enforcePasswordChange();

$user        = currentUser();
$role        = $user['role'] ?? '';
$badgeColor  = ROLE_BADGE_COLORS[$role] ?? '#555';
$currentPath = $_SERVER['PHP_SELF'] ?? '';

// Sidebar nav items per role
$navItems = [];
switch ($role) {
    case 'admin':
        $navItems = [
            ['href' => APP_URL . '/admin/dashboard.php',          'label' => 'Dashboard',   'icon' => '&#9776;'],
            ['href' => APP_URL . '/admin/users.php',              'label' => 'Users',        'icon' => '&#128101;'],
            ['href' => APP_URL . '/admin/teams.php',              'label' => 'Teams',        'icon' => '&#127937;'],
            ['href' => APP_URL . '/admin/people.php',             'label' => 'Drivers',      'icon' => '&#128100;'],
            ['href' => APP_URL . '/admin/circuits.php',           'label' => 'Circuits',     'icon' => '&#9940;'],
            ['href' => APP_URL . '/admin/seasons.php',            'label' => 'Seasons',      'icon' => '&#128197;'],
            ['href' => APP_URL . '/admin/races.php',              'label' => 'Races',        'icon' => '&#127937;'],
            ['href' => APP_URL . '/admin/standings.php',          'label' => 'Standings',    'icon' => '&#127942;'],
            ['href' => APP_URL . '/admin/results_overview.php',   'label' => 'Results',      'icon' => '&#9989;'],
            ['href' => APP_URL . '/admin/penalties.php',          'label' => 'Penalties',    'icon' => '&#9888;'],
            ['href' => APP_URL . '/admin/audit_log.php',          'label' => 'Audit Log',    'icon' => '&#128221;'],
        ];
        break;
    case 'race_director':
        $navItems = [
            ['href' => APP_URL . '/race_director/dashboard.php',     'label' => 'Dashboard',     'icon' => '&#9776;'],
            ['href' => APP_URL . '/race_director/race_entries.php',  'label' => 'Race Entries',  'icon' => '&#128203;'],
            ['href' => APP_URL . '/race_director/qualifying.php',    'label' => 'Qualifying',    'icon' => '&#9201;'],
            ['href' => APP_URL . '/race_director/results.php',       'label' => 'Race Results',  'icon' => '&#127937;'],
            ['href' => APP_URL . '/race_director/sprint.php',        'label' => 'Sprint Results','icon' => '&#9889;'],
            ['href' => APP_URL . '/race_director/penalties.php',     'label' => 'Penalties',     'icon' => '&#9888;'],
            ['href' => APP_URL . '/admin/standings.php',             'label' => 'Standings',     'icon' => '&#127942;'],
            ['href' => APP_URL . '/admin/teams.php',                 'label' => 'Teams',         'icon' => '&#127937;'],
            ['href' => APP_URL . '/admin/people.php',                'label' => 'Drivers',       'icon' => '&#128100;'],
            ['href' => APP_URL . '/admin/circuits.php',              'label' => 'Circuits',      'icon' => '&#9940;'],
        ];
        break;
    case 'team_manager':
        $navItems = [
            ['href' => APP_URL . '/team_manager/dashboard.php',     'label' => 'Dashboard',   'icon' => '&#9776;'],
            ['href' => APP_URL . '/team_manager/drivers.php',       'label' => 'Drivers',      'icon' => '&#128100;'],
            ['href' => APP_URL . '/team_manager/results.php',       'label' => 'Results',      'icon' => '&#127937;'],
            ['href' => APP_URL . '/team_manager/telemetry.php',     'label' => 'Telemetry',    'icon' => '&#128200;'],
            ['href' => APP_URL . '/team_manager/pitstops.php',      'label' => 'Pit Stops',    'icon' => '&#128295;'],
            ['href' => APP_URL . '/shared/standings.php',           'label' => 'Standings',    'icon' => '&#127942;'],
        ];
        break;
    case 'engineer':
        $navItems = [
            ['href' => APP_URL . '/engineer/dashboard.php',      'label' => 'Dashboard',    'icon' => '&#9776;'],
            ['href' => APP_URL . '/engineer/telemetry.php',      'label' => 'Telemetry',    'icon' => '&#128200;'],
            ['href' => APP_URL . '/engineer/telemetry_add.php',  'label' => 'Add Telemetry','icon' => '&#43;'],
            ['href' => APP_URL . '/engineer/pitstops.php',       'label' => 'Pit Stops',    'icon' => '&#128295;'],
            ['href' => APP_URL . '/engineer/pitstops_add.php',   'label' => 'Add Pit Stop', 'icon' => '&#43;'],
        ];
        break;
    case 'driver':
        $navItems = [
            ['href' => APP_URL . '/driver/dashboard.php',   'label' => 'Dashboard',    'icon' => '&#9776;'],
            ['href' => APP_URL . '/driver/penalties.php',   'label' => 'Penalties',    'icon' => '&#9888;'],
            ['href' => APP_URL . '/shared/standings.php',   'label' => 'Standings',    'icon' => '&#127942;'],
        ];
        break;
    case 'media':
        $navItems = [
            ['href' => APP_URL . '/media/dashboard.php',      'label' => 'Dashboard',   'icon' => '&#9776;'],
            ['href' => APP_URL . '/shared/results.php',       'label' => 'Results',      'icon' => '&#127937;'],
            ['href' => APP_URL . '/shared/standings.php',     'label' => 'Standings',    'icon' => '&#127942;'],
            ['href' => APP_URL . '/shared/circuits.php',      'label' => 'Circuits',     'icon' => '&#9940;'],
        ];
        break;
    case 'fan':
        $navItems = [
            ['href' => APP_URL . '/fan/dashboard.php',      'label' => 'Dashboard',   'icon' => '&#9776;'],
            ['href' => APP_URL . '/shared/standings.php',   'label' => 'Standings',   'icon' => '&#127942;'],
            ['href' => APP_URL . '/shared/results.php',     'label' => 'Results',     'icon' => '&#127937;'],
            ['href' => APP_URL . '/shared/circuits.php',    'label' => 'Circuits',    'icon' => '&#9940;'],
        ];
        break;
}

// Common items for all authenticated users
$commonItems = [
    ['href' => APP_URL . '/shared/change_password.php', 'label' => 'Change Password', 'icon' => '&#128274;'],
    ['href' => APP_URL . '/shared/profile.php',         'label' => 'Profile',          'icon' => '&#128100;'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="portal-body">
<nav class="top-nav portal-nav">
    <div class="nav-brand">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">&#9776;</button>
        <a href="<?= APP_URL ?>/" class="brand-link">
            <span class="brand-icon">&#9872;</span>
            <span class="brand-name"><?= h(APP_NAME) ?></span>
        </a>
    </div>
    <div class="nav-user">
        <span class="user-name"><?= h($user['name'] ?? '') ?></span>
        <span class="role-badge" style="background:<?= $badgeColor ?>"><?= h(str_replace('_', ' ', $role)) ?></span>
        <a href="<?= APP_URL ?>/auth/logout.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
</nav>
<div class="layout-wrapper">
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <?php foreach ($navItems as $item): ?>
                <li>
                    <a href="<?= h($item['href']) ?>"
                       class="nav-item<?= str_contains($currentPath, basename($item['href'])) ? ' active' : '' ?>">
                        <span class="nav-icon"><?= $item['icon'] ?></span>
                        <span class="nav-label"><?= h($item['label']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="nav-divider"></div>
            <ul class="nav-list nav-list-bottom">
                <?php foreach ($commonItems as $item): ?>
                <li>
                    <a href="<?= h($item['href']) ?>"
                       class="nav-item<?= str_contains($currentPath, basename($item['href'])) ? ' active' : '' ?>">
                        <span class="nav-icon"><?= $item['icon'] ?></span>
                        <span class="nav-label"><?= h($item['label']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>
    <main class="main-content">
