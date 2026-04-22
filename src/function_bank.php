<?php

// Directory setup
$base_path = $_SERVER['DOCUMENT_ROOT'].'/final_project';
$functions_directory = $base_path.'/functions';

// Find all files ending in .php
$files = glob($functions_directory."*php");

// Requires every file inside of the functions folder to load them all in with one require_once call
if ($files) {
    foreach ($files as $file) {
        require_once($file);
    }
}

?>