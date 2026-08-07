<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/emails/templates.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(403);
    exit;
}

$id              = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status          = $_POST['status'] ?? '';
$tracking_number = trim($_POST['tracking_number'] ?? '');

$allowed = ['pending', 'paid', 'fulfilled', 'cancelled'];
if (!$id || !in_array($status, $allowed, true)) {
    header('Location: /admin/orders.php');
    exit;
}

$pdo = getDB();

if ($status === 'fulfilled') {
    // Guard: fulfilling without a tracking number silently skips the shipping
    // email (the send below requires one). That's almost always a mis-click on
    // the status dropdown instead of the "Mark as fulfilled" button, so bounce
    // it back rather than quietly leaving the customer un-notified. Orders with
    // no customer email are exempt — there's nobody to notify either way.
    $existing = $pdo->prepare("SELECT customer_email, tracking_number FROM orders WHERE id = ?");
    $existing->execute([$id]);
    $existing = $existing->fetch();

    if ($existing
        && !empty($existing['customer_email'])
        && $tracking_number === ''
        && empty($existing['tracking_number'])
    ) {
        header('Location: /admin/order-detail.php?id=' . $id . '&fulfill=notracking');
        exit;
    }

    // Marking fulfilled also captures the tracking number (the admin prompts
    // for it as part of this action) and triggers the shipping email.
    if ($tracking_number !== '') {
        $pdo->prepare("UPDATE orders SET status = ?, fulfilled_at = CURRENT_TIMESTAMP, tracking_number = ? WHERE id = ?")
            ->execute([$status, $tracking_number, $id]);
    } else {
        $pdo->prepare("UPDATE orders SET status = ?, fulfilled_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$status, $id]);
    }

    // Send the shipping notification. Only once, only if we have an email + a
    // tracking number. Failures are logged, never fatal — the status change
    // already succeeded.
    try {
        $order = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $order->execute([$id]);
        $order = $order->fetch();

        if ($order
            && !empty($order['customer_email'])
            && !empty($order['tracking_number'])
            && empty($order['shipping_email_sent_at'])
        ) {
            $sent = send_mail(
                $order['customer_email'],
                $order['customer_name'] ?? '',
                'Your ' . SITE_NAME . ' order has shipped',
                build_shipping_email($order)
            );
            if ($sent) {
                $pdo->prepare("UPDATE orders SET shipping_email_sent_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$id]);
            }
        }
    } catch (\Throwable $e) {
        error_log('fulfill: shipping email failed for order ' . $id . ': ' . $e->getMessage());
    }
} else {
    // Clear fulfilled_at if the user reverts off fulfilled.
    $pdo->prepare("UPDATE orders SET status = ?, fulfilled_at = NULL WHERE id = ?")
        ->execute([$status, $id]);
}

header('Location: /admin/order-detail.php?id=' . $id);
exit;
