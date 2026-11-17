<?php
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly'  => true,
        'samesite'  => 'Strict',
        'secure'    => is_https(),
    ]);
    session_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/login_throttle.php';

// Already logged in
if ($_SESSION['admin_logged_in'] ?? false) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error     = '';
$locked    = false;
$pdo       = getDB();
$client_ip = login_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wait = login_lockout_seconds($pdo, $client_ip);

    if ($wait > 0) {
        // Checked before the password is even looked at, so a locked-out
        // caller learns nothing about whether their guess was close.
        $locked = true;
        $error  = 'Too many failed attempts. Try again in ' . login_wait_label($wait) . '.';
    } elseif (!csrf_verify()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        // Both comparisons always run — short-circuiting on the username
        // would leak, by timing, whether the username alone was correct.
        $user_ok = hash_equals(ADMIN_USER, $user);
        $pass_ok = hash_equals(ADMIN_PASS, $pass);

        if ($user_ok && $pass_ok) {
            login_clear($pdo, $client_ip);
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            header('Location: /admin/dashboard.php');
            exit;
        }

        login_record_failure($pdo, $client_ip);

        // Deliberately does not say which field was wrong.
        $error = 'Invalid username or password.';

        // Warn on the attempt that trips the lock, so a legitimate typo
        // isn't met with a silent wall on the next try.
        $remaining = login_lockout_seconds($pdo, $client_ip);
        if ($remaining > 0) {
            $locked = true;
            $error  = 'Too many failed attempts. Try again in ' . login_wait_label($remaining) . '.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#C25B32">
    <title>Sign in | Caneeli Admin</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    <link rel="stylesheet" href="<?php echo function_exists('asset') ? asset('admin/admin.css') : '/admin/admin.css'; ?>">
</head>
<body class="login-page">
    <aside class="login-art" aria-hidden="true">
        <span class="login-art__mark"></span>
        <p class="login-art__line">Made by hand in Oregon.</p>
    </aside>

    <main class="login-main">
        <div class="login-card">
            <a href="/" class="login-card__logo">
                <img src="/assets/images/pelican-rust.svg" alt="" width="46" height="40">
                <img src="/assets/images/logoni.svg" alt="Caneeli Designs" width="170" height="39">
            </a>

            <h1 class="login-card__title">Studio admin</h1>
            <p class="login-card__sub">Sign in to manage orders, products and pre-orders.</p>

            <?php if ($error): ?>
                <p class="error" role="alert"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <?php echo csrf_field(); ?>
                <label for="login-user">Username</label>
                <input type="text" id="login-user" name="username" required autofocus autocomplete="username"
                       autocapitalize="none" spellcheck="false"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       <?php echo $locked ? 'disabled' : ''; ?>>

                <label for="login-pass">Password</label>
                <div class="login-form__pass">
                    <input type="password" id="login-pass" name="password" required autocomplete="current-password"
                           <?php echo $locked ? 'disabled' : ''; ?>>
                    <button type="button" class="login-form__toggle" aria-controls="login-pass" aria-pressed="false">Show</button>
                </div>

                <button type="submit" class="btn btn-primary login-form__submit" <?php echo $locked ? 'disabled' : ''; ?>>
                    <?php echo $locked ? 'Locked for now' : 'Sign in'; ?>
                </button>
            </form>
        </div>

        <a class="login-back" href="/">← Back to the shop</a>
        <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?><p class="login-test">Test site: sign in with admin / password</p><?php endif; ?>
    </main>

    <script>
    (function () {
        var btn = document.querySelector('.login-form__toggle'), input = document.getElementById('login-pass');
        if (!btn || !input) return;
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            input.focus();
        });
    })();
    </script>
</body>
</html>
