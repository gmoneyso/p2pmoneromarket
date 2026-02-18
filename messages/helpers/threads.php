<?php
declare(strict_types=1);

function messages_get_or_create_thread(PDO $pdo, int $userA, int $userB, ?int $tradeId = null): int
{
    $a = min($userA, $userB);
    $b = max($userA, $userB);

    if ($tradeId !== null && $tradeId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM message_threads WHERE user_a_id = ? AND user_b_id = ? AND trade_id = ? LIMIT 1");
        $stmt->execute([$a, $b, $tradeId]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM message_threads WHERE user_a_id = ? AND user_b_id = ? AND trade_id IS NULL LIMIT 1");
    $stmt->execute([$a, $b]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int)$id;
    }

    $stmt = $pdo->prepare("INSERT INTO message_threads (user_a_id, user_b_id, trade_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$a, $b, $tradeId !== null && $tradeId > 0 ? $tradeId : null]);
    return (int)$pdo->lastInsertId();
}

function messages_fetch_threads(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("\n        SELECT\n            t.id,\n            t.trade_id,\n            t.updated_at,\n            CASE WHEN t.user_a_id = ? THEN t.user_b_id ELSE t.user_a_id END AS counterparty_id,\n            u.username AS counterparty_username,\n            m.created_at AS last_message_at\n        FROM message_threads t\n        JOIN users u ON u.id = CASE WHEN t.user_a_id = ? THEN t.user_b_id ELSE t.user_a_id END\n        LEFT JOIN messages m ON m.id = t.last_message_id\n        WHERE t.user_a_id = ? OR t.user_b_id = ?\n        ORDER BY COALESCE(m.created_at, t.updated_at) DESC\n    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function messages_fetch_thread_messages(PDO $pdo, int $threadId, int $userId): array
{
    $stmt = $pdo->prepare("\n        SELECT id, sender_id, recipient_id, ciphertext_sender, ciphertext_recipient, created_at\n        FROM messages\n        WHERE thread_id = ?\n          AND (sender_id = ? OR recipient_id = ?)\n        ORDER BY id ASC\n        LIMIT 200\n    ");
    $stmt->execute([$threadId, $userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function messages_thread_belongs_to_user(PDO $pdo, int $threadId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM message_threads WHERE id = ? AND (user_a_id = ? OR user_b_id = ?) LIMIT 1");
    $stmt->execute([$threadId, $userId, $userId]);
    return (bool)$stmt->fetchColumn();
}
