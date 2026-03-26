<?php
// Database Credentials for InfinityFree
$host = 'sql206.infinityfree.com';
$user = 'if0_41481042';
$pass = 'YOUR_VPANEL_PASSWORD'; // <-- CHANGE THIS to your password!
$db   = 'if0_41481042_hospital_queue';
$port = '3306';

$conn = mysqli_init();

// InfinityFree doesn't usually require SSL for local script connections
$conn->real_connect($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select database
if ($conn->select_db($db) === false) {
    die("Database not found: " . $db);
}
?>
