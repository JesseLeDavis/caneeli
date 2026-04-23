<?php
$pageTitle = "Cart";
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/db.php';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);

    if ($action === 'add' && $product_id) {
        $qty = intval($_POST['quantity'] ?? 1);

        // Verify product exists, is active, and has stock. Sold-out products stay
        // visible in the shop but can't be added to the cart.
        $stmt = getDB()->prepare("SELECT * FROM products WHERE id = ? AND active = 1 AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if ($product && $product['stock_qty'] > 0) {
            if (!isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id] = 0;
            }
            $new_qty = $_SESSION['cart'][$product_id] + $qty;
            $_SESSION['cart'][$product_id] = min($new_qty, $product['stock_qty']);
        }
        header('Location: /cart.php');
        exit;
    }

    if ($action === 'update' && $product_id) {
        $qty = intval($_POST['quantity'] ?? 0);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$product_id]);
        } else {
            $_SESSION['cart'][$product_id] = $qty;
        }
        header('Location: /cart.php');
        exit;
    }

    if ($action === 'remove' && $product_id) {
        unset($_SESSION['cart'][$product_id]);
        header('Location: /cart.php');
        exit;
    }
}

// Load cart products from DB
$cart_items = [];
$subtotal   = 0;

if (!empty($_SESSION['cart'])) {
    $ids        = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt       = getDB()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND active = 1");
    $stmt->execute($ids);
    $products   = $stmt->fetchAll();

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
        <h1 class="large_title">Your Cart</h1>

        <?php if (empty($cart_items)): ?>
            <div class="cart-empty">
                <div class="cart-empty__icon">🛒</div>
                <p class="cart-empty__heading">Your cart is empty</p>
                <p class="cart-empty__text">You haven't added anything yet. Browse the shop and find a piece you love.</p>
                <a href="/pages/shop/" class="btn red-button">Go Find Something You Love</a>
            </div>
        <?php else: ?>
            <div class="cart">
                <div class="cart__items">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="cart__row">
                        <a href="/pages/shop/product.php?id=<?php echo $item['id']; ?>" class="cart__image">
                            <?php if ($item['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>"
                                     alt="<?php echo sanitize($item['name']); ?>">
                            <?php else: ?>
                                <div class="cart__placeholder"></div>
                            <?php endif; ?>
                        </a>

                        <div class="cart__details">
                            <h3><a href="/pages/shop/product.php?id=<?php echo $item['id']; ?>"><?php echo sanitize($item['name']); ?></a></h3>
                            <p><?php echo sanitize($item['category']); ?></p>
                        </div>

                        <form method="POST" class="cart__qty-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                            <div class="qty-stepper qty-stepper--small">
                                <button type="button" class="qty-stepper__btn" data-dir="-1">−</button>
                                <input type="number" name="quantity" value="<?php echo $item['qty']; ?>"
                                       min="0" max="<?php echo $item['stock_qty']; ?>"
                                       class="qty-stepper__input" readonly>
                                <button type="button" class="qty-stepper__btn" data-dir="1">+</button>
                            </div>
                        </form>

                        <p class="cart__line-total"><?php echo formatPrice($item['line_total']); ?></p>

                        <form method="POST">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="cart__remove"
                                    onclick="return confirm('Remove <?php echo addslashes(sanitize($item['name'])); ?> from your cart?')">✕</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart__summary">
                    <p class="cart__summary-title">Order Summary</p>
                    <?php
                        $free_shipping_threshold = 200;
                        $remaining = $free_shipping_threshold - $subtotal;
                    ?>
                    <?php if ($remaining > 0): ?>
                        <p class="cart__shipping-note">Add <?php echo formatPrice($remaining); ?> more for free shipping!</p>
                    <?php else: ?>
                        <p class="cart__shipping-note">Free shipping applied!</p>
                    <?php endif; ?>
                    <div class="cart__subtotal">
                        <span class="cart__subtotal-label">Subtotal</span>
                        <span class="cart__subtotal-price"><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <p class="cart__note">Taxes calculated at checkout.</p>
                    <a href="/checkout.php" class="btn blue-button">Checkout</a>
                    <p class="cart__stripe-note">🔒 Secure checkout via Stripe</p>
                    <a href="/pages/shop/" class="cart__continue">← Continue Shopping</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
