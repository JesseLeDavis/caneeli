<?php
/**
 * Pre-order deposits and balances.
 *
 * A pre-order product with a preorder_deposit charges that much per piece at
 * checkout. The order is saved as 'deposit_paid' with balance_due set, so the
 * existing ship gate (admin/update-order-status.php) holds it until the rest
 * is paid. When the batch is ready Annie requests balances; each buyer gets a
 * private link (preorder-pay.php?t=<token>) that opens a Stripe Checkout for
 * the balance. Paying it lands in preorder_settle_balance(), from the webhook
 * or the pay page, whichever comes first.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/emails/templates.php';

/**
 * The deposit per piece for a pre-order product, or null when buyers pay in
 * full. Only a deposit between zero and the price counts.
 */
function preorder_deposit_for(array $product): ?float {
    $d = (float) ($product['preorder_deposit'] ?? 0);
    return ($d > 0 && $d < (float) $product['price']) ? $d : null;
}

function preorder_pay_url(array $order): string {
    return SITE_URL . '/preorder-pay.php?t=' . urlencode((string) $order['preorder_token']);
}

/** Photo of the piece on a pre-order, if the product still has one. */
function preorder_product_image(PDO $pdo, int $orderId): ?string {
    $s = $pdo->prepare("SELECT p.image_path FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ? ORDER BY i.id LIMIT 1");
    $s->execute([$orderId]);
    return $s->fetchColumn() ?: null;
}

/** Name of the piece on a pre-order (its first line item). */
function preorder_product_name(PDO $pdo, int $orderId): string {
    $s = $pdo->prepare("SELECT product_name FROM order_items WHERE order_id = ? ORDER BY id LIMIT 1");
    $s->execute([$orderId]);
    return preg_replace('/^(Pre-order|Deposit): /', '', (string) $s->fetchColumn());
}

/**
 * Email one buyer their balance link and stamp the request. The request is
 * recorded even if the email fails, so the link works and Annie can share it
 * herself. Returns whether the email went out.
 */
function preorder_request_balance(PDO $pdo, array $order, string $note = ''): bool {
    if ((float) $order['balance_due'] <= 0) return false;

    if (empty($order['preorder_token'])) {
        $order['preorder_token'] = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE orders SET preorder_token = ? WHERE id = ?")->execute([$order['preorder_token'], $order['id']]);
    }
    $pdo->prepare("UPDATE orders SET preorder_balance_requested_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$order['id']]);

    return send_mail(
        $order['customer_email'],
        $order['customer_name'] ?? '',
        'Your ' . preorder_product_name($pdo, (int) $order['id']) . ' is ready',
        build_preorder_balance_email($order, preorder_product_name($pdo, (int) $order['id']), preorder_pay_url($order), $note, preorder_product_image($pdo, (int) $order['id'])),
        '',
        null
    );
}

/**
 * Record a paid balance session against its order. Safe to call twice: only
 * an order that still owes money is changed. Returns the order id or null.
 */
function preorder_settle_balance(PDO $pdo, $session): ?int {
    if (($session->metadata->type ?? '') !== 'preorder_balance') return null;
    if (($session->payment_status ?? '') !== 'paid') return null;
    $orderId = (int) ($session->metadata->order_id ?? 0);

    $u = $pdo->prepare("
        UPDATE orders
           SET amount_paid = total, balance_due = 0, status = 'paid',
               preorder_balance_payment_intent = ?
         WHERE id = ? AND is_preorder = 1 AND balance_due > 0
    ");
    $u->execute([$session->payment_intent ?? null, $orderId]);
    if ($u->rowCount() !== 1) return $orderId ?: null; // already settled

    try {
        $o = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $o->execute([$orderId]);
        $order = $o->fetch();
        send_mail(
            $order['customer_email'],
            $order['customer_name'] ?? '',
            'Balance received for your ' . SITE_NAME . ' pre-order',
            build_preorder_balance_receipt_email($order, preorder_product_name($pdo, $orderId), (float) ($session->amount_total ?? 0) / 100, preorder_product_image($pdo, $orderId)),
            '',
            OWNER_EMAIL !== '' ? OWNER_EMAIL : null
        );
    } catch (\Throwable $e) {
        error_log("order $orderId: pre-order balance receipt failed: " . $e->getMessage());
    }
    return $orderId;
}
