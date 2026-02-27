<?php
session_start();
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
                // Save the order
                $pdo->prepare("
                    INSERT INTO orders (stripe_session_id, customer_name, customer_email, status, total)
                    VALUES (?, ?, ?, 'paid', ?)
                ")->execute([
                    $session_id,
                    $stripe_session->customer_details->name,
                    $stripe_session->customer_details->email,
                    $stripe_session->amount_total / 100,
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

                    // Auto-hide if sold out
                    $pdo->prepare("
                        UPDATE products SET active = 0 WHERE id = ? AND stock_qty = 0
                    ")->execute([$product['id']]);
                }
            }

            // Clear the cart
            unset($_SESSION['cart']);
            $order_saved = true;
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Session retrieval failed — still show success but don't process
    }
}
?>

<section class="hero">
    <div class="container">
        <div class="success-page">
            <h1 class="large_title">Thank you! 🎉</h1>
            <p>Your order has been placed. You'll receive a confirmation email from Stripe shortly.</p>
            <a href="/pages/shop/" class="btn blue-button">Continue Shopping</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
