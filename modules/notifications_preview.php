<?php
$latestNotifications = [];
$notificationsPreviewError = null;

try {
    $stmt = $pdo->prepare("\n        SELECT id, title, body, is_read, created_at\n        FROM notifications\n        WHERE user_id = ?\n        ORDER BY created_at DESC\n        LIMIT 5\n    ");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $latestNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $notificationsPreviewError = 'Notifications are not available yet.';
}
?>

<section class="card notifications-preview">
    <div class="notifications-preview-head">
        <h2>Notifications</h2>
        <a href="/notifications.php" class="view-all-link">View all</a>
    </div>

    <?php if ($notificationsPreviewError !== null): ?>
        <p class="note" style="text-align:left;"><?= htmlspecialchars($notificationsPreviewError) ?></p>
    <?php elseif (!$latestNotifications): ?>
        <p class="note" style="text-align:left;">No notifications yet.</p>
    <?php else: ?>
        <div class="notifications-preview-list">
            <?php foreach ($latestNotifications as $n): ?>
                <a class="notification-row <?= (int)$n['is_read'] === 0 ? 'unread' : '' ?>" href="/notifications.php#n<?= (int)$n['id'] ?>">
                    <strong><?= htmlspecialchars((string)$n['title']) ?></strong>
                    <span><?= htmlspecialchars((string)$n['body']) ?></span>
                    <small><?= htmlspecialchars((string)$n['created_at']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
