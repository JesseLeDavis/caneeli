<?php
/**
 * Turns a paid shop Checkout Session into an order. The ONE place this
 * happens: called by both webhook.php (Stripe's server-to-server ping) and
 * success.php (the customer's browser landing back on the site).
 *
 * Whichever arrives first saves the order; the other finds it and does
 * nothing. Previously each had its own copy of this logic, and when the
 * success page won the race the webhook bailed out early, so the customer's
 * confirmation email, Annie's BCC'd copy and the discount usage count were
 * all silently skipped.
 *
 * Custom-quote deposits/balances are NOT handled here; webhook.php routes
 * those on metadata.type before ever calling this.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/emails/templates.php';
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Save the order for a Checkout Session if it's paid and not saved yet.
 * Returns the order id, or null if the session isn't a paid shop order.
 * Throws on database/Stripe failure (the webhook turns that into a 500 so
 * Stripe retries).
 */
function fulfill_checkout_session(PDO $pdo, string $sessionId): ?int {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $existing = fulfillment_order_id($pdo, $sessionId);
    if ($existing) {
        fulfillment_send_confirmation($pdo, $existing); // no-op if already sent
        return $existing;
    }

    // price.product carries our product_id (set in checkout.php).
    $session = \Stripe\Checkout\Session::retrieve([
        'id'     => $sessionId,
        'expand' => ['line_items.data.price.product'],
    ]);

    return fulfillment_save_order($pdo, $session);
}

/**
 * The database half of fulfill_checkout_session(), given the retrieved
 * session (with line_items.data.price.product expanded). Split out so it can
 * be exercised without a live Stripe call.
 */
function fulfillment_save_order(PDO $pdo, $session): ?int {
    if (($session->payment_status ?? '') !== 'paid') return null;
    if (!empty($session->metadata->type)) return null; // custom quote payment

    $sessionId = $session->id;
    $items = fulfillment_items($session);

    $shipping = null;
    $sd = $session->collected_information->shipping_details ?? $session->shipping_details ?? null;
    if (!empty($sd->address)) {
        $a = $sd->address;
        $shipping = trim(implode("\n", array_filter([
            $sd->name ?? '',
            $a->line1 ?? '',
            $a->line2 ?? '',
            trim(($a->city ?? '') . ', ' . ($a->state ?? '') . ' ' . ($a->postal_code ?? ''), ', '),
            $a->country ?? '',
        ])));
    }

    $discountCode   = $session->metadata->discount_code ?? null;
    $discountAmount = isset($session->total_details->amount_discount)
        ? $session->total_details->amount_discount / 100
        : null;

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO orders
                (stripe_session_id, stripe_payment_intent, customer_name, customer_email, status, total, shipping_address, discount_code, discount_amount)
            VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?)
        ")->execute([
            $session->id,
            $session->payment_intent ?? null,
            $session->customer_details->name ?? '',
            $session->customer_details->email ?? '',
            $session->amount_total / 100,
            $shipping,
            $discountCode ?: null,
            $discountAmount,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt  = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price_at_purchase) VALUES (?, ?, ?, ?, ?)");
        $lockStmt  = $pdo->prepare("SELECT name, stock_qty FROM products WHERE id = ? FOR UPDATE");
        $stockStmt = $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?");
        $soldStmt  = $pdo->prepare("UPDATE products SET status = 'sold_out' WHERE id = ? AND stock_qty = 0 AND status = 'active'");
        $oversold  = [];

        foreach ($items as $it) {
            $itemStmt->execute([$orderId, $it['product_id'], $it['name'], $it['qty'], $it['price']]);
            if (!$it['product_id']) continue;

            // Stock isn't held while someone is on Stripe's page, so two people
            // can pay for the last piece. Can't undo a payment here; flag it
            // loudly so Annie can refund or make another.
            $lockStmt->execute([$it['product_id']]);
            $p = $lockStmt->fetch();
            if ($p && (int) $p['stock_qty'] < $it['qty']) {
                $oversold[] = $it['name'] . ' (ordered ' . $it['qty'] . ', only ' . (int) $p['stock_qty'] . ' left)';
            }
            $stockStmt->execute([$it['qty'], $it['product_id']]);
            $soldStmt->execute([$it['product_id']]);
        }

        if ($oversold) {
            $pdo->prepare("UPDATE orders SET notes = ? WHERE id = ?")
                ->execute(['⚠ OVERSOLD — not enough stock when this was paid: ' . implode('; ', $oversold) . '. Refund or arrange a remake.', $orderId]);
            error_log("order $orderId oversold: " . implode('; ', $oversold));
        }

        if ($discountCode) {
            $pdo->prepare("UPDATE discount_codes SET times_used = times_used + 1 WHERE code = ?")
                ->execute([$discountCode]);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Lost the race to the other caller: the unique stripe_session_id
        // index rejected our insert, and their order is the one that counts.
        if ($e->getCode() === '23000' && ($id = fulfillment_order_id($pdo, $sessionId))) {
            return $id;
        }
        throw $e;
    }

    fulfillment_send_confirmation($pdo, $orderId);
    return $orderId;
}

