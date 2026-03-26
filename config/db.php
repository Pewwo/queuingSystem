<?php
// Auto-detect environment
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($is_localhost) {
    // 🏠 LOCAL DATABASE SETTINGS
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db = 'hospital_queue'; // Your XAMPP/WAMP DB name
    $port = '3306';
}
else {
    // 🌐 INFINITYFREE DATABASE SETTINGS
    $host = 'sql206.infinityfree.com';
    $user = 'if0_41481042';
    $pass = 'W5zpW5qqJQovI'; // <-- MANUALLY REPLACE THIS BEFORE UPLOAD!
    $db = 'if0_41481042_hospital_queue';
    $port = '3306';
}

$conn = mysqli_init();

// Connection logic
$conn->real_connect($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select database
if ($conn->select_db($db) === false) {
    die("Database not found: " . $db);
}
?>
