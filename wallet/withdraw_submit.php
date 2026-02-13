<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../modules/backup_guard.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/rpc.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$address = trim((string)($_POST['address'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);
$priority = (string)($_POST['priority'] ?? 'medium');
if (!in_array($priority, ['slow', 'medium', 'fast'], true)) {
    $priority = 'medium';
}

if ($address === '' || $amount <= 0) {
    http_response_code(400);
    exit('Invalid withdrawal request');
}

try {
    $estimatedFee = wallet_estimate_fee_xmr($address, $amount, $priority);
} catch (Throwable $e) {
    http_response_code(400);
    exit('Unable to estimate network fee: ' . $e->getMessage());
}

$totalDebit = $amount + $estimatedFee;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("\n        SELECT COALESCE(SUM(CASE direction WHEN 'credit' THEN amount WHEN 'debit' THEN -amount END),0)\n        FROM balance_ledger\n        WHERE user_id = ? AND status = 'unlocked'\n        FOR UPDATE\n    ");
    $stmt->execute([$userId]);
    $available = (float)$stmt->fetchColumn();

    if ($available < $totalDebit) {
        throw new RuntimeException('Insufficient balance for amount + fee.');
    }

    // Insert minimal compatible withdrawal row for current schema.
    $stmt = $pdo->prepare("INSERT INTO withdrawals (user_id, address, amount, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$userId, $address, $amount]);
    $withdrawalId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT balance_after FROM balance_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $last = (float)($stmt->fetchColumn() ?? 0);
    $after = $last - $totalDebit;

    $stmt = $pdo->prepare("\n        INSERT INTO balance_ledger (user_id, related_type, related_id, amount, direction, status, balance_after)\n        VALUES (?, 'withdrawal', ?, ?, 'debit', 'unlocked', ?)\n    ");
    $stmt->execute([$userId, $withdrawalId, $totalDebit, $after]);

    $pdo->commit();
    notify_user($pdo, $userId, 'withdrawal_pending', 'Withdrawal request created', 'Your withdrawal request has been queued for broadcast.', 'withdrawal', $withdrawalId);
    header('Location: /wallet/withdraw.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    exit($e->getMessage());
}
