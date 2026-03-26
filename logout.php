<?php
session_start();
if (isset($_SESSION['doctor_id'])) {
    require 'db.php';
    $did = intval($_SESSION['doctor_id']);
    $conn->query("UPDATE doctors SET current_clinic_id = NULL WHERE id = $did");
}
session_destroy();
header("Location: login.php");
exit;
?>
