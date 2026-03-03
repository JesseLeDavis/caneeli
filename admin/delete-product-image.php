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

// Fetch the image to verify ownership
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
$stmt->execute([$image_id, $product_id]);
$image = $stmt->fetch();

if (!$image) {
    header("Location: /admin/edit-product.php?id=$product_id");
    exit;
}

// Delete the physical file
$file = __DIR__ . '/..' . $image['image_path'];
if (file_exists($file)) unlink($file);

// Remove from gallery
$pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$image_id]);

// If this was the featured image, promote the next available gallery image (or null)
$product = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
$product->execute([$product_id]);
$row = $product->fetch();

if ($row && $row['image_path'] === $image['image_path']) {
    $next = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1");
    $next->execute([$product_id]);
    $next_row = $next->fetch();
    $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?")
        ->execute([$next_row['image_path'] ?? null, $product_id]);
}

header("Location: /admin/edit-product.php?id=$product_id&deleted=1");
exit;
