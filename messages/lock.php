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

messages_revoke_unlock_session($pdo, $userId);
flash_set('success', 'Messages locked.');
header('Location: ' . $return);
exit;
