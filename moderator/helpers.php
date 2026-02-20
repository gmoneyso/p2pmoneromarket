<?php
declare(strict_types=1);

require_once __DIR__ . '/../trade/helpers.php';

function moderator_fetch_dispute_queue(PDO $pdo): array
{
    $stmt = $pdo->prepare("\n        SELECT\n            d.id AS dispute_id,\n            d.status AS dispute_status,\n            d.reason_text,\n            d.opened_at,\n            d.updated_at,\n            d.assigned_moderator_id,\n            t.id AS trade_id,\n            t.status AS trade_status,\n            t.xmr_amount,\n            buyer.username AS buyer_name,\n            seller.username AS seller_name\n        FROM trade_disputes d\n        JOIN trades t ON t.id = d.trade_id\n        JOIN users buyer ON buyer.id = t.buyer_id\n        JOIN users seller ON seller.id = t.seller_id\n        WHERE d.status IN ('open','under_review')\n        ORDER BY d.opened_at ASC\n        LIMIT 200\n    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
