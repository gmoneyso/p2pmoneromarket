<?php
declare(strict_types=1);

function messages_load_sender_for_send(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, username, pgp_public, backup_completed FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$sender) {
        throw new RuntimeException('Sender account not found.');
    }

    if ((int)($sender['backup_completed'] ?? 0) !== 1 || trim((string)($sender['pgp_public'] ?? '')) === '') {
        throw new RuntimeException('Complete backup setup before sending secure messages.');
    }

    return $sender;
}

function messages_load_participants_for_send(PDO $pdo, int $threadId, int $userId): array
{
    $stmt = $pdo->prepare('SELECT user_a_id, user_b_id FROM message_threads WHERE id = ? LIMIT 1');
    $stmt->execute([$threadId]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$thread) {
        throw new RuntimeException('Conversation not found.');
    }

    $userA = (int)$thread['user_a_id'];
    $userB = (int)$thread['user_b_id'];
    if ($userId !== $userA && $userId !== $userB) {
        throw new RuntimeException('Conversation not found.');
    }

    $recipientId = $userId === $userA ? $userB : $userA;

    $stmt = $pdo->prepare('SELECT id, username, pgp_public, backup_completed FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$recipientId]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$recipient || (int)($recipient['backup_completed'] ?? 0) !== 1 || trim((string)($recipient['pgp_public'] ?? '')) === '') {
        throw new RuntimeException('Recipient has no active public key.');
    }

    return [
        'recipient_id' => $recipientId,
        'recipient' => $recipient,
    ];
}

function messages_fetch_moderator_public_keys(PDO $pdo, array $excludeUserIds = []): array
{
    $excludeUserIds = array_values(array_unique(array_map('intval', $excludeUserIds)));

    $sql = "\n        SELECT u.pgp_public\n        FROM staff_roles sr\n        JOIN users u ON u.id = sr.user_id\n        WHERE sr.role = 'moderator'\n          AND sr.status = 'active'\n          AND u.backup_completed = 1\n          AND u.pgp_public IS NOT NULL\n          AND u.pgp_public <> ''\n    ";

    if ($excludeUserIds) {
        $sql .= ' AND sr.user_id NOT IN (' . implode(',', array_fill(0, count($excludeUserIds), '?')) . ')';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($excludeUserIds);

    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    return array_values(array_filter(array_map(static fn($k) => trim((string)$k), $rows), static fn($k) => $k !== ''));
}

function messages_encrypt_for_thread_participants(PDO $pdo, array $sender, array $recipient, string $body): string
{
    $keys = [
        (string)$sender['pgp_public'],
        (string)$recipient['pgp_public'],
    ];

    $moderatorKeys = messages_fetch_moderator_public_keys($pdo, [(int)$sender['id'], (int)$recipient['id']]);
    if ($moderatorKeys) {
        $keys = array_merge($keys, $moderatorKeys);
    }

    return messages_encrypt_for_recipients($keys, $body);
}

function messages_store_thread_message(PDO $pdo, int $threadId, int $senderId, int $recipientId, string $ciphertext): int
{
    if (messages_has_single_ciphertext_column($pdo)) {
        $stmt = $pdo->prepare(
            'INSERT INTO messages (thread_id, sender_id, recipient_id, ciphertext, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$threadId, $senderId, $recipientId, $ciphertext]);
    } else {
        // Backward compatibility for live DBs that still have dual columns.
        $stmt = $pdo->prepare(
            'INSERT INTO messages (thread_id, sender_id, recipient_id, ciphertext_sender, ciphertext_recipient, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$threadId, $senderId, $recipientId, $ciphertext, $ciphertext]);
    }

    $msgId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('UPDATE message_threads SET last_message_id = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$msgId, $threadId]);

    return $msgId;
}
