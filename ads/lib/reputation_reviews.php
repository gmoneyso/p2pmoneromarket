<?php
declare(strict_types=1);

function fetch_review_reputation_by_user(PDO $pdo, array $userIds): array
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if (!$userIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $sql = "
        SELECT
            r.reviewee_id AS user_id,
            ROUND(AVG(r.rating), 2) AS avg_rating,
            COUNT(*) AS review_count
        FROM reviews r
        INNER JOIN trades t ON t.id = r.trade_id
        WHERE r.reviewee_id IN ($placeholders)
          AND t.status = 'released'
        GROUP BY r.reviewee_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($userIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $row) {
        $uid = (int)$row['user_id'];
        $out[$uid] = [
            'rating' => (float)$row['avg_rating'],
            'rating_count' => (int)$row['review_count'],
        ];
    }

    return $out;
}
