<?php
declare(strict_types=1);

/* Fetch latest subaddress */
$stmt = $pdo->prepare("
    SELECT address
    FROM subaddresses
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$user['id']]);
$latest = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<section class="card addresses-card">
    <h3>Addresses</h3>

    <?php if ($latest): ?>
        <div class="address-row"
             onclick="copyAddress(this)"
             data-address="<?= htmlspecialchars($latest['address']) ?>"
             title="Click to copy">

            <span class="address-text">
                <?= htmlspecialchars($latest['address']) ?>
            </span>

            <span class="copy-hint">Tap to copy</span>
        </div>
    <?php else: ?>
        <p class="note">No deposit address yet.</p>
    <?php endif; ?>

    <div class="address-actions">
        <a href="/wallet/addresses.php" class="btn primary">
            Generate new address
        </a>


        <a href="/wallet/withdraw.php" class="btn">
            Withdraw XMR
        </a>

        <a href="/wallet/addresses.php" class="link">
            View all addresses →
        </a>
    </div>
</section>
