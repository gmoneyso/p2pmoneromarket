<?php
declare(strict_types=1);

function messages_safe_return_path(string $return, string $fallback = '/messages.php'): string
{
    $return = trim($return);
    if ($return === '') {
        return $fallback;
    }

    if (!str_starts_with($return, '/')) {
        return $fallback;
    }

    if (str_starts_with($return, '//')) {
        return $fallback;
    }

    return $return;
}

function messages_user_gnupg_home(string $username): string
{
    return '/var/www/moneromarket/backup/temp/' . $username . '/.gnupg';
}

function messages_normalize_passphrase(string $passphrase): string
{
    $trimmed = trim($passphrase);
    if ($trimmed === '') {
        return '';
    }

    $collapsed = preg_replace('/[\s-]+/', '', $trimmed);
    if ($collapsed === null) {
        return $trimmed;
    }

    return strtolower($collapsed);
}
