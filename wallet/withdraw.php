<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../modules/backup_guard.php';
require_once __DIR__ . '/rpc.php';
require_login();

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("\n    SELECT COALESCE(SUM(CASE direction WHEN 'credit' THEN amount WHEN 'debit' THEN -amount END),0)\n    FROM balance_ledger\n    WHERE user_id = ? AND status = 'unlocked'\n");
$stmt->execute([$userId]);
$available = (float)$stmt->fetchColumn();

$prefillAmount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0.0;
$prefillAddress = (string)($_GET['address'] ?? '');
$prefillPriority = (string)($_GET['priority'] ?? 'medium');
if (!in_array($prefillPriority, ['slow','medium','fast'], true)) {
    $prefillPriority = 'medium';
}

$estimate = null;
$estimateError = null;
if ($prefillAmount > 0 && $prefillAddress !== '') {
    try {
        $fee = wallet_estimate_fee_xmr($prefillAddress, $prefillAmount, $prefillPriority);
        $estimate = [
            'amount' => $prefillAmount,
            'fee' => $fee,
            'total' => $prefillAmount + $fee,
        ];
    } catch (Throwable $e) {
        $estimateError = $e->getMessage();
    }
}

$withdrawals = [];
try {
    $stmt = $pdo->prepare("SELECT id,address,amount,status,txid,created_at FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 20");
    $stmt->execute([$userId]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $withdrawals = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Withdraw XMR</title>
<link rel="stylesheet" href="/assets/global.css">
<link rel="stylesheet" href="/assets/dashboard.css">
<style>
.withdraw-wrap{max-width:900px;margin:24px auto}
.withdraw-note{font-size:.92rem;color:#aaa}
.withdraw-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}
.withdraw-stat{background:#0d0d0d;border:1px solid #222;border-radius:8px;padding:10px}
.withdraw-list .row{padding:10px;border:1px solid #222;border-radius:8px;margin-bottom:8px;background:#101010}
.status-chip{font-size:.75rem;padding:2px 8px;border-radius:999px;border:1px solid #333}
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>
<div class="container withdraw-wrap">
    <h1>Withdraw XMR</h1>
    <p class="withdraw-note">Fee is paid from your ledger balance. You can only withdraw if balance covers amount + network fee.</p>

    <div class="withdraw-meta">
        <div class="withdraw-stat"><small>Available</small><div><strong><?= number_format($available, 12) ?> XMR</strong></div></div>
        <div class="withdraw-stat"><small>Model</small><div><strong>amount + fee</strong></div></div>
        <div class="withdraw-stat"><small>Queue</small><div><strong>pending → broadcasted → confirmed</strong></div></div>
    </div>

    <form method="post" action="/wallet/withdraw_submit.php" class="card" style="display:grid;gap:10px;">
        <label>Destination address
            <input type="text" name="address" required value="<?= htmlspecialchars($prefillAddress) ?>">
        </label>
        <label>Amount (XMR)
            <input type="number" name="amount" step="0.000000000001" min="0.000000000001" required value="<?= $prefillAmount > 0 ? htmlspecialchars((string)$prefillAmount) : '' ?>">
        </label>
        <label>Speed
            <select name="priority">
                <option value="slow" <?= $prefillPriority === 'slow' ? 'selected' : '' ?>>Slow</option>
                <option value="medium" <?= $prefillPriority === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="fast" <?= $prefillPriority === 'fast' ? 'selected' : '' ?>>Fast</option>
            </select>
        </label>
        <button type="submit" class="btn">Create Withdrawal Request</button>
    </form>

    <?php if ($estimate): ?>
        <div class="card">
            <strong>Estimated total debit:</strong>
            <?= number_format($estimate['amount'], 12) ?> + <?= number_format($estimate['fee'], 12) ?> fee = <?= number_format($estimate['total'], 12) ?> XMR
        </div>
    <?php elseif ($estimateError): ?>
        <div class="card"><span class="withdraw-note">Fee estimation failed: <?= htmlspecialchars($estimateError) ?></span></div>
    <?php endif; ?>

    <section class="withdraw-list">
        <h2>Recent Withdrawals</h2>
        <?php if (!$withdrawals): ?>
            <p class="note" style="text-align:left;">No withdrawals yet.</p>
        <?php else: ?>
            <?php foreach ($withdrawals as $w): ?>
                <div class="row">
                    <div><strong>#<?= (int)$w['id'] ?></strong> · <?= htmlspecialchars((string)$w['created_at']) ?></div>
                    <div><?= number_format((float)$w['amount'], 12) ?> XMR → <?= htmlspecialchars((string)$w['address']) ?></div>
                    <div>Txid: <?= htmlspecialchars((string)($w['txid'] ?? 'pending')) ?></div>
                    <div><span class="status-chip"><?= htmlspecialchars(strtoupper((string)$w['status'])) ?></span></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
