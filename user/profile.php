<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../ads/lib/reputation_reviews.php';
require_once __DIR__ . '/../ads/lib/reputation_trades.php';

$viewerId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$usernameSlug = trim((string)($_GET['u'] ?? ''));
$userId = (int)($_GET['id'] ?? 0);

$profileUser = null;
if ($usernameSlug !== '') {
    $stmt = $pdo->prepare('SELECT id, username, pgp_public, created_at FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $usernameSlug]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($profileUser === null && $userId > 0) {
    $stmt = $pdo->prepare('SELECT id, username, pgp_public, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($profileUser === null) {
    http_response_code(404);
}

$profileId = $profileUser ? (int)$profileUser['id'] : 0;
$isOwner = $profileUser && $viewerId > 0 && $viewerId === $profileId;

$rating = 0.0;
$ratingCount = 0;
$completedTrades = 0;
$activeAdsTotal = 0;
$activeBuyAds = 0;
$activeSellAds = 0;

if ($profileUser) {
    $reviewReputation = fetch_review_reputation_by_user($pdo, [$profileId]);
    if (isset($reviewReputation[$profileId])) {
        $rating = (float)$reviewReputation[$profileId]['rating'];
        $ratingCount = (int)$reviewReputation[$profileId]['rating_count'];
    }

    $completedTradeCounts = fetch_completed_trade_counts_by_user($pdo, [$profileId]);
    $completedTrades = (int)($completedTradeCounts[$profileId] ?? 0);

    $activeStmt = $pdo->prepare("\n        SELECT\n            COUNT(*) AS total_active,\n            SUM(CASE WHEN type = 'buy' THEN 1 ELSE 0 END) AS buy_active,\n            SUM(CASE WHEN type = 'sell' THEN 1 ELSE 0 END) AS sell_active\n        FROM listings\n        WHERE user_id = :uid\n          AND status = 'active'\n    ");
    $activeStmt->execute([':uid' => $profileId]);
    $activeRow = $activeStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $activeAdsTotal = (int)($activeRow['total_active'] ?? 0);
    $activeBuyAds = (int)($activeRow['buy_active'] ?? 0);
    $activeSellAds = (int)($activeRow['sell_active'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $profileUser ? htmlspecialchars((string)$profileUser['username']) . ' | Profile' : 'Profile Not Found' ?> | MoneroMarket</title>
    <link rel="stylesheet" href="/assets/global.css">
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>
<div class="profile-wrap">
    <?php if (!$profileUser): ?>
        <div class="card profile-card">
            <h2>User not found</h2>
            <p class="note">We could not find that profile by username slug or ID.</p>
            <p><a class="btn" href="/">Back to marketplace</a></p>
        </div>
    <?php else: ?>
        <div class="card profile-card">
            <h2><?= htmlspecialchars((string)$profileUser['username']) ?></h2>
            <p class="note">Public profile information</p>

            <div class="profile-stats">
                <div class="profile-stat">
                    <span class="profile-stat-label">Reputation</span>
                    <span class="profile-stat-value">⭐ <?= number_format($rating, 2) ?></span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-label">Reviews Received</span>
                    <span class="profile-stat-value"><?= $ratingCount ?></span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-label">Completed Trades</span>
                    <span class="profile-stat-value"><?= $completedTrades ?></span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-label">Active Ads</span>
                    <span class="profile-stat-value"><?= $activeAdsTotal ?> (Buy <?= $activeBuyAds ?> / Sell <?= $activeSellAds ?>)</span>
                </div>
            </div>

            <h3 style="margin-top:16px;">Public Key</h3>
            <?php if (!empty($profileUser['pgp_public'])): ?>
                <pre><?= htmlspecialchars((string)$profileUser['pgp_public']) ?></pre>
            <?php else: ?>
                <p class="note">No public key published yet.</p>
            <?php endif; ?>

            <?php if ($isOwner): ?>
                <div style="margin-top:16px;">
                    <a class="btn" href="/reviews/index.php">View full personal review list</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
