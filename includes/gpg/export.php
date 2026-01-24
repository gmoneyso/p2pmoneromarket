<?php
require_once __DIR__ . '/../logger.php';

function gpg_export_keys(string $homeDir, string $username, string $outDir): bool
{
    putenv("GNUPGHOME=$homeDir");

    exec(
        "gpg --armor --export $username > $outDir/public.asc 2>/dev/null",
        $o1, $c1
    );

    exec(
        "gpg --armor --export-secret-keys $username > $outDir/private.asc 2>/dev/null",
        $o2, $c2
    );

    if ($c1 !== 0 || $c2 !== 0) {
        log_error("GPG export failed");
        return false;
    }

    return true;
}
