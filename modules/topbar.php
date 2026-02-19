<?php
declare(strict_types=1);

$dashUnreadNotifications = 0;
$dashUnreadMessages = 0;

try {
    $uid = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$uid]);
    $dashUnreadNotifications = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND type = 'message_new'");
    $stmt->execute([$uid]);
    $dashUnreadMessages = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $dashUnreadNotifications = 0;
    $dashUnreadMessages = 0;
}
?>

<section class="card" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
    <div>
        <h2 style="margin:0 0 4px;">Quick Actions</h2>
        <p class="note" style="margin:0;text-align:left;">Jump into trades, disputes, secure messages and notifications.</p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn" href="/trade/list.php">Trades</a>
        <a class="btn" href="/messages.php">Messages<?= $dashUnreadMessages > 0 ? ' (' . $dashUnreadMessages . ')' : '' ?></a>
        <a class="btn" href="/notifications.php">Notifications<?= $dashUnreadNotifications > 0 ? ' (' . $dashUnreadNotifications . ')' : '' ?></a>
        <a class="btn" href="/trade/disputes.php">Disputes</a>
    </div>
</section>
