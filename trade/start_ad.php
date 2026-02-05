<?php
declare(strict_types=1);

function fetch_trade_ad(PDO $pdo, int $ad_id): ?array
{
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
            l.status,
            u.username
        FROM listings l
        INNER JOIN users u ON u.id = l.user_id
        WHERE l.id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $ad_id]);
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ad || $ad['status'] !== 'active') {
        return null;
    }

    return $ad;
}
