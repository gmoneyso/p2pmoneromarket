<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Authentication Guard
|--------------------------------------------------------------------------
| - Safe to include multiple times
| - Starts session if not already started
| - Uses absolute redirect to avoid subdir issues
| - Backwards compatible with existing code
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Require user to be logged in.
 * Redirects to root login page if not authenticated.
 */
function require_login(): void
{
    // Primary auth flag (standard)
    if (!empty($_SESSION['user_id'])) {
        return;
    }

    // Legacy support (if any old flow sets this)
    if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return;
    }

    // Not authenticated
    header('Location: /login.php');
    exit;
}
