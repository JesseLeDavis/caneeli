<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/config.php';

// Already logged in
if ($_SESSION['admin_logged_in'] ?? false) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if (hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASS, $pass)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: /admin/dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | Caneeli</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
    <div class="login-wrap">
        <h1>Admin Login</h1>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required autofocus>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit" class="btn btn-primary" style="width:100%">Log In</button>
        </form>
    </div>
</body>
</html>
