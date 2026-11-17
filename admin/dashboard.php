<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();
require_once __DIR__ . '/../includes/product_status.php';
require_once __DIR__ . '/../includes/series_drop.php';

// Money actually collected: the full total for paid orders, the deposit for
// orders still owing a balance (pre-orders and commissions).
const DASH_COLLECTED = "CASE WHEN status = 'deposit_paid' THEN COALESCE(amount_paid, 0) ELSE total END";
const DASH_COUNTED   = "status IN ('paid','fulfilled','deposit_paid')";

function dash_stats(PDO $pdo, string $from, ?string $to = null): array {
    $sql = "SELECT COALESCE(SUM(" . DASH_COLLECTED . "),0) AS revenue, COUNT(*) AS orders FROM orders WHERE " . DASH_COUNTED . " AND created_at >= ?";
    $params = [$from];
    if ($to) { $sql .= " AND created_at < ?"; $params[] = $to; }
    $s = $pdo->prepare($sql);
    $s->execute($params);
    $r = $s->fetch();
    return ['revenue' => (float) $r['revenue'], 'orders' => (int) $r['orders']];
}

$today = date('Y-m-d 00:00:00');
$d30   = date('Y-m-d 00:00:00', strtotime('-29 days'));
$d60   = date('Y-m-d 00:00:00', strtotime('-59 days'));
$s30   = dash_stats($pdo, $d30);
$prev  = dash_stats($pdo, $d60, $d30);
$sToday = dash_stats($pdo, $today);
$change = $prev['revenue'] > 0 ? ($s30['revenue'] - $prev['revenue']) / $prev['revenue'] * 100 : null;

// To ship: paid and not a pre-order still waiting on its batch. A pre-order
// is ready once its balance came in (or it was paid in full and Annie asked).
$to_ship = (int) $pdo->query("
    SELECT COUNT(*) FROM orders
    WHERE status = 'paid'
      AND (is_preorder = 0 OR preorder_balance_requested_at IS NOT NULL)
")->fetchColumn();

$owed = $pdo->query("SELECT COALESCE(SUM(balance_due),0) AS amt, COUNT(*) AS n FROM orders WHERE status = 'deposit_paid' AND balance_due > 0")->fetch();

// Needs your attention
$todo = [];
if ($to_ship) $todo[] = [$to_ship . ' ' . ($to_ship === 1 ? 'order' : 'orders') . ' to pack and ship', 'Paid and ready to go.', '/admin/orders.php?status=paid', 'ship'];
$late = $pdo->query("SELECT name, preorder_ship_date FROM products WHERE status = 'preorder' AND preorder_ship_date < CURDATE()
    AND id IN (SELECT i.product_id FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.is_preorder = 1 AND o.status IN ('paid','deposit_paid'))")->fetchAll();
foreach ($late as $l) $todo[] = [$l['name'] . ' is past its ship date', 'Promised by ' . date('M j', strtotime($l['preorder_ship_date'])) . '. Ship it, or move the date so buyers are told.', '/admin/preorders.php', 'late'];
$unrequested = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE is_preorder = 1 AND status = 'deposit_paid' AND balance_due > 0 AND preorder_balance_requested_at IS NULL AND preorder_ship_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
if ($unrequested) $todo[] = [$unrequested . ' pre-order ' . ($unrequested === 1 ? 'balance' : 'balances') . ' to request', 'Their batch ships within a week. Request balances once it\'s ready.', '/admin/preorders.php', 'money'];
$quote_balance = (int) $pdo->query("SELECT COUNT(*) FROM custom_quotes WHERE status = 'deposit_paid'")->fetchColumn();
if ($quote_balance) $todo[] = [$quote_balance . ' ' . ($quote_balance === 1 ? 'commission' : 'commissions') . ' in progress', 'Deposit paid. Request the balance when the piece is finished.', '/admin/custom-orders.php', 'brush'];
try {
    $unread = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0 AND is_spam = 0")->fetchColumn();
} catch (\Throwable $e) {
    $unread = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn(); // before migrate_contact_spam.sql
}
if ($unread) $todo[] = [$unread . ' unread ' . ($unread === 1 ? 'message' : 'messages'), 'From the contact form.', '/admin/messages.php', 'mail'];

// Revenue by day, last 30 days
$rs = $pdo->prepare("SELECT DATE(created_at) AS d, SUM(" . DASH_COLLECTED . ") AS revenue FROM orders WHERE " . DASH_COUNTED . " AND created_at >= ? GROUP BY DATE(created_at)");
$rs->execute([$d30]);
$by_day = array_column($rs->fetchAll(), 'revenue', 'd');
$chart_labels = $chart_values = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M j', strtotime($day));
    $chart_values[] = (float) ($by_day[$day] ?? 0);
}

$recent_orders = $pdo->query("SELECT id, created_at, customer_name, customer_email, status, total, is_preorder FROM orders ORDER BY created_at DESC LIMIT 6")->fetchAll();
$status_label  = ['pending' => 'Pending', 'paid' => 'Paid', 'fulfilled' => 'Shipped', 'cancelled' => 'Cancelled', 'deposit_paid' => 'Deposit paid'];

// Pre-order batches
$batches = $pdo->query("
    SELECT p.id, p.name, p.preorder_ship_date, p.stock_qty,
           COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN i.quantity END), 0) AS taken,
           COALESCE(SUM(CASE WHEN o.status = 'deposit_paid' THEN o.balance_due END), 0) AS owed
      FROM products p
      LEFT JOIN order_items i ON i.product_id = p.id
      LEFT JOIN orders o ON o.id = i.order_id AND o.is_preorder = 1
     WHERE p.status = 'preorder'
     GROUP BY p.id
     ORDER BY p.preorder_ship_date IS NULL, p.preorder_ship_date
