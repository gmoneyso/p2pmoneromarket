<?php
session_start();
require_once __DIR__ . '/db/database1.php'; // adjust path if needed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.html");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (strlen($username) < 5 || strlen($password) < 7) {
    die("Error: Invalid username or password.");
}

try {
    // Fetch user by username
    $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        die("Error: Incorrect username or password.");
    }

    // Start session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $username;

    // Redirect to homepage/dashboard
    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    error_log("Login DB error: " . $e->getMessage());
    die("Error: Could not log in at this time. Please try again later.");
}