function fulfillment_order_id(PDO $pdo, string $sessionId): ?int {
    $s = $pdo->prepare("SELECT id FROM orders WHERE stripe_session_id = ?");
    $s->execute([$sessionId]);
    $id = $s->fetchColumn();
    return $id ? (int) $id : null;
}

/**
 * What was actually paid for. Line items carry product_id in their Stripe
 * product metadata; sessions created before that change carried a JSON
 * `cart` in session metadata instead, still honoured for in-flight checkouts.
 */
function fulfillment_items($session): array {
    $legacy = json_decode($session->metadata->cart ?? '', true);
    if (is_array($legacy) && $legacy) {
        return array_map(fn($i) => [
            'product_id' => !empty($i['product_id']) ? (int) $i['product_id'] : null,
            'name'       => (string) ($i['name'] ?? ''),
            'qty'        => max(1, (int) ($i['qty'] ?? 1)),
            'price'      => (float) ($i['price'] ?? 0),
        ], $legacy);
    }

    $items = [];
    foreach ($session->line_items->data ?? [] as $li) {
        $product = $li->price->product ?? null;
        $pid     = is_object($product) ? ($product->metadata->product_id ?? null) : null;
        $qty     = max(1, (int) $li->quantity);
        $items[] = [
            'product_id' => $pid ? (int) $pid : null,
            'name'       => is_object($product) ? $product->name : ($li->description ?? ''),
            'qty'        => $qty,
            // Pre-discount unit price; the discount is recorded on the order.
            'price'      => isset($li->price->unit_amount) ? $li->price->unit_amount / 100 : ($li->amount_subtotal / $qty) / 100,
        ];
    }
    return $items;
}

/**
 * Email the customer (Annie BCC'd) exactly once. The timestamp is claimed
 * before sending so the webhook and success page can't both send it, and
 * released again if the send fails so a later call retries.
 */
function fulfillment_send_confirmation(PDO $pdo, int $orderId): void {
    try {
        $claim = $pdo->prepare("UPDATE orders SET confirmation_email_sent_at = CURRENT_TIMESTAMP WHERE id = ? AND confirmation_email_sent_at IS NULL AND customer_email <> ''");
        $claim->execute([$orderId]);
        if ($claim->rowCount() !== 1) return;

        $o = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $o->execute([$orderId]);
        $order = $o->fetch();
        $l = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
        $l->execute([$orderId]);

        $sent = send_mail(
            $order['customer_email'],
            $order['customer_name'] ?? '',
            'Your ' . SITE_NAME . ' order #' . $order['id'],
            build_order_confirmation_email($order, $l->fetchAll()),
            '',
            OWNER_EMAIL !== '' ? OWNER_EMAIL : null
        );
        if (!$sent) {
            $pdo->prepare("UPDATE orders SET confirmation_email_sent_at = NULL WHERE id = ?")->execute([$orderId]);
            error_log("order $orderId: confirmation email not sent");
        }
    } catch (\Throwable $e) {
        error_log("order $orderId: confirmation email failed: " . $e->getMessage());
    }
}
