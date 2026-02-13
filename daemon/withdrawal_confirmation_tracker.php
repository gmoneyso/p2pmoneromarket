<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/notifications.php';

echo "[withdrawal-tracker] active\n";

$stmt = $pdo->query("\n    SELECT id, txid\n    FROM withdrawals\n    WHERE status = 'broadcast'\n      AND txid IS NOT NULL\n    ORDER BY id ASC\n    LIMIT 40\n");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $w) {
    $wid = (int)$w['id'];
    $txid = (string)$w['txid'];
    if ($txid === '') {
        continue;
    }

    try {
        $res = rpc([
            'jsonrpc' => '2.0',
            'id' => 'withdraw-check',
            'method' => 'get_transfer_by_txid',
            'params' => ['txid' => $txid],
        ]);

        $transfer = $res['result']['transfer'] ?? null;
        if (!is_array($transfer)) {
            continue;
        }

        $confirmations = (int)($transfer['confirmations'] ?? 0);
        if ($confirmations >= REQUIRED_CONFIRMATIONS) {
            $pdo->prepare("UPDATE withdrawals SET status = 'confirmed' WHERE id = ? AND status = 'broadcast'")
                ->execute([$wid]);
            $ownerStmt = $pdo->prepare('SELECT user_id FROM withdrawals WHERE id = ? LIMIT 1');
            $ownerStmt->execute([$wid]);
            $ownerId = (int)($ownerStmt->fetchColumn() ?? 0);
            if ($ownerId > 0) {
                notify_user($pdo, $ownerId, 'withdrawal_confirmed', 'Withdrawal confirmed', 'Your withdrawal is confirmed on-chain.', 'withdrawal', $wid);
            }
            echo "[withdrawal-tracker] confirmed #{$wid}\n";
        }
    } catch (Throwable $e) {
        echo "[withdrawal-tracker] error #{$wid}: " . $e->getMessage() . "\n";
    }
}
