<?php
declare(strict_types=1);

const WALLET_RPC_ENDPOINT = 'http://127.0.0.1:18083/json_rpc';

function wallet_rpc_call(array $payload): array
{
    $ch = curl_init(WALLET_RPC_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT => 25,
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('Wallet RPC unreachable');
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($status >= 400) {
        throw new RuntimeException('Wallet RPC HTTP error');
    }

    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (isset($data['error'])) {
        $msg = is_array($data['error']) ? (string)($data['error']['message'] ?? 'RPC error') : 'RPC error';
        throw new RuntimeException($msg);
    }

    return $data;
}

function wallet_priority_value(string $priority): int
{
    return match ($priority) {
        'slow' => 1,
        'fast' => 3,
        default => 2,
    };
}

function wallet_estimate_fee_xmr(string $address, float $amountXmr, string $priority = 'medium'): float
{
    $atomic = (int)round($amountXmr * 1e12);
    if ($atomic <= 0) {
        throw new RuntimeException('Invalid amount');
    }

    $res = wallet_rpc_call([
        'jsonrpc' => '2.0',
        'id' => 'fee-estimate',
        'method' => 'transfer',
        'params' => [
            'destinations' => [[
                'address' => $address,
                'amount' => $atomic,
            ]],
            'account_index' => 0,
            'priority' => wallet_priority_value($priority),
            'do_not_relay' => true,
            'get_tx_metadata' => false,
        ],
    ]);

    $feeAtomic = (int)($res['result']['fee'] ?? 0);
    if ($feeAtomic <= 0) {
        throw new RuntimeException('Unable to estimate fee');
    }

    return $feeAtomic / 1e12;
}

function wallet_send_xmr(string $address, float $amountXmr, string $priority = 'medium'): array
{
    $atomic = (int)round($amountXmr * 1e12);
    if ($atomic <= 0) {
        throw new RuntimeException('Invalid amount');
    }

    $res = wallet_rpc_call([
        'jsonrpc' => '2.0',
        'id' => 'withdraw-send',
        'method' => 'transfer',
        'params' => [
            'destinations' => [[
                'address' => $address,
                'amount' => $atomic,
            ]],
            'account_index' => 0,
            'priority' => wallet_priority_value($priority),
        ],
    ]);

    return [
        'txid' => (string)($res['result']['tx_hash'] ?? ''),
        'fee' => ((int)($res['result']['fee'] ?? 0)) / 1e12,
        'amount' => $amountXmr,
        'height' => (int)($res['result']['tx_key'] ?? 0),
    ];
}
