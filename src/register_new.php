<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions/db_fns.php';
require_once __DIR__ . '/functions/data_valid_fns.php';

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    header("Location: register_form.php");
    exit();
}

$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirmation = $_POST['confirmed_password'] ?? '';

$_SESSION['register_form'] = [
    'email' => $email,
    'username' => $username
];

if (!is_valid_email($email)) {
    $_SESSION['register_error'] = "Please enter a valid email address.";
    header("Location: register_form.php");
    exit();
}

if ($username === '') {
    $_SESSION['register_error'] = "Please enter a username.";
    header("Location: register_form.php");
    exit();
}

if ($password !== $password_confirmation) {
    $_SESSION['register_error'] = "Passwords do not match.";
    header("Location: register_form.php");
    exit();
}

if (!is_strong_password($password)) {
    $_SESSION['register_error'] = "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.";
    header("Location: register_form.php");
    exit();
}

$db = db_connect();

$duplicate = $db->prepare("SELECT id FROM accounts WHERE email = ? OR username = ? LIMIT 1");
if (!$duplicate) {
    $_SESSION['register_error'] = "Registration failed. Please try again.";
    $db->close();
    header("Location: register_form.php");
    exit();
}

$duplicate->bind_param("ss", $email, $username);
$duplicate->execute();
$duplicate_result = $duplicate->get_result();

if ($duplicate_result->fetch_assoc()) {
    $_SESSION['register_error'] = "An account with that email or username already exists.";
    $duplicate->close();
    $db->close();
    header("Location: register_form.php");
    exit();
}

$duplicate->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$role_id = DEFAULT_ROLE_ID;

$query = "INSERT INTO accounts (email, username, password_hash, role_id, is_active) VALUES (?, ?, ?, ?, 1)";
$stmt = $db->prepare($query);

if (!$stmt) {
    $_SESSION['register_error'] = "Registration failed. Please try again.";
    $db->close();
    header("Location: register_form.php");
    exit();
}

$stmt->bind_param("sssi", $email, $username, $password_hash, $role_id);

if (!$stmt->execute()) {
    $_SESSION['register_error'] = "Registration failed. Please try again.";
    $stmt->close();
    $db->close();
    header("Location: register_form.php");
    exit();
}

$account_id = $stmt->insert_id;
$stmt->close();

$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$audit = $db->prepare("INSERT INTO audit_logs (account_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, 'registration_success', 'account', ?, ?, ?)");
if ($audit) {
    $audit->bind_param("iiss", $account_id, $account_id, $ip_address, $user_agent);
    $audit->execute();
    $audit->close();
}

$db->close();
unset($_SESSION['register_form'], $_SESSION['register_error']);

header("Location: login.php?registered=1");
exit();
?>
