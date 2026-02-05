<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_login();

require_once __DIR__ . '/start_ad.php';

header('Content-Type: application/json');

$ad_id = (int)($_POST['ad_id'] ?? 0);
$xmr   = (float)($_POST['xmr'] ?? 0);

if ($ad_id <= 0 || $xmr <= 0) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$ad = fetch_trade_ad($pdo, $ad_id);
if (!$ad) {
    echo json_encode(['error' => 'Ad not found']);
    exit;
}

$prices = require __DIR__ . '/../includes/price_oracle.php';

$coin = strtolower($ad['crypto_pay']);
if (!isset($prices[$coin])) {
    echo json_encode(['error' => 'Price unavailable']);
    exit;
}

$market_price  = (float)$prices[$coin];
$margin        = (float)$ad['margin_percent'];
$price_per_xmr = $market_price * (1 + ($margin / 100));
$crypto_total  = $price_per_xmr * $xmr;

$fee_xmr = $xmr * 0.01;
$net_xmr = $xmr - $fee_xmr;

echo json_encode([
    'price_per_xmr' => round($price_per_xmr, 8),
    'crypto_total'  => round($crypto_total, 8),
    'fee_xmr'       => round($fee_xmr, 8),
    'net_xmr'       => round($net_xmr, 8),
    'usdt_value'    => isset($prices['usdt'])
        ? round($xmr * $prices['usdt'], 2)
        : null,
]);
