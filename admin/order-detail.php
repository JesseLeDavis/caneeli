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

$items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll();

$subtotal = 0;
foreach ($items as $it) {
    $subtotal += ((float) $it['price_at_purchase']) * (int) $it['quantity'];
}
$shipping_cost = max(0, (float) $order['total'] - $subtotal);

$stripe_dashboard = null;
if (!empty($order['stripe_payment_intent'])) {
    $stripe_dashboard = 'https://dashboard.stripe.com/payments/' . urlencode($order['stripe_payment_intent']);
} elseif (!empty($order['stripe_session_id'])) {
    $stripe_dashboard = 'https://dashboard.stripe.com/search?query=' . urlencode($order['stripe_session_id']);
}

$pageTitle = 'Order #' . $order['id'];
$activeNav = 'orders';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <a href="/admin/orders.php" style="font-size:13px;color:rgba(45,45,45,0.55)">&larr; All orders</a>
            <h1 style="margin-top:4px">Order #<?php echo $order['id']; ?></h1>
            <div style="margin-top:4px;color:rgba(45,45,45,0.55);font-size:14px">
                Placed <?php echo date('M j, Y g:ia', strtotime($order['created_at'])); ?>
            </div>
        </div>
        <span class="badge-status badge-status--<?php echo htmlspecialchars($order['status']); ?>" style="font-size:14px;padding:6px 16px"><?php echo ucfirst($order['status']); ?></span>
    </div>

    <div class="order-grid">
        <div class="order-card">
            <h3 class="order-card__title">Items</h3>
            <?php if (!$items): ?>
                <p style="color:rgba(45,45,45,0.55)">No line items recorded for this order.</p>
            <?php else: ?>
                <table style="box-shadow:none;border-radius:0">
                    <thead>
                        <tr>
                            <th style="background:none;color:var(--dark);border-bottom:1px solid rgba(194,91,50,0.2)">Product</th>
                            <th style="background:none;color:var(--dark);border-bottom:1px solid rgba(194,91,50,0.2)">Qty</th>
                            <th style="background:none;color:var(--dark);border-bottom:1px solid rgba(194,91,50,0.2)">Price</th>
                            <th style="background:none;color:var(--dark);border-bottom:1px solid rgba(194,91,50,0.2)">Line</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <?php if (!empty($it['product_id'])): ?>
                                    <a href="/admin/edit-product.php?id=<?php echo $it['product_id']; ?>"><?php echo htmlspecialchars($it['product_name']); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($it['product_name']); ?>
                                    <span style="font-size:11px;color:rgba(45,45,45,0.45);margin-left:6px">(deleted)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $it['quantity']; ?></td>
                            <td>$<?php echo number_format((float) $it['price_at_purchase'], 2); ?></td>
                            <td>$<?php echo number_format(((float) $it['price_at_purchase']) * (int) $it['quantity'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="order-totals">
                <div class="order-totals__row">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <?php if ($shipping_cost > 0): ?>
                <div class="order-totals__row">
                    <span>Shipping / tax</span>
                    <span>$<?php echo number_format($shipping_cost, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="order-totals__row order-totals__row--grand">
                    <span>Total</span>
                    <span>$<?php echo number_format((float) $order['total'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="order-side">
            <div class="order-card">
                <h3 class="order-card__title">Customer</h3>
                <div class="order-field">
                    <span class="order-field__label">Name</span>
                    <span><?php echo htmlspecialchars($order['customer_name'] ?: '—'); ?></span>
                </div>
                <div class="order-field">
                    <span class="order-field__label">Email</span>
                    <span><a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>"><?php echo htmlspecialchars($order['customer_email']); ?></a></span>
                </div>
            </div>

            <?php if (!empty($order['shipping_address'])): ?>
            <div class="order-card">
                <h3 class="order-card__title">Shipping to</h3>
                <pre class="order-address"><?php echo htmlspecialchars($order['shipping_address']); ?></pre>
            </div>
            <?php endif; ?>

            <div class="order-card">
                <h3 class="order-card__title">Status</h3>
                <form method="POST" action="/admin/update-order-status.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                    <select name="status" style="margin-bottom:12px">
                        <?php foreach (['pending', 'paid', 'fulfilled', 'cancelled'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary" style="width:100%">Update status</button>
                </form>

                <?php if ($order['status'] !== 'fulfilled'): ?>
                    <form method="POST" action="/admin/update-order-status.php" style="margin-top:10px">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="status" value="fulfilled">
                        <button type="submit" class="btn btn-secondary" style="width:100%">Mark as fulfilled</button>
                    </form>
                <?php elseif (!empty($order['fulfilled_at'])): ?>
                    <div style="margin-top:10px;font-size:13px;color:rgba(45,45,45,0.55)">
                        Fulfilled <?php echo date('M j, Y g:ia', strtotime($order['fulfilled_at'])); ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($stripe_dashboard): ?>
            <div class="order-card">
                <h3 class="order-card__title">Stripe</h3>
                <a href="<?php echo htmlspecialchars($stripe_dashboard); ?>" target="_blank" rel="noopener" class="btn btn-secondary" style="width:100%;text-align:center">Open in Stripe &rarr;</a>
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
