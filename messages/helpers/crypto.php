<?php
declare(strict_types=1);

function messages_cleanup_temp_files(string $tmpDir, string $tmpHome, array $extraFiles = []): void
{
    foreach ($extraFiles as $f) {
        @unlink($f);
    }
    foreach (glob($tmpHome . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmpHome);
    @rmdir($tmpDir);
}

function messages_import_public_key_and_get_fingerprint(string $tmpHome, string $pubFile): string
{
    $importCmd = sprintf(
        'GNUPGHOME=%s gpg --batch --yes --import %s 2>&1',
        escapeshellarg($tmpHome),
        escapeshellarg($pubFile)
    );
    $importOut = [];
    $importCode = 0;
    exec($importCmd, $importOut, $importCode);

    if ($importCode !== 0) {
        throw new RuntimeException('Unable to import recipient public key. ' . trim(implode("\n", $importOut)));
    }

    $listCmd = sprintf(
        'GNUPGHOME=%s gpg --batch --with-colons --fingerprint --list-keys 2>&1',
        escapeshellarg($tmpHome)
    );
    $listOut = [];
    $listCode = 0;
    exec($listCmd, $listOut, $listCode);

    if ($listCode !== 0 || !$listOut) {
        throw new RuntimeException('Unable to read imported key metadata. ' . trim(implode("\n", $listOut)));
    }

    foreach ($listOut as $line) {
        if (str_starts_with($line, 'fpr:')) {
            $parts = explode(':', $line);
            $fingerprint = trim((string)($parts[9] ?? ''));
            if ($fingerprint !== '') {
                return $fingerprint;
            }
        }
    }

    throw new RuntimeException('Imported key fingerprint not found.');
}

function messages_encrypt_for_recipient(string $recipientPublicKey, string $plaintext): string
{
    $recipientPublicKey = trim($recipientPublicKey);
    if ($recipientPublicKey === '' || trim($plaintext) === '') {
        throw new RuntimeException('Missing recipient key material or message body.');
    }

    $tmpDir = sys_get_temp_dir() . '/msgenc_' . bin2hex(random_bytes(8));
    $tmpHome = $tmpDir . '/gnupg';
    @mkdir($tmpHome, 0700, true);
    @chmod($tmpHome, 0700);

    $pubFile = $tmpDir . '/pub.asc';
    $plainFile = $tmpDir . '/plain.txt';

    file_put_contents($pubFile, $recipientPublicKey);
    file_put_contents($plainFile, $plaintext);

    try {
        $fingerprint = messages_import_public_key_and_get_fingerprint($tmpHome, $pubFile);

        $encryptCmd = sprintf(
            'GNUPGHOME=%s gpg --batch --yes --trust-model always --armor --encrypt -r %s %s 2>&1',
            escapeshellarg($tmpHome),
            escapeshellarg($fingerprint),
            escapeshellarg($plainFile)
        );

        $encryptOut = [];
        $encryptCode = 0;
        exec($encryptCmd, $encryptOut, $encryptCode);
        $ciphertext = trim(implode("\n", $encryptOut));

        if ($encryptCode !== 0 || $ciphertext === '' || !str_contains($ciphertext, 'BEGIN PGP MESSAGE')) {
            throw new RuntimeException('Unable to encrypt message for recipient. ' . $ciphertext);
        }

        return $ciphertext . PHP_EOL;
    } finally {
        messages_cleanup_temp_files($tmpDir, $tmpHome, [$pubFile, $plainFile]);
    }
}

function messages_decrypt_for_user(array $user, string $ciphertext, string $passphrase): ?string
{
    $username = (string)($user['username'] ?? '');
    if ($username === '' || trim($ciphertext) === '') {
        return null;
    }

    $gpgHome = messages_user_gnupg_home($username);
    if (!is_dir($gpgHome)) {
        return null;
    }

    $tmpDir = sys_get_temp_dir() . '/msgdec_' . bin2hex(random_bytes(8));
    @mkdir($tmpDir, 0700, true);

    $cipherFile = $tmpDir . '/cipher.asc';
    $passFile = $tmpDir . '/pass.txt';
    file_put_contents($cipherFile, $ciphertext);
    file_put_contents($passFile, trim($passphrase) . PHP_EOL);
    @chmod($passFile, 0600);

    $cmd = sprintf(
        'GNUPGHOME=%s gpg --batch --yes --pinentry-mode loopback --passphrase-file %s --decrypt %s 2>/dev/null',
        escapeshellarg($gpgHome),
        escapeshellarg($passFile),
        escapeshellarg($cipherFile)
    );

    $plain = shell_exec($cmd);

    @unlink($cipherFile);
    @unlink($passFile);
    @rmdir($tmpDir);

    if (!is_string($plain) || trim($plain) === '') {
        return null;
    }

    return $plain;
}
