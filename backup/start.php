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
    header('Location: /login.php');
    exit;
}

/* -----------------------------
 * Backup already completed?
 * ----------------------------- */
if ((int)$user['backup_completed'] === 1) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Backup Keys | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="/assets/global.css">
<script src="/assets/app.js" defer></script>

<style>
.backup-wrap {
    max-width: 420px;
    margin: 80px auto;
}

.loader {
    width: 36px;
    height: 36px;
    border: 3px solid #222;
    border-top: 3px solid var(--accent);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 24px auto;
    display: none;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.status-text {
    text-align: center;
    font-size: 0.9rem;
    color: #aaa;
    margin-top: 12px;
}

.success {
    color: #2ecc71;
    text-align: center;
    font-size: 0.95rem;
    margin-top: 16px;
    display: none;
}
</style>
</head>

<body>

<div class="container backup-wrap">

    <div class="card">

        <h1>Secure Backup</h1>

        <p class="note">
            You are about to generate your recovery keys.<br>
            Store them securely. They cannot be recovered if lost.
        </p>

        <button class="btn" id="startBackup">
            Generate Backup Keys
        </button>

        <div class="loader" id="loader"></div>

        <div class="status-text" id="statusText"></div>
        <div class="success" id="successMsg">
            Keys generated successfully ✓
        </div>

    </div>

</div>

<script>
const btn = document.getElementById('startBackup');
const loader = document.getElementById('loader');
const statusText = document.getElementById('statusText');
const successMsg = document.getElementById('successMsg');

btn.addEventListener('click', () => {
    btn.disabled = true;
    btn.textContent = 'Generating…';
    loader.style.display = 'block';
    statusText.textContent = 'Generating secure keys…';

    fetch('/backup/passphrase_gen.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(res => {
        if (!res || res.status !== 'ok') {
            throw new Error('Key generation failed');
        }

        loader.style.display = 'none';
        statusText.textContent = '';
        successMsg.style.display = 'block';

        setTimeout(() => {
            window.location.href = '/backup/download.php';
        }, 1400);
    })
    .catch(() => {
        loader.style.display = 'none';
        btn.disabled = false;
        btn.textContent = 'Generate Backup Keys';
        statusText.textContent = 'Something went wrong. Please try again.';
    });
});
</script>

</body>
</html>
