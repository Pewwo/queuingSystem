<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function requireRole($allowedRoles) {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
    if (!in_array(getUserRole(), $allowedRoles)) {
        header("Location: index.php?error=unauthorized");
        exit;
    }
}
?>
