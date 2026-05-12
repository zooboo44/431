<?php

function encode_var($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>
