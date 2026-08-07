<?php
/**
 * Creates the Stripe Checkout session for a custom-order deposit.
 *
 * POSTed to from quote.php. Records terms acceptance BEFORE handing off to
 * Stripe — if the customer pays, we need a timestamp proving the terms were
 * shown and accepted first.
 */
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/quotes.php';
require_once __DIR__ . '/vendor/autoload.php';

$token = trim($_POST['token'] ?? '');
$accepted = !empty($_POST['accept_terms']);

if ($token === '') {
    header('Location: ' . SITE_URL . '/quote.php');
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM custom_quotes WHERE token = ?");
$stmt->execute([$token]);
$quote = $stmt->fetch();

// Only a sent quote can be paid. Anything else (draft, cancelled, or already
// paid) goes back to the quote page, which explains the current state.
if (!$quote || $quote['status'] !== 'sent' || !$accepted) {
    header('Location: ' . SITE_URL . '/quote.php?t=' . urlencode($token));
    exit;
}

$deposit = (float) $quote['deposit_amount'];
$total   = (float) $quote['total'];

// Record acceptance now — before the payment, so the timestamp genuinely
// precedes the charge. Only set it once.
if (empty($quote['terms_accepted_at'])) {
    $pdo->prepare("UPDATE custom_quotes SET terms_accepted_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([(int) $quote['id']]);
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'mode'                 => 'payment',
        'customer_email'       => $quote['customer_email'],
        'line_items' => [[
            'price_data' => [
                'currency'    => 'usd',
                'unit_amount' => (int) round($deposit * 100),
                'product_data' => [
                    'name'        => 'Deposit — ' . $quote['title'],
                    'description' => 'Non-refundable deposit. Balance of $'
                        . number_format($total - $deposit, 2) . ' due before shipping.',
                ],
            ],
            'quantity' => 1,
        ]],
        'shipping_address_collection' => [
            'allowed_countries' => ['US'],
        ],
        'success_url' => SITE_URL . '/quote.php?t=' . urlencode($token) . '&paid=1',
        'cancel_url'  => SITE_URL . '/quote.php?t=' . urlencode($token),
        // The webhook routes on `type` — without it this session would fall
        // through to the cart path and create a junk order.
        'metadata' => [
            'type'     => 'custom_deposit',
            'quote_id' => (string) $quote['id'],
        ],
    ]);

    $pdo->prepare("UPDATE custom_quotes SET deposit_session_id = ? WHERE id = ?")
        ->execute([$session->id, (int) $quote['id']]);

    header('Location: ' . $session->url);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Quote deposit checkout error: ' . $e->getMessage());
    header('Location: ' . SITE_URL . '/error.php?reason=checkout');
    exit;
}
