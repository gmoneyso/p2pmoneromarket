<div class="ad-card card">
    <div class="ad-row">
        <!-- LEFT -->
        <div class="ad-left">
            <div class="ad-username">
                <?= htmlspecialchars($ad['username']) ?>
                <?php if (!empty($ad['online'])): ?>
                    <span class="online-dot" title="Online"></span>
                <?php endif; ?>
            </div>

            <div class="seller-meta">
                <span class="seller-rating">
                    ⭐ <?= number_format($ad['rating'] ?? 0, 1) ?>
                </span>
                <span class="seller-trades">
                    📊 <?= (int)($ad['trade_count'] ?? 0) ?> trades
                </span>
            </div>

            <!-- PRICE -->
            <div class="ad-price">
                <?= number_format((float)$ad['price_per_xmr'], 8) ?>
                <?= strtoupper($ad['crypto_pay']) ?>/XMR
            </div>

            <!-- MARKET +/- -->
            <div class="ad-market">
                Market
                <?= ((float)$ad['margin_percent'] >= 0 ? '+' : '') ?>
                <?= number_format((float)$ad['margin_percent'], 2) ?>%
            </div>

            <!-- FEE PREVIEW -->
            <?php if (!empty($ad['fee_preview'])): ?>
                <div class="ad-fee">
                    Fee ≈ <?= number_format((float)$ad['fee_preview'], 8) ?>
                    <?= strtoupper($ad['crypto_pay']) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT -->
        <div class="ad-right">
            <div class="ad-limits">
                <?= rtrim($ad['min_xmr'], '0.') ?> – <?= rtrim($ad['max_xmr'], '0.') ?> XMR
            </div>

            <div class="ad-margin">
                <?= number_format((float)$ad['margin_percent'], 2) ?>% margin
            </div>
        </div>
    </div>

    <?php if ($user_can_trade): ?>
        <div class="trade-action">
            <a href="/trade/start.php?ad_id=<?= (int)$ad['id'] ?>&type=<?= $ad['type'] === 'sell' ? 'buy' : 'sell' ?>" class="btn">
                <?= $ad['type'] === 'sell' ? 'Buy XMR' : 'Sell XMR' ?>
            </a>
        </div>
    <?php elseif ($is_logged_in): ?>
        <div class="trade-action trade-muted">
            Complete PGP backup to trade
        </div>
    <?php endif; ?>
</div>
