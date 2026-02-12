<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db/database.php';
require_once __DIR__ . '/includes/user.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$user = get_current_user_data($userId, $pdo);
if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['mark_all_read'])) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $flash = 'All notifications marked as read.';
        } elseif (isset($_POST['mark_read_id'])) {
            $id = (int)$_POST['mark_read_id'];
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                $flash = 'Notification marked as read.';
            }
        }
    } catch (Throwable $e) {
        $error = 'Notifications action failed.';
    }
}

$notifications = [];
$unreadCount = 0;

try {
    $stmt = $pdo->prepare("\n        SELECT id, type, title, body, entity_type, entity_id, is_read, created_at\n        FROM notifications\n        WHERE user_id = ?\n        ORDER BY created_at DESC\n        LIMIT 200\n    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unreadCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $error = 'Notifications are not available yet. Ensure notification tables are migrated.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/dashboard.css">
<style>
.notifications-wrap { display:flex; flex-direction:column; gap:10px; }
.notifications-tools { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
.notifications-list { display:flex; flex-direction:column; gap:10px; }
.notification-item { border:1px solid #222; border-radius:8px; padding:12px; background:#0f0f0f; }
.notification-item.unread { border-color:#f6c945; background:#14120b; }
.notification-meta { color:#8f8f8f; font-size:.84rem; margin-bottom:6px; }
.notification-title { font-weight:700; margin-bottom:4px; }
.notification-body { margin-bottom:10px; color:#ddd; }
.notification-actions { display:flex; justify-content:flex-end; }
.notice { padding:8px 10px; border-radius:6px; font-size:.9rem; }
.notice.ok { background:#12351f; color:#7ff7a7; border:1px solid #1f6a3f; }
.notice.err { background:#3a1515; color:#ff9898; border:1px solid #7a2424; }
</style>
</head>
<body>

<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="dashboard">
    <section class="card notifications-wrap">
        <div class="notifications-tools">
            <h2>Notifications <?= $unreadCount > 0 ? '(' . $unreadCount . ' unread)' : '' ?></h2>
            <?php if ($unreadCount > 0): ?>
                <form method="post">
                    <button type="submit" class="btn" name="mark_all_read" value="1">Mark all as read</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($flash): ?><div class="notice ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="notifications-list">
            <?php if (!$notifications): ?>
                <p class="note" style="text-align:left;">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <article class="notification-item <?= (int)$n['is_read'] === 0 ? 'unread' : '' ?>" id="n<?= (int)$n['id'] ?>">
                        <div class="notification-meta">
                            <?= htmlspecialchars((string)$n['created_at']) ?>
                            <?php if (!empty($n['type'])): ?> · <?= htmlspecialchars((string)$n['type']) ?><?php endif; ?>
                            <?php if (!empty($n['entity_type']) && !empty($n['entity_id'])): ?> · <?= htmlspecialchars((string)$n['entity_type']) ?> #<?= (int)$n['entity_id'] ?><?php endif; ?>
                        </div>
                        <div class="notification-title"><?= htmlspecialchars((string)$n['title']) ?></div>
                        <div class="notification-body"><?= nl2br(htmlspecialchars((string)$n['body'])) ?></div>
                        <?php if ((int)$n['is_read'] === 0): ?>
                            <div class="notification-actions">
                                <form method="post">
                                    <input type="hidden" name="mark_read_id" value="<?= (int)$n['id'] ?>">
                                    <button type="submit" class="btn">Mark as read</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
