<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$image_id   = intval($_POST['image_id']   ?? 0);
$product_id = intval($_POST['product_id'] ?? 0);

if (!$image_id || !$product_id) {
    header('Location: /admin/dashboard.php');
    exit;
}

$pdo = getDB();

// Verify this image belongs to this product
$stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?");
$stmt->execute([$image_id, $product_id]);
$image = $stmt->fetch();

if ($image) {
    $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?")
        ->execute([$image['image_path'], $product_id]);
}

header("Location: /admin/edit-product.php?id=$product_id");
exit;
