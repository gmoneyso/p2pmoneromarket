<?php
declare(strict_types=1);

if (!defined('APP_BOOTSTRAP_LOADED')) {
    define('APP_BOOTSTRAP_LOADED', true);

    $errorDir = dirname(__DIR__) . '/error';
    if (!is_dir($errorDir)) {
        @mkdir($errorDir, 0750, true);
    }

    $errorFile = $errorDir . '/php-error.log';
    if (!file_exists($errorFile)) {
        @touch($errorFile);
    }

    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $errorFile);
    error_reporting(E_ALL);

    set_exception_handler(static function (Throwable $e): void {
        error_log(sprintf(
            "[%s] Uncaught %s: %s in %s:%d\nStack trace:\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        if (!headers_sent()) {
            http_response_code(500);
        }

        exit('Internal Server Error');
    });
}
