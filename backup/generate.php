<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/backup_guard.php';

require_once __DIR__ . '/../includes/gpg/keygen.php';
require_once __DIR__ . '/../includes/gpg/export.php';

header("Content-Type: application/json");

$username = $_SESSION['username'];
$userId   = $_SESSION['user_id'];

$base = "/tmp/backups/$username";
$gpgHome = "$base/gnupg";
$outDir  = "$base/keys";

@mkdir($gpgHome, 0700, true);
@mkdir($outDir, 0700, true);

$passphrase = bin2hex(random_bytes(16));

if (!gpg_generate_keys($username, $passphrase, $gpgHome)) {
    echo json_encode(["success" => false]);
    exit;
}

if (!gpg_export_keys($gpgHome, $username, $outDir)) {
    echo json_encode(["success" => false]);
    exit;
}

/* Write combined backup file */
file_put_contents(
    "$outDir/{$username}_keys.txt",
    "Recovery Passphrase:\n$passphrase\n\n".
    "Public Key:\n".file_get_contents("$outDir/public.asc")."\n\n".
    "Private Key:\n".file_get_contents("$outDir/private.asc")
);

echo json_encode(["success" => true]);
