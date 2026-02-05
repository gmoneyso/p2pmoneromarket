let tradeActionCallback = null;

function openTradeModal({ title, text, onConfirm }) {
    document.getElementById('tradeModalTitle').textContent = title;
    document.getElementById('tradeModalText').textContent = text;

    tradeActionCallback = onConfirm;

    document.getElementById('tradeModal').classList.remove('hidden');
}

function closeTradeModal() {
    document.getElementById('tradeModal').classList.add('hidden');
    tradeActionCallback = null;
}

document.getElementById('tradeConfirmBtn')?.addEventListener('click', () => {
    if (typeof tradeActionCallback === 'function') {
        tradeActionCallback();
    }
    closeTradeModal();
});
