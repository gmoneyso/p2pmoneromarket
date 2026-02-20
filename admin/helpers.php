<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/staff.php';
require_once __DIR__ . '/../includes/notifications.php';

function admin_require_super_admin(PDO $pdo, int $userId): void
{
    staff_sync_super_admin_role($pdo);
    if (!staff_is_super_admin($pdo, $userId)) {
        http_response_code(403);
        exit('Super admin access required.');
    }
}

function admin_log_action(PDO $pdo, int $actorUserId, string $actionType, ?string $targetType = null, ?int $targetId = null, array $details = []): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_actions (actor_user_id, action_type, target_type, target_id, details_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $actorUserId,
            $actionType,
            $targetType,
            $targetId,
            $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable $e) {
        // Graceful no-op if table is not present yet.
    }
}

function admin_set_moderator_role(PDO $pdo, int $actorUserId, int $targetUserId, bool $promote, string $reason = ''): void
{
    if ($targetUserId <= 0) {
        throw new RuntimeException('Invalid target user');
    }

    $superAdminId = staff_super_admin_user_id($pdo);
    if ($targetUserId === $superAdminId) {
        throw new RuntimeException('Cannot change super admin role.');
    }

    $stmt = $pdo->prepare('SELECT role FROM staff_roles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
    $oldRole = strtolower((string)($stmt->fetchColumn() ?: 'none'));
    if (!in_array($oldRole, ['admin', 'moderator', 'none'], true)) {
        $oldRole = 'none';
    }

    if ($promote) {
        $stmt = $pdo->prepare('INSERT INTO staff_roles (user_id, role, created_at) VALUES (?, "moderator", NOW()) ON DUPLICATE KEY UPDATE role = "moderator"');
        $stmt->execute([$targetUserId]);
        $newRole = 'moderator';
    } else {
        $stmt = $pdo->prepare('DELETE FROM staff_roles WHERE user_id = ?');
        $stmt->execute([$targetUserId]);
        $newRole = 'none';
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO staff_role_events (target_user_id, old_role, new_role, changed_by_user_id, reason_text, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$targetUserId, $oldRole, $newRole, $actorUserId, trim($reason) !== '' ? trim($reason) : null]);
    } catch (Throwable $e) {
        // Optional audit table.
    }

    admin_log_action($pdo, $actorUserId, $promote ? 'moderator_promote' : 'moderator_demote', 'user', $targetUserId, ['reason' => $reason]);
}

function admin_set_user_ban(PDO $pdo, int $actorUserId, int $targetUserId, bool $ban, string $reason = ''): void
{
    if ($targetUserId <= 0) {
        throw new RuntimeException('Invalid target user');
    }

    $superAdminId = staff_super_admin_user_id($pdo);
    if ($targetUserId === $superAdminId) {
        throw new RuntimeException('Cannot ban super admin.');
    }

    if ($ban) {
        $stmt = $pdo->prepare("UPDATE user_restrictions SET status = 'lifted', lifted_by_user_id = ?, lifted_at = NOW() WHERE user_id = ? AND restriction_type = 'ban' AND status = 'active'");
        $stmt->execute([$actorUserId, $targetUserId]);

        $stmt = $pdo->prepare("INSERT INTO user_restrictions (user_id, restriction_type, status, reason_text, imposed_by_user_id, imposed_at) VALUES (?, 'ban', 'active', ?, ?, NOW())");
        $stmt->execute([$targetUserId, trim($reason) !== '' ? trim($reason) : null, $actorUserId]);

        notify_user($pdo, $targetUserId, 'account_banned', 'Account restricted', 'Your account has been restricted by platform administration.');
        admin_log_action($pdo, $actorUserId, 'user_ban', 'user', $targetUserId, ['reason' => $reason]);
        return;
    }

    $stmt = $pdo->prepare("UPDATE user_restrictions SET status = 'lifted', lifted_by_user_id = ?, lifted_at = NOW() WHERE user_id = ? AND restriction_type = 'ban' AND status = 'active'");
    $stmt->execute([$actorUserId, $targetUserId]);
    notify_user($pdo, $targetUserId, 'account_unbanned', 'Account restored', 'Your account access has been restored by platform administration.');
    admin_log_action($pdo, $actorUserId, 'user_unban', 'user', $targetUserId, ['reason' => $reason]);
}

