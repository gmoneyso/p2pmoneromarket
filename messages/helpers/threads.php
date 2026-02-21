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

function messages_has_single_ciphertext_column(PDO $pdo): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'ciphertext'");
    $cache = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

    return $cache;
}

function messages_fetch_thread_messages(PDO $pdo, int $threadId, int $userId, int $limit = 9, ?int $beforeId = null): array
{
    $limit = max(1, min(50, $limit));

    $selectCipher = messages_has_single_ciphertext_column($pdo)
        ? 'ciphertext'
        : 'CASE WHEN sender_id = ? THEN ciphertext_sender ELSE ciphertext_recipient END AS ciphertext';

    $beforeClause = $beforeId !== null && $beforeId > 0 ? ' AND id < ?' : '';
    $sql = "\n        SELECT id, sender_id, recipient_id, {$selectCipher}, created_at\n        FROM messages\n        WHERE thread_id = ?\n          AND (sender_id = ? OR recipient_id = ?)\n          {$beforeClause}\n        ORDER BY id DESC\n        LIMIT {$limit}\n    ";

    $stmt = $pdo->prepare($sql);

    if (messages_has_single_ciphertext_column($pdo)) {
        $params = [$threadId, $userId, $userId];
    } else {
        $params = [$userId, $threadId, $userId, $userId];
    }

    if ($beforeId !== null && $beforeId > 0) {
        $params[] = $beforeId;
    }

    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_reverse($rows);
}

function messages_thread_has_older_messages(PDO $pdo, int $threadId, int $userId, int $beforeId): bool
{
    if ($beforeId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("\n        SELECT 1\n        FROM messages\n        WHERE thread_id = ?\n          AND (sender_id = ? OR recipient_id = ?)\n          AND id < ?\n        LIMIT 1\n    ");
    $stmt->execute([$threadId, $userId, $userId, $beforeId]);

    return (bool)$stmt->fetchColumn();
}

function messages_thread_belongs_to_user(PDO $pdo, int $threadId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM message_threads WHERE id = ? AND (user_a_id = ? OR user_b_id = ?) LIMIT 1");
    $stmt->execute([$threadId, $userId, $userId]);
    return (bool)$stmt->fetchColumn();
}
