<?php
declare(strict_types=1);

function validate_edit_ad(array $data): array
{
    $crypto = $data['crypto_pay'] ?? '';
    $margin = $data['margin_percent'] ?? null;
    $terms  = trim($data['terms'] ?? '');

    if ($crypto === '') {
        throw new Exception('Crypto is required');
    }

    if (!is_numeric($margin)) {
        throw new Exception('Invalid margin');
    }

    return [
        'crypto_pay'     => $crypto,
        'margin_percent' => (float)$margin,
        'terms'          => $terms,
    ];
}
