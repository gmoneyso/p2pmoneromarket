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

function messages_encrypt_for_thread_participants(array $sender, array $recipient, string $body): array
{
    $cipherRecipient = messages_encrypt_for_recipient((string)$recipient['pgp_public'], $body);
    $cipherSender = messages_encrypt_for_recipient((string)$sender['pgp_public'], $body);

    return [
        'cipher_sender' => $cipherSender,
        'cipher_recipient' => $cipherRecipient,
    ];
}

function messages_store_thread_message(PDO $pdo, int $threadId, int $senderId, int $recipientId, string $cipherSender, string $cipherRecipient): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO messages (thread_id, sender_id, recipient_id, ciphertext_sender, ciphertext_recipient, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$threadId, $senderId, $recipientId, $cipherSender, $cipherRecipient]);
    $msgId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('UPDATE message_threads SET last_message_id = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$msgId, $threadId]);

    return $msgId;
}
