<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/staff.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../trade/helpers.php';
require_once __DIR__ . '/helpers.php';

require_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!staff_is_moderator($pdo, $userId)) {
    http_response_code(403);
    exit('Moderator access required.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $disputeId = (int)($_POST['dispute_id'] ?? 0);

    if ($disputeId > 0) {
        try {
            if ($action === 'claim') {
                $stmt = $pdo->prepare("UPDATE trade_disputes SET assigned_moderator_id = ?, status = 'under_review', updated_at = NOW() WHERE id = ? AND status IN ('open','under_review')");
                $stmt->execute([$userId, $disputeId]);
                flash_set('success', 'Dispute claimed.');
            } elseif ($action === 'escalate') {
                $reason = trim((string)($_POST['reason'] ?? 'Escalated to super admin.'));
                $stmt = $pdo->prepare('SELECT trade_id FROM trade_disputes WHERE id = ? LIMIT 1');
                $stmt->execute([$disputeId]);
                $tradeId = (int)($stmt->fetchColumn() ?: 0);

                $adminId = staff_super_admin_user_id($pdo);
                if ($tradeId > 0 && $adminId > 0) {
                    $stmt = $pdo->prepare("INSERT INTO trade_dispute_escalations (dispute_id, escalated_by_user_id, escalated_to_user_id, reason_text, status, created_at) VALUES (?, ?, ?, ?, 'open', NOW())");
                    $stmt->execute([$disputeId, $userId, $adminId, $reason]);

                    $evt = $pdo->prepare("INSERT INTO trade_dispute_events (dispute_id, actor_user_id, event_type, note, created_at) VALUES (?, ?, 'escalated', ?, NOW())");
                    $evt->execute([$disputeId, $userId, $reason]);

                    notify_user($pdo, $adminId, 'dispute_escalated', 'Dispute escalated to admin', sprintf('Dispute #%d was escalated by moderator.', $disputeId), 'trade_dispute', $disputeId);
                    flash_set('success', 'Dispute escalated to super admin.');
                }
            }
        } catch (Throwable $e) {
            flash_set('error', 'Unable to process moderator action.');
        }
    }

    header('Location: /moderator/dashboard.php');
    exit;
}

$queue = moderator_fetch_dispute_queue($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Moderator Panel | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<style>
.wrap{max-width:1080px;margin:22px auto;display:grid;gap:12px}
.row{border:1px solid var(--border-soft);border-radius:8px;padding:10px;background:#111}
.meta{color:var(--text-muted);font-size:.84rem}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>
<div class="container wrap">
    <section class="card">
        <h1>Moderator Panel</h1>
        <p class="note">Dispute-only operations: claim, review, resolve in trade, or escalate to super admin.</p>
        <?php if (staff_is_super_admin($pdo, $userId)): ?>
            <p><a class="btn" href="/admin/dashboard.php">Open Super Admin Panel</a></p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Open dispute queue</h2>
        <?php if (!$queue): ?>
            <p class="note" style="text-align:left;">No open disputes.</p>
        <?php else: ?>
            <?php foreach ($queue as $d): ?>
                <article class="row">
                    <div><strong>Dispute #<?= (int)$d['dispute_id'] ?></strong> · Trade #<?= (int)$d['trade_id'] ?></div>
                    <div class="meta">Buyer: <?= htmlspecialchars((string)$d['buyer_name']) ?> · Seller: <?= htmlspecialchars((string)$d['seller_name']) ?> · Amount: <?= number_format((float)$d['xmr_amount'], 12) ?> XMR</div>
                    <div class="meta">Status: <?= htmlspecialchars((string)$d['dispute_status']) ?> · Opened: <?= htmlspecialchars((string)$d['opened_at']) ?></div>
                    <p><?= nl2br(htmlspecialchars((string)($d['reason_text'] ?? ''))) ?></p>
                    <div class="actions">
                        <form method="post">
                            <input type="hidden" name="action" value="claim">
                            <input type="hidden" name="dispute_id" value="<?= (int)$d['dispute_id'] ?>">
                            <button class="btn" type="submit">Claim</button>
                        </form>
                        <a class="btn" href="/trade/view.php?id=<?= (int)$d['trade_id'] ?>">Open trade</a>
                        <form method="post">
                            <input type="hidden" name="action" value="escalate">
                            <input type="hidden" name="dispute_id" value="<?= (int)$d['dispute_id'] ?>">
                            <input type="hidden" name="reason" value="Escalated by moderator for super admin decision.">
                            <button class="btn" type="submit">Escalate to admin</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
