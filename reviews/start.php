<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$tradeId = (int)($_GET['trade_id'] ?? 0);

if ($tradeId <= 0) {
    http_response_code(400);
    exit('Invalid trade');
}

$trade = review_load_trade($pdo, $tradeId);
if (!$trade) {
    http_response_code(404);
    exit('Trade not found');
}

[$canSubmit, $reason, $revieweeId] = review_can_submit($pdo, $trade, $userId);
$alreadyReviewed = review_user_has_review($pdo, $tradeId, $userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leave Review | Trade #<?= (int)$tradeId ?></title>
<link rel="stylesheet" href="/assets/global.css">
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>

<div class="container" style="max-width:700px; margin:30px auto;">
    <div class="card">
        <h2>Trade #<?= (int)$tradeId ?> Review</h2>

        <?php if ($alreadyReviewed): ?>
            <p>You already submitted a review for this trade.</p>
            <p><a class="btn" href="/dashboard.php">Go to Dashboard</a></p>

        <?php elseif (!$canSubmit): ?>
            <p><?= htmlspecialchars($reason) ?></p>
            <p>
                <a class="btn" href="/trade/view.php?id=<?= (int)$tradeId ?>">Back to Trade</a>
                <a class="btn" href="/dashboard.php">Go to Dashboard</a>
            </p>

        <?php else: ?>
            <p>Share your experience with this counterparty. This is optional.</p>
            <form method="post" action="/reviews/submit.php" style="display:grid; gap:10px;">
                <input type="hidden" name="trade_id" value="<?= (int)$tradeId ?>">

                <label for="rating">Rating (1 to 5)</label>
                <select id="rating" name="rating" required>
                    <option value="">Select rating</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Good</option>
                    <option value="3">3 - Neutral</option>
                    <option value="2">2 - Poor</option>
                    <option value="1">1 - Bad</option>
                </select>

                <label for="comment">Comment (optional)</label>
                <textarea id="comment" name="comment" rows="4" maxlength="1000"></textarea>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn">Submit Review</button>
                    <a class="btn" href="/dashboard.php">Skip & Go to Dashboard</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
