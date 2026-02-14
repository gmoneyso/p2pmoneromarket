<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/flash.php';

$is_logged_in = isset($_SESSION['user_id']);
$headerUnreadNotifications = 0;
if ($is_logged_in && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $headerUnreadNotifications = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $headerUnreadNotifications = 0;
    }
}
?>

<header class="site-header">
    <div class="header-left">
        <a href="/" class="logo">MoneroMarket</a>
    </div>

    <nav class="header-nav">
        <?php if ($is_logged_in): ?>

            <a href="/dashboard.php" class="nav-item" title="Dashboard">
                <span class="icon" aria-hidden="true">🏠</span>
                <span class="text">Dashboard</span>
            </a>

            <a href="/trade/list.php" class="nav-item" title="Trades">
                <span class="icon" aria-hidden="true">↔️</span>
                <span class="text">Trades</span>
            </a>

            <a href="/userads.php" class="nav-item" title="My Ads">
                <span class="icon" aria-hidden="true">📋</span>
                <span class="text">My Ads</span>
            </a>

            <a href="/user/profile.php?id=<?= (int)$_SESSION['user_id'] ?>" class="nav-item" title="Profile">
                <span class="icon" aria-hidden="true">👤</span>
                <span class="text">Profile</span>
            </a>

            <a href="/notifications.php" class="nav-item nav-item-bell" title="Notifications">
                <span class="icon" aria-hidden="true">🔔</span>
                <span class="text">Notifications</span>
                <?php if ($headerUnreadNotifications > 0): ?>
                    <span class="nav-badge"><?= $headerUnreadNotifications > 99 ? '99+' : $headerUnreadNotifications ?></span>
                <?php endif; ?>
            </a>

            <a href="/logout.php" class="nav-item danger" title="Logout">
                <span class="icon" aria-hidden="true">⏻</span>
                <span class="text">Logout</span>
            </a>

        <?php else: ?>

            <a href="/login.php" class="nav-item">
                <span class="icon" aria-hidden="true">→</span>
                <span class="text">Login</span>
            </a>

            <a href="/register.php" class="nav-item">
                <span class="icon" aria-hidden="true">＋</span>
                <span class="text">Register</span>
            </a>

        <?php endif; ?>
    </nav>
</header>

<div id="toastStack" class="toast-stack" aria-live="polite" aria-atomic="true"></div>
<?php foreach (flash_take_all() as $flash): ?>
    <div class="flash-seed" data-type="<?= htmlspecialchars((string)($flash['type'] ?? 'info')) ?>" data-message="<?= htmlspecialchars((string)($flash['message'] ?? '')) ?>" hidden></div>
<?php endforeach; ?>

<footer class="site-footer-global">
    <span>MoneroMarket</span>
    <span aria-hidden="true">•</span>
    <a href="/backup/recovery.php">Recovery</a>
    <span aria-hidden="true">•</span>
    <a href="/canary.php">Canary</a>
</footer>
