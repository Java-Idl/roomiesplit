<?php
// Session state validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Determine relative redirect to auth/login.php
    $loginPath = file_exists('auth/login.php') ? 'auth/login.php' : '../auth/login.php';
    header("Location: " . $loginPath);
    exit;
}
