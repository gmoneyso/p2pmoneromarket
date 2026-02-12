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

if (!$user || (int)$user['backup_completed'] === 1) {
    header('Location: /dashboard.php');
    exit;
}

/* -----------------------------
 * Paths
 * ----------------------------- */
$username = $user['username'];
$baseDir  = '/var/www/moneromarket/backup/temp';
$userDir  = $baseDir . '/' . $username;

$passFile   = $userDir . '/pass.txt';
$backupFile = $userDir . '/' . $username . '_backup.txt';
$pubFile    = $userDir . '/public_key.txt';

/* -----------------------------
 * Validate files exist
 * ----------------------------- */
if (!file_exists($passFile) || !file_exists($backupFile) || !file_exists($pubFile)) {
    die('Required backup files missing. Please retry key generation.');
}

/* -----------------------------
 * POST handler
 * ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['confirm_download']) || $_POST['confirm_download'] !== '1') {
        $error = "You must confirm the download before completing.";
    } else {
        $passphrase = trim(file_get_contents($passFile));
        $publicKey  = trim(file_get_contents($pubFile));

        if ($passphrase === '' || $publicKey === '') {
            die("Passphrase or public key missing.");
        }

        $passHash = password_hash($passphrase, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE users
            SET recovery_code_hash = :passHash,
                pgp_public        = :pubKey,
                backup_completed  = 1
            WHERE id = :uid
        ");

        $stmt->execute([
            ':passHash' => $passHash,
            ':pubKey'   => $publicKey,
            ':uid'      => $user['id']
        ]);

        @unlink($passFile);
        @unlink($pubFile);
        @unlink($backupFile);

        header('Location: /dashboard.php');
        exit;
    }
}

$passphraseDisplay = htmlspecialchars(file_get_contents($passFile));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Download Backup | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="/assets/global.css">
<script src="/assets/app.js" defer></script>

<style>
.backup-wrap {
    max-width: 520px;
    margin: 80px auto;
}

.pass-box {
    background: #0d0d0d;
    border: 1px solid #1f1f1f;
    border-radius: 8px;
    padding: 14px;
    font-family: monospace;
    font-size: 0.95rem;
    color: #eaeaea;
    word-break: break-word;
    cursor: pointer;
}

.pass-box.copied {
    border-color: #2ecc71;
}

.warn {
    color: #c0392b;
    font-size: 0.85rem;
    margin-top: 10px;
}

.confirm {
    margin-top: 18px;
    font-size: 0.85rem;
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
</head>

<body>

<div class="container backup-wrap card">

    <h1>Recovery Backup</h1>

    <p class="note">
        This is your <strong>only recovery method</strong>.<br>
        Store it offline and never share it.
    </p>

    <label>Recovery Passphrase</label>
    <div id="passBox" class="pass-box" onclick="copyPass()">
        <?= $passphraseDisplay ?>
    </div>
    <small class="note">Tap to copy exactly as shown (no formatting changes).</small>

    <div style="margin-top:22px;">
        <a href="/backup/temp/<?= urlencode($username) ?>/<?= urlencode($username) ?>_backup.txt"
           class="btn"
           download>
            Download Encrypted Backup
        </a>
    </div>

    <p class="warn">
        ⚠ If you lose this file or passphrase, your account cannot be recovered.
    </p>

    <form method="post" class="confirm">
        <label>
            <input type="checkbox" name="confirm_download" value="1" id="confirm-checkbox">
            I have downloaded and securely stored my backup
        </label>

        <div style="margin-top:18px;">
            <button type="submit" id="complete-btn" disabled>
                Finish & Unlock Account
            </button>
        </div>
    </form>

    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

</div>

<script>
function copyPass() {
    const box = document.getElementById('passBox');
    navigator.clipboard.writeText(box.textContent.trim()).then(() => {
        box.classList.add('copied');
        setTimeout(() => box.classList.remove('copied'), 800);
    });
}

const checkbox = document.getElementById('confirm-checkbox');
const button   = document.getElementById('complete-btn');

checkbox.addEventListener('change', () => {
    button.disabled = !checkbox.checked;
});
</script>

</body>
</html>
