<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tracking.php';
require_once __DIR__ . '/includes/discounts.php';
require_once __DIR__ . '/vendor/autoload.php';

if (empty($_SESSION['cart'])) {
    header('Location: /cart.php');
    exit;
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Build line items from cart
$ids          = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt         = getDB()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND active = 1 AND status = 'active'");
$stmt->execute($ids);
$products     = $stmt->fetchAll();

// Re-check every line against live stock. The cart can go stale: a piece
// sells out, or stock drops below what's in the cart. If anything changed,
// fix the cart and send them back to see it rather than charging for
// something that isn't there.
$found = array_column($products, null, 'id');
$changed = false;
foreach ($_SESSION['cart'] as $pid => $qty) {
    $p = $found[$pid] ?? null;
    if (!$p || (int) $p['stock_qty'] <= 0) {
        unset($_SESSION['cart'][$pid]);
        $changed = true;
    } elseif ($qty > (int) $p['stock_qty'] || $qty < 1) {
        $_SESSION['cart'][$pid] = max(1, min((int) $qty, (int) $p['stock_qty']));
        $changed = true;
    }
}
if ($changed) {
    $_SESSION['cart_flash'] = 'Something in your cart just sold out or has less stock left, so your cart was updated. Have a look, then check out again.';
    header('Location: /cart.php');
    exit;
}

$line_items = [];
foreach ($products as $product) {
    $qty = (int) $_SESSION['cart'][$product['id']];

    $line_items[] = [
        'price_data' => [
            'currency'     => 'usd',
            'unit_amount'  => (int) round($product['price'] * 100), // Stripe uses cents
            'product_data' => [
                'name'        => $product['name'],
                'description' => $product['description'] ?: null,
                'images'      => $product['image_path']
                    ? [SITE_URL . $product['image_path']]
                    : [],
                // How the order gets tied back to our product (and its stock)
                // in includes/fulfillment.php. Per-item, so there's no size
                // limit, unlike the old whole-cart JSON in session metadata
                // (Stripe caps a metadata value at 500 chars, which a cart of
                // five or six pieces could blow through, failing checkout).
                'metadata'    => ['product_id' => (string) $product['id']],
            ],
        ],
        'quantity' => $qty,
    ];
    track_cart_event((int) $product['id'], 'checkout_start');
}

// Resolve active discount code (if any) and create a one-off Stripe coupon.
$discounts_arg = [];
$applied_code  = null;
if (!empty($_SESSION['discount_code_id'])) {
    $stmt = getDB()->prepare("SELECT * FROM discount_codes WHERE id = ? AND active = 1");
    $stmt->execute([(int) $_SESSION['discount_code_id']]);
    $row = $stmt->fetch();
    if ($row && ($row['max_uses'] === null || $row['times_used'] < $row['max_uses'])) {
        try {
            $coupon_args = ['duration' => 'once', 'name' => 'Promo: ' . $row['code']];
            if ($row['type'] === 'percent') {
                $coupon_args['percent_off'] = (float) $row['value'];
            } else {
                $coupon_args['amount_off'] = (int) round($row['value'] * 100);
                $coupon_args['currency']   = 'usd';
            }
            $coupon = \Stripe\Coupon::create($coupon_args);
            $discounts_arg = [['coupon' => $coupon->id]];
            $applied_code  = $row['code'];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // If the coupon can't be created, just proceed without the discount.
        }
    }
}

try {
    $session_args = [
        'payment_method_types' => ['card'],
        'line_items'           => $line_items,
        'mode'                 => 'payment',
        'success_url'          => SITE_URL . '/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => SITE_URL . '/cancel.php',
        'shipping_address_collection' => [
            'allowed_countries' => ['US'],
        ],
        // Stock isn't held while they're on Stripe's page, so keep that window
        // short (30 min is Stripe's minimum; the default is 24 hours).
        'expires_at' => time() + 30 * 60,
        'metadata' => [
            'discount_code' => $applied_code,
        ],
    ];
    if ($discounts_arg) {
        $session_args['discounts'] = $discounts_arg;
    }

    $session = \Stripe\Checkout\Session::create($session_args);

    header('Location: ' . $session->url);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Stripe checkout error: ' . $e->getMessage());
    header('Location: ' . SITE_URL . '/error.php?reason=checkout');
    exit;
}
