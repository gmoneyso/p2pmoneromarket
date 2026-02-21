<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/paths.php';

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
    return app_backup_temp_path($username . '/.gnupg');
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

function messages_log_error(string $message, array $context = []): void
{
    $logFile = app_root_path('error/messages-error.log');

    $entry = [
        'time' => gmdate('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        'msg' => $message,
        'ctx' => $context,
    ];

    @file_put_contents(
        $logFile,
        json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
