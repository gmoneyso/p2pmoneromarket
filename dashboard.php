<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db/database.php';
require_once __DIR__ . '/includes/user.php';

/* ---------------------------------
 * Auth guard
 * --------------------------------- */
require_login();

/* ---------------------------------
 * Load user
 * --------------------------------- */
$user = get_current_user_data($_SESSION['user_id'], $pdo);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/* ---------------------------------
 * Dashboard state
 * ---------------------------------
 * backup_completed = 0 → keys NOT generated / not confirmed
 * backup_completed = 1 → keys generated & backup confirmed
 */
$backup_completed = ((int)$user['backup_completed'] === 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/dashboard.css">
</head>

<body>

<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="dashboard">

    <!-- Always visible -->
    <?php include __DIR__ . '/modules/welcome.php'; ?>

    <?php if (!$backup_completed): ?>

        <!-- User has NO keys yet -->
        <?php include __DIR__ . '/modules/backup_required.php'; ?>

    <?php else: ?>

        <!-- Full dashboard unlocked -->

        <?php include __DIR__ . '/modules/topbar.php'; ?>
        <?php include __DIR__ . '/modules/notifications_preview.php'; ?>
        <?php include __DIR__ . '/modules/balance.php'; ?>
        <?php include __DIR__ . '/modules/subaddresses.php'; ?>
        <?php include __DIR__ . '/modules/reviews.php'; ?>

        <!-- Transactions (tabbed) -->
        <?php include __DIR__ . '/modules/transactions.php'; ?>

    <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
