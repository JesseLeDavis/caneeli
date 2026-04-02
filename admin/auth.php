<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly'  => true,
        'samesite'  => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/../includes/csrf.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/index.php');
    exit;
}
