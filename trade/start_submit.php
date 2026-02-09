<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';

require_login();

$userId = (int)$_SESSION['user_id'];

$listingId = (int)($_POST['listing_id'] ?? 0);
$xmrAmount = (float)($_POST['xmr_amount'] ?? 0);

if ($listingId <= 0 || $xmrAmount <= 0) {
    http_response_code(400);
    exit('Invalid request');
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("\n        SELECT *\n        FROM listings\n        WHERE id = ? AND status = 'active'\n        FOR UPDATE\n    ");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        throw new RuntimeException('Listing unavailable');
    }

    if ($listing['type'] === 'sell') {
        $sellerId = (int)$listing['user_id'];
        $buyerId  = $userId;
    } else {
        $buyerId  = (int)$listing['user_id'];
        $sellerId = $userId;
    }

    if ($buyerId === $sellerId) {
        throw new RuntimeException('Self-trading not allowed');
    }

    if ($xmrAmount < (float)$listing['min_xmr'] || $xmrAmount > (float)$listing['max_xmr']) {
        throw new RuntimeException('Amount outside ad limits');
    }

    $prices = require __DIR__ . '/../includes/price_oracle.php';
    $coin   = strtolower($listing['crypto_pay']);

    if (!isset($prices[$coin])) {
        throw new RuntimeException('Market price unavailable');
    }

    $marketPrice = (float)$prices[$coin];
    $margin      = (float)$listing['margin_percent'];
    $finalPrice  = $marketPrice * (1 + ($margin / 100));

    $cryptoAmt = round($finalPrice * $xmrAmount, 12);
    $feeXmr    = round($xmrAmount * 0.01, 12);

    $stmt = $pdo->prepare("\n        SELECT COALESCE(SUM(\n            CASE direction\n                WHEN 'credit' THEN amount\n                WHEN 'debit'  THEN -amount\n            END\n        ), 0)\n        FROM balance_ledger\n        WHERE user_id = ?\n          AND related_type != 'escrow_lock'\n    ");
    $stmt->execute([$sellerId]);
    $available = (float)$stmt->fetchColumn();

    if ($available < $xmrAmount) {
        throw new RuntimeException('Insufficient balance for escrow');
    }

    $stmt = $pdo->prepare("\n        INSERT INTO trades (\n            listing_id,\n            buyer_id,\n            seller_id,\n            xmr_amount,\n            crypto_pay,\n            market_price_snapshot,\n            margin_percent,\n            final_price,\n            crypto_amount,\n            fee_xmr,\n            status,\n            expires_at\n        ) VALUES (\n            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,\n            DATE_ADD(NOW(), INTERVAL ? MINUTE)\n        )\n    ");
    $stmt->execute([
        $listingId,
        $buyerId,
        $sellerId,
        $xmrAmount,
        $listing['crypto_pay'],
        $marketPrice,
        $margin,
        $finalPrice,
        $cryptoAmt,
        $feeXmr,
        TRADE_STATUS_PENDING_PAYMENT,
        (int)$listing['payment_time_limit'],
    ]);

    $tradeId = (int)$pdo->lastInsertId();

    ledger_append(
        $pdo,
        $sellerId,
        'escrow_lock',
        $tradeId,
        $xmrAmount,
        'debit',
        'locked'
    );

    $pdo->commit();

    header("Location: /trade/view.php?id={$tradeId}");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo $e->getMessage();
}
