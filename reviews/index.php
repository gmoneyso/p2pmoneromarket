<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$tab = (string)($_GET['tab'] ?? 'received');
if (!in_array($tab, ['received', 'given'], true)) {
    $tab = 'received';
}

$received = review_fetch_received($pdo, $userId);
$given = review_fetch_given($pdo, $userId);

$avg = 0.0;
if ($received) {
    $sum = array_sum(array_map(static fn(array $r): int => (int)$r['rating'], $received));
    $avg = $sum / max(1, count($received));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reviews | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<style>
.reviews-wrap{max-width:900px;margin:20px auto}
.review-tabs{display:flex;gap:8px;margin-bottom:14px}
.review-item{margin-bottom:12px;padding:12px;background:var(--bg-card);border-radius:8px}
.meta{font-size:.8rem;color:var(--text-muted)}
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>
<div class="reviews-wrap">
    <div class="card" style="margin-bottom:14px;">
        <h2>My Reviews</h2>
        <p class="meta">Received: <?= count($received) ?> · Average: <?= number_format($avg, 2) ?></p>
    </div>

    <div class="review-tabs">
        <a class="btn" href="/reviews/index.php?tab=received">Received Reviews</a>
        <a class="btn" href="/reviews/index.php?tab=given">Given Reviews</a>
    </div>

    <?php if ($tab === 'received'): ?>
        <?php if (!$received): ?>
            <div class="card">No received reviews yet.</div>
        <?php else: ?>
            <?php foreach ($received as $r): ?>
                <div class="review-item">
                    <strong>⭐ <?= (int)$r['rating'] ?>/5</strong>
                    <div class="meta">From <?= htmlspecialchars((string)$r['reviewer_username']) ?> · Trade #<?= (int)$r['trade_id'] ?> · <?= htmlspecialchars((string)$r['created_at']) ?></div>
                    <?php if (!empty($r['comment'])): ?>
                        <p><?= nl2br(htmlspecialchars((string)$r['comment'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <?php if (!$given): ?>
            <div class="card">No given reviews yet.</div>
        <?php else: ?>
            <?php foreach ($given as $r): ?>
                <div class="review-item">
                    <strong>⭐ <?= (int)$r['rating'] ?>/5</strong>
                    <div class="meta">To <?= htmlspecialchars((string)$r['reviewee_username']) ?> · Trade #<?= (int)$r['trade_id'] ?> · <?= htmlspecialchars((string)$r['created_at']) ?></div>
                    <?php if (!empty($r['comment'])): ?>
                        <p><?= nl2br(htmlspecialchars((string)$r['comment'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
