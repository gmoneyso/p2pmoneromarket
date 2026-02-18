<?php
declare(strict_types=1);

if (!isset($trades)) {
    throw new RuntimeException('split_user_trades.php requires $trades');
}

$ongoingTrades = array_values(array_filter(
    $trades,
    static fn(array $trade): bool => in_array(
        (string)$trade['status'],
        [TRADE_STATUS_PENDING_PAYMENT, TRADE_STATUS_PAID, TRADE_STATUS_DISPUTED],
        true
    )
));

$completedTrades = array_values(array_filter(
    $trades,
    static fn(array $trade): bool => in_array((string)$trade['status'], [
        TRADE_STATUS_RELEASED,
        TRADE_STATUS_DISPUTE_RESOLVED_BUYER,
        TRADE_STATUS_DISPUTE_RESOLVED_SELLER,
    ], true)
));

$cancelledTrades = array_values(array_filter(
    $trades,
    static fn(array $trade): bool => in_array(
        (string)$trade['status'],
        [TRADE_STATUS_CANCELLED, TRADE_STATUS_EXPIRED],
        true
    )
));
