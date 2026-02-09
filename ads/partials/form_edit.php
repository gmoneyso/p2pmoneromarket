<form method="post"
      action="<?= htmlspecialchars($action) ?>"
      class="edit-ad-form">

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$ad['id'] ?>">
    <input type="hidden" name="type" value="<?= htmlspecialchars($ad['type']) ?>">

    <!-- CRYPTO -->
    <label>
        <?= $ad['type'] === 'buy'
            ? 'Pay using crypto'
            : 'Receive payment in crypto'
        ?>
    </label>

    <select name="crypto_pay" required>
        <?php foreach ($coins as $coin): ?>
            <option value="<?= htmlspecialchars($coin) ?>"
                <?= $coin === $ad['crypto_pay'] ? 'selected' : '' ?>>
                <?= strtoupper($coin) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- MARGIN -->
    <label>Price margin (%)</label>
    <input
        type="number"
        step="0.001"
        name="margin_percent"
        required
        value="<?= htmlspecialchars($ad['margin_percent']) ?>"
    >

    <div class="hint">
        Positive = above market, Negative = below market
    </div>

    <!-- LIVE PRICE PREVIEW -->
    <div class="price-preview">
        <?php include __DIR__ . '/price_preview.php'; ?>
    </div>


    <?php if ($ad['type'] === 'sell'): ?>
        <label>Receive payment address</label>
        <input type="text" name="payin_address" maxlength="255" value="<?= htmlspecialchars((string)($ad['payin_address'] ?? '')) ?>" placeholder="Destination address">

        <label>Network (optional)</label>
        <input type="text" name="payin_network" maxlength="32" value="<?= htmlspecialchars((string)($ad['payin_network'] ?? '')) ?>" placeholder="ERC20, TRC20, etc.">

        <label>Memo / Destination Tag (optional)</label>
        <input type="text" name="payin_tag_memo" maxlength="128" value="<?= htmlspecialchars((string)($ad['payin_tag_memo'] ?? '')) ?>" placeholder="Memo / tag if required">
    <?php endif; ?>

    <!-- TERMS -->
    <label>Trade terms</label>
    <textarea name="terms"><?= htmlspecialchars($ad['terms'] ?? '') ?></textarea>

    <!-- SUBMIT -->
    <button type="submit" class="btn">
        Update <?= ucfirst($ad['type']) ?> Ad
    </button>

</form>
