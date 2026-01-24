<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/user.php';

require_login();

$user = get_current_user_data($_SESSION['user_id'], $pdo);
if (!$user) {
    session_destroy();
    header('Location: /login.html');
    exit;
}

/* Fetch all subaddresses for the user */
$stmt = $pdo->prepare("SELECT * FROM subaddresses WHERE user_id = ? ORDER BY id ASC");
$stmt->execute([$user['id']]);
$subaddresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="card">
    <h3>My Monero Addresses</h3>

    <?php if (empty($subaddresses)): ?>
        <p>You have not generated any subaddresses yet.</p>
        <a href="/wallet/generate_subaddress.php">
            <button type="button">Generate Subaddress</button>
        </a>
    <?php else: ?>
        <table class="subaddresses-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subaddress</th>
                    <th>Index</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subaddresses as $idx => $addr): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= htmlspecialchars($addr['address']) ?></td>
                        <td><?= $addr['index_no'] ?></td>
                        <td><?= $addr['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="/wallet/generate_subaddress.php">
            <button type="button">Generate New Subaddress</button>
        </a>
    <?php endif; ?>
</section>
