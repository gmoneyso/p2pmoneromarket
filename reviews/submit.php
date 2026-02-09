<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$tradeId = (int)($_POST['trade_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim((string)($_POST['comment'] ?? ''));

if ($tradeId <= 0) {
    http_response_code(400);
    exit('Invalid trade');
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    exit('Invalid rating');
}

if (strlen($comment) > 1000) {
    http_response_code(400);
    exit('Comment too long');
}

$pdo->beginTransaction();

try {
    $trade = review_load_trade($pdo, $tradeId);
    if (!$trade) {
        throw new RuntimeException('Trade not found');
    }

    [$canSubmit, $reason, $revieweeId] = review_can_submit($pdo, $trade, $userId);
    if (!$canSubmit || $revieweeId === null) {
        throw new RuntimeException($reason !== '' ? $reason : 'Cannot submit review');
    }

    $stmt = $pdo->prepare("\n        INSERT INTO reviews (trade_id, reviewer_id, reviewee_id, rating, comment)\n        VALUES (?, ?, ?, ?, ?)\n    ");
    $stmt->execute([
        $tradeId,
        $userId,
        $revieweeId,
        $rating,
        $comment !== '' ? $comment : null,
    ]);

    $pdo->commit();
    header('Location: /dashboard.php');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    exit($e->getMessage());
}
