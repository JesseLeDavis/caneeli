<?php
/**
 * Sends a pre-order straight to Stripe Checkout. Pre-orders skip the cart so
 * they never share an order (and a shipment) with pieces that ship now.
 *
 * POSTed to from the product page. The buyer must tick "I understand this
 * ships by <date>"; that time travels in the session metadata and lands on
 * the order (orders.preorder_terms_accepted_at) once it's paid. Saving the
 * order is the shared shop path in includes/fulfillment.php.
 */
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/product_status.php';
require_once __DIR__ . '/includes/fulfillment.php';
require_once __DIR__ . '/includes/preorders.php';
require_once __DIR__ . '/vendor/autoload.php';

$id       = (int) ($_POST['product_id'] ?? 0);
$qty      = max(1, (int) ($_POST['quantity'] ?? 1));
$accepted = !empty($_POST['accept_terms']);
$back     = SITE_URL . '/pages/shop/product.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
    header('Location: ' . SITE_URL . '/pages/shop/');
    exit;
}

$stmt = getDB()->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

// Batch full or no longer a pre-order: the product page explains.
if (!$product || !product_is_preorderable($product) || !$accepted) {
    header('Location: ' . $back);
    exit;
}
$qty = min($qty, (int) $product['stock_qty'], 3); // same cap as the product page

$metadata = [
    'type'              => 'preorder',
    'product_id'        => (string) $product['id'],
    'ship_date'         => (string) ($product['preorder_ship_date'] ?? ''),
    'terms_accepted_at' => (string) time(),
];
// With a deposit, Stripe charges only the deposit now; the order records the
// full price and the balance (see includes/fulfillment.php).
$deposit = preorder_deposit_for($product);
$ship    = preorder_ship_label($product['preorder_ship_date'], 'F j, Y');
if ($deposit) {
    $metadata['deposit']    = (string) $deposit;
    $metadata['full_price'] = (string) $product['price'];
}
$line_item = [
    'price_data' => [
        'currency'     => 'usd',
        'unit_amount'  => (int) round(($deposit ?? $product['price']) * 100),
        'product_data' => [
            'name'        => ($deposit ? 'Deposit: ' : 'Pre-order: ') . $product['name'],
            'description' => ($deposit
                    ? 'Deposit on a $' . number_format((float) $product['price'], 2) . ' pre-order. The remaining $'
                      . number_format((float) $product['price'] - $deposit, 2) . ' is due when it ships. '
                    : '')
                . ($ship ? 'Made to order. Ships by ' . $ship . '.' : 'Made to order.'),
            'images'      => $product['image_path'] ? [SITE_URL . $product['image_path']] : [],
            'metadata'    => ['product_id' => (string) $product['id']],
        ],
    ],
    'quantity' => $qty,
];

// Test site without Stripe keys: pretend the payment went through, so the
// whole flow (order, email, admin) can be tried. Never runs in production.
if (defined('APP_ENV') && APP_ENV === 'test' && STRIPE_SECRET_KEY === '') {
    $fake = json_decode(json_encode([
        'id'             => 'cs_test_fake_' . bin2hex(random_bytes(8)),
        'payment_status' => 'paid',
        'payment_intent' => 'pi_test_fake_' . bin2hex(random_bytes(6)),
        'amount_total'   => $line_item['price_data']['unit_amount'] * $qty,
        'metadata'       => $metadata,
        'customer_details' => ['name' => 'Test Buyer', 'email' => 'test.buyer@example.com'],
        'shipping_details' => ['name' => 'Test Buyer', 'address' => [
            'line1' => '123 Test St', 'city' => 'Portland', 'state' => 'OR', 'postal_code' => '97214', 'country' => 'US',
        ]],
        'line_items' => ['data' => [[
            'quantity' => $qty,
            'price'    => ['unit_amount' => $line_item['price_data']['unit_amount'], 'product' => [
                'name' => $line_item['price_data']['product_data']['name'],
                'metadata' => ['product_id' => (string) $product['id']],
            ]],
        ]]],
    ]));
    fulfillment_save_order(getDB(), $fake);
    header('Location: ' . SITE_URL . '/success.php?session_id=' . urlencode($fake->id));
    exit;
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types'        => ['card'],
        'line_items'                  => [$line_item],
        'mode'                        => 'payment',
        'success_url'                 => SITE_URL . '/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'                  => $back,
        'shipping_address_collection' => ['allowed_countries' => ['US']],
        // Spots aren't held while they're on Stripe's page; keep it short.
        'expires_at'                  => time() + 30 * 60,
        'metadata'                    => $metadata,
    ]);
    header('Location: ' . $session->url);
    exit;
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Stripe pre-order checkout error: ' . $e->getMessage());
    header('Location: ' . SITE_URL . '/error.php?reason=checkout');
    exit;
}
