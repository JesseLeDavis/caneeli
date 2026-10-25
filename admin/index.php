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
    <title>Admin Login | Caneeli</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-logo-wrap">
            <img src="/assets/images/logoni.svg" alt="Caneeli Designs">
            <span class="login-admin-badge">Admin</span>
        </div>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <label>Username</label>
            <input type="text" name="username" required autofocus
                   <?php echo $locked ? 'disabled' : ''; ?>>
            <label>Password</label>
            <input type="password" name="password" required
                   <?php echo $locked ? 'disabled' : ''; ?>>
            <button type="submit" class="btn btn-primary" style="width:100%"
                    <?php echo $locked ? 'disabled' : ''; ?>>
                <?php echo $locked ? 'Locked' : 'Log In'; ?>
            </button>
        </form>
    </div>
</body>
</html>
