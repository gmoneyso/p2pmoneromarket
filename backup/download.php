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
 * POST handler for "Complete"
 * ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['confirm_download']) || $_POST['confirm_download'] !== '1') {
        $error = "You must confirm the download before completing.";
    } else {
        // Read passphrase and public key
        $passphrase = trim(file_get_contents($passFile));
        $publicKey  = trim(file_get_contents($pubFile));

        if ($passphrase === '' || $publicKey === '') {
            die("Passphrase or public key missing.");
        }

        // Hash passphrase
        $passHash = password_hash($passphrase, PASSWORD_DEFAULT);

        // Update database
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

        // Delete temp files
        @unlink($passFile);
        @unlink($pubFile);
        @unlink($backupFile);

        // Redirect to unlocked dashboard
        header('Location: /dashboard.php');
        exit;
    }
}

/* -----------------------------
 * Read passphrase for display
 * ----------------------------- */
$passphraseDisplay = htmlspecialchars(file_get_contents($passFile));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Download Backup | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/dashboard.css">
<style>
textarea {
    width: 100%;
    font-family: monospace;
    font-size: 1rem;
    padding: 10px;
    box-sizing: border-box;
    resize: none;
}
button:disabled {
    background: #aaa;
    cursor: not-allowed;
}
.success-msg {
    color: green;
    font-weight: bold;
    margin-top: 10px;
}
.error-msg {
    color: red;
    font-weight: bold;
    margin-top: 10px;
}
</style>
</head>
<body>

<?php include __DIR__ . '/../partials/header.php'; ?>
<?php include __DIR__ . '/../partials/sidebar.php'; ?>

<main class="dashboard">

<section class="card info">
    <h3>Download Your Backup</h3>
    <p>
        Please copy your passphrase below and securely store it.  
        You will also download your encrypted backup file.
    </p>

    <label for="passphrase">Passphrase (copy & save):</label>
    <textarea id="passphrase" rows="3" readonly><?= $passphraseDisplay ?></textarea>

    <p>
        <a href="/backup/temp/<?= urlencode($username) ?>/<?= urlencode($username) ?>_backup.txt" download>
            <button type="button">Download Backup File</button>
        </a>
    </p>

    <form method="post">
        <label>
            <input type="checkbox" name="confirm_download" value="1" id="confirm-checkbox">
            I have downloaded and securely saved my backup file
        </label>
        <br><br>
        <button type="submit" id="complete-btn" disabled>Complete</button>
    </form>

    <?php if (!empty($error)): ?>
        <p class="error-msg"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
</section>

<script>
const checkbox = document.getElementById('confirm-checkbox');
const button = document.getElementById('complete-btn');

checkbox.addEventListener('change', function() {
    button.disabled = !this.checked;
});
</script>

</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
