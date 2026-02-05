<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_login();

require_once __DIR__ . '/start_ad.php';

$ad_id = (int)($_GET['ad_id'] ?? 0);
$type  = $_GET['type'] ?? '';

if (!$ad_id || !in_array($type, ['buy', 'sell'], true)) {
    http_response_code(400);
    exit('Invalid trade request');
}

$ad = fetch_trade_ad($pdo, $ad_id);
if (!$ad) {
    http_response_code(404);
    exit('Ad not found or inactive');
}

if ((int)$ad['user_id'] === (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit('You cannot trade with your own ad');
}

$role = $type === 'buy' ? 'buyer' : 'seller';

require_once __DIR__ . '/start_view.php';
render_trade_start_view($ad, $role);
