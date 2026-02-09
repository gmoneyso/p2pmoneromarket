<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_login();

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
$tradeId = (int)($_GET['trade_id'] ?? 0);

if ($tradeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid trade id']);
    exit;
}

trade_expire_if_due($pdo, $tradeId);
$trade = trade_load_by_id($pdo, $tradeId, false);

if (!$trade) {
    http_response_code(404);
    echo json_encode(['error' => 'Trade not found']);
    exit;
}

$role = trade_role_for_user($trade, $userId);
if ($role === null) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$expiresAtTs = strtotime((string)$trade['expires_at']) ?: time();
$now = time();
$secondsLeft = max(0, $expiresAtTs - $now);

if ((string)$trade['status'] !== TRADE_STATUS_PENDING_PAYMENT) {
    $secondsLeft = 0;
}

echo json_encode([
    'trade_id' => (int)$trade['id'],
    'status' => (string)$trade['status'],
    'expires_at' => (string)$trade['expires_at'],
    'seconds_left' => $secondsLeft,
    'payment_time_limit' => (int)($trade['payment_time_limit'] ?? 0),
]);
