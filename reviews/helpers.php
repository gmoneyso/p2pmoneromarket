<?php
declare(strict_types=1);

require_once __DIR__ . '/../trade/state_machine.php';

function review_load_trade(PDO $pdo, int $tradeId): ?array
{
    $stmt = $pdo->prepare("\n        SELECT id, buyer_id, seller_id, status\n        FROM trades\n        WHERE id = ?\n        LIMIT 1\n    ");
    $stmt->execute([$tradeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function review_user_has_review(PDO $pdo, int $tradeId, int $userId): bool
{
    $stmt = $pdo->prepare("\n        SELECT 1\n        FROM reviews\n        WHERE trade_id = ? AND reviewer_id = ?\n        LIMIT 1\n    ");
    $stmt->execute([$tradeId, $userId]);

    return (bool)$stmt->fetchColumn();
}

function review_reviewee_for_user(array $trade, int $userId): ?int
{
    if ($userId === (int)$trade['buyer_id']) {
        return (int)$trade['seller_id'];
    }

    if ($userId === (int)$trade['seller_id']) {
        return (int)$trade['buyer_id'];
    }

    return null;
}

function review_can_submit(PDO $pdo, array $trade, int $userId): array
{
    $revieweeId = review_reviewee_for_user($trade, $userId);
    if ($revieweeId === null) {
        return [false, 'Not your trade', null];
    }

    if ((string)$trade['status'] !== TRADE_STATUS_RELEASED) {
        return [false, 'You can review only after trade release', null];
    }

    if (review_user_has_review($pdo, (int)$trade['id'], $userId)) {
        return [false, 'You already submitted a review for this trade', $revieweeId];
    }

    return [true, '', $revieweeId];
}


function review_fetch_received(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.trade_id,
            r.rating,
            r.comment,
            r.created_at,
            u.username AS reviewer_username
        FROM reviews r
        INNER JOIN users u ON u.id = r.reviewer_id
        INNER JOIN trades t ON t.id = r.trade_id
        WHERE r.reviewee_id = ?
          AND t.status = 'released'
        ORDER BY r.created_at DESC, r.id DESC
    ");
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function review_fetch_given(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.trade_id,
            r.rating,
            r.comment,
            r.created_at,
            u.username AS reviewee_username
        FROM reviews r
        INNER JOIN users u ON u.id = r.reviewee_id
        INNER JOIN trades t ON t.id = r.trade_id
        WHERE r.reviewer_id = ?
          AND t.status = 'released'
        ORDER BY r.created_at DESC, r.id DESC
    ");
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
