<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/product_status.php';

$pdo = getDB();

// ── Time window ─────────────────────────────────────────────────────────
$allowed_windows = ['7' => '7 days', '30' => '30 days', '90' => '90 days', 'all' => 'All time'];
$range = $_GET['range'] ?? '30';
if (!isset($allowed_windows[$range])) $range = '30';

$since = $range === 'all' ? null : date('Y-m-d H:i:s', strtotime('-' . $range . ' days'));
$since_sql_views = $since ? "AND pv.viewed_at >= '" . $since . "'"  : '';
$since_sql_cart  = $since ? "AND ce.created_at >= '" . $since . "'"  : '';
$since_sql_ord   = $since ? "AND o.created_at >= '"  . $since . "'"  : '';

// ── Headline numbers ─────────────────────────────────────────────────────
$views_total   = (int) $pdo->query("SELECT COUNT(*)                  FROM product_views pv WHERE 1=1 $since_sql_views")->fetchColumn();
$unique_sess   = (int) $pdo->query("SELECT COUNT(DISTINCT session_id) FROM product_views pv WHERE 1=1 $since_sql_views")->fetchColumn();
$adds_total    = (int) $pdo->query("SELECT COUNT(*) FROM cart_events ce WHERE ce.event_type='add'             $since_sql_cart")->fetchColumn();
$checkouts     = (int) $pdo->query("SELECT COUNT(*) FROM cart_events ce WHERE ce.event_type='checkout_start'  $since_sql_cart")->fetchColumn();
$purchases     = (int) $pdo->query("SELECT COUNT(*) FROM orders o WHERE o.status IN ('paid','fulfilled')      $since_sql_ord")->fetchColumn();

// Funnel conversion rates (guarding against division by zero).
$view_to_add     = $views_total > 0 ? ($adds_total / $views_total)    * 100 : 0;
$add_to_checkout = $adds_total  > 0 ? ($checkouts  / $adds_total)     * 100 : 0;
$checkout_to_buy = $checkouts   > 0 ? ($purchases  / $checkouts)      * 100 : 0;

// ── Most-viewed products ─────────────────────────────────────────────────
$view_params = $since ? [$since] : [];
$view_join   = $since ? "AND pv.viewed_at >= ?" : "";
$view_cond   = $since ? "AND o2.created_at >= ?" : "";
$view_params2 = $since ? [$since, $since] : [];

$most_viewed = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.stock_qty,
        p.status,
        COUNT(pv.id)                           AS total_views,
        COUNT(DISTINCT pv.session_id)          AS unique_viewers,
        (
            SELECT COUNT(DISTINCT oi.order_id)
            FROM order_items oi
            JOIN orders o2 ON o2.id = oi.order_id
            WHERE oi.product_id = p.id
              AND o2.status IN ('paid','fulfilled')
              $view_cond
        ) AS purchases
    FROM products p
    LEFT JOIN product_views pv ON pv.product_id = p.id $view_join
    WHERE p.status != 'archived'
    GROUP BY p.id, p.name, p.stock_qty, p.status
    HAVING total_views > 0 OR purchases > 0
    ORDER BY total_views DESC, purchases DESC
    LIMIT 20
");
$most_viewed->execute($view_params2);
$most_viewed_rows = $most_viewed->fetchAll();

// ── Cart funnel by product ───────────────────────────────────────────────
$cart_params  = $since ? [$since, $since] : [];
$cart_join_a  = $since ? "AND ce.created_at >= ?" : "";
$cart_cond_o  = $since ? "AND o3.created_at >= ?" : "";

$cart_funnel = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.stock_qty,
        p.status,
        SUM(CASE WHEN ce.event_type = 'add'    THEN 1 ELSE 0 END) AS adds,
        SUM(CASE WHEN ce.event_type = 'remove' THEN 1 ELSE 0 END) AS removes,
        (
            SELECT COUNT(DISTINCT oi.order_id)
            FROM order_items oi
            JOIN orders o3 ON o3.id = oi.order_id
            WHERE oi.product_id = p.id
              AND o3.status IN ('paid','fulfilled')
              $cart_cond_o
        ) AS purchases
    FROM products p
    LEFT JOIN cart_events ce ON ce.product_id = p.id $cart_join_a
    WHERE p.status != 'archived'
    GROUP BY p.id, p.name, p.stock_qty, p.status
    HAVING adds > 0 OR purchases > 0
    ORDER BY adds DESC, purchases DESC
    LIMIT 20
