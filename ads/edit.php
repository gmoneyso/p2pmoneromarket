<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../ads/fetch_listings.php';
require_once __DIR__ . '/../config/supported_coins_ade.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$adId   = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($adId <= 0) {
    flash_set('error', 'Ad not found or not yours.');
    header('Location: /userads.php');
    exit;
}

/* Find ad */
$ad = null;
foreach ($ads as $a) {
    if ((int)$a['id'] === $adId && (int)$a['user_id'] === $userId) {
        $ad = $a;
        break;
    }
}

if (!$ad) {
    flash_set('error', 'Ad not found or not yours.');
    header('Location: /userads.php');
    exit;
}

$coins = $SUPPORTED_COINS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit <?= ucfirst($ad['type']) ?> Ad</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="/assets/global.css">
<link rel="stylesheet" href="/assets/ad_edit.css">

<script src="/assets/ad_preview.js" defer></script>
</head>
<body>

<?php require __DIR__ . '/../assets/header.php'; ?>

<div class="edit-ad-container card">

    <h2 class="edit-ad-title">
        Edit <?= ucfirst($ad['type']) ?> Ad
    </h2>

    <?php
    $action = '/ads/edit_submit.php';
    $mode   = 'edit';
    include __DIR__ . '/partials/form_edit.php';
    ?>

</div>

</body>
</html>
