<?php

// Define global constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'FORMULA_ONE');

// Default DB login credentials
$default_db_user = "visitor";
$default_db_password = "visitor_secret";

// Role logic (for display and enforcing specific edge cases with roles)
$current_role = "Visitor";
$current_id = 0;
$can_edit_own_info = false;
$can_edit_others_stats = false;

// New instance to the database with default credentials
@$db = new mysqli(DB_HOST, $default_db_user, $default_db_password, DB_NAME);

// Switch the DB user
function switch_db_user($new_user, $new_password) {
    $db->close();
    @$db = new mysqli(DB_HOST, $new_user, $new_password);
}

// Update DB credentials based on user role
function switch_db_user_from_id($user_id) {
    
    // Find the user's role
    $user_query = "SELECT role FROM accounts WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $stmt->get_result();
    $user_row = $result->fetch_assoc();

    // Check if row actually exists and update db login
    if ($user_row) {
        $role = $user_row['role'];

        if ($role == 1) {
            $current_role = "Visitor";
            $can_edit_own_info = false;
            $can_edit_others_stats = false;
            switch_db_user("visitor", "visitor_secret");
        } elseif ($role == 2) {
            $current_role = "Driver";
            $can_edit_own_info = false;
            $can_edit_others_stats = false;
            switch_db_user("driver", "driver_secret");
        } elseif ($role == 3) {
            $current_role = "Coach";
            $can_edit_own_info = true;
            $can_edit_others_stats = true;
            switch_db_user("coach", "coach_secret");
        } elseif ($role == 4) {
            $current_role = "Administrator";
            $can_edit_own_info = true;
            $can_edit_others_stats = true;
            switch_db_user("administrator", "administrator_secret");
        }
    }
}

?>