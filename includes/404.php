<?php
http_response_code(404);
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/constants.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>404 — Not Found | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="public-body">
<div class="error-page">
    <div class="error-code">404</div>
    <div class="error-title">Page Not Found</div>
    <p class="error-msg">The page you're looking for doesn't exist.</p>
    <a href="<?= APP_URL ?>/" class="btn btn-primary">Go Home</a>
</div>
</body>
</html>
