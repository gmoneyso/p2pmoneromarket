<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../messages/helpers.php';
require_once __DIR__ . '/../includes/flash.php';

require_login();

$userId = (int)$_SESSION['user_id'];
$return = messages_safe_return_path((string)($_POST['return'] ?? '/messages.php'));
$passphrase = (string)($_POST['passphrase'] ?? '');

$stmt = $pdo->prepare('SELECT backup_completed FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$backupCompleted = (int)($stmt->fetchColumn() ?: 0);

if ($backupCompleted !== 1) {
    flash_set('error', 'Complete backup setup before unlocking messages.');
    header('Location: /dashboard.php');
    exit;
}

$state = messages_get_attempt_state($pdo, $userId);
if (messages_lock_is_active($state['locked_until'])) {
    flash_set('error', 'Too many failed attempts. Try again later.');
    header('Location: ' . $return);
    exit;
}

if (!messages_verify_passphrase($pdo, $userId, $passphrase)) {
    $next = messages_record_failed_attempt($pdo, $userId);
    if (messages_lock_is_active($next['locked_until'] ?? null)) {
        flash_set('error', 'Too many failed attempts. Locked for 30 minutes.');
    } else {
        flash_set('error', 'Wrong passphrase. Try again.');
    }
    header('Location: ' . $return);
    exit;
}

messages_reset_attempts($pdo, $userId);
messages_issue_unlock_session($pdo, $userId, $passphrase);
flash_set('success', 'Messages unlocked for 72 hours.');
header('Location: ' . $return);
exit;
