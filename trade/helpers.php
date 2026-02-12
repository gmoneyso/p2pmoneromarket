<?php
declare(strict_types=1);

require_once __DIR__ . '/state_machine.php';

function trade_load_by_id(PDO $pdo, int $tradeId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT
            t.*,
            l.terms AS listing_terms,
            l.payment_time_limit,
            b.username AS buyer_name,
            s.username AS seller_name
        FROM trades t
        JOIN listings l ON l.id = t.listing_id
        JOIN users b ON b.id = t.buyer_id
        JOIN users s ON s.id = t.seller_id
        WHERE t.id = :id
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tradeId]);

    $trade = $stmt->fetch(PDO::FETCH_ASSOC);
    return $trade ?: null;
}

function trade_role_for_user(array $trade, int $userId): ?string
{
    if ($userId === (int)$trade['buyer_id']) {
        return 'buyer';
    }

    if ($userId === (int)$trade['seller_id']) {
        return 'seller';
    }

    return null;
}


function trade_latest_payment(PDO $pdo, int $tradeId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, trade_id, crypto, txid, amount, destination_address, destination_network, destination_tag_memo, confirmations, created_at
        FROM trade_payments
        WHERE trade_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$tradeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function trade_normalize_passphrase(string $passphrase): string
{
    $trimmed = trim($passphrase);
    if ($trimmed === '') {
        return '';
    }

    // Backup flow presents grouped passphrases (e.g. "ABCD EFGH ...")
    // while hashing historically used raw lowercase hex. Normalize user input
    // to the raw canonical shape before verification.
    $collapsed = preg_replace('/[\s-]+/', '', $trimmed);
    if ($collapsed === null) {
        return $trimmed;
    }

    return strtolower($collapsed);
}

function trade_verify_passphrase(PDO $pdo, int $userId, string $passphrase): bool
{
    $raw = trim($passphrase);
    if ($raw === '') {
        return false;
    }

    $stmt = $pdo->prepare("SELECT recovery_code_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $hash = (string)($stmt->fetchColumn() ?: '');

    if ($hash === '') {
        return false;
    }

    // Keep legacy compatibility: try exact input first, then canonicalized form.
    if (password_verify($raw, $hash)) {
        return true;
    }

    $normalized = trade_normalize_passphrase($raw);
    if ($normalized !== '' && $normalized !== $raw) {
        return password_verify($normalized, $hash);
    }

    return false;
}

function trade_platform_fee_user_id(PDO $pdo): int
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['Habibi']);
    $id = $stmt->fetchColumn();

    if ($id === false) {
        throw new RuntimeException('Platform fee user Habibi not found');
    }

    $cached = (int)$id;
    return $cached;
}

function ledger_last_balance(PDO $pdo, int $userId): float
{
    $stmt = $pdo->prepare("SELECT balance_after FROM balance_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $bal = $stmt->fetchColumn();

    return $bal === false ? 0.0 : (float)$bal;
}

function ledger_append(
    PDO $pdo,
    int $userId,
    string $relatedType,
    int $relatedId,
    float $amount,
    string $direction,
    string $status = 'unlocked'
): void {
    $prev = ledger_last_balance($pdo, $userId);
    $next = $direction === 'credit' ? $prev + $amount : $prev - $amount;

    $stmt = $pdo->prepare("\n        INSERT INTO balance_ledger\n            (user_id, related_type, related_id, amount, direction, status, balance_after)\n        VALUES\n            (?, ?, ?, ?, ?, ?, ?)\n    ");

    $stmt->execute([
        $userId,
        $relatedType,
        $relatedId,
        round($amount, 12),
        $direction,
        $status,
        round($next, 12),
    ]);
}

function trade_set_status(PDO $pdo, int $tradeId, string $from, string $to): void
{
    if (!trade_can_transition($from, $to)) {
        throw new RuntimeException("Invalid state transition: {$from} -> {$to}");
    }

    $stmt = $pdo->prepare("UPDATE trades SET status = :to WHERE id = :id AND status = :from");
    $stmt->execute([
        ':to' => $to,
        ':id' => $tradeId,
        ':from' => $from,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Trade state changed by another process');
    }
}

function trade_has_seller_refund(PDO $pdo, int $tradeId, int $sellerId): bool
{
    $stmt = $pdo->prepare("\n        SELECT 1\n        FROM balance_ledger\n        WHERE user_id = ?\n          AND related_type = 'escrow_release'\n          AND related_id = ?\n          AND direction = 'credit'\n        LIMIT 1\n    ");
    $stmt->execute([$sellerId, $tradeId]);

    return (bool)$stmt->fetchColumn();
}

function trade_refund_seller_escrow(PDO $pdo, array $trade): void
{
    $tradeId = (int)$trade['id'];
    $sellerId = (int)$trade['seller_id'];

    if (trade_has_seller_refund($pdo, $tradeId, $sellerId)) {
        return;
    }

    ledger_append(
        $pdo,
        $sellerId,
        'escrow_release',
        $tradeId,
        (float)$trade['xmr_amount'],
        'credit',
        'unlocked'
    );
}

function trade_expire_due(PDO $pdo, int $limit = 25): int
{
    $stmt = $pdo->prepare("\n        SELECT id\n        FROM trades\n        WHERE status = 'pending_payment'\n          AND expires_at <= NOW()\n        ORDER BY id ASC\n        LIMIT :lim\n    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $count = 0;

    foreach ($ids as $id) {
        $tradeId = (int)$id;
        $pdo->beginTransaction();

        try {
            $trade = trade_load_by_id($pdo, $tradeId, true);
            if (!$trade) {
                $pdo->rollBack();
                continue;
            }

            if ($trade['status'] !== TRADE_STATUS_PENDING_PAYMENT) {
                $pdo->rollBack();
                continue;
            }

            if (strtotime((string)$trade['expires_at']) > time()) {
                $pdo->rollBack();
                continue;
            }

            trade_set_status(
                $pdo,
                $tradeId,
                TRADE_STATUS_PENDING_PAYMENT,
                TRADE_STATUS_EXPIRED
            );

            trade_refund_seller_escrow($pdo, $trade);
            $pdo->commit();
            $count++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    return $count;
}

function trade_expire_if_due(PDO $pdo, int $tradeId): bool
{
    $pdo->beginTransaction();

    try {
        $trade = trade_load_by_id($pdo, $tradeId, true);
        if (!$trade) {
            $pdo->rollBack();
            return false;
        }

        if (
            $trade['status'] !== TRADE_STATUS_PENDING_PAYMENT ||
            strtotime((string)$trade['expires_at']) > time()
        ) {
            $pdo->rollBack();
            return false;
        }

        trade_set_status(
            $pdo,
            $tradeId,
            TRADE_STATUS_PENDING_PAYMENT,
            TRADE_STATUS_EXPIRED
        );

        trade_refund_seller_escrow($pdo, $trade);
        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}


function trade_user_has_review(PDO $pdo, int $tradeId, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM reviews
        WHERE trade_id = ? AND reviewer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tradeId, $userId]);

    return (bool)$stmt->fetchColumn();
}
