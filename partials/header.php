<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body {
    margin: 0;
    background: #0b0b0b;
    color: #e0e0e0;
    font-family: system-ui, sans-serif;
}
a { color: #ff6600; text-decoration: none; }
</style>
</head>
<body>
