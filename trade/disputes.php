<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/state_machine.php';
require_login();

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("\n    SELECT\n        t.id,
        t.status,
        t.xmr_amount,
        t.crypto_pay,
        t.crypto_amount,
        t.updated_at,
        buyer.username AS buyer_name,
        seller.username AS seller_name
    FROM trades t
    JOIN users buyer ON buyer.id = t.buyer_id
    JOIN users seller ON seller.id = t.seller_id
    WHERE t.status = ?
      AND (t.buyer_id = ? OR t.seller_id = ?)
    ORDER BY t.updated_at DESC, t.id DESC
");
$stmt->execute([TRADE_STATUS_DISPUTED, $userId, $userId]);
$disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Disputes | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<style>
.disputes-wrap { max-width: 980px; margin: 28px auto; }
.dispute-card { border: 1px solid var(--border-soft); }
.dispute-row { display: flex; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
.dispute-meta { color: var(--text-muted); font-size: .86rem; margin-top: 6px; }
.dispute-amount { font-weight: 700; color: #f6c945; }
.dispute-empty { color: var(--text-muted); }
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>

<div class="container disputes-wrap">
    <div class="card">
        <h1>Disputed Trades</h1>
        <p class="note">Basic disputes view for now (no moderator tooling yet).</p>
    </div>

    <?php if (!$disputes): ?>
        <div class="card dispute-empty">No disputed trades found for your account.</div>
    <?php else: ?>
        <?php foreach ($disputes as $trade): ?>
            <div class="card dispute-card">
                <div class="dispute-row">
                    <div>
                        <h3>Trade #<?= (int)$trade['id'] ?></h3>
                        <div class="dispute-meta">
                            Buyer: <?= htmlspecialchars((string)$trade['buyer_name']) ?> ·
                            Seller: <?= htmlspecialchars((string)$trade['seller_name']) ?>
                        </div>
                        <div class="dispute-meta">
                            Status: <?= htmlspecialchars(strtoupper(str_replace('_', ' ', (string)$trade['status']))) ?> ·
                            Last update: <?= htmlspecialchars((string)$trade['updated_at']) ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="dispute-amount"><?= number_format((float)$trade['xmr_amount'], 8) ?> XMR</div>
                        <div class="dispute-meta"><?= number_format((float)$trade['crypto_amount'], 8) ?> <?= strtoupper((string)$trade['crypto_pay']) ?></div>
                        <p style="margin-top:12px;"><a class="btn" href="/trade/view.php?id=<?= (int)$trade['id'] ?>">Open Trade</a></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
