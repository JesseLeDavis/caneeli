<?php
$pageTitle = "Order Confirmed";
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$session_id = $_GET['session_id'] ?? '';
$order_saved = false;

if ($session_id && !empty($_SESSION['cart'])) {
    try {
        $stripe_session = \Stripe\Checkout\Session::retrieve($session_id);

        if ($stripe_session->payment_status === 'paid') {
            $pdo = getDB();

            // Check we haven't already saved this order
            $check = $pdo->prepare("SELECT id FROM orders WHERE stripe_session_id = ?");
            $check->execute([$session_id]);

            if (!$check->fetch()) {
                $shipping = null;
                $shipping_details = $stripe_session->collected_information->shipping_details
                    ?? $stripe_session->shipping_details
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

                $discount_code   = $stripe_session->metadata->discount_code ?? null;
                $discount_amount = isset($stripe_session->total_details->amount_discount)
                    ? $stripe_session->total_details->amount_discount / 100
                    : null;

                $pdo->prepare("
                    INSERT INTO orders (stripe_session_id, stripe_payment_intent, customer_name, customer_email, status, total, shipping_address, discount_code, discount_amount)
                    VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?)
                ")->execute([
                    $session_id,
                    $stripe_session->payment_intent ?? null,
                    $stripe_session->customer_details->name,
                    $stripe_session->customer_details->email,
                    $stripe_session->amount_total / 100,
                    $shipping,
                    $discount_code,
                    $discount_amount,
                ]);

                $order_id = $pdo->lastInsertId();

                // Save order items and decrement stock
                $ids          = array_keys($_SESSION['cart']);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt         = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $products = $stmt->fetchAll();

                foreach ($products as $product) {
                    $qty = $_SESSION['cart'][$product['id']];

                    $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, product_name, quantity, price_at_purchase)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([
                        $order_id,
                        $product['id'],
                        $product['name'],
                        $qty,
                        $product['price'],
                    ]);

                    // Decrement stock
                    $pdo->prepare("
                        UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?)
                        WHERE id = ?
                    ")->execute([$qty, $product['id']]);

                    // Flip to sold_out if stock hit 0 (keeps the product visible
                    // in the shop but blocks add-to-cart via the status check).
                    $pdo->prepare("
                        UPDATE products SET status = 'sold_out'
                        WHERE id = ? AND stock_qty = 0 AND status = 'active'
                    ")->execute([$product['id']]);
                }
            }

            // Clear the cart + any applied discount.
            unset($_SESSION['cart'], $_SESSION['discount_code_id']);
            $order_saved = true;
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Session retrieval failed — still show success but don't process
    }
}

// Look up order details for display (works whether we just saved or
// the customer refreshed the page).
$order         = null;
$order_items   = [];
if ($session_id) {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE stripe_session_id = ?");
        $stmt->execute([$session_id]);
        $order = $stmt->fetch();

        if ($order) {
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $order_items = $stmt->fetchAll();
        }
    } catch (\Throwable $e) {
        // Fall through to the generic confirmation view.
    }
}
?>

<section class="hero">
    <div class="container">
        <div class="checkout-status checkout-status--success">
            <div class="checkout-status__intro">
                <p class="checkout-status__eyebrow">Order Confirmed</p>
                <h1 class="large_title">You're all set.</h1>
                <p class="checkout-status__lede">Order received — you'll get a confirmation email shortly. I'll be in touch once it's on its way.</p>
                <p class="checkout-status__lede">Thanks for supporting my little shop. It genuinely means a lot.</p>
                <p class="checkout-status__sign-off">-Annie</p>
            </div>

            <?php if ($order): ?>
                <div class="checkout-status__card">
                    <div class="checkout-status__card-header">
                        <div>
                            <p class="checkout-status__label">Order</p>
                            <p class="checkout-status__order-number">#<?php echo str_pad((string) $order['id'], 5, '0', STR_PAD_LEFT); ?></p>
                        </div>
                        <div>
                            <p class="checkout-status__label">Placed</p>
                            <p class="checkout-status__order-date"><?php echo date('M j, Y', strtotime($order['created_at'] ?? 'now')); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($order_items)): ?>
                        <ul class="checkout-status__items">
                            <?php foreach ($order_items as $item): ?>
                                <li class="checkout-status__item">
                                    <div class="checkout-status__item-name">
                                        <span><?php echo sanitize($item['product_name']); ?></span>
                                        <span class="checkout-status__item-qty">× <?php echo (int) $item['quantity']; ?></span>
                                    </div>
                                    <span class="checkout-status__item-price">
                                        <?php echo formatPrice($item['price_at_purchase'] * $item['quantity']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($order['discount_amount']) && (float) $order['discount_amount'] > 0): ?>
                        <div class="checkout-status__discount">
                            <span>
                                Discount
                                <?php if (!empty($order['discount_code'])): ?>
                                    <small>(<?php echo htmlspecialchars($order['discount_code']); ?>)</small>
                                <?php endif; ?>
                            </span>
                            <span>&minus;<?php echo formatPrice($order['discount_amount']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="checkout-status__total">
                        <span>Total</span>
                        <span><?php echo formatPrice($order['total']); ?></span>
                    </div>

                    <?php if (!empty($order['shipping_address'])): ?>
                        <div class="checkout-status__shipping">
                            <p class="checkout-status__label">Shipping to</p>
                            <address><?php echo nl2br(sanitize($order['shipping_address'])); ?></address>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <a href="/pages/shop/" class="btn blue-button checkout-status__cta">Keep Browsing</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
