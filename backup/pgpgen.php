<?php
declare(strict_types=1);

/*
 * pgpgen.php
 *
 * Usage:
 *   GNUPGHOME=/path/to/isolated/gnupg php pgpgen.php username
 *
 * Expects:
 *   /var/www/moneromarket/backup/temp/{username}/pass.txt
 *   /var/www/moneromarket/backup/temp/{username}/{username}_backup.txt
 *
 * Produces:
 *   public_key.txt
 *   Appends PUBLIC + PRIVATE keys to {username}_backup.txt
 */

if ($argc !== 2) {
    fwrite(STDERR, "Invalid arguments\n");
    exit(1);
}

$username = $argv[1];

/* -----------------------------
 * Paths
 * ----------------------------- */
$baseDir   = '/var/www/moneromarket/backup/temp';
$userDir   = $baseDir . '/' . $username;

$passFile  = $userDir . '/pass.txt';
$backupFile = $userDir . '/' . $username . '_backup.txt';
$pubFile   = $userDir . '/public_key.txt';

/* -----------------------------
 * Sanity checks
 * ----------------------------- */
if (!is_dir($userDir) || !file_exists($passFile) || !file_exists($backupFile)) {
    fwrite(STDERR, "User workspace incomplete\n");
    exit(2);
}

$passphrase = trim(file_get_contents($passFile));
if ($passphrase === '') {
    fwrite(STDERR, "Empty passphrase\n");
    exit(3);
}

/* -----------------------------
 * Ensure GNUPGHOME isolation
 * ----------------------------- */
$gpgHome = getenv('GNUPGHOME');
if (!$gpgHome || !is_dir($gpgHome)) {
    fwrite(STDERR, "GNUPGHOME not set or invalid\n");
    exit(4);
}

/* -----------------------------
 * Key generation batch
 * (Ed25519 + X25519)
 * ----------------------------- */
$batch = <<<EOF
%echo Generating P2P Monero OpenPGP key
Key-Type: eddsa
Key-Curve: ed25519
Key-Usage: sign
Subkey-Type: ecdh
Subkey-Curve: cv25519
Subkey-Usage: encrypt
Name-Real: {$username}
Name-Email: {$username}@p2pmonero.local
Expire-Date: 0
Passphrase: {$passphrase}
%commit
%echo done
EOF;

$batchFile = $userDir . '/gpg_batch.txt';
file_put_contents($batchFile, $batch);
chmod($batchFile, 0600);

/* -----------------------------
 * Generate key
 * ----------------------------- */
$genCmd = sprintf(
    'gpg --batch --pinentry-mode loopback --gen-key %s 2>&1',
    escapeshellarg($batchFile)
);

exec($genCmd, $out, $code);

unlink($batchFile);

if ($code !== 0) {
    fwrite(STDERR, implode("\n", $out));
    exit(5);
}

/* -----------------------------
 * Export PUBLIC key
 * ----------------------------- */
$pubKey = shell_exec(
    'gpg --armor --batch --export ' . escapeshellarg($username) . ' 2>/dev/null'
);

if (!$pubKey) {
    fwrite(STDERR, "Public key export failed\n");
    exit(6);
}

file_put_contents($pubFile, $pubKey);
chmod($pubFile, 0600);

/* -----------------------------
 * Export PRIVATE key
 * ----------------------------- */
$privKey = shell_exec(
    'gpg --armor --batch --pinentry-mode loopback ' .
    '--passphrase-file ' . escapeshellarg($passFile) . ' ' .
    '--export-secret-keys ' . escapeshellarg($username) . ' 2>/dev/null'
);

if (!$privKey) {
    fwrite(STDERR, "Private key export failed\n");
    exit(7);
}

/* -----------------------------
 * Append keys to backup file
 * ----------------------------- */
$append  = PHP_EOL;
$append .= "----- PUBLIC KEY -----" . PHP_EOL;
$append .= $pubKey . PHP_EOL;
$append .= "----- PRIVATE KEY -----" . PHP_EOL;
$append .= $privKey . PHP_EOL;

file_put_contents($backupFile, $append, FILE_APPEND);
chmod($backupFile, 0600);

/* -----------------------------
 * Done
 * ----------------------------- */
exit(0);
