<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../modules/backup_guard.php';
require_once __DIR__ . '/rpc.php';
require_login();

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];
$address = trim((string)($_POST['address'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);
$priority = (string)($_POST['priority'] ?? 'medium');

if (!in_array($priority, ['slow', 'medium', 'fast'], true)) {
    $priority = 'medium';
}

if ($address === '' || $amount <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Please enter a valid destination address and amount.',
    ]);
    exit;
}

try {
    $estimatedFee = wallet_estimate_fee_xmr($address, $amount, $priority);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'message' => 'Unable to estimate network fee right now.',
    ]);
    exit;
}

$stmt = $pdo->prepare("\n    SELECT COALESCE(SUM(CASE direction WHEN 'credit' THEN amount WHEN 'debit' THEN -amount END),0)\n    FROM balance_ledger\n    WHERE user_id = ? AND status = 'unlocked'\n");
$stmt->execute([$userId]);
$available = (float)$stmt->fetchColumn();

$totalDebit = $amount + $estimatedFee;

if ($available < $totalDebit) {
    echo json_encode([
        'ok' => false,
        'message' => 'Insufficient balance for amount + network fee.',
        'available' => $available,
        'required' => $totalDebit,
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'estimated_fee' => $estimatedFee,
    'total_debit' => $totalDebit,
]);
