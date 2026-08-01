<?php
/**
 * Persist a drag-reorder of the admin product list.
 *
 * Mirrors reorder-images.php, with one difference: the list can be filtered, so
 * the dragged rows are usually a SUBSET of the catalogue. Rather than numbering
 * them 0..n (which would yank them above everything hidden), this reuses the
 * sort_order values those rows already hold and redistributes them in the new
 * order. Positions relative to filtered-out products are preserved.
 */

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

$order = $_POST['order'] ?? [];
if (!is_array($order) || !$order) {
    fail('Missing order');
}
$order = array_values(array_unique(array_filter(array_map('intval', $order))));
if (!$order) {
    fail('Missing order');
}

$pdo = getDB();

// Only touch ids that are really products — never trust the posted list.
$placeholders = implode(',', array_fill(0, count($order), '?'));
$stmt = $pdo->prepare("SELECT id, sort_order FROM products WHERE id IN ($placeholders)");
$stmt->execute($order);
$existing = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (count($existing) !== count($order)) {
    fail('Product set mismatch', 403);
}

// The pool of positions these rows occupy, lowest first. Reassigning it in the
// dragged order is what actually performs the move.
$slots = array_values($existing);
sort($slots, SORT_NUMERIC);

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("UPDATE products SET sort_order = ? WHERE id = ?");
    foreach ($order as $i => $id) {
        $update->execute([$slots[$i], $id]);
    }
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('reorder-products failed: ' . $e->getMessage());
    fail('Could not save order', 500);
}

echo json_encode(['ok' => true]);
