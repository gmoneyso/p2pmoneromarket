<?php
declare(strict_types=1);

require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/lib/reputation_reviews.php';
require_once __DIR__ . '/lib/reputation_trades.php';

/* ===============================
   Load price oracle
   =============================== */
$prices = require __DIR__ . '/../includes/price_oracle.php';

if (!is_array($prices) || empty($prices)) {
    $ads = [];
    return;
}

/* ===============================
   Fetch ALL active listings
   =============================== */
$sql = "
    SELECT
        l.id,
        l.user_id,
        l.type,
        l.crypto_pay,
        l.margin_percent,
        l.min_xmr,
        l.max_xmr,
        l.payment_time_limit,
        l.terms,
        l.payin_address,
        l.payin_network,
        l.payin_tag_memo,
        l.created_at,
        u.username
    FROM listings l
    INNER JOIN users u ON u.id = l.user_id
    WHERE l.status = 'active'
    ORDER BY l.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userIds = array_values(array_unique(array_map(
    static fn(array $ad): int => (int)$ad['user_id'],
    $ads
)));

$reviewReputation = fetch_review_reputation_by_user($pdo, $userIds);
$completedTrades  = fetch_completed_trade_counts_by_user($pdo, $userIds);

/* ===============================
   Price calculation per ad
   =============================== */
foreach ($ads as &$ad) {

    $coin = strtolower($ad['crypto_pay']);

    if (!isset($prices[$coin])) {
        continue;
    }

    $marketPrice = (float) $prices[$coin];
    $margin      = (float) $ad['margin_percent'];

    $pricePerXmr = $marketPrice * (1 + ($margin / 100));

    $ad['market_price']     = $marketPrice;
    $ad['price_per_xmr']    = round($pricePerXmr, 8);
    $ad['min_trade_value']  = round($pricePerXmr * (float)$ad['min_xmr'], 8);
    $ad['max_trade_value']  = round($pricePerXmr * (float)$ad['max_xmr'], 8);

    $ownerId = (int)$ad['user_id'];
    $ad['online'] = true;
    $ad['rating'] = (float)($reviewReputation[$ownerId]['rating'] ?? 0.0);
    $ad['rating_count'] = (int)($reviewReputation[$ownerId]['rating_count'] ?? 0);
    $ad['trade_count'] = (int)($completedTrades[$ownerId] ?? 0);
}

unset($ad);
