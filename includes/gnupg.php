<?php

function generate_recovery_passphrase(): string
{
    // 10 groups of 4 chars (human copyable)
    $words = [];
    for ($i = 0; $i < 10; $i++) {
        $words[] = substr(bin2hex(random_bytes(2)), 0, 4);
    }
    return implode(' ', $words);
}

function generate_pgp_keypair(string $username, string $passphrase): array
{
    $homedir = sys_get_temp_dir() . '/pgp_' . bin2hex(random_bytes(8));
    mkdir($homedir, 0700, true);

    $batchFile = $homedir . '/keygen.conf';

    $config = <<<CFG
Key-Type: EDDSA
Key-Curve: ed25519
Subkey-Type: ECDH
Subkey-Curve: cv25519
Name-Real: {$username}
Name-Email: {$username}@moneromarket.local
Expire-Date: 0
Passphrase: {$passphrase}
%commit
CFG;

    file_put_contents($batchFile, $config);

    // Generate key
    exec("gpg --homedir {$homedir} --batch --generate-key {$batchFile}");

    // Export keys
    $public  = shell_exec("gpg --homedir {$homedir} --armor --export {$username}@moneromarket.local");
    $private = shell_exec("gpg --homedir {$homedir} --armor --export-secret-keys {$username}@moneromarket.local");

    // Cleanup
    exec("rm -rf {$homedir}");

    if (!$public || !$private) {
        throw new Exception('PGP key generation failed');
    }

    return [$public, $private];
}
