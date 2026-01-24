<?php
function get_current_user_data(int $user_id, PDO $pdo): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, username, balance, pgp_public, backup_completed
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
