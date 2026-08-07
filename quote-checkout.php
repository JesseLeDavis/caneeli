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

$token    = trim($_POST['token'] ?? '');
$mode     = ($_POST['mode'] ?? 'deposit') === 'balance' ? 'balance' : 'deposit';
$accepted = !empty($_POST['accept_terms']);

if ($token === '') {
    header('Location: ' . SITE_URL . '/quote.php');
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM custom_quotes WHERE token = ?");
$stmt->execute([$token]);
$quote = $stmt->fetch();

if (!$quote) {
    header('Location: ' . SITE_URL . '/quote.php?t=' . urlencode($token));
    exit;
}

// Each payment is valid from exactly one state: a deposit from 'sent', a
// balance from 'balance_requested'. Anything else goes back to the quote page,
// which explains where things actually stand.
$requiredStatus = $mode === 'balance' ? 'balance_requested' : 'sent';
if ($quote['status'] !== $requiredStatus || ($mode === 'deposit' && !$accepted)) {
    header('Location: ' . SITE_URL . '/quote.php?t=' . urlencode($token));
    exit;
}

$deposit = (float) $quote['deposit_amount'];
$total   = (float) $quote['total'];

// Charge the balance from the order, not the quote — a manual settle could
// have moved it, and the order is the record of what's actually owed.
$amount = $deposit;
if ($mode === 'balance') {
    $ostmt = $pdo->prepare("SELECT balance_due FROM orders WHERE id = ?");
    $ostmt->execute([(int) $quote['order_id']]);
    $amount = (float) ($ostmt->fetchColumn() ?: 0);

    if ($amount <= 0) {
        header('Location: ' . SITE_URL . '/quote.php?t=' . urlencode($token));
        exit;
    }
}

// Record acceptance now — before the payment, so the timestamp genuinely
// precedes the charge. Only set it once, at the deposit.
if ($mode === 'deposit' && empty($quote['terms_accepted_at'])) {
    $pdo->prepare("UPDATE custom_quotes SET terms_accepted_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([(int) $quote['id']]);
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    $args = [
        'payment_method_types' => ['card'],
        'mode'                 => 'payment',
        'customer_email'       => $quote['customer_email'],
        'line_items' => [[
            'price_data' => [
                'currency'    => 'usd',
                'unit_amount' => (int) round($amount * 100),
                'product_data' => $mode === 'balance'
                    ? [
                        'name'        => 'Balance — ' . $quote['title'],
                        'description' => 'Remaining balance on your commission. Total $'
                            . number_format($total, 2) . ', deposit of $'
                            . number_format($deposit, 2) . ' already paid.',
                    ]
                    : [
                        'name'        => 'Deposit — ' . $quote['title'],
                        'description' => 'Non-refundable deposit. Balance of $'
                            . number_format($total - $deposit, 2) . ' due before shipping.',
                    ],
            ],
            'quantity' => 1,
        ]],
        'success_url' => SITE_URL . '/quote.php?t=' . urlencode($token) . '&paid=1',
        'cancel_url'  => SITE_URL . '/quote.php?t=' . urlencode($token),
        // The webhook routes on `type` — without it this session would fall
        // through to the cart path and create a junk order.
        'metadata' => [
            'type'     => $mode === 'balance' ? 'custom_balance' : 'custom_deposit',
            'quote_id' => (string) $quote['id'],
        ],
    ];

    // Only collect the address at the deposit — by balance time we already
    // have it, and re-asking invites a mismatch with where it's being shipped.
    if ($mode === 'deposit') {
        $args['shipping_address_collection'] = ['allowed_countries' => ['US']];
    }

    $session = \Stripe\Checkout\Session::create($args);

    $col = $mode === 'balance' ? 'balance_session_id' : 'deposit_session_id';
    $pdo->prepare("UPDATE custom_quotes SET $col = ? WHERE id = ?")
        ->execute([$session->id, (int) $quote['id']]);

    header('Location: ' . $session->url);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Quote deposit checkout error: ' . $e->getMessage());
    header('Location: ' . SITE_URL . '/error.php?reason=checkout');
    exit;
}
