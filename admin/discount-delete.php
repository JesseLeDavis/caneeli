<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    header('Location: /admin/discounts.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    getDB()->prepare("DELETE FROM discount_codes WHERE id = ?")->execute([$id]);
}
header('Location: /admin/discounts.php?msg=' . urlencode('Code deleted.'));
exit;
