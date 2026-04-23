<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$is_ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

function respond_and_exit(bool $ok, string $redirect = '/admin/products.php'): void {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok]);
    } else {
        header('Location: ' . $redirect);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    respond_and_exit(false);
}

$id = intval($_POST['id'] ?? 0);
if (!$id) {
    respond_and_exit(false);
}

$pdo = getDB();
$pdo->prepare("UPDATE products SET featured = NOT featured WHERE id = ?")->execute([$id]);

respond_and_exit(true);
