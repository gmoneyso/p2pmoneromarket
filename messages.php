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

$unlockError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'unlock_thread') {
    $unlockThreadId = (int)($_POST['thread_id'] ?? 0);
    $unlockPassphrase = (string)($_POST['passphrase'] ?? '');

    if ($unlockThreadId <= 0 || trim($unlockPassphrase) === '') {
        $unlockError = 'Passphrase is required.';
        messages_log_error('Unlock validation failed', [
            'user_id' => $userId,
            'thread_id' => $unlockThreadId,
            'reason' => 'missing_passphrase_or_thread',
        ]);
    } elseif (!messages_thread_belongs_to_user($pdo, $unlockThreadId, $userId)) {
        $unlockError = 'Conversation not found.';
        messages_log_error('Unlock rejected for non-owned thread', [
            'user_id' => $userId,
            'thread_id' => $unlockThreadId,
        ]);
    } else {
        $state = messages_get_attempt_state($pdo, $userId);
        if (messages_lock_is_active($state['locked_until'])) {
            $unlockError = 'Too many failed attempts. Try again later.';
            messages_log_error('Unlock blocked by lockout', [
                'user_id' => $userId,
                'thread_id' => $unlockThreadId,
                'locked_until' => $state['locked_until'],
            ]);
        } elseif (!messages_verify_passphrase($pdo, $userId, $unlockPassphrase)) {
            $next = messages_record_failed_attempt($pdo, $userId);
            if (messages_lock_is_active($next['locked_until'] ?? null)) {
                $unlockError = 'Too many failed attempts. Locked for 30 minutes.';
            } else {
                $unlockError = 'Wrong passphrase. Try again.';
            }
            messages_log_error('Unlock passphrase verification failed', [
                'user_id' => $userId,
                'thread_id' => $unlockThreadId,
                'failed_count' => (int)($next['failed_count'] ?? 0),
                'locked_until' => $next['locked_until'] ?? null,
            ]);
        } else {
            messages_reset_attempts($pdo, $userId);
            messages_issue_unlock_session($pdo, $userId, $unlockPassphrase);
            header('Location: /messages.php?thread_id=' . $unlockThreadId);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'lock_thread') {
    messages_revoke_unlock_session($pdo, $userId);
    $lockThreadId = (int)($_POST['thread_id'] ?? 0);
    header('Location: /messages.php' . ($lockThreadId > 0 ? ('?thread_id=' . $lockThreadId) : ''));
    exit;
}

$isUnlocked = messages_is_unlocked($pdo, $userId);
$unlockPassphrase = (string)($_SESSION['messages_unlock_passphrase'] ?? '');

$beforeId = (int)($_GET['before_id'] ?? 0);
if ($beforeId <= 0) {
    $beforeId = null;
}

$messages = [];
$renderedMessages = [];
$hasMore = false;
$nextBeforeId = null;
if ($activeThread) {
    $messages = messages_fetch_thread_messages($pdo, (int)$activeThread['id'], $userId, 9, $beforeId);

    if ($messages === []) {
        messages_log_error('No messages fetched for thread', [
            'user_id' => $userId,
            'thread_id' => (int)$activeThread['id'],
            'before_id' => $beforeId,
            'is_unlocked' => $isUnlocked,
        ]);
    }

    foreach ($messages as $m) {
        $mine = (int)$m['sender_id'] === $userId;
        $cipher = (string)$m['ciphertext'];
        $plain = $isUnlocked ? messages_decrypt_for_user($currentUser, $cipher, $unlockPassphrase) : null;

        $renderedMessages[] = [
            'id' => (int)$m['id'],
            'mine' => $mine,
            'created_at' => (string)$m['created_at'],
            'cipher' => $cipher,
            'plain' => $plain,
        ];
    }

    if ($renderedMessages) {
        $nextBeforeId = (int)$renderedMessages[0]['id'];
        $hasMore = messages_thread_has_older_messages($pdo, (int)$activeThread['id'], $userId, $nextBeforeId);
    }
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
.messages-wrap {
    max-width: 1120px;
    margin: 18px auto;
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 12px;
    align-items: start;
}
.messages-sidebar {
    padding: 12px;
}
.thread-list {
    display: grid;
    gap: 7px;
}
.thread-link {
    display: block;
    padding: 9px 10px;
    border: 1px solid #2a2a2a;
    border-radius: 9px;
    text-decoration: none;
    color: inherit;
    background: #121212;
}
.thread-link.active {
    border-color: #ff6600;
    background: #191919;
}
.thread-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.thread-name {
    font-size: .92rem;
    font-weight: 600;
}
.thread-time {
    font-size: .72rem;
    color: #999;
    white-space: nowrap;
}
.msg-meta {
    font-size: .72rem;
    color: #9b9b9b;
}
.msg-btn-sm {
    width: auto;
    padding: 7px 10px;
    font-size: .78rem;
    font-weight: 600;
    border-radius: 7px;
    border: 1px solid transparent;
    background: #252525;
    color: #ddd;
    cursor: pointer;
}
.msg-btn-sm:hover {
    background: #303030;
}
.msg-btn-sm.msg-btn-accent {
    background: var(--accent);
    color: #000;
}
.msg-btn-sm.msg-btn-accent:hover {
    background: var(--accent-soft);
}
.chat-card {
    padding: 0;
    overflow: hidden;
}
.chat-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border-bottom: 1px solid #242424;
    background: #111;
}
.chat-title {
    margin: 0;
    font-size: .95rem;
    font-weight: 600;
}
.chat-meta {
    margin: 2px 0 0;
    font-size: .72rem;
    color: #9a9a9a;
}
.chat-head-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
.chat-body {
    padding: 12px;
    background: #0f0f0f;
    min-height: 430px;
    max-height: 62vh;
    overflow: auto;
}
.chat-empty {
    color: #9a9a9a;
    font-size: .85rem;
}
.message-row {
    display: flex;
    margin: 0 0 8px;
}
.message-row.mine {
    justify-content: flex-end;
}
.message-row.theirs {
    justify-content: flex-start;
}
.msg-bubble {
    width: fit-content;
    max-width: min(78%, 720px);
    padding: 9px 10px;
    border-radius: 11px;
    border: 1px solid #2a2a2a;
    box-shadow: 0 1px 0 rgba(0,0,0,.15);
}
.msg-bubble.mine {
    background: #15321d;
    border-color: #2c5a39;
}
.msg-bubble.theirs {
    background: #18222f;
    border-color: #2e4b66;
}
.msg-text {
    font-size: .87rem;
    line-height: 1.4;
    white-space: pre-wrap;
    word-break: break-word;
}
.msg-time {
    margin-top: 5px;
    font-size: .69rem;
    color: #9d9d9d;
    text-align: right;
}
.msg-fallback {
    background: #20170f;
    border: 1px solid #4a3620;
    border-radius: 8px;
    padding: 7px;
    margin-top: 6px;
}
.msg-fallback-note {
    margin: 0 0 6px;
    font-size: .73rem;
    color: #e5bf92;
}
.msg-cipher {
    margin: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: .69rem;
    line-height: 1.28;
    max-height: 110px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-all;
    color: #d9d9d9;
}
.msg-fallback-actions {
    margin-top: 7px;
}
.chat-notice {
    margin: 0 0 10px;
    border: 1px solid #524027;
    background: #20180f;
    color: #e9c59a;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: .78rem;
}
.chat-compose {
    border-top: 1px solid #242424;
    background: #101010;
    padding: 10px 12px;
}
.chat-compose-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: end;
}
.chat-compose textarea {
    min-height: 44px;
    max-height: 140px;
    resize: vertical;
    margin: 0;
}

.unlock-wrap {
    max-width: 520px;
    margin: 24px auto;
    padding: 14px;
    border: 1px solid #2a2a2a;
    border-radius: 10px;
    background: #111;
}
.unlock-wrap label {
    display: block;
    font-size: .83rem;
    color: #cfcfcf;
    margin-bottom: 7px;
}
.unlock-input {
    width: 100%;
    background: #0f0f0f;
    border: 1px solid #2e2e2e;
    color: #e6e6e6;
    border-radius: 8px;
    padding: 10px 11px;
    margin: 0 0 10px;
}
.unlock-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(255,102,0,.15);
}

