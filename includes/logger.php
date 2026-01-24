<?php

function log_error(string $message, array $context = []): void
{
    $logFile = __DIR__ . '/../logs/error.log';

    $entry = [
        'time' => gmdate('Y-m-d H:i:s'),
        'ip'   => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        'msg'  => $message,
        'ctx'  => $context
    ];

    file_put_contents(
        $logFile,
        json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
