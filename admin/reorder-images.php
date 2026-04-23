<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    fail('Forbidden', 403);
}

$product_id = (int) ($_POST['product_id'] ?? 0);
$order      = $_POST['order']      ?? [];
if (!$product_id || !is_array($order) || !$order) {
    fail('Missing product or order');
}
$order = array_values(array_filter(array_map('intval', $order)));

$pdo = getDB();

// Verify every image id belongs to this product before trusting the reorder.
$placeholders = implode(',', array_fill(0, count($order), '?'));
$check = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND id IN ($placeholders)");
$check->execute(array_merge([$product_id], $order));
if ((int) $check->fetchColumn() !== count($order)) {
    fail('Image set mismatch', 403);
}

$pdo->beginTransaction();
$update = $pdo->prepare("UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?");
foreach ($order as $i => $id) {
    $update->execute([$i, $id, $product_id]);
}
$pdo->commit();

echo json_encode(['ok' => true]);
