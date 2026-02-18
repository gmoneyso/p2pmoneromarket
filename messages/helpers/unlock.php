<?php
declare(strict_types=1);

function messages_verify_passphrase(PDO $pdo, int $userId, string $passphrase): bool
{
    $raw = trim($passphrase);
    if ($raw === '') {
        return false;
    }

    $stmt = $pdo->prepare("SELECT recovery_code_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $hash = (string)($stmt->fetchColumn() ?: '');

    if ($hash === '') {
        return false;
    }

    if (password_verify($raw, $hash)) {
        return true;
    }

    $normalized = messages_normalize_passphrase($raw);
    return $normalized !== '' && $normalized !== $raw && password_verify($normalized, $hash);
}

function messages_get_attempt_state(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT failed_count, locked_until FROM message_unlock_attempts WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['failed_count' => 0, 'locked_until' => null];
    }

    return [
        'failed_count' => (int)$row['failed_count'],
        'locked_until' => $row['locked_until'],
    ];
}

function messages_record_failed_attempt(PDO $pdo, int $userId): array
{
    $state = messages_get_attempt_state($pdo, $userId);
    $failed = $state['failed_count'] + 1;
    $lockedUntil = null;

    if ($failed >= MESSAGES_MAX_FAILED_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + (MESSAGES_LOCK_MINUTES * 60));
        $failed = 0;
    }

    $stmt = $pdo->prepare("\n        INSERT INTO message_unlock_attempts (user_id, failed_count, locked_until, updated_at)\n        VALUES (:uid, :failed, :locked_until, NOW())\n        ON DUPLICATE KEY UPDATE\n            failed_count = VALUES(failed_count),\n            locked_until = VALUES(locked_until),\n            updated_at = NOW()\n    ");
    $stmt->execute([
        ':uid' => $userId,
        ':failed' => $failed,
        ':locked_until' => $lockedUntil,
    ]);

    return ['failed_count' => $failed, 'locked_until' => $lockedUntil];
}

function messages_reset_attempts(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("\n        INSERT INTO message_unlock_attempts (user_id, failed_count, locked_until, updated_at)\n        VALUES (:uid, 0, NULL, NOW())\n        ON DUPLICATE KEY UPDATE\n            failed_count = 0,\n            locked_until = NULL,\n            updated_at = NOW()\n    ");
    $stmt->execute([':uid' => $userId]);
}

function messages_lock_is_active(?string $lockedUntil): bool
{
    if ($lockedUntil === null || $lockedUntil === '') {
        return false;
    }

    return strtotime($lockedUntil) > time();
}

function messages_issue_unlock_session(PDO $pdo, int $userId, string $passphrase): string
{
    $stmt = $pdo->prepare("UPDATE message_unlock_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL");
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare("SELECT recovery_code_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $hashSnapshot = (string)($stmt->fetchColumn() ?: '');

    if ($hashSnapshot === '') {
        throw new RuntimeException('Backup passphrase is not configured for this account.');
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare("\n        INSERT INTO message_unlock_sessions (user_id, token_hash, recovery_hash_snapshot, expires_at, created_at, revoked_at)\n        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW(), NULL)\n    ");
    $stmt->execute([$userId, $tokenHash, $hashSnapshot, MESSAGES_UNLOCK_HOURS]);

    $_SESSION['messages_unlock_token'] = $token;
    $_SESSION['messages_unlock_passphrase'] = trim($passphrase);
    $_SESSION['messages_unlock_expires_at'] = time() + (MESSAGES_UNLOCK_HOURS * 3600);

    return $token;
}

function messages_revoke_unlock_session(PDO $pdo, int $userId): void
{
    $token = (string)($_SESSION['messages_unlock_token'] ?? '');
    if ($token !== '') {
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare("UPDATE message_unlock_sessions SET revoked_at = NOW() WHERE user_id = ? AND token_hash = ? AND revoked_at IS NULL");
        $stmt->execute([$userId, $tokenHash]);
    }

    unset($_SESSION['messages_unlock_token'], $_SESSION['messages_unlock_passphrase'], $_SESSION['messages_unlock_expires_at']);
}

function messages_is_unlocked(PDO $pdo, int $userId): bool
{
    $token = (string)($_SESSION['messages_unlock_token'] ?? '');
    $passphrase = (string)($_SESSION['messages_unlock_passphrase'] ?? '');
    if ($token === '' || $passphrase === '') {
        return false;
    }

    if (((int)($_SESSION['messages_unlock_expires_at'] ?? 0)) <= time()) {
        messages_revoke_unlock_session($pdo, $userId);
        return false;
    }

    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare("\n        SELECT s.expires_at, s.revoked_at, s.recovery_hash_snapshot, u.recovery_code_hash, u.backup_completed\n        FROM message_unlock_sessions s\n        JOIN users u ON u.id = s.user_id\n        WHERE s.user_id = ? AND s.token_hash = ?\n        LIMIT 1\n    ");
    $stmt->execute([$userId, $tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    if (!empty($row['revoked_at'])) {
        return false;
    }

    if (strtotime((string)$row['expires_at']) <= time()) {
        messages_revoke_unlock_session($pdo, $userId);
        return false;
    }

    if ((int)$row['backup_completed'] !== 1) {
        messages_revoke_unlock_session($pdo, $userId);
        return false;
    }

    if ((string)$row['recovery_hash_snapshot'] !== (string)$row['recovery_code_hash']) {
        messages_revoke_unlock_session($pdo, $userId);
        return false;
    }

    return true;
}
