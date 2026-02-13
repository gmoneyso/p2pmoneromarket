<?php
declare(strict_types=1);

function notify_user(PDO $pdo, int $userId, string $type, string $title, string $body, ?string $entityType = null, ?int $entityId = null): void
{
    try {
        $stmt = $pdo->prepare("\n            INSERT INTO notifications (user_id, type, title, body, entity_type, entity_id, created_at, updated_at)\n            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())\n        ");
        $stmt->execute([$userId, $type, $title, $body, $entityType, $entityId]);
    } catch (Throwable $e) {
        // Graceful no-op if notifications table is not migrated yet.
    }
}
