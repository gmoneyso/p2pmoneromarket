<?php
declare(strict_types=1);

function validate_edit_ad(array $data): array
{
    $crypto = $data['crypto_pay'] ?? '';
    $margin = $data['margin_percent'] ?? null;
    $terms  = trim($data['terms'] ?? '');
    $payinAddress = trim((string)($data['payin_address'] ?? ''));
    $payinNetwork = trim((string)($data['payin_network'] ?? ''));
    $payinTagMemo = trim((string)($data['payin_tag_memo'] ?? ''));

    if ($crypto === '') {
        throw new Exception('Crypto is required');
    }

    if (!is_numeric($margin)) {
        throw new Exception('Invalid margin');
    }

    if (strlen($payinAddress) > 255) {
        throw new Exception('Payment address too long');
    }

    if (strlen($payinNetwork) > 32) {
        throw new Exception('Payment network too long');
    }

    if (strlen($payinTagMemo) > 128) {
        throw new Exception('Payment memo/tag too long');
    }

    if (($data['type'] ?? '') === 'sell' && $payinAddress === '') {
        throw new Exception('Payment address is required for sell ads');
    }

    return [
        'crypto_pay'     => $crypto,
        'margin_percent' => (float)$margin,
        'terms'          => $terms,
        'payin_address'  => $payinAddress !== '' ? $payinAddress : null,
        'payin_network'  => $payinNetwork !== '' ? $payinNetwork : null,
        'payin_tag_memo' => $payinTagMemo !== '' ? $payinTagMemo : null,
    ];
}
