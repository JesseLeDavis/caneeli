<?php
// Stripe Webhook Handler
// Register this URL in your Stripe dashboard under Developers → Webhooks
// URL: https://yourdomain.com/webhook.php
// Events to listen for: checkout.session.completed

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/emails/templates.php';
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
$shipping_details = $full_session->collected_information->shipping_details
    ?? $full_session->shipping_details
    ?? null;
if (!empty($shipping_details->address)) {
    $addr = $shipping_details->address;
    $shipping = trim(implode("\n", array_filter([
        $shipping_details->name ?? '',
        $addr->line1 ?? '',
        $addr->line2 ?? '',
        trim(($addr->city ?? '') . ', ' . ($addr->state ?? '') . ' ' . ($addr->postal_code ?? ''), ', '),
        $addr->country ?? '',
    ])));
}

// --- Custom-order balance -----------------------------------------------
// Settles an existing order rather than creating one. Reaching balance_due 0
// and status 'paid' is what releases the fulfillment gate.
if (($full_session->metadata->type ?? '') === 'custom_balance') {
    $quote_id = (int) ($full_session->metadata->quote_id ?? 0);

    $qstmt = $pdo->prepare("SELECT * FROM custom_quotes WHERE id = ?");
    $qstmt->execute([$quote_id]);
    $quote = $qstmt->fetch();

    if (!$quote || empty($quote['order_id'])) {
        error_log('webhook: custom_balance for unknown/unconverted quote ' . $quote_id);
        http_response_code(200);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Guarded on balance_due > 0 so a redelivered event — or a balance
        // already settled manually — can't double-apply.
        $pdo->prepare("
            UPDATE orders
               SET amount_paid = total,
                   balance_due = 0,
                   status = 'paid',
                   stripe_payment_intent = COALESCE(stripe_payment_intent, ?)
             WHERE id = ? AND balance_due > 0
        ")->execute([
            $stripe_session->payment_intent ?? null,
            (int) $quote['order_id'],
        ]);

        // Keep the balance's payment intent — Stripe doesn't link the two
        // charges, so without this there's no way back to the balance payment
        // from the admin (the order keeps the deposit's intent).
        $pdo->prepare("UPDATE custom_quotes SET status = 'paid', balance_payment_intent = ? WHERE id = ?")
            ->execute([$stripe_session->payment_intent ?? null, $quote_id]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('webhook: custom balance failed for quote ' . $quote_id . ': ' . $e->getMessage());
        http_response_code(500);
        exit;
    }

    try {
        $sent = send_mail(
            $quote['customer_email'],
            $quote['customer_name'],
            'Payment received — your ' . SITE_NAME . ' piece ships soon',
            build_balance_receipt_email($quote),
            '',
            OWNER_EMAIL !== '' ? OWNER_EMAIL : null
        );
        if (!$sent) {
            error_log('webhook: balance receipt not sent for quote ' . $quote_id);
        }
    } catch (\Throwable $e) {
        error_log('webhook: balance receipt failed for quote ' . $quote_id . ': ' . $e->getMessage());
    }

    http_response_code(200);
    exit;
}