")->fetchAll();

// Top sellers, last 30 days
$ts = $pdo->prepare("
    SELECT oi.product_id, COALESCE(p.name, oi.product_name) AS name, p.image_path,
           SUM(oi.quantity) AS units, SUM(oi.quantity * oi.price_at_purchase) AS revenue
      FROM order_items oi
      JOIN orders o ON o.id = oi.order_id
      LEFT JOIN products p ON p.id = oi.product_id
     WHERE o.status IN ('paid','fulfilled','deposit_paid') AND o.created_at >= ?
     GROUP BY oi.product_id, oi.product_name, p.name, p.image_path
     ORDER BY revenue DESC
     LIMIT 5
");
$ts->execute([$d30]);
$top_sellers = $ts->fetchAll();

$signups_7d = (int) $pdo->query("SELECT COUNT(*) FROM email_signups WHERE created_at >= '" . date('Y-m-d 00:00:00', strtotime('-6 days')) . "'")->fetchColumn();
$signups    = (int) $pdo->query("SELECT COUNT(*) FROM email_signups")->fetchColumn();

$drop = series_state();
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$money = fn($n, $d = 0) => '$' . number_format((float) $n, $d);
$todoIcons = [
    'ship'  => '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    'late'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'money' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
    'brush' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    'mail'  => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
];
$initials = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    return $i !== '' ? $i : '?';
};

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/layout-top.php';
?>

