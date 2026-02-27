<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/vendor/autoload.php';

if (empty($_SESSION['cart'])) {
    header('Location: /cart.php');
    exit;
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Build line items from cart
$ids          = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt         = getDB()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND active = 1");
$stmt->execute($ids);
$products     = $stmt->fetchAll();

$line_items = [];
foreach ($products as $product) {
    $qty = $_SESSION['cart'][$product['id']];

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
            ],
        ],
        'quantity' => $qty,
    ];
}

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items'           => $line_items,
        'mode'                 => 'payment',
        'success_url'          => SITE_URL . '/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => SITE_URL . '/cart.php',
        'shipping_address_collection' => [
            'allowed_countries' => ['US', 'CA'],
        ],
    ]);

    header('Location: ' . $session->url);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    header('Location: /cart.php?error=1');
    exit;
}
