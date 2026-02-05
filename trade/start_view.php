<?php
function render_trade_start_view(array $ad, string $role): void
{
    $isBuying = $role === 'buyer';
    $action   = $isBuying ? 'Buy' : 'Sell';
?>
<link rel="stylesheet" href="/trade/trade.css">

<div class="trade-box">

<h2>
    <?= $action ?> XMR <?= $isBuying ? 'from' : 'to' ?>
    <strong><?= htmlspecialchars($ad['username']) ?></strong>
</h2>

<p class="trade-limits">
    Limits: <?= rtrim($ad['min_xmr'], '0.') ?> – <?= rtrim($ad['max_xmr'], '0.') ?> XMR
</p>

<label>
    Amount (XMR)
    <input
        type="number"
        id="xmr_amount"
        step="0.00000001"
        min="<?= $ad['min_xmr'] ?>"
        max="<?= $ad['max_xmr'] ?>"
        placeholder="0.00"
    >
</label>

<div class="trade-preview">
    <p>Crypto to pay:
        <span id="crypto_amount">–</span> <?= strtoupper($ad['crypto_pay']) ?>
    </p>
    <p>Platform fee (1%):
        <span id="fee_xmr">–</span> XMR
    </p>
    <p>You receive:
        <span id="net_xmr">–</span> XMR
    </p>
    <p>USDT value:
        <span id="usdt_value">–</span>
    </p>
</div>

<form method="post" action="/trade/start_submit.php" id="tradeForm">
    <input type="hidden" name="listing_id" value="<?= (int)$ad['id'] ?>">
    <input type="hidden" name="xmr_amount" id="xmr_hidden">
    <button type="button" onclick="openStartTradeModal()">
        Start Trade
    </button>
</form>

</div>

<!-- CONFIRM MODAL (delete-modal style) -->
<div id="startTradeModal" class="modal hidden">
    <div class="card modal-card">
        <h3 id="startTradeTitle">Confirm Trade</h3>
        <p id="startTradeText"></p>
        <div class="modal-actions">
            <button class="btn" onclick="closeStartTradeModal()">Cancel</button>
            <button class="btn danger" onclick="confirmStartTrade()">Continue</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/start_js.php'; ?>
<?php } ?>
