<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db/database.php';

/* ===============================
   Security config
   =============================== */

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_COOLDOWN', 300); // 5 minutes

/* ===============================
   Helpers
   =============================== */

function client_ua(): string {
    return substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
}

/* ===============================
   Rate limiting (UA-bound)
   =============================== */

$ua = client_ua();

$_SESSION['login_attempts'] ??= [];

$attempt = $_SESSION['login_attempts'][$ua] ?? [
    'count' => 0,
    'time'  => time()
];

$error = null;

if (
    $attempt['count'] >= MAX_LOGIN_ATTEMPTS &&
    (time() - $attempt['time']) < LOGIN_COOLDOWN
) {
    $error = "Too many login attempts. Try again later.";
}

/* ===============================
   CSRF token
   =============================== */

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

/* ===============================
   Handle POST
   =============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {

    /* Honeypot (bots fill this) */
    if (!empty($_POST['website'] ?? '')) {
        http_response_code(400);
        exit;
    }

    /* CSRF check */
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf'] ?? '')) {
        $error = "Invalid request";
    } else {

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $generic  = "Wrong username or password";

        if (strlen($username) < 5 || strlen($password) < 7) {
            $error = $generic;
        } else {
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, username, password_hash
                     FROM users
                     WHERE username = ?
                     LIMIT 1"
                );
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $error = $generic;
                } else {
                    /* Success */
                    session_regenerate_id(true);
                    unset($_SESSION['login_attempts']);

                    $_SESSION['user_id']   = (int)$user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['ua_bind']   = hash('sha256', $ua);

                    header("Location: index.php");
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Login DB error: ".$e->getMessage());
                $error = "Login temporarily unavailable";
            }
        }
    }

    /* Record failed attempt */
    $_SESSION['login_attempts'][$ua] = [
        'count' => $attempt['count'] + 1,
        'time'  => time()
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | MoneroMarket</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    margin:0;
    background:#0b0b0b;
    color:#e0e0e0;
    font-family:system-ui,sans-serif;
}
.container {
    max-width:420px;
    margin:80px auto;
    padding:30px;
    background:#121212;
    border-radius:10px;
}
h1 {
    text-align:center;
    margin-bottom:20px;
}
label {
    font-size:0.85rem;
    margin-bottom:6px;
    display:block;
}
.field {
    position:relative;
}
input {
    width:100%;
    padding:12px;
    margin-bottom:18px;
    background:#1d1d1d;
    border:none;
    border-radius:6px;
    color:#fff;
}
input:focus {
    outline:1px solid #ff6600;
}
.toggle {
    position:absolute;
    right:10px;
    top:36px;
    font-size:0.75rem;
    cursor:pointer;
    color:#aaa;
}
button {
    width:100%;
    padding:12px;
    background:#ff6600;
    border:none;
    border-radius:6px;
    font-weight:600;
    cursor:pointer;
}
.error {
    background:#2a1414;
    border:1px solid #ff5252;
    color:#ffb3b3;
    padding:10px;
    border-radius:6px;
    font-size:0.85rem;
    margin-bottom:16px;
    text-align:center;
}
.links {
    font-size:0.8rem;
    text-align:center;
    margin-top:10px;
}
.links a {
    color:#ff6600;
    text-decoration:none;
    margin:0 5px;
}
.note {
    font-size:0.75rem;
    color:#888;
    text-align:center;
    margin-top:15px;
}
/* Honeypot */
.hp { display:none; }
</style>
</head>

<body>
<div class="container">

<h1>Login</h1>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" novalidate>

<input type="hidden" name="csrf"
       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">

<label>Username</label>
<input type="text"
       name="username"
       required minlength="5" maxlength="32"
       autocomplete="username">

<label>Password</label>
<div class="field">
    <input type="password"
           id="password"
           name="password"
           required minlength="7"
           autocomplete="current-password">
    <span class="toggle" data-target="password">view</span>
</div>

<button type="submit">Login</button>
</form>

<div class="links">
    <a href="register.php">Register</a> |
    <a href="/backup/recovery.php">Recovery</a>
</div>

<div class="note">
    Access is monitored. Repeated failures will lock you out.
</div>

</div>

<script>
(() => {
    document.querySelectorAll('.toggle').forEach(el => {
        el.addEventListener('click', () => {
            const input = document.getElementById(el.dataset.target);
            input.type = input.type === 'password' ? 'text' : 'password';
            el.textContent = input.type === 'password' ? 'view' : 'hide';
        });
    });
})();
</script>

</body>
</html>
