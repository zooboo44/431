<?php

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_strong_password($password) {
    if (strlen($password) < 8) {
        return false;
    }

    return preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function is_positive_id($value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0;
}

?>
