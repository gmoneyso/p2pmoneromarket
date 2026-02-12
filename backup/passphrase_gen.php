<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/user.php';

header('Content-Type: application/json');

/* -----------------------------
 * Auth guard
 * ----------------------------- */
require_login();

$user = get_current_user_data($_SESSION['user_id'], $pdo);
if (!$user || (int)$user['backup_completed'] === 1) {
    http_response_code(403);
    echo json_encode(['status' => 'forbidden']);
    exit;
}

$username = $user['username'];

/* -----------------------------
 * Base paths
 * ----------------------------- */
$baseDir = '/var/www/moneromarket/backup/temp';
$userDir = $baseDir . '/' . $username;

$backupFile = $userDir . '/' . $username . '_backup.txt';
$passFile   = $userDir . '/pass.txt';
$pubFile    = $userDir . '/public_key.txt';

$gpgHome    = $userDir . '/.gnupg';
$pgpScript  = __DIR__ . '/pgpgen.php';

/* -----------------------------
 * Ensure directories exist
 * ----------------------------- */
foreach ([$baseDir, $userDir, $gpgHome] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    chmod($dir, 0700);
}

/* -----------------------------
 * 1) Professional notice
 * ----------------------------- */
$notice = <<<TXT
P2P Monero does not store your cryptographic keys.
You are solely responsible for securely backing up and protecting them.
Loss of this file means permanent loss of access.

USERNAME:
{$username}

TXT;

/* -----------------------------
 * 2) Generate passphrase
 * ----------------------------- */
$rawPassphrase = bin2hex(random_bytes(24));

/* -----------------------------
 * 3) Write username_backup.txt
 * ----------------------------- */
$content  = $notice;
$content .= "RECOVERY PASSPHRASE:\n";
$content .= $rawPassphrase . "\n";

file_put_contents($backupFile, $content);
chmod($backupFile, 0600);

/* -----------------------------
 * 5) Write pass.txt (raw)
 * ----------------------------- */
file_put_contents($passFile, $rawPassphrase . PHP_EOL);
chmod($passFile, 0600);

/* -----------------------------
 * 6) Call pgpgen.php with isolated GNUPGHOME
 * ----------------------------- */
$cmd = sprintf(
    'GNUPGHOME=%s php %s %s',
    escapeshellarg($gpgHome),
    escapeshellarg($pgpScript),
    escapeshellarg($username)
);

exec($cmd, $output, $exitCode);

if ($exitCode !== 0 || !file_exists($pubFile)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'pgp_failed',
        'debug'  => $output
    ]);
    exit;
}

/* -----------------------------
 * Success
 * ----------------------------- */
echo json_encode(['status' => 'ok']);
