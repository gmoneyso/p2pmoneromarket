<?php
declare(strict_types=1);

if (!isset($trades)) {
    throw new RuntimeException('split_user_trades.php requires $trades');
}

$ongoingTrades = array_values(array_filter(
    $trades,
    static fn(array $trade): bool => in_array(
        (string)$trade['status'],
        [TRADE_STATUS_PENDING_PAYMENT, TRADE_STATUS_PAID_UNCONFIRMED, TRADE_STATUS_DISPUTED],
        true
    )
));

$completedTrades = array_values(array_filter(
    $trades,
    static fn(array $trade): bool => (string)$trade['status'] === TRADE_STATUS_RELEASED
));

$cancelledTrades = array_values(array_filter(
    $trades,
    static fn(array $trade): bool => in_array(
        (string)$trade['status'],
        [TRADE_STATUS_CANCELLED, TRADE_STATUS_EXPIRED],
        true
    )
));
