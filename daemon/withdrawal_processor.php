<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../wallet/rpc.php';

echo "[withdrawal-processor] active\n";

$stmt = $pdo->query("\n    SELECT id, user_id, address, amount, status\n    FROM withdrawals\n    WHERE status = 'pending'\n    ORDER BY id ASC\n    LIMIT 20\n");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as $w) {
    $wid = (int)$w['id'];
    $uid = (int)$w['user_id'];
    $amount = (float)$w['amount'];

    try {
        $result = wallet_send_xmr((string)$w['address'], $amount, 'medium');
        $txid = (string)$result['txid'];
        if ($txid === '') {
            throw new RuntimeException('Missing txid from wallet RPC');
        }

        $pdo->prepare("UPDATE withdrawals SET status = 'broadcast', txid = ? WHERE id = ? AND status = 'pending'")
            ->execute([$txid, $wid]);
        notify_user($pdo, $uid, 'withdrawal_broadcasted', 'Withdrawal broadcasted', 'Your withdrawal was broadcast to the Monero network.', 'withdrawal', $wid);

        echo "[withdrawal-processor] broadcasted #{$wid} tx={$txid}\n";
    } catch (Throwable $e) {
        // rollback reserved debit by crediting same amount back from the ledger withdrawal debit row
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("\n                SELECT amount\n                FROM balance_ledger\n                WHERE user_id = ? AND related_type = 'withdrawal' AND related_id = ? AND direction = 'debit'\n                ORDER BY id DESC LIMIT 1\n            ");
            $stmt->execute([$uid, $wid]);
            $reserved = (float)($stmt->fetchColumn() ?? 0);

            $stmt = $pdo->prepare("SELECT balance_after FROM balance_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$uid]);
            $last = (float)($stmt->fetchColumn() ?? 0);

            if ($reserved > 0) {
                $newBalance = $last + $reserved;
                $pdo->prepare("\n                    INSERT INTO balance_ledger (user_id, related_type, related_id, amount, direction, status, balance_after)\n                    VALUES (?, 'withdrawal', ?, ?, 'credit', 'unlocked', ?)\n                ")->execute([$uid, $wid, $reserved, $newBalance]);
            }

            $pdo->prepare("UPDATE withdrawals SET status = 'failed' WHERE id = ? AND status = 'pending'")
                ->execute([$wid]);

            $pdo->commit();
            notify_user($pdo, $uid, 'withdrawal_failed', 'Withdrawal failed', 'Withdrawal could not be broadcast. Reserved balance has been restored.', 'withdrawal', $wid);
            echo "[withdrawal-processor] failed #{$wid}: " . $e->getMessage() . "\n";
        } catch (Throwable $txe) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "[withdrawal-processor] rollback error #{$wid}: " . $txe->getMessage() . "\n";
        }
    }
}
