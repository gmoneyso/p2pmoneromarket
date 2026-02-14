<?php
declare(strict_types=1);

require_once __DIR__ . '/../db/database.php';

const WALLET_RPC = 'http://127.0.0.1:18083/json_rpc';
const REQUIRED_CONFIRMATIONS = 10;
const WITHDRAWAL_CONFIRMATIONS = 1;
const POLL_INTERVAL = 10;

function rpc(array $payload): array
{
    $ch = curl_init(WALLET_RPC);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20
    ]);

    $res = curl_exec($ch);
    if ($res === false) {
        throw new RuntimeException('Wallet RPC unreachable');
    }

    return json_decode($res, true, 512, JSON_THROW_ON_ERROR);
}
