<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/product_status.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    header('Location: /admin/products.php');
    exit;
}

$action = $_POST['action']  ?? '';
$ids    = $_POST['ids']     ?? [];

if (!is_array($ids)) $ids = [];
$ids = array_values(array_filter(array_map('intval', $ids)));

if (!$ids || !$action) {
    header('Location: /admin/products.php');
    exit;
}

$pdo          = getDB();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$count        = count($ids);
$msg          = '';

// Status-change bulk actions ----------------------------------------------
$status_actions = [
    'status_active'   => 'active',
    'status_sold_out' => 'sold_out',
    'status_draft'    => 'draft',
    'status_archived' => 'archived',
];

if (isset($status_actions[$action])) {
    $new_status = $status_actions[$action];
    $active     = product_active_for_status($new_status);
    $stmt = $pdo->prepare("UPDATE products SET status = ?, active = ? WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$new_status, $active], $ids));
    $msg = $count . ' product(s) marked as ' . product_status_label($new_status) . '.';

} elseif ($action === 'feature') {
    $stmt = $pdo->prepare("UPDATE products SET featured = 1 WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $msg = $count . ' product(s) featured.';

} elseif ($action === 'unfeature') {
    $stmt = $pdo->prepare("UPDATE products SET featured = 0 WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $msg = $count . ' product(s) unfeatured.';

} elseif ($action === 'delete') {
    // Remove uploaded image files first (best-effort).
    $img_stmt = $pdo->prepare("SELECT image_path FROM products WHERE id IN ($placeholders) AND image_path IS NOT NULL");
    $img_stmt->execute($ids);
    foreach ($img_stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        $abs = __DIR__ . '/..' . $path;
        if (is_file($abs)) @unlink($abs);
    }
    $gal_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id IN ($placeholders)");
    $gal_stmt->execute($ids);
    foreach ($gal_stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        $abs = __DIR__ . '/..' . $path;
        if (is_file($abs)) @unlink($abs);
    }
    $del = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
    $del->execute($ids);
    $msg = $count . ' product(s) deleted.';
}

header('Location: /admin/products.php?msg=' . urlencode($msg));
exit;
