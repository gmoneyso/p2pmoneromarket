<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$tradeId = (int)($_POST['trade_id'] ?? 0);

if ($tradeId <= 0) {
    http_response_code(400);
    exit('Invalid trade');
}

trade_expire_if_due($pdo, $tradeId);

$pdo->beginTransaction();

try {
    $trade = trade_load_by_id($pdo, $tradeId, true);

    if (!$trade) {
        throw new RuntimeException('Trade not found');
    }

    $role = trade_role_for_user($trade, $userId);
    if ($role === null) {
        throw new RuntimeException('Not your trade');
    }

    if ($trade['status'] !== TRADE_STATUS_PENDING_PAYMENT) {
        throw new RuntimeException('Trade cannot be cancelled now');
    }

    trade_set_status(
        $pdo,
        $tradeId,
        TRADE_STATUS_PENDING_PAYMENT,
        TRADE_STATUS_CANCELLED
    );

    trade_refund_seller_escrow($pdo, $trade);

    $pdo->commit();

    header("Location: /trade/view.php?id={$tradeId}");
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    exit($e->getMessage());
}
