<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/product_status.php';

header('Content-Type: application/json');

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    fail('Forbidden', 403);
}

$id    = (int) ($_POST['id']    ?? 0);
$field = $_POST['field']         ?? '';
$value = $_POST['value']         ?? '';

if (!$id) fail('Missing id');

$pdo = getDB();

switch ($field) {
    case 'price':
        $v = (float) $value;
        if ($v < 0) fail('Price cannot be negative');
        $pdo->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$v, $id]);
        echo json_encode(['ok' => true, 'value' => $v]);
        break;

    case 'stock_qty':
        $v = (int) $value;
        if ($v < 0) fail('Stock cannot be negative');
        $pdo->prepare("UPDATE products SET stock_qty = ? WHERE id = ?")->execute([$v, $id]);
        echo json_encode(['ok' => true, 'value' => $v]);
        break;

    case 'status':
        if (!in_array($value, PRODUCT_STATUSES, true)) fail('Invalid status');
        $active = product_active_for_status($value);
        $pdo->prepare("UPDATE products SET status = ?, active = ? WHERE id = ?")
            ->execute([$value, $active, $id]);
        echo json_encode(['ok' => true, 'value' => $value]);
        break;

    default:
        fail('Unknown field');
}
