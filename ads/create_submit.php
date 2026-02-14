<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/lib/ad_validator.php';
require_once __DIR__ . '/lib/ad_creator.php';
require_once __DIR__ . '/../includes/flash.php';

$type = $_POST['type'] ?? '';

try {

    if ($type === 'buy') {
        create_ad($_POST, $pdo, 'buy');

    } elseif ($type === 'sell') {
        create_ad($_POST, $pdo, 'sell');

    } else {
        throw new Exception('Invalid ad type');
    }

    flash_set('success', 'Ad created successfully.');
    header('Location: /dashboard.php');
    exit;

} catch (Exception $e) {
    flash_set('error', 'Could not create ad. Please review your inputs.');
    header('Location: /ads/create.php');
    exit;
}
