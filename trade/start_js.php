<script>
const xmrInput = document.getElementById('xmr_amount');
const hidden   = document.getElementById('xmr_hidden');
const cryptoEl = document.getElementById('crypto_amount');
const feeEl    = document.getElementById('fee_xmr');
const netEl    = document.getElementById('net_xmr');
const usdtEl   = document.getElementById('usdt_value');
const actionEl = document.getElementById('trade_action');
const payinAddressInput = document.getElementById('payin_address_trade');

let controller = null;

xmrInput.addEventListener('input', () => {
    const xmr = parseFloat(xmrInput.value);

    if (!xmr || xmr <= 0) {
        cryptoEl.textContent =
        feeEl.textContent =
        netEl.textContent =
        usdtEl.textContent = '–';
        return;
    }

    hidden.value = xmr.toFixed(8);

    if (controller) controller.abort();
    controller = new AbortController();

    fetch('/trade/start_pricing.php', {
        method: 'POST',
        signal: controller.signal,
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            ad_id: <?= (int)$ad['id'] ?>,
            xmr: xmr
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) return;
        cryptoEl.textContent = d.crypto_total.toFixed(8);
        feeEl.textContent    = d.fee_xmr.toFixed(8);
        netEl.textContent    = d.net_xmr.toFixed(8);
        usdtEl.textContent   = d.usdt_value ? '$' + d.usdt_value : '–';
    })
    .catch(() => {});
});

function openStartTradeModal() {
    if (!hidden.value) {
        alert('Enter a valid XMR amount');
        return;
    }

    if (payinAddressInput) {
        const v = (payinAddressInput.value || '').trim();
        if (!v) {
            alert('Enter your payment address to sell XMR in this trade');
            payinAddressInput.focus();
            return;
        }
    }

    const verb = (actionEl?.value || 'Start');
    document.getElementById('startTradeText').textContent =
        verb + ' Monero with <?= htmlspecialchars($ad['username']) ?> at your locked rate.';

    document.getElementById('startTradeModal').classList.remove('hidden');
}

function closeStartTradeModal() {
    document.getElementById('startTradeModal').classList.add('hidden');
}

function confirmStartTrade() {
    document.getElementById('tradeForm').submit();
}
</script>
