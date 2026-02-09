<?php
declare(strict_types=1);

$is_logged_in = isset($_SESSION['user_id']);
?>

<header class="site-header">
    <div class="header-left">
        <a href="/" class="logo">MoneroMarket</a>
    </div>

    <nav class="header-nav">
        <?php if ($is_logged_in): ?>

            <a href="/dashboard.php" class="nav-item" title="Dashboard">
                <span class="icon">⌂</span>
                <span class="text">Dashboard</span>
            </a>

            <a href="/trade/list.php" class="nav-item" title="Trades">
                <span class="icon">↔</span>
                <span class="text">Trades</span>
            </a>

            <a href="/userads.php" class="nav-item" title="My Ads">
                <span class="icon">≡</span>
                <span class="text">My Ads</span>
            </a>

            <a href="/logout.php" class="nav-item danger" title="Logout">
                <span class="icon">⏻</span>
                <span class="text">Logout</span>
            </a>

        <?php else: ?>

            <a href="/login.php" class="nav-item">
                <span class="icon">→</span>
                <span class="text">Login</span>
            </a>

            <a href="/register.php" class="nav-item">
                <span class="icon">+</span>
                <span class="text">Register</span>
            </a>

        <?php endif; ?>
    </nav>
</header>
