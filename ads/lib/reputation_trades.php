<?php
declare(strict_types=1);

function fetch_completed_trade_counts_by_user(PDO $pdo, array $userIds): array
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if (!$userIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $sql = "
        SELECT user_id, COUNT(*) AS completed_trades
        FROM (
            SELECT buyer_id AS user_id
            FROM trades
            WHERE status = 'released'
              AND buyer_id IN ($placeholders)

            UNION ALL

            SELECT seller_id AS user_id
            FROM trades
            WHERE status = 'released'
              AND seller_id IN ($placeholders)
        ) x
        GROUP BY user_id
    ";

    $params = array_merge($userIds, $userIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $out[(int)$row['user_id']] = (int)$row['completed_trades'];
    }

    return $out;
}
