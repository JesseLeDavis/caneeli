<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header('Location: /admin/orders.php');
    exit;
}

// Set when a fulfil attempt was blocked — see update-order-status.php. Used to
// explain the bounce and open the right form.
$needsTracking = ($_GET['fulfill'] ?? '') === 'notracking';
$balanceDue    = ($_GET['fulfill'] ?? '') === 'balancedue';

// Flash messages from the balance actions (admin/order-balance.php).
$balanceFlash = (string) ($_GET['balance'] ?? '');
$balanceError = (string) ($_GET['balance_err'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    $activeNav = 'orders';
    require __DIR__ . '/layout-top.php';
    echo '<div class="container"><p>Order not found. <a href="/admin/orders.php">Back to orders</a>.</p></div></body></html>';
    exit;
}

// Custom orders carry a quote — used for the balance actions below. Guarded so
// a missing table (migration not yet run) can't break the whole order page.
$quoteRow = null;
if (!empty($order['quote_id'])) {
    try {
        $qs = $pdo->prepare("SELECT * FROM custom_quotes WHERE id = ?");
        $qs->execute([(int) $order['quote_id']]);
        $quoteRow = $qs->fetch() ?: null;
    } catch (\Throwable $e) {
        $quoteRow = null;
    }
}

$items_stmt = $pdo->prepare("SELECT i.*, p.image_path FROM order_items i LEFT JOIN products p ON p.id = i.product_id WHERE i.order_id = ? ORDER BY i.id");
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll();

$subtotal = 0;
foreach ($items as $it) {
    $subtotal += ((float) $it['price_at_purchase']) * (int) $it['quantity'];
}
$discount_amount = (float) ($order['discount_amount'] ?? 0);
$shipping_cost   = max(0, (float) $order['total'] - $subtotal + $discount_amount);

$stripe_dashboard = null;
if (!empty($order['stripe_payment_intent'])) {
    $stripe_dashboard = 'https://dashboard.stripe.com/payments/' . urlencode($order['stripe_payment_intent']);
} elseif (!empty($order['stripe_session_id'])) {
    $stripe_dashboard = 'https://dashboard.stripe.com/search?query=' . urlencode($order['stripe_session_id']);
}

// A custom order's balance is a second, unlinked Stripe charge — the order row
// only ever holds the deposit's intent, so the balance link comes off the quote.
// Null for cart orders and for balances settled outside Stripe.
$balanceDashboard = null;
if ($quoteRow) {
    if (!empty($quoteRow['balance_payment_intent'])) {
        $balanceDashboard = 'https://dashboard.stripe.com/payments/' . urlencode($quoteRow['balance_payment_intent']);
    } elseif (!empty($quoteRow['balance_session_id'])) {
        $balanceDashboard = 'https://dashboard.stripe.com/search?query=' . urlencode($quoteRow['balance_session_id']);
    }
}

// How many orders this customer has placed, for the customer card.
$customerOrders = 0;
if (!empty($order['customer_email'])) {
    $co = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_email = ? AND status <> 'cancelled'");
    $co->execute([$order['customer_email']]);
    $customerOrders = (int) $co->fetchColumn();
}
$owes      = $order['balance_due'] !== null && (float) $order['balance_due'] > 0;
$canShip   = $order['status'] !== 'fulfilled' && $order['status'] !== 'cancelled' && !$owes;

$pageTitle = 'Order #' . $order['id'];
$activeNav = 'orders';
require __DIR__ . '/layout-top.php';
?>

<div class="container ui-page">
    <div class="ui-head">
        <div>
            <a class="ui-back" href="/admin/orders.php">← Orders</a>
            <div class="ui-head__title">
                <h1><?php echo !empty($order['is_preorder']) ? 'Pre-order' : 'Order'; ?> #<?php echo (int) $order['id']; ?></h1>
                <?php echo admin_status_badge($order['status']); ?>
            </div>
            <p class="ui-head__sub">
                Placed <?php echo date('M j, Y', strtotime($order['created_at'])); ?> at <?php echo date('g:ia', strtotime($order['created_at'])); ?>
                <?php if (!empty($order['is_preorder']) && !empty($order['preorder_ship_date'])): ?>
                    <span class="ui-head__sep"></span>Promised by <strong><?php echo date('M j', strtotime($order['preorder_ship_date'])); ?></strong>, <a href="/admin/preorders.php">see the batch</a>
                <?php endif; ?>
            </p>
        </div>
        <div class="ui-head__actions">
            <?php if ($stripe_dashboard && !$balanceDashboard): ?>
                <a class="btn btn-secondary" href="<?php echo htmlspecialchars($stripe_dashboard); ?>" target="_blank" rel="noopener">View in Stripe</a>
            <?php endif; ?>
            <?php if ($canShip): ?>
                <a class="btn btn-primary" href="#fulfill" onclick="var d=document.getElementById('fulfill');if(d){d.open=true;}">Mark as shipped</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($balanceError): ?>
        <p class="error"><?php echo htmlspecialchars($balanceError); ?></p>
    <?php endif; ?>
    <?php if ($balanceFlash): ?>
        <p class="success"><?php echo htmlspecialchars($balanceFlash); ?></p>
    <?php endif; ?>

    <div class="order-grid">
        <div class="order-card">
            <h2 class="order-card__title">Items</h2>
            <?php if (!$items): ?>
                <p class="ui-muted">No line items recorded for this order.</p>
            <?php else: ?>
                <ul class="ui-items">
                    <?php foreach ($items as $it):
                        $qty = (int) $it['quantity'];
                        $name = preg_replace('/^(Pre-order|Deposit): /', '', $it['product_name']); ?>
                    <li class="ui-item">
                        <span class="ui-item__thumb"><?php if (!empty($it['image_path'])): ?><img src="<?php echo htmlspecialchars($it['image_path']); ?>" alt=""><?php endif; ?></span>
                        <span class="ui-item__main">
                            <?php if (!empty($it['product_id'])): ?>
                                <a href="/admin/edit-product.php?id=<?php echo (int) $it['product_id']; ?>"><?php echo htmlspecialchars($name); ?></a>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars($name); ?></strong>
                            <?php endif; ?>
                            <span><?php echo $qty; ?> × $<?php echo number_format((float) $it['price_at_purchase'], 2); ?><?php echo empty($it['product_id']) && empty($order['quote_id']) ? ', product deleted' : ''; ?><?php echo !empty($order['quote_id']) ? ', custom piece' : ''; ?></span>
                        </span>
                        <span class="ui-item__amount">$<?php echo number_format((float) $it['price_at_purchase'] * $qty, 2); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="order-totals">
                <div class="order-totals__row">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <?php if ($discount_amount > 0): ?>
                <div class="order-totals__row">
                    <span>Discount<?php if (!empty($order['discount_code'])): ?> <small style="color:rgba(45,45,45,0.55)">(<?php echo htmlspecialchars($order['discount_code']); ?>)</small><?php endif; ?></span>
                    <span style="color:<?php echo '#C25B32'; ?>">&minus;$<?php echo number_format($discount_amount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="order-totals__row">
                    <span>Shipping</span>
                    <span><?php echo $shipping_cost > 0 ? '$' . number_format($shipping_cost, 2) : 'Included'; ?></span>
                </div>
                <div class="order-totals__row order-totals__row--grand">
                    <span>Total</span>
                    <span>$<?php echo number_format((float) $order['total'], 2); ?></span>
                </div>
                <?php if ($order['balance_due'] !== null && $order['amount_paid'] !== null && (float) $order['amount_paid'] < (float) $order['total'] + 0.001 && ($owes || !empty($order['quote_id']) || !empty($order['is_preorder']))): ?>
                <div class="order-totals__row order-totals__row--muted">
                    <span>Paid</span>
                    <span>$<?php echo number_format((float) $order['amount_paid'], 2); ?></span>
                </div>
                <?php if ($owes): ?>
                <div class="order-totals__row order-totals__row--owed">
                    <span>Still owed</span>
                    <span>$<?php echo number_format((float) $order['balance_due'], 2); ?></span>
                </div>
                <?php endif; endif; ?>
            </div>
        </div>

        <div class="order-side">
            <div class="order-card">
                <h2 class="order-card__title">Customer</h2>
                <div class="ui-customer">
                    <?php echo admin_avatar($order['customer_name'] ?: $order['customer_email'] ?: '?', 'ui-avatar--lg'); ?>
                    <div>
                        <strong><?php echo htmlspecialchars($order['customer_name'] ?: 'No name'); ?></strong>
                        <?php if (!empty($order['customer_email'])): ?><a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>"><?php echo htmlspecialchars($order['customer_email']); ?></a><?php endif; ?>
                    </div>
                </div>
                <?php if ($customerOrders > 1): ?>
                    <a class="ui-customer__more" href="/admin/orders.php?q=<?php echo urlencode($order['customer_email']); ?>"><?php echo $customerOrders; ?> orders from this customer</a>
                <?php elseif ($customerOrders === 1): ?>
                    <p class="ui-customer__more">First order</p>
                <?php endif; ?>
                <?php if (!empty($order['shipping_address'])): ?>
                    <div class="ui-customer__ship">
                        <span class="ui-label">Shipping to</span>
                        <address><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></address>
                    </div>
                <?php endif; ?>
            </div>

            <div class="order-card">
                <h2 class="order-card__title">Fulfillment</h2>
                <?php if ($balanceDue): ?>
                    <div class="error" style="margin-bottom:12px">
                        Not fulfilled — $<?php echo number_format((float) $order['balance_due'], 2); ?>
                        is still owed on this order. Collect the balance before it ships.
                    </div>
                <?php elseif ($needsTracking): ?>
                    <div class="error" style="margin-bottom:12px">
                        Not fulfilled yet — add a tracking number below so the customer gets their shipping email.
                    </div>
                <?php endif; ?>

                <?php if ($order['balance_due'] !== null && (float) $order['balance_due'] > 0): ?>
                    <div class="ui-owed">
                        <strong><?php echo !empty($order['is_preorder']) ? 'Pre-order.' : 'Custom order.'; ?></strong>
                        $<?php echo number_format((float) $order['amount_paid'], 2); ?> deposit paid, <strong>$<?php echo number_format((float) $order['balance_due'], 2); ?> still owed</strong>.
                        <div>It can ship once the balance is paid.</div>
                    </div>

                    <details style="margin-bottom:10px">
                        <summary class="btn btn-primary" style="width:100%;box-sizing:border-box;text-align:center;cursor:pointer;list-style:none">Request balance</summary>
                        <form method="POST" action="/admin/order-balance.php" style="margin-top:12px;padding:14px;background:rgba(194,91,50,0.05);border-radius:10px">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                            <input type="hidden" name="action" value="request">
                            <label style="display:block;font-size:12px;color:rgba(45,45,45,0.6);margin-bottom:4px">Add a note <span style="font-weight:400;opacity:.7">(optional)</span></label>
                            <textarea name="note" rows="3" placeholder="It's finished and I'm really happy with how the glaze came out…"
                                      style="width:100%;margin-bottom:10px;font-family:inherit"></textarea>
                            <p style="margin:0 0 12px;font-size:12px;color:rgba(45,45,45,0.55)">
                                Emails <?php echo htmlspecialchars($order['customer_email']); ?> a link to pay
                                $<?php echo number_format((float) $order['balance_due'], 2); ?>.
                            </p>
                            <button type="submit" class="btn btn-primary" style="width:100%">Send balance request</button>
                        </form>
                    </details>

                    <?php if (!empty($order['is_preorder']) && !empty($order['preorder_token'])):
                        require_once __DIR__ . '/../includes/preorders.php';
                        $payUrl = preorder_pay_url($order); ?>
                        <div style="margin:10px 0;font-size:12px;color:rgba(45,45,45,0.6)">
                            Buyer's pay link<?php echo empty($order['preorder_balance_requested_at']) ? ' (the Pay button appears once you request the balance)' : ''; ?>:
                            <div style="display:flex;gap:6px;margin-top:4px">
                                <input type="text" readonly value="<?php echo htmlspecialchars($payUrl); ?>" onclick="this.select()" style="flex:1;min-width:0;margin:0;font-size:12px">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='Copied'">Copy</button>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($order['preorder_balance_requested_at'])): ?>
                        <div style="font-size:12px;color:rgba(45,45,45,0.6);margin:8px 0">
                            Balance requested <?php echo date('M j, Y g:ia', strtotime($order['preorder_balance_requested_at'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($quoteRow && !empty($quoteRow['balance_email_sent_at'])): ?>
                        <p style="margin:0 0 10px;font-size:12px;color:rgba(45,45,45,0.55)">
                            Balance requested <?php echo date('M j, Y g:ia', strtotime($quoteRow['balance_email_sent_at'])); ?>
                        </p>
                    <?php endif; ?>

                    <details style="margin-bottom:10px">
                        <summary class="btn btn-secondary" style="width:100%;box-sizing:border-box;text-align:center;cursor:pointer;list-style:none">Mark balance as paid</summary>
                        <form method="POST" action="/admin/order-balance.php" style="margin-top:12px;padding:14px;background:rgba(45,45,45,0.04);border-radius:10px"
                              onsubmit="return confirm('Record $<?php echo number_format((float) $order['balance_due'], 2); ?> as paid? Stripe won\'t be charged — only use this if you already have the money.')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $order['id']; ?>">
                            <input type="hidden" name="action" value="manual">
                            <label style="display:block;font-size:12px;color:rgba(45,45,45,0.6);margin-bottom:4px">How was it paid?</label>
                            <input type="text" name="how" placeholder="Cash at pickup" autocomplete="off" style="width:100%;margin-bottom:10px">
                            <p style="margin:0 0 12px;font-size:12px;color:rgba(45,45,45,0.55)">
                                For money collected outside Stripe — cash or a check. Records the payment and unlocks fulfillment.
                            </p>
                            <button type="submit" class="btn btn-primary" style="width:100%">Record as paid</button>
                        </form>
                    </details>
                <?php elseif (!empty($order['balance_paid_manually_at'])): ?>
                    <p style="margin:0 0 10px;font-size:12px;color:rgba(45,45,45,0.55)">
                        Balance recorded as paid outside Stripe on
                        <?php echo date('M j, Y', strtotime($order['balance_paid_manually_at'])); ?>.
                    </p>
                <?php endif; ?>

                <?php if ($order['status'] !== 'fulfilled'): ?>
                    <?php if ($order['status'] === 'cancelled'): ?>
                    <p class="ui-muted">This order was cancelled.</p>
                    <?php elseif (!$owes): ?>
                    <details id="fulfill" class="ui-ship" <?php echo $needsTracking ? 'open' : ''; ?>>
                        <summary class="btn btn-primary">Mark as shipped</summary>
                        <form method="POST" action="/admin/update-order-status.php" style="margin-top:12px;padding:14px;background:rgba(194,91,50,0.05);border-radius:10px">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="status" value="fulfilled">
                            <label style="display:block;font-size:12px;color:rgba(45,45,45,0.6);margin-bottom:4px">Tracking number</label>
                            <input type="text" name="tracking_number" inputmode="numeric" autocomplete="off" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>" placeholder="e.g. 9400 1234 5678 9012 3456 78" style="width:100%;margin-bottom:6px">
                            <p style="margin:0 0 12px;font-size:12px;color:rgba(45,45,45,0.55)">
                                <?php if (!empty($order['customer_email'])): ?>
                                    Saving will email the tracking number to <?php echo htmlspecialchars($order['customer_email']); ?>.
                                <?php else: ?>
                                    No customer email on file — the order will be fulfilled without a shipping email.
                                <?php endif; ?>
                            </p>
                            <button type="submit" class="btn btn-primary" style="width:100%">Mark shipped and email tracking</button>
                        </form>
                    </details>
                    <?php endif; ?>
                <?php elseif (!empty($order['fulfilled_at'])): ?>
                    <div style="margin-top:10px;font-size:13px;color:rgba(45,45,45,0.55)">
                        <span class="ui-done">Shipped <?php echo date('M j, Y', strtotime($order['fulfilled_at'])); ?></span>
                        <?php if (!empty($order['tracking_number'])): ?><br>Tracking <?php echo htmlspecialchars($order['tracking_number']); ?><?php endif; ?>
                        <?php if (!empty($order['shipping_email_sent_at'])): ?>
                            <br>Shipping email sent <?php echo date('M j, Y g:ia', strtotime($order['shipping_email_sent_at'])); ?>
                        <?php elseif (!empty($order['customer_email'])): ?>
                            <br><span style="color:#C25B32">Shipping email not sent yet.</span>
                            <details style="margin-top:8px">
                                <summary class="btn btn-mustard" style="width:100%;box-sizing:border-box;text-align:center;cursor:pointer;list-style:none">Send tracking email</summary>
                                <form method="POST" action="/admin/update-order-status.php" style="margin-top:12px;padding:14px;background:rgba(194,91,50,0.05);border-radius:10px">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="status" value="fulfilled">
                                    <label style="display:block;font-size:12px;color:rgba(45,45,45,0.6);margin-bottom:4px">Tracking number</label>
                                    <input type="text" name="tracking_number" inputmode="numeric" autocomplete="off" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>" placeholder="e.g. 9400 1234 5678 9012 3456 78" style="width:100%;margin-bottom:12px">
                                    <button type="submit" class="btn btn-primary" style="width:100%">Send to <?php echo htmlspecialchars($order['customer_email']); ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <details class="ui-disclose">
                    <summary>Change status manually</summary>
                    <form method="POST" action="/admin/update-order-status.php">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                        <select name="status" aria-label="Order status">
                            <?php foreach (['pending', 'paid', 'fulfilled', 'cancelled'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>><?php echo admin_order_status_label($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary">Update</button>
                    </form>
                </details>
            </div>

            <div class="order-card" id="notes">
                <h2 class="order-card__title">Notes</h2>
                <form method="POST" action="/admin/update-order-notes.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                    <label style="display:block;font-size:12px;color:rgba(45,45,45,0.6);margin-bottom:4px">Tracking number</label>
                    <input type="text" name="tracking_number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>" placeholder="e.g. 9400 1234 5678 9012 3456 78" style="width:100%;margin-bottom:12px">
                    <label style="display:block;font-size:12px;color:rgba(45,45,45,0.6);margin-bottom:4px">Notes</label>
                    <textarea name="notes" rows="4" placeholder="Carrier, packaging notes, customer requests…" style="width:100%;margin-bottom:12px;font-family:inherit"><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-primary" style="width:100%">Save</button>
                </form>
            </div>

            <?php if ($stripe_dashboard && $balanceDashboard): ?>
            <div class="order-card">
                <h2 class="order-card__title">Payments in Stripe</h2>
                <?php if ($balanceDashboard): ?>
                    <p style="margin:0 0 10px;font-size:12px;color:rgba(45,45,45,0.55)">
                        A custom order is two separate Stripe charges — Stripe doesn't link them, so both are here.
                    </p>
                    <a href="<?php echo htmlspecialchars($stripe_dashboard); ?>" target="_blank" rel="noopener"
                       class="btn btn-secondary" style="width:100%;text-align:center;margin-bottom:8px">
                        Deposit $<?php echo number_format((float) $quoteRow['deposit_amount'], 2); ?> &rarr;
                    </a>
                    <a href="<?php echo htmlspecialchars($balanceDashboard); ?>" target="_blank" rel="noopener"
                       class="btn btn-secondary" style="width:100%;text-align:center">
                        Balance $<?php echo number_format((float) $quoteRow['total'] - (float) $quoteRow['deposit_amount'], 2); ?> &rarr;
                    </a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($stripe_dashboard); ?>" target="_blank" rel="noopener" class="btn btn-secondary" style="width:100%;text-align:center">Open in Stripe &rarr;</a>
                <?php endif; ?>
                <div style="margin-top:10px;font-size:11px;color:rgba(45,45,45,0.45);word-break:break-all">
                    <?php echo htmlspecialchars($order['stripe_session_id']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
