<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

$currentPath = $_SERVER['PHP_SELF'] ?? '';
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
