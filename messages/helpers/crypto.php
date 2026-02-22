<?php
declare(strict_types=1);

function messages_cleanup_temp_files(string $tmpDir, string $tmpHome, array $extraFiles = []): void
{
    foreach ($extraFiles as $f) {
        @unlink($f);
    }

    if (is_dir($tmpHome)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpHome, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $path) {
            if ($path->isDir()) {
                @rmdir($path->getPathname());
            } else {
                @unlink($path->getPathname());
            }
        }
    }

    @rmdir($tmpHome);
    @rmdir($tmpDir);
}

function messages_run_command(string $command, string $stdin = ''): array
{
    $spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($command, $spec, $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('Failed to start gpg process.');
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $code = proc_close($proc);

    return [
        'code' => (int)$code,
        'stdout' => (string)$stdout,
        'stderr' => (string)$stderr,
    ];
}

function messages_fingerprint_from_public_key(string $tmpHome, string $publicKey): string
{
    $cmd = sprintf(
        'GNUPGHOME=%s gpg --batch --with-colons --import-options show-only --import',
        escapeshellarg($tmpHome)
    );

    $result = messages_run_command($cmd, $publicKey);
    if ($result['code'] !== 0 || trim($result['stdout']) === '') {
        throw new RuntimeException('Unable to inspect recipient public key. ' . trim($result['stdout'] . "\n" . $result['stderr']));
    }

    foreach (explode("\n", $result['stdout']) as $line) {
        if (str_starts_with($line, 'fpr:')) {
            $parts = explode(':', $line);
            $fingerprint = trim((string)($parts[9] ?? ''));
            if ($fingerprint !== '') {
                return $fingerprint;
            }
        }
    }

    throw new RuntimeException('Recipient key fingerprint not found.');
}

function messages_import_public_key(string $tmpHome, string $publicKey): void
{
    $cmd = sprintf(
        'GNUPGHOME=%s gpg --batch --yes --import',
        escapeshellarg($tmpHome)
    );

    $result = messages_run_command($cmd, $publicKey);
    if ($result['code'] !== 0) {
        throw new RuntimeException('Unable to import recipient public key. ' . trim($result['stdout'] . "\n" . $result['stderr']));
    }
}

function messages_encrypt_for_recipients(array $recipientPublicKeys, string $plaintext): string
{
    $keys = array_values(array_unique(array_filter(array_map(static fn($k) => trim((string)$k), $recipientPublicKeys), static fn($k) => $k !== '')));
    if (!$keys || trim($plaintext) === '') {
        throw new RuntimeException('Missing recipient key material or message body.');
    }

    $tmpDir = sys_get_temp_dir() . '/msgenc_' . bin2hex(random_bytes(8));
    $tmpHome = $tmpDir . '/gnupg';
    @mkdir($tmpHome, 0700, true);
    @chmod($tmpHome, 0700);

    try {
        $fingerprints = [];

        foreach ($keys as $key) {
            $fingerprints[] = messages_fingerprint_from_public_key($tmpHome, $key);
            messages_import_public_key($tmpHome, $key);
        }

        $recipientArgs = implode(' ', array_map(static fn($fpr) => '-r ' . escapeshellarg((string)$fpr), $fingerprints));
        $cmd = sprintf(
            'GNUPGHOME=%s gpg --batch --yes --trust-model always --armor --encrypt %s',
            escapeshellarg($tmpHome),
            $recipientArgs
        );

        $result = messages_run_command($cmd, $plaintext);
        $ciphertext = trim($result['stdout']);

        if ($result['code'] !== 0 || $ciphertext === '' || !str_contains($ciphertext, 'BEGIN PGP MESSAGE')) {
            throw new RuntimeException('Unable to encrypt message for recipients. ' . trim($result['stdout'] . "\n" . $result['stderr']));
        }

        return $ciphertext . PHP_EOL;
    } finally {
        messages_cleanup_temp_files($tmpDir, $tmpHome);
    }
}


function messages_decrypt_for_user(array $user, string $ciphertext, string $passphrase): ?string
{
    $username = trim((string)($user['username'] ?? ''));
    if ($username === '' || trim($ciphertext) === '' || trim($passphrase) === '') {
        return null;
    }

    $gpgHome = messages_user_gnupg_home($username);
    if (!is_dir($gpgHome)) {
        return null;
    }

    $passFile = tempnam(sys_get_temp_dir(), 'msgpass_');
    if ($passFile === false) {
        return null;
    }

    file_put_contents($passFile, trim($passphrase) . PHP_EOL);
    @chmod($passFile, 0600);

    try {
        $cmd = sprintf(
            'GNUPGHOME=%s gpg --batch --yes --pinentry-mode loopback --passphrase-file %s --decrypt',
            escapeshellarg($gpgHome),
            escapeshellarg($passFile)
        );

        $result = messages_run_command($cmd, $ciphertext);
        if ($result['code'] !== 0) {
            $stderr = trim((string)$result['stderr']);
            $stdout = trim((string)$result['stdout']);

            messages_log_error('Message decrypt failed', [
                'user' => $username,
                'gpg_home' => $gpgHome,
                'gpg_exit_code' => $result['code'],
                'gpg_stderr' => $stderr,
                'gpg_stdout' => $stdout,
                'gpg_error' => $stderr !== '' ? $stderr : $stdout,
            ]);
            return null;
        }

        $plain = trim($result['stdout']);
        return $plain === '' ? null : $plain;
    } finally {
        @unlink($passFile);
    }
}
