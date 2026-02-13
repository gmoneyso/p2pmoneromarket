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
if (!in_array($prefillPriority, ['slow', 'medium', 'fast'], true)) {
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

$detailsById = [];
foreach ($withdrawals as $w) {
    $wid = (int)$w['id'];
    $txid = (string)($w['txid'] ?? '');
    $detailsById[$wid] = [
        'timestamp' => (string)$w['created_at'],
        'amount' => (float)$w['amount'],
        'fee' => null,
        'notes' => '',
        'destination' => (string)$w['address'],
        'payment_id' => '0000000000000000',
        'txid' => $txid,
        'tx_key' => null,
        'transfers' => [],
    ];

    if ($txid === '') {
        continue;
    }

    try {
        $t = wallet_get_transfer_by_txid($txid);
        if (!$t) {
            continue;
        }

        $detailsById[$wid]['timestamp'] = (string)($t['timestamp'] ?? $detailsById[$wid]['timestamp']);
        $detailsById[$wid]['fee'] = isset($t['fee']) ? ((int)$t['fee'] / 1e12) : null;
        $detailsById[$wid]['notes'] = (string)($t['note'] ?? '');
        $detailsById[$wid]['payment_id'] = (string)($t['payment_id'] ?? $detailsById[$wid]['payment_id']);
        $detailsById[$wid]['tx_key'] = isset($t['tx_key']) ? (string)$t['tx_key'] : null;

        if (isset($t['destinations']) && is_array($t['destinations'])) {
            foreach ($t['destinations'] as $d) {
                $addr = (string)($d['address'] ?? '');
                $amt = isset($d['amount']) ? ((int)$d['amount'] / 1e12) : 0.0;
                if ($addr !== '') {
                    $detailsById[$wid]['transfers'][] = ['address' => $addr, 'amount' => $amt];
                }
            }
        }

        if (isset($t['address']) && (string)$t['address'] !== '') {
            $detailsById[$wid]['destination'] = (string)$t['address'];
        }
    } catch (Throwable $e) {
        // keep graceful static details
    }
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
.withdraw-wrap { max-width: 980px; margin: 24px auto; }
.withdraw-note { font-size: .92rem; color: #aaa; }
.withdraw-head { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
.withdraw-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
.withdraw-stat { background:#0d0d0d; border:1px solid #222; border-radius:10px; padding:10px; }
.withdraw-form { display:grid; gap:10px; }
.withdraw-list { margin-top: 16px; }
.withdraw-card { margin-bottom: 10px; border: 1px solid #232323; border-radius: 10px; background:#0d0d0d; overflow: hidden; }
.withdraw-card-head { display:flex; justify-content:space-between; gap:10px; align-items:center; padding:12px; cursor:pointer; }
.withdraw-card:hover { border-color:#2f2f2f; }
.withdraw-card-main { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.withdraw-amount { color:#f6c945; font-weight:700; }
.withdraw-status { font-size:.75rem; padding:2px 8px; border-radius:999px; border:1px solid #333; }
.withdraw-card-body { display:none; border-top:1px solid #222; padding:12px; background:#111; }
.withdraw-card.open .withdraw-card-body { display:block; }
.withdraw-details { display:grid; grid-template-columns: 170px 1fr; gap:6px 10px; font-size:.92rem; }
.withdraw-details code { word-break: break-all; font-size:.85rem; }
.copy-btn { width:auto; padding:6px 10px; font-size:.82rem; }
.transfer-list { margin:8px 0 0; padding-left:20px; }
@media (max-width: 760px){
  .withdraw-meta { grid-template-columns: 1fr; }
  .withdraw-details { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>
<div class="container withdraw-wrap">
    <div class="withdraw-head">
        <h1>Withdraw XMR</h1>
        <a class="btn" style="width:auto;" href="/dashboard.php">Back to Dashboard</a>
    </div>

    <p class="withdraw-note">Fee is paid from your ledger balance. You can only withdraw if balance covers amount + network fee.</p>

    <div class="withdraw-meta">
        <div class="withdraw-stat"><small>Available</small><div><strong><?= number_format($available, 12) ?> XMR</strong></div></div>
        <div class="withdraw-stat"><small>Model</small><div><strong>amount + fee</strong></div></div>
        <div class="withdraw-stat"><small>Lifecycle</small><div><strong>pending → broadcast → confirmed</strong></div></div>
    </div>

    <form method="post" action="/wallet/withdraw_submit.php" class="card withdraw-form">
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
        <h2>Withdrawal Transactions</h2>
        <?php if (!$withdrawals): ?>
            <p class="note" style="text-align:left;">No withdrawals yet.</p>
        <?php else: ?>
            <?php foreach ($withdrawals as $w): $wid=(int)$w['id']; $d=$detailsById[$wid] ?? null; ?>
                <article class="withdraw-card" id="withdraw-<?= $wid ?>">
                    <div class="withdraw-card-head" onclick="toggleWithdrawCard(this.closest('.withdraw-card'))">
                        <div class="withdraw-card-main">
                            <strong>#<?= $wid ?></strong>
                            <span class="withdraw-amount">-<?= number_format((float)$w['amount'], 12) ?> XMR</span>
                            <span>→ <?= htmlspecialchars((string)$w['address']) ?></span>
                        </div>
                        <span class="withdraw-status"><?= htmlspecialchars(strtoupper((string)$w['status'])) ?></span>
                    </div>
                    <div class="withdraw-card-body">
                        <div class="withdraw-details">
                            <strong>Timestamp:</strong><span><?= htmlspecialchars((string)($d['timestamp'] ?? $w['created_at'])) ?></span>
                            <strong>Amount:</strong><span>-<?= number_format((float)$w['amount'], 12) ?> XMR</span>
                            <strong>Fee:</strong><span><?= $d && $d['fee'] !== null ? number_format((float)$d['fee'], 12) . ' XMR' : 'N/A' ?></span>
                            <strong>Notes:</strong><span><?= htmlspecialchars((string)($d['notes'] ?? '-')) ?></span>
                            <strong>Destination:</strong><code><?= htmlspecialchars((string)($d['destination'] ?? $w['address'])) ?></code>
                            <strong>Payment ID:</strong><code><?= htmlspecialchars((string)($d['payment_id'] ?? '0000000000000000')) ?></code>
                            <strong>TX ID:</strong>
                            <div>
                                <code><?= htmlspecialchars((string)($d['txid'] ?? $w['txid'] ?? 'pending')) ?></code>
                                <?php if (!empty($d['txid'])): ?>
                                    <button type="button" class="btn copy-btn" onclick="copyTxid(event, '<?= htmlspecialchars((string)$d['txid']) ?>')">Copy TXID</button>
                                <?php endif; ?>
                            </div>
                            <strong>TX Key:</strong><code><?= htmlspecialchars((string)($d['tx_key'] ?? 'N/A')) ?></code>
                            <strong>Transfers:</strong>
                            <div>
                                <?php if ($d && !empty($d['transfers'])): ?>
                                    <ul class="transfer-list">
                                        <?php foreach ($d['transfers'] as $t): ?>
                                            <li><code><?= htmlspecialchars((string)$t['address']) ?></code>: <?= number_format((float)$t['amount'], 12) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span>N/A</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<script>
function toggleWithdrawCard(card) {
    if (!card) return;
    card.classList.toggle('open');
}
function copyTxid(e, txid) {
    e.stopPropagation();
    navigator.clipboard.writeText(txid).then(() => {
        const btn = e.currentTarget;
        const old = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => btn.textContent = old, 900);
    }).catch(() => {});
}
</script>
</body>
</html>
