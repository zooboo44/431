<?php
http_response_code(403);
$pageTitle = '403 — Access Denied';
// Avoid circular includes; use simple output if header not available
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/constants.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 — Access Denied | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="public-body">
<div class="error-page">
    <div class="error-code">403</div>
    <div class="error-title">Access Denied</div>
    <p class="error-msg">You don't have permission to access this resource.</p>
    <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
</div>
</body>
</html>
