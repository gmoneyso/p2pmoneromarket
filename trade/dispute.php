<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$tradeId = (int)($_POST['trade_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));

if ($tradeId <= 0) {
    flash_set('error', 'Invalid trade request.');
    header('Location: /trade/list.php');
    exit;
}

$pdo->beginTransaction();

try {
    $trade = trade_load_by_id($pdo, $tradeId, true);

    if (!$trade) {
        throw new RuntimeException('Trade not found');
    }

    $role = trade_role_for_user($trade, $userId);
    if ($role === null) {
        throw new RuntimeException('Not your trade');
    }

    if ($trade['status'] !== TRADE_STATUS_PAID) {
        throw new RuntimeException('Only paid trades can be disputed');
    }

    trade_open_dispute($pdo, $trade, $userId, $reason);

    $pdo->commit();

    trade_notify_participants(
        $pdo,
        $trade,
        'trade_disputed',
        'Trade dispute opened',
        sprintf('Trade #%d has been disputed and is awaiting moderator review.', $tradeId),
        $userId
    );
    trade_notify_moderators(
        $pdo,
        'trade_dispute_queue',
        'New dispute requires review',
        sprintf('Trade #%d was disputed. Open dispute queue to review.', $tradeId),
        $tradeId
    );

    header("Location: /trade/view.php?id={$tradeId}");
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', 'Unable to open dispute for this trade.');
    header("Location: /trade/view.php?id={$tradeId}");
    exit;
}
