<?php
$pageTitle = "Checkout Canceled";
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/db.php';

// Pull the (still-intact) cart so we can preview it for reassurance.
$cart_items = [];
$subtotal   = 0;

if (!empty($_SESSION['cart'])) {
    $ids          = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = getDB()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND active = 1");
    $stmt->execute($ids);
    $products     = $stmt->fetchAll();

    foreach ($products as $product) {
        $qty          = $_SESSION['cart'][$product['id']];
        $line_total   = $product['price'] * $qty;
        $subtotal    += $line_total;
        $cart_items[] = array_merge($product, ['qty' => $qty, 'line_total' => $line_total]);
    }
}
?>

<section class="hero">
    <div class="container">
        <div class="checkout-status checkout-status--cancel">
            <div class="checkout-status__intro">
                <p class="checkout-status__eyebrow">Checkout Paused</p>
                <h1 class="large_title">No charge made.</h1>
                <p class="checkout-status__lede">You stepped back from checkout — that's fine. Nothing was charged and your cart is right where you left it.</p>
                <p class="checkout-status__lede">Whenever you're ready, pick up where you stopped.</p>
                <p class="checkout-status__sign-off">-Annie</p>
            </div>

            <?php if (!empty($cart_items)): ?>
                <div class="checkout-status__card">
                    <div class="checkout-status__card-header">
                        <div>
                            <p class="checkout-status__label">Still in your cart</p>
                            <p class="checkout-status__order-number"><?php echo count($cart_items); ?> <?php echo count($cart_items) === 1 ? 'piece' : 'pieces'; ?></p>
                        </div>
                    </div>

                    <ul class="checkout-status__items">
                        <?php foreach ($cart_items as $item): ?>
                            <li class="checkout-status__item checkout-status__item--with-image">
                                <?php if ($item['image_path']): ?>
                                    <span class="checkout-status__thumb">
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>"
                                             alt="<?php echo sanitize($item['name']); ?>">
                                    </span>
                                <?php else: ?>
                                    <span class="checkout-status__thumb checkout-status__thumb--placeholder"></span>
                                <?php endif; ?>
                                <div class="checkout-status__item-name">
                                    <span><?php echo sanitize($item['name']); ?></span>
                                    <span class="checkout-status__item-qty">× <?php echo (int) $item['qty']; ?></span>
                                </div>
                                <span class="checkout-status__item-price"><?php echo formatPrice($item['line_total']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="checkout-status__total">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="checkout-status__card checkout-status__card--empty">
                    <p>Your cart is empty now. If that's a surprise, the session may have timed out — have a browse and add what you wanted back in.</p>
                </div>
            <?php endif; ?>

            <div class="checkout-status__actions">
                <?php if (!empty($cart_items)): ?>
                    <a href="/cart.php" class="btn red-button checkout-status__cta">Back to Cart</a>
                    <a href="/pages/shop/" class="checkout-status__secondary">Keep Browsing</a>
                <?php else: ?>
                    <a href="/pages/shop/" class="btn blue-button checkout-status__cta">Browse the Shop</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
