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
<style>
    .review-form {
        display: grid;
        gap: 12px;
    }

    .star-rating-wrap {
        display: grid;
        gap: 8px;
    }

    .star-rating {
        display: inline-flex;
        gap: 6px;
        user-select: none;
        touch-action: pan-y;
    }

    .star-rating button {
        border: 0;
        background: transparent;
        padding: 2px;
        margin: 0;
        cursor: pointer;
        font-size: 2rem;
        line-height: 1;
        color: #7f8796;
        transition: transform .12s ease, color .12s ease;
    }

    .star-rating button:hover,
    .star-rating button:focus-visible {
        transform: translateY(-1px) scale(1.03);
        outline: none;
    }

    .star-rating button.selected {
        color: #f6c945;
        text-shadow: 0 0 10px rgba(246, 201, 69, .25);
    }

    .star-rating-hint {
        font-size: .98rem;
        font-weight: 700;
        text-align: center;
        letter-spacing: .02em;
        color: #f1f1f1;
        min-height: 1.2em;
    }
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>

<div class="container" style="max-width:700px; margin:30px auto;">
    <div class="card">
        <h2>Trade #<?= (int)$tradeId ?> Review</h2>

        <?php if ($alreadyReviewed): ?>
            <p>You already submitted a review for this trade.</p>
            <p><a class="btn" href="/dashboard.php">Go to Dashboard</a>
                <a class="btn" href="/reviews/index.php">My Reviews</a></p>

        <?php elseif (!$canSubmit): ?>
            <p><?= htmlspecialchars($reason) ?></p>
            <p>
                <a class="btn" href="/trade/view.php?id=<?= (int)$tradeId ?>">Back to Trade</a>
                <a class="btn" href="/dashboard.php">Go to Dashboard</a>
                <a class="btn" href="/reviews/index.php">My Reviews</a>
            </p>

        <?php else: ?>
            <p>Share your experience with this counterparty. This is optional.</p>
            <form method="post" action="/reviews/submit.php" class="review-form">
                <input type="hidden" name="trade_id" value="<?= (int)$tradeId ?>">
                <input type="hidden" id="rating" name="rating" required>

                <div class="star-rating-wrap">
                    <label>Rating (1 to 5)</label>
                    <div class="star-rating" id="starRating" role="radiogroup" aria-label="Review rating">
                        <button type="button" data-value="1" role="radio" aria-checked="false" aria-label="1 star">★</button>
                        <button type="button" data-value="2" role="radio" aria-checked="false" aria-label="2 stars">★</button>
                        <button type="button" data-value="3" role="radio" aria-checked="false" aria-label="3 stars">★</button>
                        <button type="button" data-value="4" role="radio" aria-checked="false" aria-label="4 stars">★</button>
                        <button type="button" data-value="5" role="radio" aria-checked="false" aria-label="5 stars">★</button>
                    </div>
                    <div class="star-rating-hint" id="ratingHint">Select a star rating.</div>
                </div>

                <label for="comment">Comment (optional)</label>
                <textarea id="comment" name="comment" rows="4" maxlength="1000"></textarea>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn">Submit Review</button>
                    <a class="btn" href="/dashboard.php">Skip & Go to Dashboard</a>
                    <a class="btn" href="/reviews/index.php">My Reviews</a>
                </div>
            </form>
            <script>
                (() => {
                    const starWrap = document.getElementById('starRating');
                    const stars = Array.from(starWrap.querySelectorAll('button[data-value]'));
                    const hidden = document.getElementById('rating');
                    const hint = document.getElementById('ratingHint');
                    const labels = {
                        1: 'Bad',
                        2: 'Poor',
                        3: 'Neutral',
                        4: 'Good',
                        5: 'Excellent',
                    };
                    let selected = 0;
                    let dragging = false;

                    const paint = (value) => {
                        stars.forEach((star, idx) => {
                            const isOn = idx < value;
                            star.classList.toggle('selected', isOn);
                            star.setAttribute('aria-checked', String(Number(idx + 1 === value)));
                        });
                        hint.textContent = value > 0 ? labels[value] : 'Select a star rating.';
                    };

                    const setValue = (value) => {
                        selected = value;
                        hidden.value = value > 0 ? String(value) : '';
                        paint(value);
                    };

                    const valueFromPoint = (x, y) => {
                        const target = document.elementFromPoint(x, y);
                        const btn = target ? target.closest('#starRating button[data-value]') : null;
                        return btn ? Number(btn.dataset.value || 0) : 0;
                    };

                    stars.forEach((star) => {
                        star.addEventListener('click', () => {
                            const value = Number(star.dataset.value || 0);
                            setValue(selected === value ? 0 : value);
                        });

                        star.addEventListener('pointerdown', (e) => {
                            dragging = true;
                            starWrap.setPointerCapture(e.pointerId);
                            const value = valueFromPoint(e.clientX, e.clientY);
                            if (value) setValue(value);
                        });

                        star.addEventListener('pointermove', (e) => {
                            if (!dragging) return;
                            const value = valueFromPoint(e.clientX, e.clientY);
                            if (value) setValue(value);
                        });

                        star.addEventListener('pointerup', (e) => {
                            dragging = false;
                            starWrap.releasePointerCapture(e.pointerId);
                        });
                    });

                    setValue(0);
                })();
            </script>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
