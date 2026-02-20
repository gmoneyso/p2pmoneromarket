<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/helpers.php';

require_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
admin_require_super_admin($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));

    try {
        if ($action === 'promote_mod') {
            admin_set_moderator_role($pdo, $userId, $targetUserId, true, $reason);
            flash_set('success', 'User promoted to moderator.');
        } elseif ($action === 'demote_mod') {
            admin_set_moderator_role($pdo, $userId, $targetUserId, false, $reason);
            flash_set('success', 'Moderator removed.');
        } elseif ($action === 'ban_user') {
            admin_set_user_ban($pdo, $userId, $targetUserId, true, $reason);
            flash_set('success', 'User banned.');
        } elseif ($action === 'unban_user') {
            admin_set_user_ban($pdo, $userId, $targetUserId, false, $reason);
            flash_set('success', 'User unbanned.');
        } elseif ($action === 'flag_scam') {
            admin_set_user_scam_flag($pdo, $userId, $targetUserId, true, $reason);
            flash_set('success', 'Scam alert added.');
        } elseif ($action === 'clear_scam') {
            admin_set_user_scam_flag($pdo, $userId, $targetUserId, false, $reason);
            flash_set('success', 'Scam alert removed.');
        }
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }

    header('Location: /admin/dashboard.php');
    exit;
}

$users = admin_fetch_users_overview($pdo);
$platformTotal = admin_platform_total_balance($pdo);
$moderatorCount = count(array_filter($users, static fn($u) => (string)($u['staff_role'] ?? '') === 'moderator'));
$bannedCount = count(array_filter($users, static fn($u) => (int)$u['is_banned'] === 1));
$flaggedCount = count(array_filter($users, static fn($u) => (int)$u['scam_alert'] === 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | MoneroMarket</title>
<link rel="stylesheet" href="/assets/global.css">
<style>
.admin-wrap{max-width:1180px;margin:22px auto;display:grid;gap:12px}
.admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px}
.admin-stat{padding:12px;border:1px solid var(--border-soft);border-radius:10px;background:#111}
.admin-stat h3{margin:0 0 6px;font-size:.84rem;color:var(--text-muted)}
.admin-stat p{margin:0;font-size:1.1rem;font-weight:700}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:8px;border-bottom:1px solid #252525;text-align:left;vertical-align:top}
small.muted{color:var(--text-muted)}
.action-row{display:flex;gap:6px;flex-wrap:wrap}
.action-row form{display:inline}
.badge{display:inline-block;padding:2px 6px;border-radius:999px;font-size:.72rem;border:1px solid #333}
.badge.mod{background:#12233f;border-color:#2d4c7d}
.badge.ban{background:#3d1717;border-color:#7b2b2b}
.badge.flag{background:#4a3312;border-color:#7a5822}
</style>
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>

<div class="container admin-wrap">
    <section class="card">
        <h1>Super Admin Panel (Habibi)</h1>
        <p class="note">Manage moderators, bans, scam alerts, balances, and platform-level oversight.</p>
        <p><a class="btn" href="/moderator/dashboard.php">Open Moderator Panel</a></p>
    </section>

    <section class="admin-grid">
        <article class="admin-stat"><h3>Total platform balance</h3><p><?= number_format($platformTotal, 12) ?> XMR</p></article>
        <article class="admin-stat"><h3>Moderators</h3><p><?= number_format($moderatorCount) ?></p></article>
        <article class="admin-stat"><h3>Banned users</h3><p><?= number_format($bannedCount) ?></p></article>
        <article class="admin-stat"><h3>Scam-alert users</h3><p><?= number_format($flaggedCount) ?></p></article>
    </section>

    <section class="card table-wrap">
        <h2>User control center</h2>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Status</th>
                    <th>Balance</th>
                    <th>Subaddresses</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php
                    $uid = (int)$u['id'];
                    $isSuperAdmin = (string)$u['username'] === staff_super_admin_username();
                    $isMod = (string)($u['staff_role'] ?? '') === 'moderator';
                    $isBanned = (int)$u['is_banned'] === 1;
                    $isFlagged = (int)$u['scam_alert'] === 1;
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string)$u['username']) ?></strong>
                        <br><small class="muted">ID #<?= $uid ?> · Joined <?= htmlspecialchars((string)$u['created_at']) ?></small>
                    </td>
                    <td>
                        <?php if ($isSuperAdmin): ?><span class="badge">super_admin</span><?php endif; ?>
                        <?php if ($isMod): ?><span class="badge mod">moderator</span><?php endif; ?>
                        <?php if ($isBanned): ?><span class="badge ban">banned</span><?php endif; ?>
                        <?php if ($isFlagged): ?><span class="badge flag">scam_alert</span><?php endif; ?>
                    </td>
                    <td><?= number_format((float)$u['balance_after'], 12) ?> XMR</td>
                    <td>
                        <?= (int)$u['subaddress_count'] ?>
                        <br><a href="/admin/user_addresses.php?user_id=<?= $uid ?>">View addresses</a>
                    </td>
                    <td>
                        <?php if (!$isSuperAdmin): ?>
                        <div class="action-row">
                            <form method="post">
                                <input type="hidden" name="action" value="<?= $isMod ? 'demote_mod' : 'promote_mod' ?>">
                                <input type="hidden" name="target_user_id" value="<?= $uid ?>">
                                <input type="hidden" name="reason" value="manual role update">
                                <button class="btn" type="submit"><?= $isMod ? 'Demote mod' : 'Promote mod' ?></button>
                            </form>

                            <form method="post">
                                <input type="hidden" name="action" value="<?= $isBanned ? 'unban_user' : 'ban_user' ?>">
                                <input type="hidden" name="target_user_id" value="<?= $uid ?>">
                                <input type="hidden" name="reason" value="admin action">
                                <button class="btn" type="submit"><?= $isBanned ? 'Unban' : 'Ban' ?></button>
                            </form>

                            <form method="post">
                                <input type="hidden" name="action" value="<?= $isFlagged ? 'clear_scam' : 'flag_scam' ?>">
                                <input type="hidden" name="target_user_id" value="<?= $uid ?>">
                                <input type="hidden" name="reason" value="admin review">
                                <button class="btn" type="submit"><?= $isFlagged ? 'Clear scam' : 'Set scam' ?></button>
                            </form>
                        </div>
                        <?php else: ?>
                            <small class="muted">Super admin protected</small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
</body>
</html>
