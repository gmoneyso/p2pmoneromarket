<?php
declare(strict_types=1);

if (!isset($trade, $userId)) {
    throw new RuntimeException('trade_card_user.php requires $trade and $userId');
}

$isBuyer = (int)$trade['buyer_id'] === $userId;
$counterparty = $isBuyer ? (string)$trade['seller_username'] : (string)$trade['buyer_username'];
$verb = $isBuyer ? 'Buying XMR from' : 'Selling XMR to';
$status = (string)$trade['status'];
?>
<a class="trade-row" href="/trade/view.php?id=<?= (int)$trade['id'] ?>">
    <div>
        <strong>Trade #<?= (int)$trade['id'] ?></strong>
        <p><?= htmlspecialchars($verb . ' ' . $counterparty) ?></p>
        <small>Created: <?= htmlspecialchars((string)$trade['created_at']) ?></small>
    </div>
    <div class="trade-row-right">
        <span><?= number_format((float)$trade['xmr_amount'], 8) ?> XMR</span>
        <span class="trade-badge trade-status-<?= htmlspecialchars($status) ?>">
            <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $status))) ?>
        </span>
    </div>
</a>
