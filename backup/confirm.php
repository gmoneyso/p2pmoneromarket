<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/gpg/import.php';
require_once __DIR__ . '/../includes/gpg/cleanup.php';

$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];

$base = "/tmp/backups/$username/keys";

$public = file_get_contents("$base/public.asc");
$passphrase = trim(shell_exec("sed -n '2p' $base/{$username}_keys.txt"));

gpg_import_public("$base/public.asc");

$stmt = $pdo->prepare("
    UPDATE users 
    SET pgp_public = ?, recovery_code_hash = ?, backup_completed = 1
    WHERE id = ?
");

$stmt->execute([
    $public,
    password_hash($passphrase, PASSWORD_DEFAULT),
    $userId
]);

gpg_cleanup("/tmp/backups/$username");

header("Location: /dashboard.php");
