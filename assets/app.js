/* ============================
   Global UI Helpers
   ============================ */

window.showToast = function showToast(message, type = 'info', durationMs) {
    if (!message) return;

    const stack = document.getElementById('toastStack');
    if (!stack) return;

    const t = String(type || 'info').toLowerCase();
    const normalizedType = ['success', 'error', 'info'].includes(t) ? t : 'info';
    const duration = typeof durationMs === 'number'
        ? durationMs
        : (normalizedType === 'error' ? 5000 : 3000);

    const toast = document.createElement('div');
    toast.className = `toast-pill toast-${normalizedType}`;
    toast.textContent = message;
    stack.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('show'));

    const remove = () => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 220);
    };

    setTimeout(remove, Math.min(5000, Math.max(1500, duration)));
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.flash-seed').forEach((seed) => {
        const message = seed.dataset.message || '';
        const type = seed.dataset.type || 'info';
        if (message) {
            window.showToast(message, type);
        }
    });
});

/* Copy TXID to clipboard */
function copyTxid(el) {
    const txid = el.dataset.txid;
    if (!txid) return;

    navigator.clipboard.writeText(txid).then(() => {
        el.classList.add('copied');
        setTimeout(() => el.classList.remove('copied'), 600);
        window.showToast('Copied', 'success', 3000);
    });
}

/* Dashboard mobile menu */
function toggleDashMenu() {
    const menu = document.getElementById('dashMenu');
    if (!menu) return;

    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

/* Optional: close menu on outside click */
document.addEventListener('click', (e) => {
    const menu = document.getElementById('dashMenu');
    const toggle = document.querySelector('.menu-toggle');

    if (!menu || !toggle) return;

    if (!menu.contains(e.target) && !toggle.contains(e.target)) {
        menu.style.display = 'none';
    }
});

function copyAddress(el) {
    const address = el.dataset.address;
    if (!address) return;

    navigator.clipboard.writeText(address).then(() => {
        el.classList.add('copied');
        setTimeout(() => el.classList.remove('copied'), 600);
        window.showToast('Copied', 'success', 3000);
    });
}
