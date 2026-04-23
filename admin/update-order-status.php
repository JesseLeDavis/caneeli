<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(403);
    exit;
}

$id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = $_POST['status'] ?? '';

$allowed = ['pending', 'paid', 'fulfilled', 'cancelled'];
if (!$id || !in_array($status, $allowed, true)) {
    header('Location: /admin/orders.php');
    exit;
}

$pdo = getDB();

if ($status === 'fulfilled') {
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, fulfilled_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$status, $id]);
} else {
    // Clear fulfilled_at if the user reverts off fulfilled.
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, fulfilled_at = NULL WHERE id = ?");
    $stmt->execute([$status, $id]);
}

header('Location: /admin/order-detail.php?id=' . $id);
exit;
