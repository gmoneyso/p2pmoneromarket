<?php
// auth_check.php
declare(strict_types=1);

session_start();

// Check if user is logged in
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit;
}

// Optional: Update last seen timestamp
$_SESSION['last_seen'] = time();
