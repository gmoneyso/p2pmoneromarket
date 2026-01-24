<?php
require_once __DIR__ . '/../logger.php';

function gpg_import_public(string $file): bool
{
    exec("gpg --import $file 2>&1", $out, $code);

    if ($code !== 0) {
        log_error("Public key import failed", ["out" => $out]);
        return false;
    }
    return true;
}
