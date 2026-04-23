<?php

// Use the admin role because they are the only user with permissions
$username = "administrator";
$password = "administrator_secret";

// Connect to database
@$db = new mysqli(DB_HOST, $username, $password, DB_NAME);

// Check if connection was successful
if (mysqli_connect_errno()) {
    echo("<p>Error: Cound not connect to database. <br/>
    Please try again later. </p>");
    exit;
}

// Generate passwords for every sample user in the database
// In this case, all accounts will have their password be the same as the username
$query = "SELECT drivers.id, drivers.first_name, drivers.last_name
FROM drivers";
$stmt = $db->prepare($query);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $f_name, $l_name);

$accounts = [];
while ($stmt->fetch()) {
    $username = strtolower($f_name)."_".strtolower($l_name);
    $accounts[] = [
        'id' => $id,
        'password' => password_hash($username, PASSWORD_BCRYPT)
    ];
}

// Set account passwords to the just generated hashes
$query2 = "UPDATE accounts SET password_hash = ? WHERE driver_id = ?";
$stmt2 = $db->prepare($query2);
foreach($accounts as $account) {
    $stmt2->bind_param("si", $account['password'], account['id']);
    $stmt2->execute();
}

$db->close();

?>