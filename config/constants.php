<?php

define('APP_URL', 'http://localhost/f1app');
define('APP_NAME', 'F1 Management System');
define('SESSION_TIMEOUT', 7200); // 2 hours in seconds

define('RESTRICTED_ROLES', ['admin', 'race_director', 'team_manager', 'engineer', 'driver']);

define('ROLE_DASHBOARDS', [
    'admin'          => APP_URL . '/admin/dashboard.php',
    'race_director'  => APP_URL . '/race_director/dashboard.php',
    'team_manager'   => APP_URL . '/team_manager/dashboard.php',
    'engineer'       => APP_URL . '/engineer/dashboard.php',
    'driver'         => APP_URL . '/driver/dashboard.php',
    'media'          => APP_URL . '/media/dashboard.php',
    'fan'            => APP_URL . '/fan/dashboard.php',
]);

// Race points: position → points
define('RACE_POINTS', [
    1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10,
    6 => 8,  7 => 6,  8 => 4,  9 => 2,  10 => 1,
]);

// Sprint points: position → points
define('SPRINT_POINTS', [
    1 => 8, 2 => 7, 3 => 6, 4 => 5, 5 => 4,
    6 => 3, 7 => 2, 8 => 1,
]);

define('FASTEST_LAP_BONUS', 1);

define('ROLE_BADGE_COLORS', [
    'admin'          => '#e10600',
    'race_director'  => '#ff8000',
    'team_manager'   => '#0066cc',
    'engineer'       => '#00d2be',
    'driver'         => '#00a550',
    'media'          => '#7b2d8b',
    'fan'            => '#555555',
]);

// Rate limiting
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_WINDOW_MINUTES', 15);
define('RESET_TOKEN_MAX_REQUESTS', 3);
define('RESET_TOKEN_WINDOW_MINUTES', 60);
define('RESET_TOKEN_EXPIRY_HOURS', 1);
