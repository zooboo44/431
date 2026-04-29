<?php
ob_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../middleware/auth_check.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

$currentPath = $_SERVER['PHP_SELF'] ?? '';

if (!($publicPage ?? false)) {
    // Authenticated portal pages
    startSecureSession();
    requireLogin();
    checkSessionTimeout();
    checkDisplacedSession();
    enforcePasswordChange();

    $user       = currentUser();
    $role       = $user['role'] ?? '';
    $badgeColor = ROLE_BADGE_COLORS[$role] ?? '#555';

    $navItems = [];
    switch ($role) {
        case 'admin':
            $navItems = [
                ['href' => APP_URL . '/admin/dashboard.php',        'label' => 'Dashboard',  'icon' => '&#9776;'],
                ['href' => APP_URL . '/admin/users.php',            'label' => 'Users',       'icon' => '&#128101;'],
                ['href' => APP_URL . '/admin/teams.php',            'label' => 'Teams',       'icon' => '&#127937;'],
                ['href' => APP_URL . '/admin/people.php',           'label' => 'Drivers',     'icon' => '&#128100;'],
                ['href' => APP_URL . '/admin/circuits.php',         'label' => 'Circuits',    'icon' => '&#9940;'],
                ['href' => APP_URL . '/admin/seasons.php',          'label' => 'Seasons',     'icon' => '&#128197;'],
                ['href' => APP_URL . '/admin/races.php',            'label' => 'Races',       'icon' => '&#127937;'],
                ['href' => APP_URL . '/admin/standings.php',        'label' => 'Standings',   'icon' => '&#127942;'],
                ['href' => APP_URL . '/admin/results_overview.php', 'label' => 'Results',     'icon' => '&#9989;'],
                ['href' => APP_URL . '/admin/penalties.php',        'label' => 'Penalties',   'icon' => '&#9888;'],
                ['href' => APP_URL . '/admin/telemetry.php',        'label' => 'Telemetry',   'icon' => '&#128200;'],
                ['href' => APP_URL . '/admin/pitstops.php',         'label' => 'Pit Stops',   'icon' => '&#128295;'],
                ['href' => APP_URL . '/admin/audit_log.php',        'label' => 'Audit Log',   'icon' => '&#128221;'],
            ];
            break;
        case 'team_manager':
            $navItems = [
                ['href' => APP_URL . '/team_manager/dashboard.php', 'label' => 'Dashboard',  'icon' => '&#9776;'],
                ['href' => APP_URL . '/team_manager/drivers.php',   'label' => 'Drivers',     'icon' => '&#128100;'],
                ['href' => APP_URL . '/team_manager/results.php',   'label' => 'Results',     'icon' => '&#127937;'],
                ['href' => APP_URL . '/team_manager/race_data.php', 'label' => 'Race Data',   'icon' => '&#128200;'],
                ['href' => APP_URL . '/team_manager/pitstops.php',  'label' => 'Pit Stops',   'icon' => '&#128295;'],
                ['href' => APP_URL . '/team_manager/telemetry.php', 'label' => 'Telemetry',   'icon' => '&#128200;'],
                ['href' => APP_URL . '/team_manager/circuits.php',  'label' => 'Circuits',    'icon' => '&#9940;'],
                ['href' => APP_URL . '/shared/standings.php',       'label' => 'Standings',   'icon' => '&#127942;'],
            ];
            break;
        case 'driver':
            $navItems = [
                ['href' => APP_URL . '/driver/dashboard.php',  'label' => 'Dashboard',  'icon' => '&#9776;'],
                ['href' => APP_URL . '/driver/penalties.php',  'label' => 'Penalties',  'icon' => '&#9888;'],
                ['href' => APP_URL . '/shared/standings.php',  'label' => 'Standings',  'icon' => '&#127942;'],
            ];
            break;
    }

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
<?php
} else {
    // Public pages — no auth required
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="public-body">
<nav class="top-nav public-nav">
    <div class="nav-brand">
        <a href="<?= APP_URL ?>/" class="brand-link">
            <span class="brand-icon">&#9872;</span>
            <span class="brand-name"><?= h(APP_NAME) ?></span>
        </a>
    </div>
    <div class="nav-links">
        <a href="<?= APP_URL ?>/" class="nav-link<?= (str_ends_with($currentPath, 'index.php') || $currentPath === '/f1app/') ? ' active' : '' ?>">Home</a>
        <a href="<?= APP_URL ?>/public/standings.php" class="nav-link<?= str_contains($currentPath, 'standings') ? ' active' : '' ?>">Standings</a>
        <a href="<?= APP_URL ?>/public/results.php" class="nav-link<?= str_contains($currentPath, 'results') ? ' active' : '' ?>">Results</a>
        <a href="<?= APP_URL ?>/public/circuits.php" class="nav-link<?= str_contains($currentPath, 'circuits') ? ' active' : '' ?>">Circuits</a>
    </div>
    <div class="nav-actions">
        <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-primary">Portal</a>
    </div>
</nav>
<main class="public-main">
<?php
}
