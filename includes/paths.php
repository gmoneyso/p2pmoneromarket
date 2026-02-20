<?php
declare(strict_types=1);

function app_root_path(string $path = ''): string
{
    $root = dirname(__DIR__);
    if ($path === '') {
        return $root;
    }

    $clean = ltrim(str_replace('\\', '/', $path), '/');
    return $root . '/' . $clean;
}

function app_backup_temp_path(string $relative = ''): string
{
    $base = app_root_path('backup/temp');
    if ($relative === '') {
        return $base;
    }

    $clean = ltrim(str_replace('\\', '/', $relative), '/');
    return $base . '/' . $clean;
}
