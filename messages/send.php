<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../messages/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/notifications.php';

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

try {
    $sender = messages_load_sender_for_send($pdo, $userId);
    $participants = messages_load_participants_for_send($pdo, $threadId, $userId);
    $recipientId = (int)$participants['recipient_id'];
    $recipient = $participants['recipient'];

    $encrypted = messages_encrypt_for_thread_participants($sender, $recipient, $body);

    $pdo->beginTransaction();
    $msgId = messages_store_thread_message(
        $pdo,
        $threadId,
        $userId,
        $recipientId,
        (string)$encrypted['cipher_sender'],
        (string)$encrypted['cipher_recipient']
    );
    $pdo->commit();

    notify_user(
        $pdo,
        $recipientId,
        'message_new',
        'New secure message',
        sprintf('You have a new secure message from %s.', (string)$sender['username']),
        'message_thread',
        $threadId
    );

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
