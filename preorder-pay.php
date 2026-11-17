<?php
/**
 * Public page for paying a pre-order's balance.
 *
 * Reached from the "your lamp is ready" email via a private token link (no
 * login), same model as quote.php. POSTing opens a Stripe Checkout for the
 * balance; Stripe sends the buyer back here with ?paid=<session id>, and the
 * balance is recorded by preorder_settle_balance() (the webhook does the same
 * thing, whichever lands first).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/preorders.php';
require_once __DIR__ . '/vendor/autoload.php';

$token = trim($_GET['t'] ?? $_POST['t'] ?? '');
$pdo   = getDB();
$order = null;
if ($token !== '') {
    $s = $pdo->prepare("SELECT * FROM orders WHERE preorder_token = ? AND is_preorder = 1");
    $s->execute([$token]);
    $order = $s->fetch() ?: null;
}
$self = SITE_URL . '/preorder-pay.php?t=' . urlencode($token);

// ── Back from Stripe: record the payment ──────────────────────────────
if ($order && !empty($_GET['paid']) && (float) $order['balance_due'] > 0) {
    try {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        $session = \Stripe\Checkout\Session::retrieve($_GET['paid']);
        if ((int) ($session->metadata->order_id ?? 0) === (int) $order['id']) {
            preorder_settle_balance($pdo, $session);
        }
    } catch (\Throwable $e) {
        error_log('preorder-pay: ' . $e->getMessage()); // the webhook will still record it
    }
    header('Location: ' . $self);
    exit;
}

// ── Pay: open a Stripe Checkout for the balance ───────────────────────
// Only once Annie has asked for it: until then the batch may not be ready,
// so there's nothing to pay early.
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && (float) $order['balance_due'] > 0 && !empty($order['preorder_balance_requested_at'])) {
    $name    = preorder_product_name($pdo, (int) $order['id']);
    $balance = (float) $order['balance_due'];
    $meta    = ['type' => 'preorder_balance', 'order_id' => (string) $order['id']];

    // Test site without Stripe keys: pretend it was paid.
    if (APP_ENV === 'test' && STRIPE_SECRET_KEY === '') {
        preorder_settle_balance($pdo, json_decode(json_encode([
            'payment_status' => 'paid', 'payment_intent' => 'pi_test_fake_' . bin2hex(random_bytes(6)),
            'amount_total' => (int) round($balance * 100), 'metadata' => $meta,
        ])));
        header('Location: ' . $self);
        exit;
    }

    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'customer_email'       => $order['customer_email'] ?: null,
            'line_items'           => [[
                'price_data' => [
                    'currency'     => 'usd',
                    'unit_amount'  => (int) round($balance * 100),
                    'product_data' => ['name' => 'Balance: ' . $name . ' (pre-order #' . (int) $order['id'] . ')'],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $self . '&paid={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $self,
            'metadata'    => $meta,
        ]);
        header('Location: ' . $session->url);
        exit;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('preorder-pay checkout error: ' . $e->getMessage());
        header('Location: ' . SITE_URL . '/error.php?reason=checkout');
        exit;
    }
}

$pageTitle = 'Your pre-order';
$noindex   = true;
include __DIR__ . '/includes/header.php';
?>

<?php if (!$order): ?>
<section class="receipt-page">
    <div class="container receipt-page__grid">
        <div class="receipt-page__intro">
            <p class="receipt-page__kicker">Pre-order</p>
            <h1 class="receipt-page__title">We couldn't find that pre-order.</h1>
            <p class="receipt-page__lede">This link might have a typo in it. Reply to Annie's email and she'll send it again.</p>
        </div>
        <div class="receipt-page__actions"><a href="<?php echo SITE_URL; ?>/pages/contact.php" class="btn red-button">Get in touch</a></div>
    </div>
</section>
<?php else:
    $name    = preorder_product_name($pdo, (int) $order['id']);
    $image   = preorder_product_image($pdo, (int) $order['id']);
    $total   = (float) $order['total'];
    $balance = (float) $order['balance_due'];
    $paid    = $total - $balance;
    $first   = trim(explode(' ', (string) $order['customer_name'])[0]);
    $qtyRow  = $pdo->prepare("SELECT SUM(quantity) FROM order_items WHERE order_id = ?");
    $qtyRow->execute([$order['id']]);
    $qty     = max(1, (int) $qtyRow->fetchColumn());
    $ready   = !empty($order['preorder_balance_requested_at']);
    $state   = $order['status'] === 'cancelled' ? 'cancelled'
             : ($balance > 0 ? 'due' : ($order['status'] === 'fulfilled' ? 'shipped' : 'paid'));
    $ship    = !empty($order['preorder_ship_date']) ? date('F j', strtotime($order['preorder_ship_date'])) : null;
?>
<section class="receipt-page">
    <div class="container receipt-page__grid">

        <div class="receipt-page__intro">
            <p class="receipt-page__kicker">Pre-order #<?php echo (int) $order['id']; ?></p>
            <?php if ($state === 'due' && $ready): ?>
                <h1 class="receipt-page__title">It's ready<?php echo $first !== '' ? ', ' . sanitize($first) : ''; ?>.</h1>
                <p class="receipt-page__lede">Your <?php echo sanitize($name); ?> is made. Pay the remaining <strong><?php echo formatPrice($balance); ?></strong> and Annie will pack it up and ship it within a few days.</p>
            <?php elseif ($state === 'due'): ?>
                <h1 class="receipt-page__title">Your deposit is in.</h1>
                <p class="receipt-page__lede">Annie is making this batch now<?php echo $ship ? ', and yours ships by <strong>' . $ship . '</strong>' : ''; ?>. When your piece is ready, you'll get an email with a link to pay the remaining <?php echo formatPrice($balance); ?>. Nothing to do until then.</p>
            <?php elseif ($state === 'paid'): ?>
                <h1 class="receipt-page__title">Paid in full. Thank you<?php echo $first !== '' ? ', ' . sanitize($first) : ''; ?>.</h1>
                <p class="receipt-page__lede">Annie is packing it up, and you'll get a tracking number by email as soon as it ships.</p>
            <?php elseif ($state === 'shipped'): ?>
                <h1 class="receipt-page__title">It's on its way.</h1>
                <p class="receipt-page__lede">Paid in full and shipped. Check your email for the tracking number.</p>
            <?php else: ?>
                <h1 class="receipt-page__title">This pre-order was cancelled.</h1>
                <p class="receipt-page__lede">If that's a surprise, reply to Annie's email or <a href="<?php echo SITE_URL; ?>/pages/contact.php">get in touch</a>.</p>
            <?php endif; ?>

            <?php if ($state === 'due' && $ready): ?>
            <form method="POST" action="<?php echo htmlspecialchars($self); ?>" class="pay-form">
                <button type="submit" class="btn red-button pay-form__btn">Pay <?php echo formatPrice($balance); ?></button>
                <p class="pay-form__secure">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    Secure checkout through Stripe. Your card details never touch this site.
                </p>
            </form>
            <?php endif; ?>
        </div>

        <article class="receipt" aria-labelledby="receipt-title">
            <div class="receipt__head">
                <div>
                    <h2 class="receipt__title" id="receipt-title"><?php echo $state === 'due' && $ready ? 'Balance due' : 'Receipt'; ?></h2>
                    <p class="receipt__brand">Caneeli Designs</p>
                </div>
                <p class="receipt__number">Pre-order #<?php echo (int) $order['id']; ?></p>
            </div>

            <dl class="receipt__meta">
                <div><dt>Ordered</dt><dd><?php echo date('M j, Y', strtotime($order['created_at'])); ?></dd></div>
                <div><dt>Status</dt><dd><?php echo ['due' => 'Deposit paid', 'paid' => 'Paid in full', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'][$state]; ?></dd></div>
            </dl>

            <ul class="receipt__items">
                <li class="receipt__item">
                    <span class="receipt__thumb"><?php if ($image): ?><img src="<?php echo htmlspecialchars($image); ?>" alt=""><?php endif; ?></span>
                    <span class="receipt__item-main">
                        <span class="receipt__item-name"><?php echo sanitize($name); ?></span>
                        <span class="receipt__item-sub">Pre-order, <?php echo $qty; ?> × <?php echo formatPrice($total / $qty); ?></span>
                    </span>
                    <span class="receipt__amount"><?php echo formatPrice($total); ?></span>
                </li>
            </ul>

            <dl class="receipt__totals">
                <div><dt>Total</dt><dd><?php echo formatPrice($total); ?></dd></div>
                <div><dt>Shipping</dt><dd>Included</dd></div>
                <div class="receipt__paid"><dt><?php echo $balance > 0 ? 'Deposit paid' : 'Paid'; ?></dt><dd>−<?php echo formatPrice($paid); ?></dd></div>
                <div class="receipt__total"><dt><?php echo $balance > 0 ? ($ready ? 'Left to pay' : 'Due when it ships') : 'Balance'; ?></dt><dd><?php echo formatPrice($balance); ?></dd></div>
            </dl>

            <?php if (!empty($order['shipping_address'])): ?>
            <div class="receipt__ship">
                <h3>Shipping to</h3>
                <address><?php echo nl2br(sanitize($order['shipping_address'])); ?></address>
            </div>
            <?php endif; ?>

            <div class="receipt__foot">
                Questions? <a href="/pages/contact.php?about=<?php echo urlencode('Pre-order #' . (int) $order['id']); ?>">Get in touch</a> and mention pre-order #<?php echo (int) $order['id']; ?>.
            </div>
        </article>

        <div class="receipt-page__actions">
            <?php if ($state !== 'due'): ?><a href="/pages/shop/" class="btn red-button">Keep browsing</a><?php endif; ?>
            <button type="button" class="receipt-page__print" onclick="window.print()">Print</button>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
