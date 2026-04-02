<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /admin/dashboard.php');
    exit;
}

$pdo = getDB();
$pdo->prepare("UPDATE products SET featured = NOT featured WHERE id = ?")
    ->execute([$id]);

header('Location: /admin/dashboard.php');
exit;
