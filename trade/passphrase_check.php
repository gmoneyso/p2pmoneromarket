<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';

require_login();
header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];
$mode = (string)($_POST['mode'] ?? '');
$passphrase = (string)($_POST['passphrase'] ?? '');

try {
    $ownerId = 0;

    if ($mode === 'start') {
        $listingId = (int)($_POST['listing_id'] ?? 0);
        if ($listingId <= 0) {
            throw new RuntimeException('Invalid listing');
        }

        $stmt = $pdo->prepare("SELECT id, user_id, type, status FROM listings WHERE id = ? LIMIT 1");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$listing || (string)$listing['status'] !== 'active') {
            throw new RuntimeException('Listing unavailable');
        }

        if ((string)$listing['type'] === 'sell') {
            $ownerId = (int)$listing['user_id'];
        } else {
            $ownerId = $userId;
        }

        if ($ownerId <= 0) {
            throw new RuntimeException('Invalid owner');
        }
    } elseif ($mode === 'release') {
        $tradeId = (int)($_POST['trade_id'] ?? 0);
        if ($tradeId <= 0) {
            throw new RuntimeException('Invalid trade');
        }

        $trade = trade_load_by_id($pdo, $tradeId, false);
        if (!$trade) {
            throw new RuntimeException('Trade not found');
        }

        if ((int)$trade['seller_id'] !== $userId) {
            throw new RuntimeException('Not authorized');
        }

        $ownerId = (int)$trade['seller_id'];
    } else {
        throw new RuntimeException('Invalid mode');
    }

    $ok = trade_verify_passphrase($pdo, $ownerId, $passphrase);

    echo json_encode([
        'ok' => $ok,
        'message' => $ok ? 'Passphrase verified.' : 'Wrong passphrase. Try again.',
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}
