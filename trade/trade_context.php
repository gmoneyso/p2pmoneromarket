<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_login();

$userId  = (int)$_SESSION['user_id'];
$tradeId = (int)($_GET['id'] ?? 0);

if (!$tradeId) {
    http_response_code(400);
    exit('Invalid trade');
}

trade_expire_if_due($pdo, $tradeId);
$trade = trade_load_by_id($pdo, $tradeId, false);

if (!$trade) {
    http_response_code(404);
    exit('Trade not found');
}

$role = trade_role_for_user($trade, $userId);
if ($role === null) {
    http_response_code(403);
    exit('Not your trade');
}

$counterparty = $role === 'buyer'
    ? $trade['seller_name']
    : $trade['buyer_name'];

$status = (string)$trade['status'];
$canPay = $role === 'buyer' && $status === TRADE_STATUS_PENDING_PAYMENT;
$canConfirm = $role === 'seller' && $status === TRADE_STATUS_PAID;
$canCancel = in_array($status, [TRADE_STATUS_PENDING_PAYMENT], true);
$canDispute = in_array($status, [TRADE_STATUS_PAID], true);

return [
    'trade'        => $trade,
    'role'         => $role,
    'counterparty' => $counterparty,
    'canPay'       => $canPay,
    'canConfirm'   => $canConfirm,
    'canCancel'    => $canCancel,
    'canDispute'   => $canDispute,
    'isWaiting'    => !$canPay && !$canConfirm,
];
