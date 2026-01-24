<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db/database.php';
require_once __DIR__ . '/../includes/user.php';

header('Content-Type: application/json');
require_login();

$user = get_current_user_data($_SESSION['user_id'], $pdo);
if (!$user) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

/* -----------------------------
 * Monero Wallet RPC
 * ----------------------------- */
$rpcUrl = 'http://127.0.0.1:18083/json_rpc'; // your wallet RPC
$rpcUser = 'user';  // optional
$rpcPass = 'pass';  // optional

$ch = curl_init($rpcUrl);
$data = [
    'jsonrpc' => '2.0',
    'id' => '0',
    'method' => 'create_address',
    'params' => [
        'account_index' => 0,
        'label' => $user['username'] . '_' . time()
    ]
];

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
if ($rpcUser && $rpcPass) {
    curl_setopt($ch, CURLOPT_USERPWD, "$rpcUser:$rpcPass");
}

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $err]);
    exit;
}

$resData = json_decode($response, true);
if (!isset($resData['result']['address'], $resData['result']['address_index'])) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Invalid RPC response']);
    exit;
}

$address = $resData['result']['address'];
$index   = $resData['result']['address_index'];

/* -----------------------------
 * Insert into database
 * ----------------------------- */
$stmt = $pdo->prepare("INSERT INTO subaddresses (user_id, address, index_no) VALUES (?, ?, ?)");
$stmt->execute([$user['id'], $address, $index]);

/* -----------------------------
 * Success
 * ----------------------------- */
echo json_encode([
    'status' => 'ok',
    'address' => $address,
    'index' => $index
]);
