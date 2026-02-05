<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/price_oracle.php';

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
    /* Load listing */
    $stmt = $pdo->prepare("
        SELECT *
        FROM listings
        WHERE id = ? AND status = 'active'
        FOR UPDATE
    ");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        throw new RuntimeException('Listing unavailable');
    }

    /* Resolve roles */
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

    /* Validate limits */
    if ($xmrAmount < (float)$listing['min_xmr'] || $xmrAmount > (float)$listing['max_xmr']) {
        throw new RuntimeException('Amount outside ad limits');
    }

    /* Get oracle price */
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

    /* Seller available balance (excluding escrow locks) */
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE direction
                WHEN 'credit' THEN amount
                WHEN 'debit'  THEN -amount
            END
        ), 0)
        FROM balance_ledger
        WHERE user_id = ?
          AND related_type != 'escrow_lock'
    ");
    $stmt->execute([$sellerId]);
    $available = (float)$stmt->fetchColumn();

    if ($available < $xmrAmount) {
        throw new RuntimeException('Insufficient balance for escrow');
    }

    /* Create trade */
    $stmt = $pdo->prepare("
        INSERT INTO trades (
            listing_id,
            buyer_id,
            seller_id,
            xmr_amount,
            crypto_pay,
            market_price_snapshot,
            margin_percent,
            final_price,
            crypto_amount,
            fee_xmr,
            expires_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            DATE_ADD(NOW(), INTERVAL ? MINUTE)
        )
    ");
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
        $listing['payment_time_limit']
    ]);

    $tradeId = (int)$pdo->lastInsertId();

    /* Lock escrow */
    $newBalance = $available - $xmrAmount;

    $stmt = $pdo->prepare("
        INSERT INTO balance_ledger (
            user_id,
            related_type,
            related_id,
            amount,
            direction,
            status,
            balance_after
        ) VALUES (
            ?, 'escrow_lock', ?, ?, 'debit', 'locked', ?
        )
    ");
    $stmt->execute([
        $sellerId,
        $tradeId,
        $xmrAmount,
        $newBalance
    ]);

    $pdo->commit();

    header("Location: /trade/view.php?id={$tradeId}");
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo $e->getMessage();
}
