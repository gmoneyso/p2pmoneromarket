<?php
declare(strict_types=1);

if (!isset($pdo, $userId)) {
    throw new RuntimeException('fetch_user_trades.php requires $pdo and $userId');
}

$sql = "
    SELECT
        t.id,
        t.listing_id,
        t.buyer_id,
        t.seller_id,
        t.xmr_amount,
        t.crypto_pay,
        t.market_price_snapshot,
        t.margin_percent,
        t.final_price,
        t.crypto_amount,
        t.fee_xmr,
        t.status,
        t.expires_at,
        t.created_at,
        l.type AS listing_type,
        u_buyer.username AS buyer_username,
        u_seller.username AS seller_username
    FROM trades t
    INNER JOIN listings l ON l.id = t.listing_id
    INNER JOIN users u_buyer ON u_buyer.id = t.buyer_id
    INNER JOIN users u_seller ON u_seller.id = t.seller_id
    WHERE t.buyer_id = :buyerUserId OR t.seller_id = :sellerUserId
    ORDER BY t.created_at DESC, t.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':buyerUserId' => $userId,
    ':sellerUserId' => $userId,
]);
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