");
$cart_funnel->execute($cart_params);
$cart_funnel_rows = $cart_funnel->fetchAll();

// ── Popular but out of stock ─────────────────────────────────────────────
// Products with demand signal (views or adds) that can't be bought right now.
$pop_out_params = $since ? [$since, $since] : [];
$pop_out = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.stock_qty,
        p.status,
        COUNT(DISTINCT pv.id)                                                                        AS views,
        COALESCE(SUM(CASE WHEN ce.event_type = 'add' THEN 1 ELSE 0 END), 0)                          AS adds
    FROM products p
    LEFT JOIN product_views pv ON pv.product_id = p.id " . ($since ? "AND pv.viewed_at >= ?" : "") . "
    LEFT JOIN cart_events   ce ON ce.product_id = p.id " . ($since ? "AND ce.created_at >= ?" : "") . "
    WHERE (p.stock_qty = 0 OR p.status = 'sold_out')
      AND p.status != 'archived'
    GROUP BY p.id, p.name, p.stock_qty, p.status
    HAVING views > 0 OR adds > 0
    ORDER BY (views + adds*3) DESC
    LIMIT 10
");
$pop_out->execute($pop_out_params);
$pop_out_rows = $pop_out->fetchAll();

// ── Abandoned carts ─────────────────────────────────────────────────────
// Count sessions that added to cart but never started checkout.
$abandon_params = $since ? [$since, $since] : [];
$abandon_sql = "
    SELECT COUNT(*) FROM (
        SELECT ce.session_id
        FROM cart_events ce
        WHERE ce.event_type = 'add'
          AND ce.session_id IS NOT NULL
          " . ($since ? "AND ce.created_at >= ?" : "") . "
          AND ce.session_id NOT IN (
            SELECT session_id FROM cart_events
            WHERE event_type = 'checkout_start'
              AND session_id IS NOT NULL
              " . ($since ? "AND created_at >= ?" : "") . "
          )
        GROUP BY ce.session_id
    ) t
";
$stmt = $pdo->prepare($abandon_sql);
$stmt->execute($abandon_params);
$abandoned_count = (int) $stmt->fetchColumn();

