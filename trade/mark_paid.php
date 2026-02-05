<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_login();

$userId  = (int)$_SESSION['user_id'];
$tradeId = (int)($_POST['trade_id'] ?? 0);

if (!$tradeId) {
    http_response_code(400);
    exit('Invalid trade');
}

$pdo->beginTransaction();

try {
    /* Lock trade */
    $stmt = $pdo->prepare("
        SELECT *
        FROM trades
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$tradeId]);
    $trade = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trade) {
        throw new Exception('Trade not found');
    }

    if ((int)$trade['buyer_id'] !== $userId) {
        throw new Exception('Not buyer');
    }

    if ($trade['status'] !== 'pending_payment') {
        throw new Exception('Trade not payable');
    }

    /* Update trade status */
    $stmt = $pdo->prepare("
        UPDATE trades
        SET status = 'paid'
        WHERE id = ?
    ");
    $stmt->execute([$tradeId]);

    $pdo->commit();

    header("Location: /trade/view.php?id={$tradeId}");
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(400);
    exit($e->getMessage());
}
