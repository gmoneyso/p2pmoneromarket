<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_login();

$userId  = (int)$_SESSION['user_id'];
$tradeId = (int)($_POST['trade_id'] ?? 0);
$txid    = trim((string)($_POST['txid'] ?? ''));

if (!$tradeId) {
    http_response_code(400);
    exit('Invalid trade');
}

if ($txid === '' || !preg_match('/^[A-Za-z0-9]{16,128}$/', $txid)) {
    http_response_code(400);
    exit('Invalid txid format');
}

trade_expire_if_due($pdo, $tradeId);

$pdo->beginTransaction();

try {
    $trade = trade_load_by_id($pdo, $tradeId, true);

    if (!$trade) {
        throw new RuntimeException('Trade not found');
    }

    $role = trade_role_for_user($trade, $userId);
    if ($role !== 'buyer') {
        throw new RuntimeException('Not buyer');
    }

    if ($trade['status'] !== TRADE_STATUS_PENDING_PAYMENT) {
        if ($trade['status'] === TRADE_STATUS_EXPIRED) {
            throw new RuntimeException('Trade expired');
        }
        throw new RuntimeException('Trade not payable');
    }

    if (strtotime((string)$trade['expires_at']) <= time()) {
        trade_set_status(
            $pdo,
            $tradeId,
            TRADE_STATUS_PENDING_PAYMENT,
            TRADE_STATUS_EXPIRED
        );
        trade_refund_seller_escrow($pdo, $trade);
        throw new RuntimeException('Trade expired');
    }

    if (trade_latest_payment($pdo, $tradeId) !== null) {
        throw new RuntimeException('Payment proof already submitted');
    }

    $stmt = $pdo->prepare("
        INSERT INTO trade_payments (trade_id, crypto, txid, amount, destination_address, destination_network, destination_tag_memo, confirmations)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $tradeId,
        strtolower((string)$trade['crypto_pay']),
        $txid,
        (float)$trade['crypto_amount'],
        $trade['payin_address_snapshot'] ?? null,
        $trade['payin_network_snapshot'] ?? null,
        $trade['payin_tag_memo_snapshot'] ?? null,
    ]);

    trade_set_status(
        $pdo,
        $tradeId,
        TRADE_STATUS_PENDING_PAYMENT,
        TRADE_STATUS_PAID_UNCONFIRMED
    );

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
