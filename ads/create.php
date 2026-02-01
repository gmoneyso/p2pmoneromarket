<?php
declare(strict_types=1);

session_start();

/* Auth + backup guard */
require_once __DIR__ . '/../modules/backup_guard.php';

require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/csrf.php';
ob_start();
require_once __DIR__ . '/../modules/balance.php';
ob_end_clean();

$balances = [
    'xmr' => $availableBalance
];

/* Allowed coins (single source of truth) */
$coins = [
    'btc','eth','ltc','bch','xrp','xlm',
    'link','dot','yfi','sol','usdt'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Ad</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/global.css">
<script src="/assets/app.js" defer></script>
<script src="/assets/ad_preview.js" defer></script>

<style>
/* ===== Tabs (same as index.php) ===== */
.tabs {
    display: flex;
    max-width: 700px;
    margin: 16px auto;
}
.tab-btn {
    flex: 1;
    padding: 12px;
    background: var(--bg-input);
    border: none;
    color: var(--text-main);
    cursor: pointer;
}
.tab-btn.active {
    background: var(--accent);
    color: #000;
    font-weight: 600;
}

/* ===== Forms ===== */
.tab-content {
    max-width: 700px;
    margin: auto;
    display: none;
}
.tab-content.active {
    display: block;
}
.ad-form {
    display: grid;
    gap: 10px;
}

/* Responsive */
@media (min-width: 640px) {
    .ad-form {
        grid-template-columns: 1fr 1fr;
    }
    .ad-form textarea,
    .ad-form button {
        grid-column: span 2;
    }
}
</style>

<script>
function showTab(type) {
    document.getElementById('buyForm').classList.toggle('active', type === 'buy');
    document.getElementById('sellForm').classList.toggle('active', type === 'sell');
    document.getElementById('btnBuy').classList.toggle('active', type === 'buy');
    document.getElementById('btnSell').classList.toggle('active', type === 'sell');
}
</script>
</head>

<body>

<?php require __DIR__ . '/../assets/header.php'; ?>

<div class="container">
    <h1>Create P2P Ad</h1>

    <?php include __DIR__ . '/partials/tabs.php'; ?>

    <!-- BUY TAB → Buy form → CREATES SELL ADS -->
    <div id="buyForm" class="tab-content active card">
        <?php include __DIR__ . '/partials/form_buy.php'; ?>
    </div>

    <!-- SELL TAB → Sell form → CREATES BUY ADS -->
    <div id="sellForm" class="tab-content card">
        <?php include __DIR__ . '/partials/form_sell.php'; ?>
    </div>

    <div class="note">
        Prices are calculated from live market rates when a trade begins.
    </div>
</div>

</body>
</html>
