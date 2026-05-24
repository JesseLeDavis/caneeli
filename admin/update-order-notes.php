<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(403);
    exit;
}

$id              = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$tracking_number = trim($_POST['tracking_number'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

if (!$id) {
    header('Location: /admin/orders.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("UPDATE orders SET tracking_number = ?, notes = ? WHERE id = ?");
$stmt->execute([
    $tracking_number !== '' ? $tracking_number : null,
    $notes           !== '' ? $notes           : null,
    $id,
]);

header('Location: /admin/order-detail.php?id=' . $id . '#notes');
exit;
