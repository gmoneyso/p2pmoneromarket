<?php
declare(strict_types=1);

const TRADE_STATUS_PENDING_PAYMENT = 'pending_payment';
const TRADE_STATUS_PAID_UNCONFIRMED = 'paid_unconfirmed';
const TRADE_STATUS_RELEASED = 'released';
const TRADE_STATUS_CANCELLED = 'cancelled';
const TRADE_STATUS_EXPIRED = 'expired';
const TRADE_STATUS_DISPUTED = 'disputed';

const TRADE_TERMINAL_STATUSES = [
    TRADE_STATUS_RELEASED,
    TRADE_STATUS_CANCELLED,
    TRADE_STATUS_EXPIRED,
    TRADE_STATUS_DISPUTED,
];

function trade_allowed_transitions(): array
{
    return [
        TRADE_STATUS_PENDING_PAYMENT => [
            TRADE_STATUS_PAID_UNCONFIRMED,
            TRADE_STATUS_CANCELLED,
            TRADE_STATUS_EXPIRED,
        ],
        TRADE_STATUS_PAID_UNCONFIRMED => [
            TRADE_STATUS_RELEASED,
            TRADE_STATUS_DISPUTED,
        ],
        TRADE_STATUS_RELEASED => [],
        TRADE_STATUS_CANCELLED => [],
        TRADE_STATUS_EXPIRED => [],
        TRADE_STATUS_DISPUTED => [],
    ];
}

function trade_can_transition(string $from, string $to): bool
{
    $map = trade_allowed_transitions();
    return in_array($to, $map[$from] ?? [], true);
}

function trade_is_terminal(string $status): bool
{
    return in_array($status, TRADE_TERMINAL_STATUSES, true);
}
