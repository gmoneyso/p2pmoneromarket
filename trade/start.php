<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_login();

require_once __DIR__ . '/start_ad.php';

$ad_id = (int)($_GET['ad_id'] ?? 0);

if (!$ad_id) {
    http_response_code(400);
    exit('Invalid trade request');
}

$ad = fetch_trade_ad($pdo, $ad_id);
if (!$ad) {
    http_response_code(404);
    exit('Ad not found or inactive');
}

if ((int)$ad['user_id'] === (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit('You cannot trade with your own ad');
}

$role = $ad['type'] === 'sell' ? 'buyer' : 'seller';

require_once __DIR__ . '/start_view.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Start Trade | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<link rel="stylesheet" href="/assets/dashboard.css">
<link rel="stylesheet" href="/trade/trade.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>

<main class="dashboard trade-page-main">
    <section class="card trade-shell">
        <div class="trade-shell-head">
            <h1>Start Trade</h1>
            <a class="trade-link" href="/trade/list.php">My Trades</a>
        </div>

        <?php render_trade_start_view($ad, $role); ?>
    </section>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
