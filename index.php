<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Use your master database connection
require_once __DIR__ . '/db/database1.php';

$is_logged_in = isset($_SESSION['user_id']);

$listings = [];

try {
    // Fetch active listings
    $stmt = $db->prepare("
        SELECT l.id, l.type, l.title, l.price, l.currency, l.payment_method, l.created_at, u.username AS owner
        FROM listings l
        INNER JOIN users u ON l.user_id = u.id
        WHERE l.status = 'active'
        ORDER BY l.created_at DESC
        LIMIT 25
    ");
    $stmt->execute();
    $listings = $stmt->fetchAll();
} catch (PDOException $e) {
    // Log error or handle silently for Tor-safe page
    error_log("DB Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monero P2P Market</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body { margin:0; background:#0b0b0b; color:#e0e0e0; font-family:system-ui,sans-serif; }
        header { background:#121212; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; }
        header a { color:#ff6600; text-decoration:none; margin-left:15px; font-size:0.9rem; }
        .container { max-width:1100px; margin:30px auto; padding:0 20px; }
        table { width:100%; border-collapse:collapse; background:#121212; }
        th, td { padding:12px; border-bottom:1px solid #1f1f1f; font-size:0.85rem; text-align:left; }
        th { background:#181818; }
        tr:hover { background:#161616; }
        .empty { padding:40px; text-align:center; color:#888; }
        .type-buy { color:#4caf50; font-weight:bold; }
        .type-sell { color:#ff5252; font-weight:bold; }
    </style>
</head>
<body>

<header>
    <strong>Monero P2P Market</strong>
    <nav>
        <?php if ($is_logged_in): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.html">Login</a>
            <a href="register.html">Register</a>
        <?php endif; ?>
    </nav>
</header>

<div class="container">
    <h2>Public Listings</h2>

    <?php if (empty($listings)): ?>
        <div class="empty">
            No listings available yet.<br>
            Register and create the first one.
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Price</th>
                    <th>Payment</th>
                    <th>Owner</th>
                    <th>Posted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listings as $l): ?>
                    <tr>
                        <td class="type-<?= htmlspecialchars($l['type']) ?>">
                            <?= strtoupper(htmlspecialchars($l['type'])) ?>
                        </td>
                        <td><?= htmlspecialchars($l['title']) ?></td>
                        <td><?= htmlspecialchars($l['price']) ?> <?= htmlspecialchars($l['currency']) ?></td>
                        <td><?= htmlspecialchars($l['payment_method']) ?></td>
                        <td><?= htmlspecialchars($l['owner']) ?></td>
                        <td><?= htmlspecialchars($l['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
