<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/helpers.php';

require_login();

$userId = (int)($_SESSION['user_id'] ?? 0);
admin_require_super_admin($pdo, $userId);

$targetUserId = (int)($_GET['user_id'] ?? 0);
if ($targetUserId <= 0) {
    http_response_code(400);
    exit('Invalid user.');
}

$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$targetUserId]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) {
    http_response_code(404);
    exit('User not found.');
}

$addresses = admin_fetch_subaddresses($pdo, $targetUserId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Addresses | Admin</title>
<link rel="stylesheet" href="/assets/global.css">
</head>
<body>
<?php require __DIR__ . '/../assets/header.php'; ?>
<div class="container" style="max-width:980px;margin:22px auto;display:grid;gap:12px;">
    <section class="card">
        <h1>Addresses for <?= htmlspecialchars((string)$target['username']) ?> (ID #<?= (int)$target['id'] ?>)</h1>
        <p><a class="btn" href="/admin/dashboard.php">Back to admin panel</a></p>
    </section>

    <section class="card">
        <?php if (!$addresses): ?>
            <p class="note" style="text-align:left;">No generated addresses found for this user.</p>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #222;">ID</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #222;">Address</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #222;">Index</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #222;">Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($addresses as $a): ?>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #1a1a1a;"><?= (int)$a['id'] ?></td>
                        <td style="padding:8px;border-bottom:1px solid #1a1a1a;"><code><?= htmlspecialchars((string)$a['address']) ?></code></td>
                        <td style="padding:8px;border-bottom:1px solid #1a1a1a;"><?= (int)$a['index_no'] ?></td>
                        <td style="padding:8px;border-bottom:1px solid #1a1a1a;"><?= htmlspecialchars((string)$a['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
