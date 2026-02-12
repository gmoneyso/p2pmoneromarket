<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/user.php';

require_login();

$user = get_current_user_data((int)$_SESSION['user_id'], $pdo);
if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = (string)($_POST['confirm_recovery'] ?? '');
    if ($confirm !== '1') {
        $error = 'You must confirm reset to continue.';
    } else {
        $stmt = $pdo->prepare("\n            UPDATE users\n            SET pgp_public = NULL,\n                recovery_code_hash = NULL,\n                backup_completed = 0\n            WHERE id = :uid\n        ");
        $stmt->execute([':uid' => (int)$user['id']]);

        header('Location: /backup/start.php?recovery=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Recover Backup | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/global.css">
<style>
.recovery-wrap { max-width: 560px; margin: 80px auto; }
.recovery-warning {
    border: 1px solid #5c2c17;
    background: #1a120d;
    color: #f6d1ba;
    border-radius: 8px;
    padding: 12px;
    margin: 14px 0;
}
.recovery-actions { display: flex; gap: 8px; margin-top: 18px; flex-direction: column; }
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>
<div class="container recovery-wrap">
    <h1>Backup Recovery</h1>
    <p class="note">Logged in as <strong><?= htmlspecialchars((string)$user['username']) ?></strong>.</p>

    <div class="recovery-warning">
        This will rotate your backup package. Existing backup passphrase and public key will be replaced.
        You must generate and download a new backup before trading again.
    </div>

    <?php if ($error !== ''): ?>
        <p class="note" style="color:#ff8a80;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>
            <input type="checkbox" name="confirm_recovery" value="1" required>
            I understand my previous backup passphrase and key package will be replaced.
        </label>

        <div class="recovery-actions">
            <button type="submit">Reset Backup and Generate New Keys</button>
            <a href="/dashboard.php" class="btn">Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
