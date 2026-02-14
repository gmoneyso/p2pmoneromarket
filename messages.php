<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db/database.php';
require_once __DIR__ . '/messages/helpers.php';
require_once __DIR__ . '/includes/flash.php';

require_login();

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id, username, pgp_public, backup_completed FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$currentUser) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

if ((int)$currentUser['backup_completed'] !== 1) {
    flash_set('error', 'Complete backup setup to use secure messages.');
    header('Location: /dashboard.php');
    exit;
}

$isUnlocked = messages_is_unlocked($pdo, $userId);

$targetUserId = (int)($_GET['user_id'] ?? 0);
$tradeId = (int)($_GET['trade_id'] ?? 0);
$threadId = (int)($_GET['thread_id'] ?? 0);

if ($threadId <= 0 && $targetUserId > 0 && $targetUserId !== $userId) {
    $stmt = $pdo->prepare('SELECT id, backup_completed FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$targetUser || (int)$targetUser['backup_completed'] !== 1) {
        flash_set('error', 'This user is not available for secure messaging yet.');
        header('Location: /messages.php');
        exit;
    }

    $threadId = messages_get_or_create_thread($pdo, $userId, $targetUserId, $tradeId > 0 ? $tradeId : null);
    header('Location: /messages.php?thread_id=' . $threadId);
    exit;
}

$threads = messages_fetch_threads($pdo, $userId);

$activeThread = null;
foreach ($threads as $t) {
    if ((int)$t['id'] === $threadId) {
        $activeThread = $t;
        break;
    }
}

$messages = [];
if ($activeThread && $isUnlocked) {
    $messages = messages_fetch_thread_messages($pdo, (int)$activeThread['id'], $userId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<style>
.messages-wrap { max-width: 1100px; margin: 24px auto; display:grid; grid-template-columns: 320px 1fr; gap: 12px; }
.messages-sidebar .thread-link { display:block; padding:10px; border:1px solid #2a2a2a; border-radius:8px; margin-bottom:8px; text-decoration:none; color:inherit; }
.messages-sidebar .thread-link.active { border-color:#ff6600; background:#171717; }
.msg-bubble { padding:10px 12px; border-radius:10px; margin-bottom:8px; border:1px solid #2a2a2a; }
.msg-bubble.mine { background:#152012; border-color:#2d5f2a; }
.msg-bubble.theirs { background:#131a22; border-color:#274a66; }
.msg-meta { font-size:.75rem; color:#aaa; margin-top:4px; }
.msg-compose { margin-top:12px; display:grid; gap:8px; }
.msg-compose textarea { min-height: 90px; }
.unlock-box { max-width: 520px; margin: 24px auto; }
@media (max-width: 860px){ .messages-wrap{ grid-template-columns: 1fr; } }
</style>
</head>
<body>
<?php require __DIR__ . '/assets/header.php'; ?>

<?php if (!$isUnlocked): ?>
    <div class="container unlock-box card">
        <h2>Unlock Secure Messages</h2>
        <p class="note" style="text-align:left;">Enter your backup passphrase to unlock message encryption/decryption for 72 hours.</p>
        <form method="post" action="/messages/unlock.php" class="msg-compose">
            <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/messages.php') ?>">
            <label>Backup passphrase
                <input type="password" name="passphrase" required autocomplete="off">
            </label>
            <button class="btn" type="submit">Unlock Messages (72h)</button>
        </form>
    </div>
<?php else: ?>
    <div class="container messages-wrap">
        <aside class="card messages-sidebar">
            <h3>Conversations</h3>
            <form method="post" action="/messages/lock.php" style="margin-bottom:10px;">
                <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/messages.php') ?>">
                <button type="submit" class="btn">Lock messages now</button>
            </form>
            <?php if (!$threads): ?>
                <p class="note" style="text-align:left;">No conversations yet.</p>
            <?php else: ?>
                <?php foreach ($threads as $t): ?>
                    <a class="thread-link <?= (int)$t['id'] === $threadId ? 'active' : '' ?>" href="/messages.php?thread_id=<?= (int)$t['id'] ?>">
                        <strong><?= htmlspecialchars((string)$t['counterparty_username']) ?></strong>
                        <?php if (!empty($t['trade_id'])): ?>
                            <div class="msg-meta">Trade #<?= (int)$t['trade_id'] ?></div>
                        <?php endif; ?>
                        <div class="msg-meta">Updated <?= htmlspecialchars((string)$t['updated_at']) ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </aside>

        <section class="card">
            <?php if (!$activeThread): ?>
                <h3>Select a conversation</h3>
                <p class="note" style="text-align:left;">Open a profile or trade and click Message to start.</p>
            <?php else: ?>
                <h3>Chat with <?= htmlspecialchars((string)$activeThread['counterparty_username']) ?></h3>
                <div>
                    <?php foreach ($messages as $m): ?>
                        <?php
                            $mine = (int)$m['sender_id'] === $userId;
                            $cipher = $mine ? (string)$m['ciphertext_sender'] : (string)$m['ciphertext_recipient'];
                            $plain = messages_decrypt_for_user($currentUser, $cipher, (string)($_SESSION['messages_unlock_passphrase'] ?? ''));
                        ?>
                        <div class="msg-bubble <?= $mine ? 'mine' : 'theirs' ?>">
                            <?= nl2br(htmlspecialchars($plain ?? '[Unable to decrypt with current key/passphrase]')) ?>
                            <div class="msg-meta"><?= htmlspecialchars((string)$m['created_at']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form class="msg-compose" method="post" action="/messages/send.php">
                    <input type="hidden" name="thread_id" value="<?= (int)$activeThread['id'] ?>">
                    <input type="hidden" name="return" value="/messages.php?thread_id=<?= (int)$activeThread['id'] ?>">
                    <label>Message
                        <textarea name="body" maxlength="4000" required></textarea>
                    </label>
                    <button type="submit" class="btn">Send</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>

</body>
</html>
