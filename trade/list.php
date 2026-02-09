<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../modules/backup_guard.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/state_machine.php';

$userId = (int)$_SESSION['user_id'];

require __DIR__ . '/user/fetch_user_trades.php';
require __DIR__ . '/user/split_user_trades.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Trades</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="/assets/global.css">
<link rel="stylesheet" href="/trade/trade.css">
<script src="/trade/usertrades.js" defer></script>
</head>

<body>

<?php require __DIR__ . '/../assets/header.php'; ?>

<div class="usertrades-header">
    <button class="tab-btn active" id="btnOngoing" onclick="showTradeTab('ongoing')">
        Ongoing Trades
    </button>
    <button class="tab-btn" id="btnCompleted" onclick="showTradeTab('completed')">
        Completed Trades
    </button>
    <button class="tab-btn" id="btnAll" onclick="showTradeTab('all')">
        All Trades
    </button>
</div>

<div class="listings trade-list-wrap">
    <div id="ongoingTab">
        <?php if (!$ongoingTrades): ?>
            <p class="trade-empty">No ongoing trades found.</p>
        <?php else: ?>
            <?php foreach ($ongoingTrades as $trade): ?>
                <?php require __DIR__ . '/user/trade_card_user.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="completedTab" style="display:none;">
        <?php if (!$completedTrades): ?>
            <p class="trade-empty">No completed trades yet.</p>
        <?php else: ?>
            <?php foreach ($completedTrades as $trade): ?>
                <?php require __DIR__ . '/user/trade_card_user.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="allTab" style="display:none;">
        <?php if (!$trades): ?>
            <p class="trade-empty">No trades yet.</p>
        <?php else: ?>
            <?php foreach ($trades as $trade): ?>
                <?php require __DIR__ . '/user/trade_card_user.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
