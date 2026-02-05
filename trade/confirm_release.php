<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_login();

$userId  = (int)$_SESSION['user_id'];
$tradeId = (int)($_POST['trade_id'] ?? 0);

if (!$tradeId) {
    http_response_code(400);
    exit('Invalid trade');
}

$pdo->beginTransaction();

try {
    /* Lock trade */
    $stmt = $pdo->prepare("
        SELECT *
        FROM trades
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$tradeId]);
    $trade = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trade) {
        throw new Exception('Trade not found');
    }

    if ((int)$trade['seller_id'] !== $userId) {
        throw new Exception('Not seller');
    }

    if ($trade['status'] !== 'paid') {
        throw new Exception('Trade not releasable');
    }

    $xmrAmount = (float)$trade['xmr_amount'];
    $feeXmr    = (float)$trade['fee_xmr'];
    $buyerId   = (int)$trade['buyer_id'];
    $sellerId  = (int)$trade['seller_id'];

    /* Get seller balance */
    $stmt = $pdo->prepare("
        SELECT balance_after
        FROM balance_ledger
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$sellerId]);
    $sellerBalance = (float)$stmt->fetchColumn();

    /* Seller escrow release (debit) */
    $stmt = $pdo->prepare("
        INSERT INTO balance_ledger
            (user_id, related_type, related_id, amount, direction, status, balance_after)
        VALUES (?, 'escrow_release', ?, ?, 'debit', 'unlocked', ?)
    ");
    $stmt->execute([
        $sellerId,
        $tradeId,
        $xmrAmount,
        $sellerBalance - $xmrAmount
    ]);

    /* Buyer balance */
    $stmt->execute([$buyerId]); // reset cursor safety
    $stmt = $pdo->prepare("
        SELECT balance_after
        FROM balance_ledger
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$buyerId]);
    $buyerBalance = (float)$stmt->fetchColumn();

    /* Buyer escrow credit */
    $stmt = $pdo->prepare("
        INSERT INTO balance_ledger
            (user_id, related_type, related_id, amount, direction, status, balance_after)
        VALUES (?, 'escrow_release', ?, ?, 'credit', 'unlocked', ?)
    ");
    $stmt->execute([
        $buyerId,
        $tradeId,
        $xmrAmount,
        $buyerBalance + $xmrAmount
    ]);

    /* Buyer fee debit */
    $stmt = $pdo->prepare("
        INSERT INTO balance_ledger
            (user_id, related_type, related_id, amount, direction, status, balance_after)
        VALUES (?, 'fee', ?, ?, 'debit', 'unlocked', ?)
    ");
    $stmt->execute([
        $buyerId,
        $tradeId,
        $feeXmr,
        ($buyerBalance + $xmrAmount) - $feeXmr
    ]);

    /* Update trade */
    $stmt = $pdo->prepare("
        UPDATE trades
        SET status = 'released'
        WHERE id = ?
    ");
    $stmt->execute([$tradeId]);

    $pdo->commit();

    header("Location: /reviews/start.php?trade_id={$tradeId}");
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit($e->getMessage());
}
