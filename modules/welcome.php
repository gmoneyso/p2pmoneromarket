<?php
$unreadNotifications = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $unreadNotifications = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $unreadNotifications = 0;
}
?>
<section class="card dashboard-header">

    <div class="dash-left">
        <h2>My Dashboard</h2>
    </div>

    <div class="dash-right">

        <!-- Desktop icons -->
        <div class="dash-actions desktop-only">
            <a href="/ads/create.php" class="dash-icon" title="Create Ad">＋</a>
            <a href="/wallet/withdraw.php" class="dash-icon" title="Withdraw">💸</a>
            <a href="/notifications.php" class="dash-icon dash-icon-bell" title="Notifications">
                🔔
                <?php if ($unreadNotifications > 0): ?>
                    <span class="notif-badge"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span>
                <?php endif; ?>
            </a>
            <a href="/messages.php" class="dash-icon" title="Messages">✉️</a>
            <a href="/user/profile.php?id=<?= (int)$_SESSION['user_id'] ?>" class="dash-icon" title="Profile">👤</a>
        </div>

        <!-- Mobile menu -->
        <div class="mobile-only">
            <button class="menu-toggle" onclick="toggleDashMenu()">☰</button>
            <div class="dash-dropdown" id="dashMenu">
                <a href="/ads/create.php">➕ Create Ad</a>
                <a href="/wallet/withdraw.php">💸 Withdraw</a>
                <a href="/notifications.php">🔔 Notifications<?= $unreadNotifications > 0 ? ' (' . (string)($unreadNotifications > 99 ? '99+' : $unreadNotifications) . ')' : '' ?></a>
                <a href="/messages.php">✉️ Messages</a>
                <a href="/user/profile.php?id=<?= (int)$_SESSION['user_id'] ?>">👤 Profile</a>
            </div>
        </div>

    </div>

</section>
