<?php

require_once __DIR__ . '/../config.php';

function db_connect() {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($db->connect_error) {
        die('Database connection failed.');
    }

    $db->set_charset('utf8mb4');
    return $db;
}

?>
