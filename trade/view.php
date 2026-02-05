<?php
declare(strict_types=1);

$ctx = require __DIR__ . '/trade_context.php';
$trade = $ctx['trade'];
?>

<h2>Trade #<?= (int)$trade['id'] ?></h2>

<p>
    <?= strtoupper($trade['crypto_pay']) ?>
    ↔
    <?= number_format((float)$trade['xmr_amount'], 8) ?> XMR
</p>

<hr>

<?php if ($ctx['canPay']): ?>

    <p>
        Send
        <strong><?= number_format((float)$trade['crypto_amount'], 8) ?>
            <?= strtoupper($trade['crypto_pay']) ?></strong>
        to <strong><?= htmlspecialchars($ctx['counterparty']) ?></strong>
    </p>

    <p>
        You will receive
        <strong><?= number_format((float)$trade['xmr_amount'], 8) ?> XMR</strong>
    </p>

    <pre class="trade-terms">
<?= htmlspecialchars($trade['terms'] ?? '') ?>
    </pre>

    <form method="post" action="/trade/mark_paid.php">
        <input type="hidden" name="trade_id" value="<?= (int)$trade['id'] ?>">
        <button type="submit">I have paid</button>
    </form>

<?php elseif ($ctx['canConfirm']): ?>

    <p>
        Waiting for payment from
        <strong><?= htmlspecialchars($ctx['counterparty']) ?></strong>
    </p>

    <form method="post" action="/trade/confirm_release.php">
        <input type="hidden" name="trade_id" value="<?= (int)$trade['id'] ?>">
        <button type="submit">Confirm payment received</button>
    </form>

<?php else: ?>

    <p>
        Waiting for
        <?= $trade['status'] === 'paid'
            ? 'seller to confirm receipt'
            : 'buyer to make payment' ?>
    </p>

<?php endif; ?>
