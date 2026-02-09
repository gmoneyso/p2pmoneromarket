<?php
declare(strict_types=1);

$ctx = require __DIR__ . '/trade_context.php';
$trade = $ctx['trade'];
$status = (string)$trade['status'];
$isTerminal = in_array($status, [
    TRADE_STATUS_RELEASED,
    TRADE_STATUS_CANCELLED,
    TRADE_STATUS_EXPIRED,
    TRADE_STATUS_DISPUTED,
], true);
$hasCountdown = $status === TRADE_STATUS_PENDING_PAYMENT;
$payment = trade_latest_payment($pdo, (int)$trade['id']);

function explorer_tx_url(string $coin, string $txid): ?string
{
    $map = [
        'btc' => 'https://mempool.space/tx/%s',
        'eth' => 'https://etherscan.io/tx/%s',
        'ltc' => 'https://blockchair.com/litecoin/transaction/%s',
        'bch' => 'https://blockchair.com/bitcoin-cash/transaction/%s',
        'xrp' => 'https://xrpscan.com/tx/%s',
        'xlm' => 'https://stellarchain.io/transactions/%s',
        'link' => 'https://etherscan.io/tx/%s',
        'dot' => 'https://subscan.io/extrinsic/%s',
        'yfi' => 'https://etherscan.io/tx/%s',
        'sol' => 'https://solscan.io/tx/%s',
        'usdt' => 'https://etherscan.io/tx/%s',
    ];

    $coin = strtolower($coin);
    if (!isset($map[$coin])) {
        return null;
    }

    return sprintf($map[$coin], rawurlencode($txid));
}

