<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';

$updated = trade_expire_due($pdo, 100);
echo "expired={$updated}\n";
