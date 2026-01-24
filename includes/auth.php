<?php
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.html');
        exit;
    }
}
