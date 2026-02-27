<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/dashboard.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /admin/dashboard.php');
    exit;
}

$pdo = getDB();

// Get image path before deleting so we can remove the file
$stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if ($product) {
    // Delete the image file if it exists
    if ($product['image_path']) {
        $file = __DIR__ . '/..' . $product['image_path'];
        if (file_exists($file)) unlink($file);
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: /admin/dashboard.php');
exit;
