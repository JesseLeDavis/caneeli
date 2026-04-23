<?php
// Stripe Webhook Handler
// Register this URL in your Stripe dashboard under Developers → Webhooks
// URL: https://yourdomain.com/webhook.php
// Events to listen for: checkout.session.completed

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$payload   = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        STRIPE_WEBHOOK_SECRET
    );
} catch (\Exception $e) {
    http_response_code(400);
    exit;
}

if ($event->type !== 'checkout.session.completed') {
    http_response_code(200);
    exit;
}

$stripe_session = $event->data->object;

$pdo   = getDB();
$check = $pdo->prepare("SELECT id FROM orders WHERE stripe_session_id = ?");
$check->execute([$stripe_session->id]);
if ($check->fetch()) {
    http_response_code(200);
    exit;
}

$full_session = \Stripe\Checkout\Session::retrieve([
    'id'     => $stripe_session->id,
    'expand' => ['line_items'],
]);

$shipping = null;
if (!empty($full_session->shipping_details->address)) {
    $addr = $full_session->shipping_details->address;
    $shipping = trim(implode("\n", array_filter([
        $full_session->shipping_details->name ?? '',
        $addr->line1 ?? '',
        $addr->line2 ?? '',
        trim(($addr->city ?? '') . ', ' . ($addr->state ?? '') . ' ' . ($addr->postal_code ?? ''), ', '),
        $addr->country ?? '',
    ])));
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO orders
            (stripe_session_id, stripe_payment_intent, customer_name, customer_email, status, total, shipping_address)
        VALUES (?, ?, ?, ?, 'paid', ?, ?)
    ")->execute([
        $stripe_session->id,
        $stripe_session->payment_intent ?? null,
        $full_session->customer_details->name ?? '',
        $full_session->customer_details->email ?? '',
        $stripe_session->amount_total / 100,
        $shipping,
    ]);

    $order_id = (int) $pdo->lastInsertId();

    // Prefer our own cart snapshot (has product_id) over Stripe's line_items.
    $cart_json = $full_session->metadata->cart ?? null;
    $cart      = $cart_json ? json_decode($cart_json, true) : null;

    $item_stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, quantity, price_at_purchase)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (is_array($cart) && $cart) {
        foreach ($cart as $item) {
            $item_stmt->execute([
                $order_id,
                $item['product_id'] ?? null,
                $item['name'] ?? '',
                (int) ($item['qty'] ?? 1),
                (float) ($item['price'] ?? 0),
            ]);
        }
    } elseif (!empty($full_session->line_items->data)) {
        // Fallback: reconstruct from Stripe line items if metadata is missing.
        foreach ($full_session->line_items->data as $li) {
            $item_stmt->execute([
                $order_id,
                null,
                $li->description ?? '',
                (int) $li->quantity,
                ($li->amount_total / max(1, $li->quantity)) / 100,
            ]);
        }
    }

    // Bump usage counter for the applied discount code (if any).
    $applied_code = $full_session->metadata->discount_code ?? null;
    if ($applied_code) {
        $pdo->prepare("UPDATE discount_codes SET times_used = times_used + 1 WHERE code = ?")
            ->execute([$applied_code]);
    }

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit;
}

http_response_code(200);
