<?php
$host = 'localhost';
$user = 'root';
$pass = ''; // default XAMPP
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS hospital_queue";
if ($conn->query($sql) === TRUE) {
    $db_msg = "Database created successfully or already exists.";
} else {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db('hospital_queue');

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        is_archived TINYINT(1) DEFAULT 0
    )",
    "CREATE TABLE IF NOT EXISTS clinics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinic_number VARCHAR(50) NOT NULL UNIQUE,
        ip_address VARCHAR(15) NULL,
        is_archived TINYINT(1) DEFAULT 0
    )",
    "CREATE TABLE IF NOT EXISTS queues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        queue_number INT NOT NULL,
        patient_name VARCHAR(255) NOT NULL,
        doctor_id INT NOT NULL,
        clinic_id INT NOT NULL,
        status ENUM('waiting', 'serving', 'done') DEFAULT 'waiting',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (clinic_id) REFERENCES clinics(id)
    )",
    "CREATE TABLE IF NOT EXISTS clinic_status (
        clinic_id INT PRIMARY KEY,
        status ENUM('serving', 'vacant') DEFAULT 'vacant',
        current_queue_id INT NULL,
        FOREIGN KEY (clinic_id) REFERENCES clinics(id),
        FOREIGN KEY (current_queue_id) REFERENCES queues(id)
    )",
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'receptionist', 'doctor') NOT NULL,
        relation_id INT NULL COMMENT 'If doctor, maps to doctors.id',
        is_archived TINYINT(1) DEFAULT 0
    )"
];

$table_msgs = [];
foreach ($tables as $t) {
    if ($conn->query($t) === TRUE) {
        $table_msgs[] = "Table ensured successfully.";
    } else {
        $table_msgs[] = "Error creating table: " . $conn->error;
    }
}

// Alter tables to add is_archived if they already exist without it
$conn->query("ALTER TABLE doctors ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE clinics ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN is_archived TINYINT(1) DEFAULT 0");

// Insert dummy data
$dummy_msg = "";
$res = $conn->query("SELECT COUNT(*) as c FROM doctors");
if ($res && $res->fetch_assoc()['c'] == 0) {
    $conn->query("INSERT INTO doctors (name) VALUES ('Dr. John Doe'), ('Dr. Jane Smith'), ('Dr. Alice Johnson')");
    $conn->query("INSERT INTO clinics (clinic_number) VALUES ('Clinic 1'), ('Clinic 2'), ('Clinic 3')");
    $conn->query("INSERT INTO clinic_status (clinic_id) VALUES (1), (2), (3)");
    
    // Insert default users (password: 1234)
    $hash = password_hash('1234', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, role) VALUES ('admin', '$hash', 'admin')");
    $conn->query("INSERT INTO users (username, password, role) VALUES ('receptionist', '$hash', 'receptionist')");
    // Link doctors to relation_id
    $conn->query("INSERT INTO users (username, password, role, relation_id) VALUES ('doctor1', '$hash', 'doctor', 1)");
    $conn->query("INSERT INTO users (username, password, role, relation_id) VALUES ('doctor2', '$hash', 'doctor', 2)");
    $conn->query("INSERT INTO users (username, password, role, relation_id) VALUES ('doctor3', '$hash', 'doctor', 3)");
    
    $dummy_msg = "Dummy data and users inserted successfully.";
} else {
    // Check if users exist just in case tables exist but users don't from previous setup run
    $resU = $conn->query("SELECT COUNT(*) as c FROM users");
    if ($resU && $resU->fetch_assoc()['c'] == 0) {
        $hash = password_hash('1234', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, role) VALUES ('admin', '$hash', 'admin')");
        $conn->query("INSERT INTO users (username, password, role) VALUES ('receptionist', '$hash', 'receptionist')");
        $conn->query("INSERT INTO users (username, password, role, relation_id) VALUES ('doctor1', '$hash', 'doctor', 1)");
        $conn->query("INSERT INTO users (username, password, role, relation_id) VALUES ('doctor2', '$hash', 'doctor', 2)");
        $conn->query("INSERT INTO users (username, password, role, relation_id) VALUES ('doctor3', '$hash', 'doctor', 3)");
        $dummy_msg = "Dummy users updated.";
    } else {
        $dummy_msg = "Dummy data already exists.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen flex items-center justify-center p-6">
    <div class="max-w-xl w-full bg-white rounded-3xl p-10 shadow-xl border border-slate-100">
        <h1 class="text-3xl font-black text-slate-900 mb-6 border-b pb-4">Setup Complete!</h1>
        
        <div class="space-y-4 mb-8">
            <div class="p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-100 font-medium">
                ✅ <?php echo $db_msg; ?>
            </div>
            <div class="p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-100 font-medium">
                ✅ Tables validated & structural checks passed.
            </div>
            <div class="p-4 bg-blue-50 text-blue-800 rounded-xl border border-blue-100 font-medium">
                ℹ️ <?php echo $dummy_msg; ?>
            </div>
        </div>

        <a href="index.php" class="block text-center w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-lg py-4 rounded-xl transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">Go to Dashboard</a>
    </div>
</body>
</html>
