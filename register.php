<?php
session_start();
require_once __DIR__ . '/db/database1.php'; // path relative to moneromarket root

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.html");
    exit;
}

// Collect input
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

// Validate username
if (strlen($username) < 5 || !preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
    die("Error: Username must be at least 5 characters and contain only letters, numbers, _ or -.");
}

// Validate password strength
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{7,}$/', $password)) {
    die("Error: Password must be at least 7 characters and include uppercase, lowercase, number, and special character.");
}

// Confirm password
if ($password !== $confirm) {
    die("Error: Passwords do not match.");
}

try {
    // Check for existing username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        die("Error: Username already taken.");
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_ARGON2ID);

    // Insert new user (only username + password_hash, everything else default)
    $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    $stmt->execute([$username, $hash]);

    // Get new user ID
    $user_id = $db->lastInsertId();

    // Start session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;

    // Redirect to homepage/dashboard
    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    error_log("Registration DB error: " . $e->getMessage());
    die("Error: Could not register at this time. Please try again later.");
}
