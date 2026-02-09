<?php
function validate_ad(array $d): array {
    $allowedCoins = [
        'btc','eth','ltc','bch','xrp','xlm',
        'link','dot','yfi','sol','usdt'
    ];

    if (!in_array($d['crypto_pay'], $allowedCoins, true)) {
        throw new Exception('Invalid coin');
    }

    $margin = round((float)$d['margin_percent'], 3);
    if ($margin < -50 || $margin > 50) {
        throw new Exception('Invalid margin');
    }

    $min = (float)$d['min_xmr'];
    $max = (float)$d['max_xmr'];
    if ($min <= 0 || $max <= 0 || $min > $max) {
        throw new Exception('Invalid XMR range');
    }

    $time = (int)$d['payment_time_limit'];
    if ($time < 5 || $time > 720) {
        throw new Exception('Invalid time limit');
    }

    $payinAddress = trim((string)($d['payin_address'] ?? ''));
    $payinNetwork = trim((string)($d['payin_network'] ?? ''));
    $payinTagMemo = trim((string)($d['payin_tag_memo'] ?? ''));

    if (strlen($payinAddress) > 255) {
        throw new Exception('Payment address too long');
    }
    if (strlen($payinNetwork) > 32) {
        throw new Exception('Payment network too long');
    }
    if (strlen($payinTagMemo) > 128) {
        throw new Exception('Payment memo/tag too long');
    }

    if (($d['type'] ?? '') === 'sell' && $payinAddress === '') {
        throw new Exception('Payment address is required for sell ads');
    }

    return [
        'crypto_pay' => $d['crypto_pay'],
        'margin_percent' => $margin,
        'min_xmr' => $min,
        'max_xmr' => $max,
        'payment_time_limit' => $time,
        'terms' => trim($d['terms'] ?? ''),
        'payin_address' => $payinAddress !== '' ? $payinAddress : null,
        'payin_network' => $payinNetwork !== '' ? $payinNetwork : null,
        'payin_tag_memo' => $payinTagMemo !== '' ? $payinTagMemo : null,
    ];
}
