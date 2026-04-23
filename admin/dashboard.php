<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

// ── Revenue / orders (counting paid + fulfilled only) ─────────────────────
function revenueStats(PDO $pdo, ?string $since): array {
    $where = "status IN ('paid','fulfilled')";
    $params = [];
    if ($since) {
        $where .= " AND created_at >= ?";
        $params[] = $since;
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders FROM orders WHERE $where");
    $stmt->execute($params);
    $row = $stmt->fetch();
    return [
        'revenue' => (float) $row['revenue'],
        'orders'  => (int)   $row['orders'],
    ];
}

$today_start = date('Y-m-d 00:00:00');
$d7          = date('Y-m-d 00:00:00', strtotime('-6 days'));
$d30         = date('Y-m-d 00:00:00', strtotime('-29 days'));

$stats_today = revenueStats($pdo, $today_start);
$stats_7d    = revenueStats($pdo, $d7);
$stats_30d   = revenueStats($pdo, $d30);
$stats_all   = revenueStats($pdo, null);

$aov_30d = $stats_30d['orders'] > 0 ? $stats_30d['revenue'] / $stats_30d['orders'] : 0;

// Orders that need action
$needs_action = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','paid')")->fetchColumn();

// New signups (7d)
$new_signups_7d = (int) $pdo->query("
    SELECT COUNT(*) FROM email_signups WHERE created_at >= '" . $d7 . "'
")->fetchColumn();

$total_signups = (int) $pdo->query("SELECT COUNT(*) FROM email_signups")->fetchColumn();

// Product stats
$product_stats = $pdo->query("
    SELECT
        COUNT(*)                                                        AS total,
        SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END)                     AS active,
        SUM(CASE WHEN stock_qty = 0 THEN 1 ELSE 0 END)                  AS sold_out,
        SUM(CASE WHEN stock_qty > 0 AND stock_qty <= 3 THEN 1 ELSE 0 END) AS low_stock
    FROM products
")->fetch();

// Low stock / sold out products (show up to 5 each)
$low_stock_products = $pdo->query("
    SELECT id, name, stock_qty
    FROM products
    WHERE stock_qty > 0 AND stock_qty <= 3 AND active = 1
    ORDER BY stock_qty ASC, name ASC
    LIMIT 5
")->fetchAll();

$sold_out_products = $pdo->query("
    SELECT id, name
    FROM products
    WHERE stock_qty = 0 AND active = 1
    ORDER BY updated_at DESC
    LIMIT 5
")->fetchAll();

// Top sellers (last 30d, paid/fulfilled only)
$top_sellers_stmt = $pdo->prepare("
    SELECT
        oi.product_id,
        COALESCE(p.name, oi.product_name) AS name,
        SUM(oi.quantity)                  AS units_sold,
        SUM(oi.quantity * oi.price_at_purchase) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.status IN ('paid','fulfilled')
      AND o.created_at >= ?
    GROUP BY oi.product_id, name
    ORDER BY units_sold DESC, revenue DESC
    LIMIT 5
");
$top_sellers_stmt->execute([$d30]);
$top_sellers = $top_sellers_stmt->fetchAll();

// Recent orders (5 most recent)
$recent_orders = $pdo->query("
    SELECT id, created_at, customer_name, customer_email, status, total
    FROM orders
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Dashboard</h1>
        <div style="font-size:13px;color:rgba(45,45,45,0.55)">
            <?php echo date('l, F j, Y'); ?>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-card__label">Revenue (30d)</div>
            <div class="kpi-card__value">$<?php echo number_format($stats_30d['revenue'], 2); ?></div>
            <div class="kpi-card__sub">
                Today: $<?php echo number_format($stats_today['revenue'], 2); ?> &middot;
                7d: $<?php echo number_format($stats_7d['revenue'], 2); ?>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card__label">Orders (30d)</div>
            <div class="kpi-card__value"><?php echo $stats_30d['orders']; ?></div>
            <div class="kpi-card__sub">
                All time: <?php echo $stats_all['orders']; ?> &middot;
                Today: <?php echo $stats_today['orders']; ?>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-card__label">Avg order (30d)</div>
            <div class="kpi-card__value">$<?php echo number_format($aov_30d, 2); ?></div>
            <div class="kpi-card__sub">
                All-time revenue: $<?php echo number_format($stats_all['revenue'], 2); ?>
            </div>
        </div>
        <a href="/admin/orders.php?status=paid" class="kpi-card kpi-card--action <?php echo $needs_action > 0 ? 'kpi-card--alert' : ''; ?>">
            <div class="kpi-card__label">Needs action</div>
            <div class="kpi-card__value"><?php echo $needs_action; ?></div>
            <div class="kpi-card__sub">
                <?php echo $needs_action === 0 ? 'All caught up' : 'Orders awaiting fulfillment'; ?>
            </div>
        </a>
    </div>

    <div class="dash-grid">
        <!-- Left column -->
        <div class="dash-col">
            <div class="order-card">
                <div class="dash-card-header">
                    <h3 class="order-card__title" style="margin:0;padding:0;border:none">Recent orders</h3>
                    <a href="/admin/orders.php" style="font-size:13px">View all &rarr;</a>
                </div>
                <?php if (!$recent_orders): ?>
                    <p style="color:rgba(45,45,45,0.55);margin-top:12px">No orders yet. Your first one will show up here.</p>
                <?php else: ?>
                    <table style="box-shadow:none;border-radius:0;margin-top:12px">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $o): ?>
                            <tr>
                                <td><a href="/admin/order-detail.php?id=<?php echo $o['id']; ?>"><strong>#<?php echo $o['id']; ?></strong></a></td>
                                <td>
                                    <?php echo htmlspecialchars($o['customer_name'] ?: '—'); ?>
                                    <div style="font-size:11px;color:rgba(45,45,45,0.5)"><?php echo date('M j, g:ia', strtotime($o['created_at'])); ?></div>
                                </td>
                                <td>$<?php echo number_format((float) $o['total'], 2); ?></td>
                                <td><span class="badge-status badge-status--<?php echo htmlspecialchars($o['status']); ?>"><?php echo ucfirst($o['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="order-card">
                <div class="dash-card-header">
                    <h3 class="order-card__title" style="margin:0;padding:0;border:none">Top sellers (30d)</h3>
                </div>
                <?php if (!$top_sellers): ?>
                    <p style="color:rgba(45,45,45,0.55);margin-top:12px">No sales in the last 30 days yet.</p>
                <?php else: ?>
                    <table style="box-shadow:none;border-radius:0;margin-top:12px">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Units</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_sellers as $ts): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($ts['product_id'])): ?>
                                        <a href="/admin/edit-product.php?id=<?php echo $ts['product_id']; ?>"><?php echo htmlspecialchars($ts['name']); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($ts['name']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int) $ts['units_sold']; ?></td>
                                <td>$<?php echo number_format((float) $ts['revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right column -->
        <div class="dash-col">
            <div class="order-card">
                <div class="dash-card-header">
                    <h3 class="order-card__title" style="margin:0;padding:0;border:none">Inventory</h3>
                    <a href="/admin/products.php" style="font-size:13px">Manage &rarr;</a>
                </div>
                <div class="inv-stats">
                    <div class="inv-stats__row">
                        <span>Total products</span>
                        <strong><?php echo (int) $product_stats['total']; ?></strong>
                    </div>
                    <div class="inv-stats__row">
                        <span>Active / visible</span>
                        <strong><?php echo (int) $product_stats['active']; ?></strong>
                    </div>
                    <div class="inv-stats__row <?php echo $product_stats['low_stock'] > 0 ? 'inv-stats__row--warn' : ''; ?>">
                        <span>Low stock (≤ 3)</span>
                        <strong><?php echo (int) $product_stats['low_stock']; ?></strong>
                    </div>
                    <div class="inv-stats__row <?php echo $product_stats['sold_out'] > 0 ? 'inv-stats__row--warn' : ''; ?>">
                        <span>Sold out</span>
                        <strong><?php echo (int) $product_stats['sold_out']; ?></strong>
                    </div>
                </div>

                <?php if ($low_stock_products): ?>
                    <div class="inv-list-label">Running low</div>
                    <ul class="inv-list">
                        <?php foreach ($low_stock_products as $p): ?>
                            <li>
                                <a href="/admin/edit-product.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                                <span class="inv-list__qty"><?php echo (int) $p['stock_qty']; ?> left</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($sold_out_products): ?>
                    <div class="inv-list-label">Sold out</div>
                    <ul class="inv-list">
                        <?php foreach ($sold_out_products as $p): ?>
                            <li>
                                <a href="/admin/edit-product.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                                <span class="inv-list__qty inv-list__qty--out">0</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="order-card">
                <div class="dash-card-header">
                    <h3 class="order-card__title" style="margin:0;padding:0;border:none">Email signups</h3>
                    <a href="/admin/email-signups.php" style="font-size:13px">View &rarr;</a>
                </div>
                <div class="inv-stats">
                    <div class="inv-stats__row">
                        <span>New this week</span>
                        <strong><?php echo $new_signups_7d; ?></strong>
                    </div>
                    <div class="inv-stats__row">
                        <span>Total</span>
                        <strong><?php echo $total_signups; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
