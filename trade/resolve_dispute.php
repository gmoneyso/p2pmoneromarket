<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$tradeId = (int)($_POST['trade_id'] ?? 0);
$winner = (string)($_POST['winner'] ?? '');

if ($tradeId <= 0) {
    flash_set('error', 'Invalid dispute resolution request.');
    header('Location: /trade/disputes.php');
    exit;
}

if (!trade_is_moderator($pdo, $userId)) {
    flash_set('error', 'Moderator access required.');
    header("Location: /trade/view.php?id={$tradeId}");
    exit;
}

$pdo->beginTransaction();

try {
    $trade = trade_load_by_id($pdo, $tradeId, true);
    if (!$trade) {
        throw new RuntimeException('Trade not found');
    }

    trade_resolve_dispute($pdo, $trade, $userId, $winner);

    $pdo->commit();
    flash_set('success', 'Dispute resolved.');
    header("Location: /trade/view.php?id={$tradeId}");
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', 'Unable to resolve dispute right now.');
    header("Location: /trade/view.php?id={$tradeId}");
    exit;
}
