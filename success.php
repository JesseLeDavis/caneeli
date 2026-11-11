<?php
$pageTitle = "Order Confirmed";
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/fulfillment.php';

$session_id = $_GET['session_id'] ?? '';

// Save the order now if Stripe's webhook hasn't yet (it usually lands within
// a second or two either way). Same code path as webhook.php, built from what
// Stripe says was paid, not from this browser's cart.
if ($session_id !== '') {
    try {
        if (fulfill_checkout_session(getDB(), $session_id)) {
            unset($_SESSION['cart'], $_SESSION['discount_code_id']);
        }
    } catch (\Throwable $e) {
        // The webhook will still save it; show the generic confirmation.
        error_log('success page: ' . $e->getMessage());
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
