<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit;
}

require_once __DIR__ . '/bootstrap.php';

echo "[daemon] deposit daemon started\n";

while (true) {
    try {
        require __DIR__ . '/transfer_scanner.php';
        require __DIR__ . '/confirmation_tracker.php';
        require __DIR__ . '/withdrawal_processor.php';
        require __DIR__ . '/withdrawal_confirmation_tracker.php';
    } catch (Throwable $e) {
        echo "[error] {$e->getMessage()}\n";
    }

    sleep(POLL_INTERVAL);
}