$pageTitle = 'Insights';
$activeNav = 'insights';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Insights</h1>
        <div class="range-tabs">
            <?php foreach ($allowed_windows as $val => $label): ?>
                <a href="/admin/insights.php?range=<?php echo urlencode($val); ?>"
                   class="range-tab <?php echo $range === $val ? 'is-active' : ''; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Funnel headline -->
    <div class="funnel-grid">
        <div class="funnel-step">
            <div class="funnel-step__label">Product views</div>
            <div class="funnel-step__value"><?php echo number_format($views_total); ?></div>
            <div class="funnel-step__sub"><?php echo number_format($unique_sess); ?> unique visitors</div>
        </div>
        <div class="funnel-arrow"><?php echo number_format($view_to_add, 1); ?>%</div>
        <div class="funnel-step">
            <div class="funnel-step__label">Cart adds</div>
            <div class="funnel-step__value"><?php echo number_format($adds_total); ?></div>
            <div class="funnel-step__sub">of <?php echo number_format($views_total); ?> views</div>
        </div>
        <div class="funnel-arrow"><?php echo number_format($add_to_checkout, 1); ?>%</div>
        <div class="funnel-step">
            <div class="funnel-step__label">Checkouts started</div>
            <div class="funnel-step__value"><?php echo number_format($checkouts); ?></div>
            <div class="funnel-step__sub">of <?php echo number_format($adds_total); ?> cart adds</div>
        </div>
        <div class="funnel-arrow"><?php echo number_format($checkout_to_buy, 1); ?>%</div>
        <div class="funnel-step funnel-step--end">
            <div class="funnel-step__label">Purchases</div>
            <div class="funnel-step__value"><?php echo number_format($purchases); ?></div>
            <div class="funnel-step__sub">paid / fulfilled</div>
        </div>
    </div>

    <?php if ($abandoned_count > 0): ?>
        <div class="insight-callout">
            <strong><?php echo $abandoned_count; ?></strong> abandoned cart<?php echo $abandoned_count === 1 ? '' : 's'; ?>
            — customer added items but never started checkout.
        </div>
    <?php endif; ?>

    <!-- Most-viewed products -->
    <div class="order-card" style="margin-top:24px">
        <h3 class="order-card__title">Most-viewed products</h3>
        <?php if (!$most_viewed_rows): ?>
            <p style="color:rgba(45,45,45,0.55)">No product views in this window yet.</p>
        <?php else: ?>
            <table style="box-shadow:none;border-radius:0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Views</th>
                        <th>Unique</th>
                        <th>Purchases</th>
                        <th>View→buy</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($most_viewed_rows as $r): ?>
                        <?php
                            $unique = (int) $r['unique_viewers'];
                            $buys   = (int) $r['purchases'];
                            $conv   = $unique > 0 ? ($buys / $unique) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <a href="/admin/edit-product.php?id=<?php echo $r['id']; ?>">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </a>
                            </td>
                            <td><?php echo (int) $r['total_views']; ?></td>
                            <td><?php echo $unique; ?></td>
                            <td><?php echo $buys; ?></td>
                            <td><?php echo $unique > 0 ? number_format($conv, 1) . '%' : '—'; ?></td>
                            <td>
                                <span class="badge-status badge-status--<?php echo htmlspecialchars($r['status']); ?>">
                                    <?php echo product_status_label($r['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Cart funnel by product -->
    <div class="order-card" style="margin-top:20px">
        <h3 class="order-card__title">Cart activity by product</h3>
        <?php if (!$cart_funnel_rows): ?>
            <p style="color:rgba(45,45,45,0.55)">No cart activity yet.</p>
        <?php else: ?>
            <table style="box-shadow:none;border-radius:0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Adds</th>
                        <th>Removes</th>
                        <th>Purchases</th>
                        <th>Add→buy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_funnel_rows as $r): ?>
                        <?php
                            $adds = (int) $r['adds'];
                            $buys = (int) $r['purchases'];
                            $conv = $adds > 0 ? ($buys / $adds) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <a href="/admin/edit-product.php?id=<?php echo $r['id']; ?>">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </a>
                            </td>
                            <td><?php echo $adds; ?></td>
                            <td><?php echo (int) $r['removes']; ?></td>
                            <td><?php echo $buys; ?></td>
                            <td><?php echo $adds > 0 ? number_format($conv, 1) . '%' : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Popular but out of stock -->
    <?php if ($pop_out_rows): ?>
    <div class="order-card" style="margin-top:20px;border-top-color:var(--mustard)">
        <h3 class="order-card__title">Wanted but unavailable</h3>
        <p style="font-size:13px;color:rgba(45,45,45,0.55);margin-bottom:12px">
            People are looking at these, but they're sold out or out of stock. Candidates to restock.
        </p>
        <table style="box-shadow:none;border-radius:0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Views</th>
                    <th>Cart adds</th>
                    <th>State</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pop_out_rows as $r): ?>
                    <tr>
                        <td>
                            <a href="/admin/edit-product.php?id=<?php echo $r['id']; ?>">
                                <?php echo htmlspecialchars($r['name']); ?>
                            </a>
                        </td>
                        <td><?php echo (int) $r['views']; ?></td>
                        <td><?php echo (int) $r['adds']; ?></td>
                        <td>
                            <?php if ((int) $r['stock_qty'] === 0): ?>
                                <span class="badge-inactive">Stock 0</span>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'sold_out'): ?>
                                <span class="badge-status badge-status--sold_out" style="margin-left:4px">Sold Out</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
