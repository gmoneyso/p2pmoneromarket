<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/user.php';

/* -----------------------------
 * Auth guard
 * ----------------------------- */
require_login();

$user = get_current_user_data($_SESSION['user_id'], $pdo);

if (!$user) {
    session_destroy();
    header('Location: /login.html');
    exit;
}

/* -----------------------------
 * Backup already completed?
 * ----------------------------- */
if ((int)$user['backup_completed'] === 1) {
    header('Location: /dashboard.php');
    exit;
}

/* -----------------------------
 * Prepare temp workspace
 * ----------------------------- */
$username = $user['username'];
$baseTmp  = '/var/www/moneromarket/backup/temp';
$userTmp  = $baseTmp . '/' . $username;

if (!is_dir($userTmp)) {
    mkdir($userTmp, 0700, true);
}
chmod($userTmp, 0700);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generating Keys | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/loader.css">
<style>
.success {
    color: #4caf50;
    font-weight: bold;
    margin-top: 15px;
}
.error {
    color: #f44336;
    font-weight: bold;
    margin-top: 15px;
}
.hidden {
    display: none;
}
</style>
</head>

<body>

<section class="loader-card">
    <h2>Generating Your Backup Keys</h2>

    <p id="status-text">
        Please wait while your cryptographic keys are being created.
        This may take up to 30 seconds.
    </p>

    <div class="loader" id="spinner"></div>

    <p class="note">
        Do not refresh or close this page.
    </p>

    <p id="success-msg" class="success hidden">
        ✔ Keys generated successfully. Preparing download…
    </p>

    <p id="error-msg" class="error hidden">
        ✖ Key generation failed. Please contact support.
    </p>
</section>

<script>
(function () {
    fetch('/backup/passphrase_gen.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(resp => resp.json())
    .then(data => {
        const spinner = document.getElementById('spinner');
        const status  = document.getElementById('status-text');
        const success = document.getElementById('success-msg');
        const error   = document.getElementById('error-msg');

        spinner.classList.add('hidden');

        if (data.status === 'ok') {
            status.textContent = 'Key generation completed.';
            success.classList.remove('hidden');

            setTimeout(() => {
                window.location.href = '/backup/download.php';
            }, 1500);

        } else {
            status.textContent = 'An error occurred.';
            error.classList.remove('hidden');
        }
    })
    .catch(() => {
        document.getElementById('spinner').classList.add('hidden');
        document.getElementById('status-text').textContent = 'Network error.';
        document.getElementById('error-msg').classList.remove('hidden');
    });
})();
</script>

</body>
</html>
