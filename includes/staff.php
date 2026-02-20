<?php
declare(strict_types=1);

function staff_super_admin_username(): string
{
    return 'Habibi';
}

function staff_super_admin_user_id(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([staff_super_admin_username()]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function staff_sync_super_admin_role(PDO $pdo): void
{
    $superAdminId = staff_super_admin_user_id($pdo);
    if ($superAdminId <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO staff_roles (user_id, role, created_at) VALUES (?, "admin", NOW()) ON DUPLICATE KEY UPDATE role = "admin"');
        $stmt->execute([$superAdminId]);

        $stmt = $pdo->prepare('UPDATE staff_roles SET role = "moderator" WHERE role = "admin" AND user_id <> ?');
        $stmt->execute([$superAdminId]);
    } catch (Throwable $e) {
        // graceful no-op when table is unavailable
    }
}

function staff_user_role(PDO $pdo, int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $superAdminId = staff_super_admin_user_id($pdo);
    if ($superAdminId > 0 && $userId === $superAdminId) {
        return 'admin';
    }

    try {
        $stmt = $pdo->prepare('SELECT role FROM staff_roles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $role = strtolower(trim((string)($stmt->fetchColumn() ?: '')));
        if ($role === 'moderator') {
            return 'moderator';
        }
        if ($role === 'admin') {
            return null;
        }
    } catch (Throwable $e) {
        // Schema not migrated yet; fallback below.
    }

    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $username = (string)($stmt->fetchColumn() ?: '');

    if ($username === staff_super_admin_username()) {
        return 'admin';
    }

    return null;
}

function staff_is_super_admin(PDO $pdo, int $userId): bool
{
    return staff_user_role($pdo, $userId) === 'admin';
}

function staff_is_moderator(PDO $pdo, int $userId): bool
{
    return in_array((string)staff_user_role($pdo, $userId), ['admin', 'moderator'], true);
}

function staff_is_admin(PDO $pdo, int $userId): bool
{
    return staff_is_super_admin($pdo, $userId);
}

function staff_moderator_user_ids(PDO $pdo): array
{
    $superAdminId = staff_super_admin_user_id($pdo);
    $ids = [];

    try {
        $stmt = $pdo->query("SELECT user_id FROM staff_roles WHERE role = 'moderator'");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $ids = array_values(array_unique(array_map('intval', $rows ?: [])));
    } catch (Throwable $e) {
        // Schema not migrated yet; fallback below.
    }

    if ($superAdminId > 0 && !in_array($superAdminId, $ids, true)) {
        $ids[] = $superAdminId;
    }

    return array_values(array_unique(array_filter($ids, static fn($v) => (int)$v > 0)));
}
