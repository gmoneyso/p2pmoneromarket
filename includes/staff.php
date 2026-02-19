<?php
declare(strict_types=1);

function staff_user_role(PDO $pdo, int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT role FROM staff_roles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $role = strtolower(trim((string)($stmt->fetchColumn() ?: '')));
        if (in_array($role, ['admin', 'moderator'], true)) {
            return $role;
        }
    } catch (Throwable $e) {
        // Schema not migrated yet; fallback below.
    }

    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $username = (string)($stmt->fetchColumn() ?: '');

    if ($username === 'Habibi') {
        return 'admin';
    }

    return null;
}

function staff_is_moderator(PDO $pdo, int $userId): bool
{
    return in_array((string)staff_user_role($pdo, $userId), ['admin', 'moderator'], true);
}

function staff_is_admin(PDO $pdo, int $userId): bool
{
    return staff_user_role($pdo, $userId) === 'admin';
}

function staff_moderator_user_ids(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT user_id FROM staff_roles WHERE role IN ('admin','moderator')");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $ids = array_values(array_unique(array_map('intval', $rows ?: [])));
        if ($ids) {
            return $ids;
        }
    } catch (Throwable $e) {
        // Schema not migrated yet; fallback below.
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute(['Habibi']);
    $id = (int)($stmt->fetchColumn() ?: 0);

    return $id > 0 ? [$id] : [];
}