// --- Custom-order deposit -----------------------------------------------
// Must be handled before the cart path below: a deposit session has no `cart`
// metadata, so it would otherwise fall through and create a junk empty order.
if (($full_session->metadata->type ?? '') === 'custom_deposit') {
    $quote_id = (int) ($full_session->metadata->quote_id ?? 0);

    $qstmt = $pdo->prepare("SELECT * FROM custom_quotes WHERE id = ?");
    $qstmt->execute([$quote_id]);
    $quote = $qstmt->fetch();

    if (!$quote) {
        error_log('webhook: custom_deposit for unknown quote ' . $quote_id);
        http_response_code(200);
        exit;
    }

    $total   = (float) $quote['total'];
    $deposit = (float) $quote['deposit_amount'];

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO orders
                (quote_id, stripe_session_id, stripe_payment_intent, customer_name, customer_email,
                 status, total, amount_paid, balance_due, shipping_address)
            VALUES (?, ?, ?, ?, ?, 'deposit_paid', ?, ?, ?, ?)
        ")->execute([
            $quote_id,
            $stripe_session->id,
            $stripe_session->payment_intent ?? null,
            $full_session->customer_details->name ?? $quote['customer_name'],
            $full_session->customer_details->email ?? $quote['customer_email'],
            $total,
            $deposit,
            $total - $deposit,
            $shipping,
        ]);

        $order_id = (int) $pdo->lastInsertId();

        // One line item for the piece itself, priced at the full total —
        // amount_paid/balance_due on the order track what's actually been
        // collected. product_id is null: custom pieces aren't in `products`,
        // so there's no stock to decrement.
        $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, quantity, price_at_purchase)
            VALUES (?, NULL, ?, 1, ?)
        ")->execute([$order_id, $quote['title'], $total]);

        $pdo->prepare("UPDATE custom_quotes SET status = 'deposit_paid', order_id = ? WHERE id = ?")
            ->execute([$order_id, $quote_id]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('webhook: custom deposit failed for quote ' . $quote_id . ': ' . $e->getMessage());
        http_response_code(500);
        exit;
    }

    // Receipt. Same fail-safe treatment as the cart confirmation: a mail
    // failure must not turn a successful payment into a 500.
    try {
        if (empty($quote['deposit_email_sent_at'])) {
            $sent = send_mail(
                $quote['customer_email'],
                $quote['customer_name'],
                'Deposit received for your ' . SITE_NAME . ' commission',
                build_deposit_receipt_email($quote),
                '',
                OWNER_EMAIL !== '' ? OWNER_EMAIL : null
            );
            if ($sent) {
                $pdo->prepare("UPDATE custom_quotes SET deposit_email_sent_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$quote_id]);
            }
        }
    } catch (\Throwable $e) {
        error_log('webhook: deposit receipt failed for quote ' . $quote_id . ': ' . $e->getMessage());
    }

    http_response_code(200);
    exit;
}

try {
    $pdo->beginTransaction();

    $discount_code   = $full_session->metadata->discount_code ?? null;
    $discount_amount = isset($full_session->total_details->amount_discount)
        ? $full_session->total_details->amount_discount / 100
        : null;

    $pdo->prepare("
        INSERT INTO orders
            (stripe_session_id, stripe_payment_intent, customer_name, customer_email, status, total, shipping_address, discount_code, discount_amount)
        VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?)
    ")->execute([
        $stripe_session->id,
        $stripe_session->payment_intent ?? null,
        $full_session->customer_details->name ?? '',
        $full_session->customer_details->email ?? '',
        $stripe_session->amount_total / 100,
        $shipping,
        $discount_code,
        $discount_amount,
    ]);

    $order_id = (int) $pdo->lastInsertId();

    // Prefer our own cart snapshot (has product_id) over Stripe's line_items.
    $cart_json = $full_session->metadata->cart ?? null;
    $cart      = $cart_json ? json_decode($cart_json, true) : null;

    $item_stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, quantity, price_at_purchase)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stock_stmt    = $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?");
    $sold_out_stmt = $pdo->prepare("UPDATE products SET status = 'sold_out' WHERE id = ? AND stock_qty = 0 AND status = 'active'");

    if (is_array($cart) && $cart) {
        foreach ($cart as $item) {
            $item_stmt->execute([
                $order_id,
                $item['product_id'] ?? null,
                $item['name'] ?? '',
                (int) ($item['qty'] ?? 1),
                (float) ($item['price'] ?? 0),
            ]);
            if (!empty($item['product_id'])) {
                $stock_stmt->execute([(int) ($item['qty'] ?? 1), (int) $item['product_id']]);
                $sold_out_stmt->execute([(int) $item['product_id']]);
            }
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

// Send the order confirmation email. Isolated from the DB work above: a mail
// failure must never turn a successful order into a 500 (Stripe would retry and
// the dedup check would just skip re-insertion anyway).
try {
    $order_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch();

    $line_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
    $line_stmt->execute([$order_id]);
    $line_items = $line_stmt->fetchAll();

    if ($order && !empty($order['customer_email']) && empty($order['confirmation_email_sent_at'])) {
        $sent = send_mail(
            $order['customer_email'],
            $order['customer_name'] ?? '',
            'Your ' . SITE_NAME . ' order #' . $order['id'],
            build_order_confirmation_email($order, $line_items),
            '',
            OWNER_EMAIL !== '' ? OWNER_EMAIL : null
        );
        if ($sent) {
            $pdo->prepare("UPDATE orders SET confirmation_email_sent_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$order_id]);
        }
    }
} catch (\Throwable $e) {
    error_log('webhook: confirmation email failed for order ' . $order_id . ': ' . $e->getMessage());
}

http_response_code(200);
