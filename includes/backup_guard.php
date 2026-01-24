<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.html");
    exit;
}

$stmt = $pdo->prepare("SELECT backup_completed FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['backup_completed'] == 1) {
    header("Location: /dashboard.php");
    exit;
}
