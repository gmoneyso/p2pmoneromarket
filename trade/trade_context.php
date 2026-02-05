<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_login();

$userId  = (int)$_SESSION['user_id'];
$tradeId = (int)($_GET['id'] ?? 0);

if (!$tradeId) {
    http_response_code(400);
    exit('Invalid trade');
}

/* Load trade */
$stmt = $pdo->prepare("
    SELECT
        t.*,
        b.username AS buyer_name,
        s.username AS seller_name
    FROM trades t
    JOIN users b ON b.id = t.buyer_id
    JOIN users s ON s.id = t.seller_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->execute([$tradeId]);
$trade = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trade) {
    http_response_code(404);
    exit('Trade not found');
}

/* Role */
if ($userId === (int)$trade['buyer_id']) {
    $role = 'buyer';
} elseif ($userId === (int)$trade['seller_id']) {
    $role = 'seller';
} else {
    http_response_code(403);
    exit('Not your trade');
}

/* Counterparty */
$counterparty = $role === 'buyer'
    ? $trade['seller_name']
    : $trade['buyer_name'];

/* Permissions */
$canPay       = $role === 'buyer'  && $trade['status'] === 'pending_payment';
$canConfirm   = $role === 'seller' && $trade['status'] === 'paid';
$isWaiting    = !$canPay && !$canConfirm;

return [
    'trade'        => $trade,
    'role'         => $role,
    'counterparty' => $counterparty,
    'canPay'       => $canPay,
    'canConfirm'   => $canConfirm,
    'isWaiting'    => $isWaiting,
];
