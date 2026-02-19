<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/staff.php';
require_once __DIR__ . '/../trade/helpers.php';

require_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!staff_is_moderator($pdo, $userId)) {
    http_response_code(403);
    exit('Moderator access required.');
}

$stats = [
    'users_total' => 0,
    'trades_total' => 0,
    'disputes_open' => 0,
    'disputes_resolved' => 0,
    'fees_total_xmr' => 0.0,
    'withdrawals_pending' => 0,
];

$stats['users_total'] = (int)($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0);
$stats['trades_total'] = (int)($pdo->query('SELECT COUNT(*) FROM trades')->fetchColumn() ?: 0);
$stats['disputes_open'] = (int)($pdo->query("SELECT COUNT(*) FROM trade_disputes WHERE status = 'open'")->fetchColumn() ?: 0);
$stats['disputes_resolved'] = (int)($pdo->query("SELECT COUNT(*) FROM trade_disputes WHERE status IN ('resolved_buyer','resolved_seller')")->fetchColumn() ?: 0);
$stats['withdrawals_pending'] = (int)($pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status IN ('queued','processing')")->fetchColumn() ?: 0);

$platformFeeUserId = trade_platform_fee_user_id($pdo);
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM balance_ledger WHERE user_id = ? AND related_type = 'fee' AND direction = 'credit'");
$stmt->execute([$platformFeeUserId]);
$stats['fees_total_xmr'] = (float)($stmt->fetchColumn() ?: 0.0);

$disputeQueueStmt = $pdo->query("\n    SELECT\n        t.id,\n        t.status,\n        t.updated_at,\n        t.xmr_amount,\n        buyer.username AS buyer_name,\n        seller.username AS seller_name,\n        d.reason_text,\n        d.opened_at\n    FROM trades t\n    JOIN trade_disputes d ON d.trade_id = t.id\n    JOIN users buyer ON buyer.id = t.buyer_id\n    JOIN users seller ON seller.id = t.seller_id\n    WHERE d.status = 'open'\n    ORDER BY d.opened_at DESC\n    LIMIT 25\n");
$disputeQueue = $disputeQueueStmt ? ($disputeQueueStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

$latestNotificationsStmt = $pdo->prepare("\n    SELECT id, type, title, created_at\n    FROM notifications\n    WHERE user_id = ?\n    ORDER BY created_at DESC\n    LIMIT 10\n");
$latestNotificationsStmt->execute([$userId]);
$latestNotifications = $latestNotificationsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<style>
.admin-wrap{max-width:1100px;margin:22px auto;display:grid;gap:12px}
.admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.admin-stat{padding:12px;border:1px solid var(--border-soft);border-radius:10px;background:#111}
.admin-stat h3{margin:0 0 6px;font-size:.86rem;color:var(--text-muted);font-weight:600}
.admin-stat p{margin:0;font-size:1.2rem;font-weight:700}
.admin-list{display:grid;gap:8px}
.admin-row{border:1px solid var(--border-soft);border-radius:9px;padding:10px;background:#101010}
.admin-meta{color:var(--text-muted);font-size:.82rem}
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>
<div class="container admin-wrap">
    <section class="card">
        <h1>Admin / Moderator Dashboard</h1>
        <p class="note">Operational overview for disputes, fees, withdrawals, and platform health.</p>
    </section>

    <section class="admin-grid">
        <article class="admin-stat"><h3>Total users</h3><p><?= number_format($stats['users_total']) ?></p></article>
        <article class="admin-stat"><h3>Total trades</h3><p><?= number_format($stats['trades_total']) ?></p></article>
        <article class="admin-stat"><h3>Open disputes</h3><p><?= number_format($stats['disputes_open']) ?></p></article>
        <article class="admin-stat"><h3>Resolved disputes</h3><p><?= number_format($stats['disputes_resolved']) ?></p></article>
        <article class="admin-stat"><h3>Total fees acquired (XMR)</h3><p><?= number_format($stats['fees_total_xmr'], 12) ?></p></article>
        <article class="admin-stat"><h3>Withdrawals pending</h3><p><?= number_format($stats['withdrawals_pending']) ?></p></article>
    </section>

    <section class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <h2>Open dispute queue</h2>
            <a href="/trade/disputes.php" class="btn">Open dispute panel</a>
        </div>
        <?php if (!$disputeQueue): ?>
            <p class="note" style="text-align:left;">No open disputes in queue.</p>
        <?php else: ?>
            <div class="admin-list">
                <?php foreach ($disputeQueue as $row): ?>
                    <article class="admin-row">
                        <div><strong>Trade #<?= (int)$row['id'] ?></strong> · <?= htmlspecialchars((string)$row['buyer_name']) ?> vs <?= htmlspecialchars((string)$row['seller_name']) ?></div>
                        <div class="admin-meta"><?= htmlspecialchars((string)$row['opened_at']) ?> · <?= number_format((float)$row['xmr_amount'], 12) ?> XMR</div>
                        <div class="admin-meta"><?= nl2br(htmlspecialchars((string)($row['reason_text'] ?? ''))) ?></div>
                        <p style="margin:8px 0 0;"><a class="btn" href="/trade/view.php?id=<?= (int)$row['id'] ?>">Review trade</a></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Recent moderation notifications</h2>
        <?php if (!$latestNotifications): ?>
            <p class="note" style="text-align:left;">No moderation notifications yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($latestNotifications as $n): ?>
                    <li><strong><?= htmlspecialchars((string)$n['title']) ?></strong> <span class="admin-meta">(<?= htmlspecialchars((string)$n['type']) ?> · <?= htmlspecialchars((string)$n['created_at']) ?>)</span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
