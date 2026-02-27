<?php
// Stripe Webhook Handler
// Register this URL in your Stripe dashboard under Developers → Webhooks
// URL: https://yourdomain.com/webhook.php
// Events to listen for: checkout.session.completed

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$payload   = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        STRIPE_WEBHOOK_SECRET
    );
} catch (\Exception $e) {
    http_response_code(400);
    exit;
}

// Only handle completed checkouts
if ($event->type !== 'checkout.session.completed') {
    http_response_code(200);
    exit;
}

$stripe_session = $event->data->object;

// Skip if already processed
$pdo   = getDB();
$check = $pdo->prepare("SELECT id FROM orders WHERE stripe_session_id = ?");
$check->execute([$stripe_session->id]);
if ($check->fetch()) {
    http_response_code(200);
    exit;
}

// Retrieve full session with line items
$full_session = \Stripe\Checkout\Session::retrieve([
    'id'     => $stripe_session->id,
    'expand' => ['line_items'],
]);

// Save order
$pdo->prepare("
    INSERT INTO orders (stripe_session_id, customer_name, customer_email, status, total)
    VALUES (?, ?, ?, 'paid', ?)
")->execute([
    $stripe_session->id,
    $stripe_session->customer_details->name ?? '',
    $stripe_session->customer_details->email ?? '',
    $stripe_session->amount_total / 100,
]);

http_response_code(200);