function admin_set_user_scam_flag(PDO $pdo, int $actorUserId, int $targetUserId, bool $flag, string $note = ''): void
{
    if ($targetUserId <= 0) {
        throw new RuntimeException('Invalid target user');
    }

    $superAdminId = staff_super_admin_user_id($pdo);
    if ($targetUserId === $superAdminId) {
        throw new RuntimeException('Cannot flag super admin.');
    }

    if ($flag) {
        $stmt = $pdo->prepare("UPDATE user_flags SET status = 'cleared', cleared_by_user_id = ?, cleared_at = NOW() WHERE user_id = ? AND flag_type = 'scam_alert' AND status = 'active'");
        $stmt->execute([$actorUserId, $targetUserId]);

        $stmt = $pdo->prepare("INSERT INTO user_flags (user_id, flag_type, status, note, set_by_user_id, set_at) VALUES (?, 'scam_alert', 'active', ?, ?, NOW())");
        $stmt->execute([$targetUserId, trim($note) !== '' ? trim($note) : null, $actorUserId]);
        admin_log_action($pdo, $actorUserId, 'user_flag_scam', 'user', $targetUserId, ['note' => $note]);
        return;
    }

    $stmt = $pdo->prepare("UPDATE user_flags SET status = 'cleared', cleared_by_user_id = ?, cleared_at = NOW() WHERE user_id = ? AND flag_type = 'scam_alert' AND status = 'active'");
    $stmt->execute([$actorUserId, $targetUserId]);
    admin_log_action($pdo, $actorUserId, 'user_unflag_scam', 'user', $targetUserId, ['note' => $note]);
}

function admin_platform_total_balance(PDO $pdo): float
{
    $stmt = $pdo->query('SELECT COALESCE(SUM(t.balance_after), 0) FROM (SELECT user_id, MAX(id) AS max_id FROM balance_ledger GROUP BY user_id) x JOIN balance_ledger t ON t.id = x.max_id');
    return (float)($stmt->fetchColumn() ?: 0.0);
}

function admin_fetch_users_overview(PDO $pdo): array
{
    $stmt = $pdo->query("\n        SELECT\n            u.id, u.username, u.created_at,\n            COALESCE(b.balance_after, 0) AS balance_after,\n            sr.role AS staff_role,\n            EXISTS(SELECT 1 FROM user_restrictions ur WHERE ur.user_id = u.id AND ur.restriction_type = 'ban' AND ur.status = 'active' AND (ur.expires_at IS NULL OR ur.expires_at > NOW())) AS is_banned,\n            EXISTS(SELECT 1 FROM user_flags uf WHERE uf.user_id = u.id AND uf.flag_type = 'scam_alert' AND uf.status = 'active') AS scam_alert,\n            (SELECT COUNT(*) FROM subaddresses sa WHERE sa.user_id = u.id) AS subaddress_count\n        FROM users u\n        LEFT JOIN staff_roles sr ON sr.user_id = u.id\n        LEFT JOIN (\n            SELECT bl.user_id, bl.balance_after\n            FROM balance_ledger bl\n            INNER JOIN (SELECT user_id, MAX(id) AS max_id FROM balance_ledger GROUP BY user_id) m\n                ON m.user_id = bl.user_id AND m.max_id = bl.id\n        ) b ON b.user_id = u.id\n        ORDER BY u.id ASC\n    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_fetch_subaddresses(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, address, index_no, created_at FROM subaddresses WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