$paymentExplorer = $payment ? explorer_tx_url((string)$payment['crypto'], (string)$payment['txid']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trade #<?= (int)$trade['id'] ?> | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<link rel="stylesheet" href="/trade/trade.css">
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>

<div class="trade-detail-wrap">
    <section class="card trade-shell">
        <div class="trade-shell-head">
            <h1>Trade #<?= (int)$trade['id'] ?></h1>
            <a class="trade-link" href="/trade/list.php">Back to Trades</a>
        </div>

        <?php if ($status === TRADE_STATUS_RELEASED): ?>
            <div class="trade-complete-banner">✅ COMPLETE — Trade released successfully</div>
        <?php endif; ?>

        <div class="trade-summary-row">
            <span><?= strtoupper($trade['crypto_pay']) ?> ↔ <?= number_format((float)$trade['xmr_amount'], 8) ?> XMR</span>
            <span class="trade-badge trade-status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(strtoupper(str_replace('_', ' ', $status))) ?></span>
        </div>

        <?php if ($hasCountdown): ?>
            <div class="trade-timer-box" data-trade-id="<?= (int)$trade['id'] ?>">
                <p>
                    Time left to complete payment:
                    <strong id="tradeTimer">--:--</strong>
                </p>
                <small>
                    Timer started from ad payment window (<?= (int)($trade['payment_time_limit'] ?? 0) ?> minutes).
                </small>
            </div>
        <?php endif; ?>

        <?php if ($payment): ?>
            <div class="trade-note">
                Payment proof txid: <code><?= htmlspecialchars((string)$payment['txid']) ?></code>
                <?php if ($paymentExplorer): ?>
                    · <a class="trade-link" target="_blank" rel="noopener" href="<?= htmlspecialchars($paymentExplorer) ?>">Open Explorer</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($ctx['canPay']): ?>
            <div class="trade-panel">
                <p>Send <strong><?= number_format((float)$trade['crypto_amount'], 8) ?> <?= strtoupper($trade['crypto_pay']) ?></strong> to <strong><?= htmlspecialchars((string)($trade['payin_address_snapshot'] ?? 'N/A')) ?></strong>.</p>
                <?php if (!empty($trade['payin_network_snapshot'])): ?>
                    <p>Network: <strong><?= htmlspecialchars((string)$trade['payin_network_snapshot']) ?></strong></p>
                <?php endif; ?>
                <?php if (!empty($trade['payin_tag_memo_snapshot'])): ?>
                    <p>Memo/Tag: <strong><?= htmlspecialchars((string)$trade['payin_tag_memo_snapshot']) ?></strong></p>
                <?php endif; ?>
                <p>You will receive <strong><?= number_format((float)$trade['xmr_amount'], 8) ?> XMR</strong>.</p>
                <pre class="trade-terms"><?= htmlspecialchars((string)($trade['listing_terms'] ?? '')) ?></pre>

                <form method="post" action="/trade/mark_paid.php" class="trade-action-form">
                    <input type="hidden" name="trade_id" value="<?= (int)$trade['id'] ?>">
                    <label for="txid"><small>Payment TxID (for explorer verification)</small></label>
                    <input id="txid" type="text" name="txid" minlength="16" maxlength="128" required placeholder="Paste payment transaction id">
                    <button type="submit">I Have Paid</button>
                </form>
            </div>

        <?php elseif ($ctx['canConfirm']): ?>
            <div class="trade-panel">
                <p>Buyer <strong><?= htmlspecialchars($ctx['counterparty']) ?></strong> marked payment as sent (awaiting your confirmation).</p>
                <form method="post" action="/trade/confirm_release.php" class="trade-action-form">
                    <input type="hidden" name="trade_id" value="<?= (int)$trade['id'] ?>">
                    <button type="submit">Confirm Payment Received</button>
                </form>
            </div>

        <?php else: ?>
            <div class="trade-note">
                <?php if ($status === TRADE_STATUS_CANCELLED): ?>
                    Trade was cancelled. Escrow was returned to seller balance.
                <?php elseif ($status === TRADE_STATUS_EXPIRED): ?>
                    Trade expired before payment confirmation.
                <?php elseif ($status === TRADE_STATUS_DISPUTED): ?>
                    Trade is currently disputed and pending manual review.
                <?php elseif ($status === TRADE_STATUS_RELEASED): ?>
                    Trade completed successfully.
                <?php else: ?>
                    Waiting for next trade action.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$isTerminal): ?>
            <div class="trade-actions-row">
                <?php if ($ctx['canCancel']): ?>
                    <form method="post" action="/trade/cancel.php" class="trade-action-form">
                        <input type="hidden" name="trade_id" value="<?= (int)$trade['id'] ?>">
                        <button type="submit" class="btn danger">Cancel Trade</button>
                    </form>
                <?php endif; ?>

                <?php if ($ctx['canDispute']): ?>
                    <form method="post" action="/trade/dispute.php" class="trade-action-form">
                        <input type="hidden" name="trade_id" value="<?= (int)$trade['id'] ?>">
                        <button type="submit" class="btn">Open Dispute</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if ($hasCountdown): ?>
<script>
(() => {
    const box = document.querySelector('.trade-timer-box');
    const timerEl = document.getElementById('tradeTimer');

    if (!box || !timerEl) return;

    const tradeId = box.getAttribute('data-trade-id');
    let secondsLeft = 0;

    const fmt = (total) => {
        const m = Math.floor(total / 60);
        const s = total % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    };

    const render = () => {
        timerEl.textContent = fmt(Math.max(0, secondsLeft));
        if (secondsLeft <= 0) {
            setTimeout(() => window.location.reload(), 1000);
        }
    };

    const sync = () => {
        fetch('/trade/timer.php?trade_id=' + encodeURIComponent(tradeId), {credentials: 'same-origin'})
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(d => {
                if (!d || typeof d.seconds_left === 'undefined') return;
                secondsLeft = Number(d.seconds_left) || 0;
                render();
                if (d.status !== 'pending_payment') {
                    setTimeout(() => window.location.reload(), 800);
                }
            })
            .catch(() => {});
    };

    sync();
    setInterval(() => {
        if (secondsLeft > 0) {
            secondsLeft -= 1;
            render();
        }
    }, 1000);
    setInterval(sync, 15000);
})();
</script>
<?php endif; ?>
</body>
</html>
