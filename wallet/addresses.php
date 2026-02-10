<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';

require_login();

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT address, created_at
    FROM subaddresses
    WHERE user_id = ?
    ORDER BY id DESC
");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

function short_addr(string $addr): string {
    return substr($addr, 0, 6) . '…' . substr($addr, -6);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Deposit Addresses</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="/assets/global.css">
<link rel="stylesheet" href="/assets/dashboard.css">

<style>
/* Page-only layout helpers */
.address-page {
    width: min(100%, 980px);
    margin: 28px auto;
    background: #101010;
    border: 1px solid #1f1f1f;
}

.address-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.addr-card {
    cursor: pointer;
    margin-bottom: 0;
    background: #0c0c0c;
    border: 1px solid #212121;
    border-radius: 10px;
    transition: border-color .15s ease, background .15s ease, transform .05s ease;
}

.addr-card:hover {
    border-color: #2d2d2d;
    background: #111;
}

.addr-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.addr-short,
.addr-full {
    margin: 0;
}

.addr-full {
    display: none;
    margin-top: 8px;
    font-size: .82rem;
    line-height: 1.35;
    color: #cfcfcf;
    word-break: break-all;
}

.copy-chip {
    font-size: .72rem;
    color: #8f8f8f;
}

.addr-meta {
    margin-top: 8px;
}

.generate-wrap {
    margin-top: 28px;
}

@media (min-width: 760px) {
    .address-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1100px) {
    .address-list {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 900px) {
    .addr-short {
        display: none;
    }

    .addr-full {
        display: block;
    }
}
</style>
</head>

<body>

<div class="container address-page">

    <h1>Deposit Addresses</h1>

    <div class="address-list">

        <?php if (!$addresses): ?>
            <p class="note">No deposit addresses yet.</p>
        <?php endif; ?>

        <?php foreach ($addresses as $a): ?>
            <div class="addr-card card"
                 onclick="copyAddress(this)"
                 data-address="<?= htmlspecialchars($a['address']) ?>">

                <div class="addr-top">
                    <div class="addr-short mono">
                        <?= htmlspecialchars(short_addr($a['address'])) ?>
                    </div>
                    <span class="copy-chip">Click to copy</span>
                </div>

                <div class="addr-full mono">
                    <?= htmlspecialchars($a['address']) ?>
                </div>

                <div class="addr-meta">
                    Created <?= htmlspecialchars(date('Y-m-d H:i', strtotime($a['created_at']))) ?>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <div class="generate-wrap">
        <button class="btn" id="generateSubaddress">
            + Generate New Address
        </button>
    </div>

</div>

<script>
function copyAddress(card) {
    const addr = card.dataset.address;

    navigator.clipboard.writeText(addr).then(() => {
        card.classList.add('copied');

        const full = card.querySelector('.addr-full');
        if (full) full.style.display = 'block';

        setTimeout(() => {
            card.classList.remove('copied');
        }, 900);
    });
}

document.getElementById('generateSubaddress')?.addEventListener('click', () => {
    fetch('/wallet/generate_subaddress.php', { method: 'POST' })
        .then(r => r.json())
        .then(() => location.reload())
        .catch(() => alert('Failed to generate address'));
});
</script>

</body>
</html>
