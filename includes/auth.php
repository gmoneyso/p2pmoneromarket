<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Authentication Guard
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['user_id']) && (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)) {
        header('Location: /login.php');
        exit;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        header('Location: /login.php');
        exit;
    }

    // Centralized active-ban gate.
    // Use the shared DB getter so auth does not depend on include order or global scope.
    try {
        require_once __DIR__ . '/../db/database.php';
        $pdo = db_get_pdo();

        $stmt = $pdo->prepare("SELECT 1 FROM user_restrictions WHERE user_id = ? AND restriction_type = 'ban' AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
        $stmt->execute([$userId]);
        if ((bool)$stmt->fetchColumn()) {
            session_destroy();
            header('Location: /login.php?banned=1');
            exit;
        }
    } catch (Throwable $e) {
        // If moderation tables are not migrated yet, proceed normally.
    }
}
