<section class="card dashboard-header">

    <div class="dash-left">
        <h2>My Dashboard</h2>
    </div>

    <div class="dash-right">

        <!-- Desktop icons -->
        <div class="dash-actions desktop-only">
            <a href="/ads/create.php" class="dash-icon" title="Create Ad">＋</a>
            <a href="/notifications.php" class="dash-icon" title="Notifications">🔔</a>
            <a href="/messages.php" class="dash-icon" title="Messages">✉️</a>
            <a href="/user/profile.php?id=<?= (int)$_SESSION['user_id'] ?>" class="dash-icon" title="Profile">👤</a>
        </div>

        <!-- Mobile menu -->
        <div class="mobile-only">
            <button class="menu-toggle" onclick="toggleDashMenu()">☰</button>
            <div class="dash-dropdown" id="dashMenu">
                <a href="/ads/create.php">➕ Create Ad</a>
                <a href="/notifications.php">🔔 Notifications</a>
                <a href="/messages.php">✉️ Messages</a>
                <a href="/user/profile.php?id=<?= (int)$_SESSION['user_id'] ?>">👤 Profile</a>
            </div>
        </div>

    </div>

</section>
