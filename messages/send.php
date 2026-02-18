<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../messages/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/logger.php';

require_login();

$userId = (int)$_SESSION['user_id'];
$threadId = (int)($_POST['thread_id'] ?? 0);
$body = trim((string)($_POST['body'] ?? ''));
$return = messages_safe_return_path((string)($_POST['return'] ?? '/messages.php'));

if ($threadId <= 0 || $body === '') {
    flash_set('error', 'Message body is required.');
    header('Location: ' . $return);
    exit;
}

if (!messages_is_unlocked($pdo, $userId)) {
    flash_set('error', 'Unlock messages first.');
    header('Location: /messages.php?thread_id=' . $threadId);
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, pgp_public, backup_completed FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$sender = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sender) {
    flash_set('error', 'Sender account not found.');
    header('Location: ' . $return);
    exit;
}

if ((int)($sender['backup_completed'] ?? 0) !== 1 || trim((string)($sender['pgp_public'] ?? '')) === '') {
    flash_set('error', 'Complete backup setup before sending secure messages.');
    header('Location: /dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT user_a_id, user_b_id FROM message_threads WHERE id = ? LIMIT 1");
$stmt->execute([$threadId]);
$thread = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$thread) {
    flash_set('error', 'Conversation not found.');
    header('Location: /messages.php');
    exit;
}

$userA = (int)$thread['user_a_id'];
$userB = (int)$thread['user_b_id'];
if ($userId !== $userA && $userId !== $userB) {
    flash_set('error', 'Conversation not found.');
    header('Location: /messages.php');
    exit;
}

$recipientId = $userId === $userA ? $userB : $userA;
$stmt = $pdo->prepare("SELECT id, username, pgp_public FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$recipientId]);
$recipient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$recipient || trim((string)($recipient['pgp_public'] ?? '')) === '') {
    flash_set('error', 'Recipient has no active public key.');
    header('Location: ' . $return);
    exit;
}

try {
    $cipherRecipient = messages_encrypt_for_recipient((string)$recipient['username'], (string)$recipient['pgp_public'], $body);
    $cipherSender = messages_encrypt_for_recipient((string)$sender['username'], (string)($sender['pgp_public'] ?? ''), $body);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("\n        INSERT INTO messages (thread_id, sender_id, recipient_id, ciphertext_sender, ciphertext_recipient, created_at)\n        VALUES (?, ?, ?, ?, ?, NOW())\n    ");
    $stmt->execute([$threadId, $userId, $recipientId, $cipherSender, $cipherRecipient]);
    $msgId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("UPDATE message_threads SET last_message_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$msgId, $threadId]);

    $pdo->commit();

    flash_set('success', 'Message sent.');
    header('Location: ' . $return);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_error('Message send failed', [
        'user_id' => $userId,
        'thread_id' => $threadId,
        'error' => $e->getMessage(),
    ]);

    flash_set('error', 'Unable to send message right now.');
    header('Location: ' . $return);
    exit;
}
