<?php
declare(strict_types=1);

const MESSAGES_UNLOCK_HOURS = 72;
const MESSAGES_MAX_FAILED_ATTEMPTS = 5;
const MESSAGES_LOCK_MINUTES = 30;

function messages_safe_return_path(string $return, string $fallback = '/messages.php'): string
{
    $return = trim($return);
    if ($return === '') {
        return $fallback;
    }

    if (!str_starts_with($return, '/')) {
        return $fallback;
    }

    if (str_starts_with($return, '//')) {
        return $fallback;
    }

    return $return;
}

function messages_user_gnupg_home(string $username): string
{
    return '/var/www/moneromarket/backup/temp/' . $username . '/.gnupg';
}

function messages_normalize_passphrase(string $passphrase): string
{
    $trimmed = trim($passphrase);
    if ($trimmed === '') {
        return '';
    }

    $collapsed = preg_replace('/[\s-]+/', '', $trimmed);
    if ($collapsed === null) {
        return $trimmed;
    }

    return strtolower($collapsed);
}

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

function messages_encrypt_for_recipient(string $recipientUsername, string $recipientPublicKey, string $plaintext): string
{
    $tmpDir = sys_get_temp_dir() . '/msgenc_' . bin2hex(random_bytes(8));
    $tmpHome = $tmpDir . '/gnupg';
    @mkdir($tmpHome, 0700, true);
    @chmod($tmpHome, 0700);

    $pubFile = $tmpDir . '/pub.asc';
    $plainFile = $tmpDir . '/plain.txt';

    file_put_contents($pubFile, $recipientPublicKey);
    file_put_contents($plainFile, $plaintext);

    $importCmd = sprintf('GNUPGHOME=%s gpg --batch --yes --import %s 2>&1', escapeshellarg($tmpHome), escapeshellarg($pubFile));
    exec($importCmd, $out, $code);
    if ($code !== 0) {
        @unlink($pubFile);
        @unlink($plainFile);
        @array_map('unlink', glob($tmpHome . '/*') ?: []);
        @rmdir($tmpHome);
        @rmdir($tmpDir);
        throw new RuntimeException('Unable to import recipient public key.');
    }

    $encryptCmd = sprintf(
        'GNUPGHOME=%s gpg --batch --yes --trust-model always --armor --encrypt -r %s -r %s %s 2>&1',
        escapeshellarg($tmpHome),
        escapeshellarg($recipientUsername . '@p2pmonero.local'),
        escapeshellarg($recipientUsername),
        escapeshellarg($plainFile)
    );

    $ciphertext = shell_exec($encryptCmd);

    @unlink($pubFile);
    @unlink($plainFile);
    @array_map('unlink', glob($tmpHome . '/*') ?: []);
    @rmdir($tmpHome);
    @rmdir($tmpDir);

    if (!is_string($ciphertext) || trim($ciphertext) === '') {
        throw new RuntimeException('Unable to encrypt message for recipient.');
    }

    return $ciphertext;
}

function messages_decrypt_for_user(array $user, string $ciphertext, string $passphrase): ?string
{
    $username = (string)($user['username'] ?? '');
    if ($username === '' || trim($ciphertext) === '') {
        return null;
    }

    $gpgHome = messages_user_gnupg_home($username);
    if (!is_dir($gpgHome)) {
        return null;
    }

    $tmpDir = sys_get_temp_dir() . '/msgdec_' . bin2hex(random_bytes(8));
    @mkdir($tmpDir, 0700, true);

    $cipherFile = $tmpDir . '/cipher.asc';
    $passFile = $tmpDir . '/pass.txt';
    file_put_contents($cipherFile, $ciphertext);
    file_put_contents($passFile, trim($passphrase) . PHP_EOL);
    @chmod($passFile, 0600);

    $cmd = sprintf(
        'GNUPGHOME=%s gpg --batch --yes --pinentry-mode loopback --passphrase-file %s --decrypt %s 2>/dev/null',
        escapeshellarg($gpgHome),
        escapeshellarg($passFile),
        escapeshellarg($cipherFile)
    );

    $plain = shell_exec($cmd);

    @unlink($cipherFile);
    @unlink($passFile);
    @rmdir($tmpDir);

    if (!is_string($plain) || trim($plain) === '') {
        return null;
    }

    return $plain;
}

function messages_get_or_create_thread(PDO $pdo, int $userA, int $userB, ?int $tradeId = null): int
{
    $a = min($userA, $userB);
    $b = max($userA, $userB);

    if ($tradeId !== null && $tradeId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM message_threads WHERE user_a_id = ? AND user_b_id = ? AND trade_id = ? LIMIT 1");
        $stmt->execute([$a, $b, $tradeId]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM message_threads WHERE user_a_id = ? AND user_b_id = ? AND trade_id IS NULL LIMIT 1");
    $stmt->execute([$a, $b]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int)$id;
    }

    $stmt = $pdo->prepare("INSERT INTO message_threads (user_a_id, user_b_id, trade_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$a, $b, $tradeId !== null && $tradeId > 0 ? $tradeId : null]);
    return (int)$pdo->lastInsertId();
}

function messages_fetch_threads(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("\n        SELECT\n            t.id,\n            t.trade_id,\n            t.updated_at,\n            CASE WHEN t.user_a_id = :uid THEN t.user_b_id ELSE t.user_a_id END AS counterparty_id,\n            u.username AS counterparty_username,\n            m.created_at AS last_message_at\n        FROM message_threads t\n        JOIN users u ON u.id = CASE WHEN t.user_a_id = :uid THEN t.user_b_id ELSE t.user_a_id END\n        LEFT JOIN messages m ON m.id = t.last_message_id\n        WHERE t.user_a_id = :uid OR t.user_b_id = :uid\n        ORDER BY COALESCE(m.created_at, t.updated_at) DESC\n    ");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function messages_fetch_thread_messages(PDO $pdo, int $threadId, int $userId): array
{
    $stmt = $pdo->prepare("\n        SELECT id, sender_id, recipient_id, ciphertext_sender, ciphertext_recipient, created_at\n        FROM messages\n        WHERE thread_id = :tid\n          AND (sender_id = :uid OR recipient_id = :uid)\n        ORDER BY id ASC\n        LIMIT 200\n    ");
    $stmt->execute([':tid' => $threadId, ':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function messages_thread_belongs_to_user(PDO $pdo, int $threadId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM message_threads WHERE id = ? AND (user_a_id = ? OR user_b_id = ?) LIMIT 1");
    $stmt->execute([$threadId, $userId, $userId]);
    return (bool)$stmt->fetchColumn();
}
