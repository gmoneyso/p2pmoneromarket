<?php
function render_trade_start_view(array $c): void {
?>
<h2>
    You are <?= $c['role'] === 'buyer' ? 'buying' : 'selling' ?>
</h2>

<p>
    <input
        id="xmr_amount"
        type="number"
        step="0.00000001"
        min="<?= $c['min_xmr'] ?>"
        max="<?= $c['max_xmr'] ?>"
        placeholder="0.00"
    >
    XMR
    from <strong><?= htmlspecialchars($c['ad_owner']) ?></strong>
</p>

<hr>

<div id="preview">
    <p>You will pay: <span id="crypto_amount">–</span> <?= $c['crypto_pay'] ?></p>
    <p>Platform fee (1%): <span id="fee_xmr">–</span> XMR</p>
    <p>You will receive: <span id="net_xmr">–</span> XMR</p>
    <p>Value in USDT: <span id="usdt_value">–</span></p>
</div>

<form method="post">
    <input type="hidden" name="xmr_amount" id="xmr_hidden">
    <button type="submit">Start Trade</button>
</form>

<?php require __DIR__ . '/start_js.php'; ?>
<?php
}
