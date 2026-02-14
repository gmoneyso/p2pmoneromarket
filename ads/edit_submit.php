<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../includes/csrf.php';
csrf_verify(); // ✅ correct function

require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/lib/ad_updater.php';
require_once __DIR__ . '/../includes/flash.php';

$adId = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';

if ($adId <= 0 || !in_array($type, ['buy', 'sell'], true)) {
    flash_set('error', 'Could not update ad. Please review your inputs.');
    header('Location: /userads.php');
    exit;
}

try {
    update_ad($adId, $_POST, $pdo, $type);
    flash_set('success', 'Ad updated successfully.');
    header('Location: /userads.php?updated=1');
    exit;
} catch (Throwable $e) {
    flash_set('error', 'Could not update ad. Please review your inputs.');
    header('Location: /ads/edit.php?id=' . $adId);
    exit;
}
