document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('.ad-form').forEach(form => {

        const marginInput = form.querySelector('input[name="margin_percent"]');
        const coinSelect  = form.querySelector('select[name="crypto_pay"]');
        const maxInput    = form.querySelector('input[name="max_xmr"]');

        const marketEl = form.querySelector('.market-price');
        const yourEl   = form.querySelector('.your-price');
        const feeEl    = form.querySelector('.fee-preview');
        const warning  = form.querySelector('.balance-warning');

        if (!marginInput || !coinSelect) return;

        let marketPrice = null;
        let priceReqSeq = 0;
        let priceCtrl = null;

        /* ==========================
           PRICE PREVIEW
        ========================== */
        async function fetchMarketPrice() {
            const coin = coinSelect.value;
            const reqId = ++priceReqSeq;

            if (priceCtrl) priceCtrl.abort();
            priceCtrl = new AbortController();

            try {
                const res = await fetch(`/api/price.php?coin=${encodeURIComponent(coin)}`, {
                    signal: priceCtrl.signal
                });
                const data = await res.json();

                if (reqId !== priceReqSeq) return;

                marketPrice = Number(data.price);
                if (!Number.isFinite(marketPrice)) throw new Error();

                marketEl.textContent =
                    marketPrice.toFixed(2) + ' ' + coin.toUpperCase();

                updatePreview();

            } catch {
                if (reqId !== priceReqSeq) return;
                marketEl.textContent = '–';
                yourEl.textContent = '–';
                feeEl.textContent = '–';
            }
        }

        function updatePreview() {
            if (!Number.isFinite(marketPrice)) return;

            const margin = parseFloat(marginInput.value || 0);
            const yourPrice = marketPrice * (1 + margin / 100);

            yourEl.textContent =
                yourPrice.toFixed(2) + ' ' + coinSelect.value.toUpperCase();

            const fee = yourPrice * 0.001;
            feeEl.textContent =
                fee.toFixed(6) + ' ' + coinSelect.value.toUpperCase();
        }

        /* ==========================
           SELL BALANCE WARNING (UX)
        ========================== */
        if (
            maxInput &&
            warning &&
            typeof window.USER_BALANCE_XMR === 'number'
        ) {
            maxInput.addEventListener('input', () => {
                const value = parseFloat(maxInput.value);

                if (
                    Number.isFinite(value) &&
                    value > window.USER_BALANCE_XMR
                ) {
                    warning.style.display = 'block';
                } else {
                    warning.style.display = 'none';
                }
            });
        }

        coinSelect.addEventListener('change', fetchMarketPrice);
        marginInput.addEventListener('input', updatePreview);

        fetchMarketPrice();
    });
});
