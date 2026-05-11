<?php

function redirect_to($path) {
    header("Location: " . $path);
    exit();
}

?>