<div class="container dash">
    <div class="dash__head">
        <h1><?php echo $greeting; ?>, Annie</h1>
        <p><?php echo date('l, F j'); ?></p>
    </div>

    <div class="dash-kpis">
        <div class="dash-kpi">
            <span class="dash-kpi__label">Revenue, 30 days</span>
            <span class="dash-kpi__value"><?php echo $money($s30['revenue']); ?></span>
            <span class="dash-kpi__sub">
                <?php if ($change !== null): ?>
                    <span class="dash-pill dash-pill--<?php echo $change >= 0 ? 'up' : 'down'; ?>"><?php echo ($change >= 0 ? '+' : '−') . number_format(abs($change), 0); ?>%</span> vs previous 30
                <?php else: ?><?php echo $money($sToday['revenue']); ?> today<?php endif; ?>
            </span>
        </div>
        <div class="dash-kpi">
            <span class="dash-kpi__label">Orders, 30 days</span>
            <span class="dash-kpi__value"><?php echo $s30['orders']; ?></span>
            <span class="dash-kpi__sub"><?php echo $sToday['orders']; ?> today</span>
        </div>
        <a class="dash-kpi dash-kpi--link<?php echo $to_ship ? ' dash-kpi--hot' : ''; ?>" href="/admin/orders.php?status=paid">
            <span class="dash-kpi__label">To ship</span>
            <span class="dash-kpi__value"><?php echo $to_ship; ?></span>
            <span class="dash-kpi__sub"><?php echo $to_ship ? 'Ready to pack' : 'All caught up'; ?></span>
        </a>
        <a class="dash-kpi dash-kpi--link" href="/admin/preorders.php">
            <span class="dash-kpi__label">Owed to you</span>
            <span class="dash-kpi__value"><?php echo $money($owed['amt']); ?></span>
            <span class="dash-kpi__sub"><?php echo (int) $owed['n'] ? (int) $owed['n'] . ' ' . ((int) $owed['n'] === 1 ? 'balance' : 'balances') . ' outstanding' : 'Nothing outstanding'; ?></span>
        </a>
    </div>

    <div class="dash-grid">
        <div class="dash-col">
            <section class="order-card">
                <div class="dash-card-header"><h2 class="order-card__title">Needs your attention</h2></div>
                <?php if (!$todo): ?>
                    <p class="dash-empty dash-empty--good">All caught up. Nothing needs you right now.</p>
                <?php else: ?>
                    <ul class="dash-todo">
                        <?php foreach ($todo as [$title, $sub, $href, $kind]): ?>
                            <li><a href="<?php echo $href; ?>" class="dash-todo__item dash-todo__item--<?php echo $kind; ?>">
                                <span class="dash-todo__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $todoIcons[$kind]; ?></svg></span>
                                <span class="dash-todo__text"><strong><?php echo htmlspecialchars($title); ?></strong><span><?php echo htmlspecialchars($sub); ?></span></span>
                                <svg class="dash-todo__go" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="order-card">
                <div class="dash-card-header">
                    <h2 class="order-card__title">Revenue, last 30 days</h2>
                    <span class="dash-card-header__value"><?php echo $money($s30['revenue'], 2); ?></span>
                </div>
                <div class="dash-chart"><canvas id="revenue-chart" aria-label="Daily revenue, last 30 days" role="img"></canvas></div>
                <p class="dash-note">Deposits count on the day they're paid; balances aren't counted until they come in.</p>
            </section>

            <section class="order-card">
                <div class="dash-card-header">
                    <h2 class="order-card__title">Recent orders</h2>
                    <a href="/admin/orders.php">View all</a>
                </div>
                <?php if (!$recent_orders): ?>
                    <p class="dash-empty">No orders yet. Your first one will show up here.</p>
                <?php else: ?>
                <ul class="dash-orders">
                    <?php foreach ($recent_orders as $o): ?>
                        <li><a href="/admin/order-detail.php?id=<?php echo (int) $o['id']; ?>">
                            <span class="dash-orders__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials($o['customer_name'] ?: $o['customer_email'] ?: '?')); ?></span>
                            <span class="dash-orders__who"><strong><?php echo htmlspecialchars($o['customer_name'] ?: $o['customer_email'] ?: 'Customer'); ?></strong>
                                <span><?php echo ($o['is_preorder'] ? 'Pre-order #' : 'Order #') . (int) $o['id']; ?>, <?php echo date('M j', strtotime($o['created_at'])); ?></span></span>
                            <span class="badge-status badge-status--<?php echo htmlspecialchars($o['status']); ?>"><?php echo $status_label[$o['status']] ?? ucfirst($o['status']); ?></span>
                            <span class="dash-orders__total"><?php echo $money($o['total'], 2); ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
        </div>

        <div class="dash-col">
            <?php if ($drop !== 'over'): [$dd, $dh, $dm] = series_time_left(); ?>
            <section class="dash-drop" data-drop-release="<?php echo series_release_ts() * 1000; ?>">
                <div class="dash-drop__top">
                    <span class="dash-drop__tag"><?php echo $drop === 'live' ? 'Out now' : 'Next drop'; ?></span>
                    <span class="dash-drop__date"><?php echo htmlspecialchars(series_release_label()); ?></span>
                </div>
                <h2 class="dash-drop__name"><?php echo htmlspecialchars(SERIES_NAME); ?></h2>
                <?php if ($drop === 'soon'): ?>
                    <div class="dash-drop__clock">
                        <span><b data-d><?php echo $dd; ?></b>days</span>
                        <span><b data-h><?php echo $dh; ?></b>hours</span>
                        <span><b data-m><?php echo $dm; ?></b>min</span>
                    </div>
                <?php else: ?>
                    <p class="dash-drop__when">Live in the shop. The home page countdown comes down <?php echo date('M j', series_release_ts() + SERIES_BAR_DAYS_AFTER * 86400); ?>.</p>
                <?php endif; ?>
                <a class="dash-drop__list" href="/admin/email-signups.php"><strong><?php echo number_format($signups); ?></strong> on the mailing list<?php echo $signups_7d ? ', +' . $signups_7d . ' this week' : ''; ?></a>
            </section>
            <?php endif; ?>

            <section class="order-card">
                <div class="dash-card-header">
                    <h2 class="order-card__title">Pre-orders</h2>
                    <a href="/admin/preorders.php">Manage</a>
                </div>
                <?php if (!$batches): ?>
                    <p class="dash-empty">No batches open. Set a product's status to Pre-order to open one.</p>
                <?php else: foreach ($batches as $b):
                    $spots = (int) $b['taken'] + (int) $b['stock_qty']; ?>
                    <a class="dash-batch" href="/admin/preorders.php">
                        <span class="dash-batch__top"><strong><?php echo htmlspecialchars($b['name']); ?></strong><span><?php echo (int) $b['taken']; ?> of <?php echo $spots; ?> spots</span></span>
                        <span class="dash-batch__bar"><span style="width:<?php echo $spots ? min(100, round($b['taken'] / $spots * 100)) : 0; ?>%"></span></span>
                        <span class="dash-batch__sub"><span><?php echo $b['preorder_ship_date'] ? 'Ships by ' . date('M j', strtotime($b['preorder_ship_date'])) : 'No ship date'; ?></span><?php if ((float) $b['owed'] > 0): ?><span><?php echo $money($b['owed']); ?> owed</span><?php endif; ?></span>
                    </a>
                <?php endforeach; endif; ?>
            </section>

            <section class="order-card">
                <div class="dash-card-header"><h2 class="order-card__title">Top sellers, last 30 days</h2></div>
                <?php if (!$top_sellers): ?>
                    <p class="dash-empty">No sales in the last 30 days yet.</p>
                <?php else: ?>
                <ul class="dash-sellers">
                    <?php foreach ($top_sellers as $t): ?>
                        <li>
                            <span class="dash-sellers__thumb"><?php if ($t['image_path']): ?><img src="<?php echo htmlspecialchars($t['image_path']); ?>" alt=""><?php endif; ?></span>
                            <span class="dash-sellers__name"><?php if ($t['product_id']): ?><a href="/admin/edit-product.php?id=<?php echo (int) $t['product_id']; ?>"><?php echo htmlspecialchars($t['name']); ?></a><?php else: echo htmlspecialchars($t['name']); endif; ?>
                                <span><?php echo (int) $t['units']; ?> sold</span></span>
                            <span class="dash-sellers__rev"><?php echo $money($t['revenue']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const el = document.getElementById('revenue-chart');
    if (!el || typeof Chart === 'undefined') return;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($chart_values); ?>,
                backgroundColor: 'rgba(194, 91, 50, 0.85)',
                hoverBackgroundColor: '#A64B26',
                borderRadius: 4,
                borderSkipped: false,
                barPercentage: 0.62,
                categoryPercentage: 0.9,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#24221F', padding: 10, cornerRadius: 8, displayColors: false,
                    titleFont: { weight: '600' }, bodyFont: { weight: '700', size: 14 },
                    callbacks: { label: c => '$' + Number(c.parsed.y).toLocaleString('en-US', { maximumFractionDigits: 0 }) }
                }
            },
            scales: {
                x: {
                    grid: { display: false }, border: { display: false },
                    ticks: { color: '#9A958B', font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 6 }
                },
                y: {
                    beginAtZero: true, border: { display: false },
                    grid: { color: 'rgba(36, 34, 31, 0.06)', drawTicks: false },
                    ticks: {
                        color: '#9A958B', font: { size: 11 }, padding: 8, maxTicksLimit: 4,
                        callback: v => v >= 1000 ? '$' + (v / 1000).toFixed(v % 1000 ? 1 : 0) + 'k' : '$' + v
                    }
                }
            }
        }
    });
})();

// Drop countdown: keep the minutes current while the dashboard is open
(function () {
    const box = document.querySelector('.dash-drop[data-drop-release]');
    if (!box || !box.querySelector('[data-m]')) return;
    const release = +box.dataset.dropRelease;
    function tick() {
        const s = Math.max(0, Math.floor((release - Date.now()) / 1000));
        box.querySelector('[data-d]').textContent = Math.floor(s / 86400);
        box.querySelector('[data-h]').textContent = Math.floor(s % 86400 / 3600);
        box.querySelector('[data-m]').textContent = Math.floor(s % 3600 / 60);
    }
    setInterval(tick, 30000);
})();
</script>

</body>
</html>
