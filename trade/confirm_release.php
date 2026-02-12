<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_login();

$userId  = (int)$_SESSION['user_id'];
$tradeId = (int)($_POST['trade_id'] ?? 0);

if (!$tradeId) {
    http_response_code(400);
    exit('Invalid trade');
}

$pdo->beginTransaction();

try {
    $trade = trade_load_by_id($pdo, $tradeId, true);

    if (!$trade) {
        throw new RuntimeException('Trade not found');
    }

    $role = trade_role_for_user($trade, $userId);
    if ($role !== 'seller') {
        throw new RuntimeException('Not seller');
    }

    if ($trade['status'] !== TRADE_STATUS_PAID) {
        throw new RuntimeException('Trade not releasable');
    }

    $sellerId = (int)$trade['seller_id'];

    $xmrAmount = (float)$trade['xmr_amount'];
    $feeXmr    = (float)$trade['fee_xmr'];
    $buyerId   = (int)$trade['buyer_id'];
    $platformFeeUserId = trade_platform_fee_user_id($pdo);

    ledger_append(
        $pdo,
        $sellerId,
        'escrow_release',
        $tradeId,
        $xmrAmount,
        'debit',
        'unlocked'
    );

    ledger_append(
        $pdo,
        $buyerId,
        'escrow_release',
        $tradeId,
        $xmrAmount,
        'credit',
        'unlocked'
    );

    ledger_append(
        $pdo,
        $buyerId,
        'fee',
        $tradeId,
        $feeXmr,
        'debit',
        'unlocked'
    );

    ledger_append(
        $pdo,
        $platformFeeUserId,
        'fee',
        $tradeId,
        $feeXmr,
        'credit',
        'unlocked'
    );

    trade_set_status(
        $pdo,
        $tradeId,
        TRADE_STATUS_PAID,
        TRADE_STATUS_RELEASED
    );

    $pdo->commit();

    header("Location: /reviews/start.php?trade_id={$tradeId}");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    exit($e->getMessage());
}
