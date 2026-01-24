<?php
require_once __DIR__ . '/../logger.php';

function gpg_generate_keys(string $username, string $passphrase, string $homeDir): bool
{
    putenv("GNUPGHOME=$homeDir");

    $batch = <<<EOF
Key-Type: RSA
Key-Length: 2048
Name-Real: $username
Name-Email: $username@p2p.local
Expire-Date: 0
Passphrase: $passphrase
%commit
EOF;

    $cmd = "gpg --batch --pinentry-mode loopback --gen-key";
    $proc = proc_open(
        $cmd,
        [["pipe","r"],["pipe","w"],["pipe","w"]],
        $pipes
    );

    if (!is_resource($proc)) {
        log_error("Failed to start gpg");
        return false;
    }

    fwrite($pipes[0], $batch);
    fclose($pipes[0]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $code = proc_close($proc);

    if ($code !== 0) {
        log_error("GPG keygen failed", ["error" => $stderr]);
        return false;
    }

    return true;
}