@media (max-width: 960px) {
    .messages-wrap {
        grid-template-columns: 1fr;
    }
    .chat-body {
        min-height: 360px;
        max-height: 56vh;
    }
}
@media (max-width: 640px) {
    .container.messages-wrap {
        margin: 10px auto;
        padding: 10px;
    }
    .messages-sidebar,
    .chat-card {
        padding: 10px;
    }
    .chat-card {
        padding: 0;
    }
    .chat-head,
    .chat-compose {
        padding: 9px 10px;
    }
    .chat-compose-row {
        grid-template-columns: 1fr;
    }
    .msg-bubble {
        max-width: 90%;
    }
}
</style>
</head>
<body>
<?php require __DIR__ . '/assets/header.php'; ?>

<div class="container messages-wrap">
        <aside class="card messages-sidebar">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;">
                <h3 style="margin:0;">Conversations</h3>
                <?php if ($isUnlocked): ?>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="action" value="lock_thread">
                        <input type="hidden" name="thread_id" value="<?= (int)$threadId ?>">
                        <button type="submit" class="msg-btn-sm">Lock</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!$threads): ?>
                <p class="note" style="text-align:left;">No conversations yet.</p>
            <?php else: ?>
                <div class="thread-list">
                    <?php foreach ($threads as $t): ?>
                        <a class="thread-link <?= (int)$t['id'] === $threadId ? 'active' : '' ?>" href="/messages.php?thread_id=<?= (int)$t['id'] ?>">
                            <div class="thread-head">
                                <div class="thread-name"><?= htmlspecialchars((string)$t['counterparty_username']) ?></div>
                                <div class="thread-time"><?= htmlspecialchars((string)$t['updated_at']) ?></div>
                            </div>
                            <?php if (!empty($t['trade_id'])): ?>
                                <div class="msg-meta">Trade #<?= (int)$t['trade_id'] ?></div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <section class="card chat-card">
            <?php if (!$activeThread): ?>
                <div style="padding:14px;">
                    <h3>Select a conversation</h3>
                    <p class="note" style="text-align:left;">Open a profile or trade and click Message to start.</p>
                </div>
            <?php else: ?>
                <header class="chat-head">
                    <div>
                        <h3 class="chat-title">Chat with <?= htmlspecialchars((string)$activeThread['counterparty_username']) ?></h3>
                        <?php if (!empty($activeThread['trade_id'])): ?>
                            <p class="chat-meta">Trade #<?= (int)$activeThread['trade_id'] ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="chat-head-actions">
                        <a href="/messages.php" class="msg-btn-sm" style="text-decoration:none;display:inline-block;">Back</a>
                    </div>
                </header>

                <div class="chat-body">
                    <?php if ($unlockError !== ''): ?>
                        <div class="chat-notice"><?= htmlspecialchars($unlockError) ?></div>
                    <?php endif; ?>

                    <?php if (!$isUnlocked): ?>
                        <div class="unlock-wrap">
                            <h3 style="margin:0 0 8px;">Unlock Messages</h3>
                            <p class="note" style="text-align:left;margin:0 0 10px;">Enter your passphrase to decrypt this thread. Session stays unlocked for 30 minutes.</p>
                            <form method="post" action="/messages.php?thread_id=<?= (int)$activeThread['id'] ?>">
                                <input type="hidden" name="action" value="unlock_thread">
                                <input type="hidden" name="thread_id" value="<?= (int)$activeThread['id'] ?>">
                                <label for="msg-passphrase">Passphrase</label>
                                <input id="msg-passphrase" class="unlock-input" type="password" name="passphrase" required autocomplete="off">
                                <button type="submit" class="msg-btn-sm msg-btn-accent">Unlock (30m)</button>
                            </form>
                        </div>
                    <?php elseif (!$renderedMessages): ?>
                        <p class="chat-empty">No messages yet.</p>
                    <?php else: ?>
                        <?php if ($hasMore && $nextBeforeId !== null): ?>
                            <div style="margin-bottom:8px;">
                                <a class="msg-btn-sm" style="text-decoration:none;display:inline-block;" href="/messages.php?thread_id=<?= (int)$activeThread['id'] ?>&before_id=<?= (int)$nextBeforeId ?>">Load older messages</a>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($renderedMessages as $row): ?>
                            <div class="message-row <?= $row['mine'] ? 'mine' : 'theirs' ?>">
                                <article class="msg-bubble <?= $row['mine'] ? 'mine' : 'theirs' ?>">
                                    <?php if (!empty($row['plain'])): ?>
                                        <div class="msg-text"><?= nl2br(htmlspecialchars((string)$row['plain'])) ?></div>
                                    <?php else: ?>
                                        <div class="msg-fallback">
                                            <pre class="msg-cipher" id="cipher-<?= $row['id'] ?>"><?= htmlspecialchars($row['cipher']) ?></pre>
                                            <div class="msg-fallback-actions">
                                                <button
                                                    type="button"
                                                    class="msg-btn-sm"
                                                    data-copy-target="cipher-<?= $row['id'] ?>"
                                                    onclick="copyCiphertext(this)"
                                                >Copy encrypted block</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="msg-time"><?= htmlspecialchars($row['created_at']) ?></div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form class="chat-compose" method="post" action="/messages/send.php">
                    <input type="hidden" name="thread_id" value="<?= (int)$activeThread['id'] ?>">
                    <input type="hidden" name="return" value="/messages.php?thread_id=<?= (int)$activeThread['id'] ?>">
                    <div class="chat-compose-row">
                        <textarea name="body" maxlength="4000" required placeholder="Type a secure message..."></textarea>
                        <button type="submit" class="msg-btn-sm msg-btn-accent">Send</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
<script>
function copyCiphertext(button) {
    const id = button.getAttribute('data-copy-target');
    const source = id ? document.getElementById(id) : null;
    if (!source) return;

    const text = source.textContent || '';
    if (!text.trim()) return;

    navigator.clipboard.writeText(text).then(() => {
        const old = button.textContent;
        button.textContent = 'Copied';
        setTimeout(() => {
            button.textContent = old;
        }, 1100);
    }).catch(() => {
        button.textContent = 'Copy failed';
        setTimeout(() => {
            button.textContent = 'Copy encrypted block';
        }, 1200);
    });
}
</script>

</body>
</html>
