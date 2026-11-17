<?php
$pageTitle = "Order Confirmed";
$noindex = true; // private or transactional: keep out of search results
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
            // Photos come from the product, if it still exists.
            $stmt = $pdo->prepare("SELECT i.*, p.image_path FROM order_items i LEFT JOIN products p ON p.id = i.product_id WHERE i.order_id = ? ORDER BY i.id");
            $stmt->execute([$order['id']]);
            $order_items = $stmt->fetchAll();
        }
    } catch (\Throwable $e) {
        // Fall through to the generic confirmation view.
    }
}
?>

<?php
$first     = $order ? trim(explode(' ', (string) $order['customer_name'])[0]) : '';
$isPre     = $order && !empty($order['is_preorder']);
$shipDate  = $isPre && !empty($order['preorder_ship_date']) ? date('F j', strtotime($order['preorder_ship_date'])) : null;
$subtotal  = array_sum(array_map(fn($i) => (float) $i['price_at_purchase'] * (int) $i['quantity'], $order_items));
$discount  = (float) ($order['discount_amount'] ?? 0);
$balance   = (float) ($order['balance_due'] ?? 0);
?>
<section class="receipt-page">
    <div class="container receipt-page__grid">

        <div class="receipt-page__intro">
            <p class="receipt-page__kicker"><?php echo $isPre ? 'Pre-order confirmed' : 'Order confirmed'; ?></p>
            <h1 class="receipt-page__title">Thank you<?php echo $first !== '' ? ', ' . sanitize($first) : ''; ?>.</h1>
            <p class="receipt-page__lede">
                <?php if ($isPre): ?>
                    Your pre-order is in. Annie is making this batch now<?php echo $shipDate ? ', and yours ships by <strong>' . $shipDate . '</strong>' : ''; ?>.
                <?php else: ?>
                    Your order is in, and Annie will pack it up by hand.
                <?php endif; ?>
            </p>

            <ol class="receipt-steps">
                <li>
                    <span class="receipt-steps__title">Confirmation email</span>
                    <span>A copy of this receipt is on its way<?php echo $order && $order['customer_email'] ? ' to <strong>' . sanitize($order['customer_email']) . '</strong>' : ''; ?>.</span>
                </li>
                <?php if ($isPre): ?>
                <li>
                    <span class="receipt-steps__title">Made to order</span>
                    <span><?php echo $balance > 0
                        ? 'When your piece is ready, you\'ll get an email with a link to pay the remaining ' . formatPrice($balance) . '.'
                        : 'If anything holds the batch up, you\'ll hear right away and can cancel for a full refund.'; ?></span>
                </li>
                <?php else: ?>
                <li>
                    <span class="receipt-steps__title">Packed by hand</span>
                    <span>In-stock pieces ship within 5–10 business days.</span>
                </li>
                <?php endif; ?>
                <li>
                    <span class="receipt-steps__title">Tracking</span>
                    <span>You'll get a tracking number by email as soon as it ships.</span>
                </li>
            </ol>

            <p class="receipt-page__note">Thanks for supporting a small studio. It genuinely means a lot. <span class="receipt-page__sign">Annie</span></p>
        </div>

        <?php if ($order): ?>
        <article class="receipt" aria-labelledby="receipt-title">
            <div class="receipt__head">
                <div>
                    <h2 class="receipt__title" id="receipt-title">Receipt</h2>
                    <p class="receipt__brand">Caneeli Designs</p>
                </div>
                <p class="receipt__number">Order #<?php echo (int) $order['id']; ?></p>
            </div>

            <dl class="receipt__meta">
                <div><dt>Date</dt><dd><?php echo date('M j, Y', strtotime($order['created_at'] ?? 'now')); ?></dd></div>
                <div><dt>Status</dt><dd><?php echo $balance > 0 ? 'Deposit paid' : 'Paid'; ?></dd></div>
                <?php if (!empty($order['customer_email'])): ?>
                <div class="receipt__meta-wide"><dt>Email</dt><dd><?php echo sanitize($order['customer_email']); ?></dd></div>
                <?php endif; ?>
            </dl>

            <?php if ($order_items): ?>
            <ul class="receipt__items">
                <?php foreach ($order_items as $item):
                    $qty = (int) $item['quantity'];
                    $name = preg_replace('/^(Pre-order|Deposit): /', '', $item['product_name']); ?>
                <li class="receipt__item">
                    <span class="receipt__thumb">
                        <?php if (!empty($item['image_path'])): ?><img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt=""><?php endif; ?>
                    </span>
                    <span class="receipt__item-main">
                        <span class="receipt__item-name"><?php echo sanitize($name); ?></span>
                        <span class="receipt__item-sub"><?php echo $isPre ? 'Pre-order, ' : ''; ?><?php echo $qty; ?> × <?php echo formatPrice($item['price_at_purchase']); ?></span>
                    </span>
                    <span class="receipt__amount"><?php echo formatPrice($item['price_at_purchase'] * $qty); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <dl class="receipt__totals">
                <div><dt>Subtotal</dt><dd><?php echo formatPrice($subtotal); ?></dd></div>
                <?php if ($discount > 0): ?>
                <div class="receipt__discount"><dt>Discount<?php echo !empty($order['discount_code']) ? ' (' . htmlspecialchars($order['discount_code']) . ')' : ''; ?></dt><dd>−<?php echo formatPrice($discount); ?></dd></div>
                <?php endif; ?>
                <div><dt>Shipping</dt><dd>Included</dd></div>
                <div class="receipt__total"><dt>Total</dt><dd><?php echo formatPrice($order['total']); ?></dd></div>
                <?php if ($balance > 0): ?>
                <div class="receipt__paid"><dt>Deposit paid</dt><dd><?php echo formatPrice((float) $order['amount_paid']); ?></dd></div>
                <div class="receipt__due"><dt>Due before it ships</dt><dd><?php echo formatPrice($balance); ?></dd></div>
                <?php endif; ?>
            </dl>

            <?php if (!empty($order['shipping_address'])): ?>
            <div class="receipt__ship">
                <h3>Shipping to</h3>
                <address><?php echo nl2br(sanitize($order['shipping_address'])); ?></address>
            </div>
            <?php endif; ?>

            <div class="receipt__foot">
                Questions about your order? <a href="/pages/contact.php?about=<?php echo urlencode('Order #' . (int) $order['id']); ?>">Get in touch</a> and mention order #<?php echo (int) $order['id']; ?>.
            </div>
        </article>
        <?php endif; ?>

        <div class="receipt-page__actions">
            <a href="/pages/shop/" class="btn red-button">Keep browsing</a>
            <?php if ($order): ?><button type="button" class="receipt-page__print" onclick="window.print()">Print receipt</button><?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
