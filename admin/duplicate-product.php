<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin/products.php');
    exit;
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    header('Location: /admin/products.php');
    exit;
}

// Duplicate starts as a draft so the admin can edit before publishing.
$ins = $pdo->prepare("
    INSERT INTO products
        (name, description, craft_signals, price, stock_qty, category, image_path, active, status, featured)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'draft', 0)
");
$ins->execute([
    $source['name'] . ' (copy)',
    $source['description'],
    $source['craft_signals'],
    $source['price'],
    $source['stock_qty'],
    $source['category'],
    $source['image_path'],
]);
$new_id = (int) $pdo->lastInsertId();

// Copy gallery rows (reuses the same image paths — not a file copy).
$imgs = $pdo->prepare("SELECT image_path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
$imgs->execute([$id]);
$copy_img = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
foreach ($imgs->fetchAll() as $img) {
    $copy_img->execute([$new_id, $img['image_path'], $img['sort_order']]);
}

header('Location: /admin/edit-product.php?id=' . $new_id . '&msg=' . urlencode('Duplicated — edit and publish when ready.'));
exit;
