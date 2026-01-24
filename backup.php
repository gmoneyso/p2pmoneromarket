<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['new_user'])) {
    exit('No registration data found.');
}

$user = $_SESSION['new_user'];

// Handle acknowledgment and file download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge'])) {
    $backup = <<<TXT
MONEROMARKET ACCOUNT BACKUP
===========================

Username:
{$user['username']}

Deposit Address:
{$user['subaddress']}

PGP Recovery Passphrase:
{$user['pgp_passphrase']}

Recovery Private Key:
{$user['recovery_private_key']}

IMPORTANT:
- Store this file OFFLINE
- Anyone with this data can access your account
- Lost credentials = permanent loss

Generated on:
TXT;

    $backup .= date('Y-m-d H:i:s');
    $fileName = "moneromarket_backup_{$user['username']}.txt";

    // Force download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($backup));

    ob_clean();
    flush();
    echo $backup;
    unset($_SESSION['new_user']); // clear sensitive session
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MoneroMarket :: Backup</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: 'Ubuntu Mono', monospace; background: #000; color: #e5e5e5; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0;}
.container { max-width:500px; padding:30px; background:#050505; border:1px solid #111; }
h1 { color:#00ff9c; text-align:center; margin-bottom:20px;}
label {display:block; margin-bottom:8px; font-size:14px;}
button {padding:12px; width:100%; background:#00ff9c; border:none; color:#000; font-weight:bold; cursor:pointer; font-size:15px;}
button:hover {background:#00cc7a;}
.notice {font-size:12px; color:#666; margin-top:15px; text-align:center;}
</style>
</head>
<body>
<div class="container">
<h1>ACCOUNT BACKUP</h1>
<p>Save your recovery info now. This is the **only time** it will be shown.</p>

<form method="post">
<label>
<input type="checkbox" name="acknowledge" required>
I have saved my backup securely
</label>

<button type="submit">DOWNLOAD BACKUP</button>
</form>

<div class="notice">
After download, you will be redirected to your dashboard.
</div>
</div>

<script>
if(window.history.replaceState){window.history.replaceState(null,null,window.location.href);}
</script>
