<?php
// Function to safely get environment variables with a default value
function get_env_var($key, $default) {
    return getenv($key) ?: (isset($_ENV[$key]) ? $_ENV[$key] : $default);
}

// Database Credentials (read from environment variables for deployment)
$host = get_env_var('DB_HOST', 'localhost');
$user = get_env_var('DB_USER', 'root');
$pass = get_env_var('DB_PASS', '');
$db   = get_env_var('DB_NAME', 'hospital_queue');
$port = get_env_var('DB_PORT', '3306');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select database if it's already created
if ($conn->select_db($db) === false) {
    // Database might not exist yet, created via setup.php
}
?>

