<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

$allowed_statuses = ['all', 'paid', 'deposit_paid', 'fulfilled', 'pending', 'cancelled'];
$filter = $_GET['status'] ?? 'all';
if (!in_array($filter, $allowed_statuses, true)) {
    $filter = 'all';
}
$q = trim($_GET['q'] ?? '');

// Status counts for the tabs.
$counts_by_status = [
    'all' => 0, 'pending' => 0, 'paid' => 0, 'deposit_paid' => 0, 'fulfilled' => 0, 'cancelled' => 0,
];
$rows = $pdo->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status")->fetchAll();
foreach ($rows as $r) {
    $counts_by_status[$r['status']] = (int) $r['c'];
    $counts_by_status['all']       += (int) $r['c'];
}

// CSV export (respects current filter).
if (isset($_GET['export'])) {
    $where = $filter === 'all' ? '' : 'WHERE status = :status';
    $sql   = "SELECT id, created_at, customer_name, customer_email, status, total
              FROM orders
              $where
              ORDER BY created_at DESC";
    $stmt  = $pdo->prepare($sql);
    if ($filter !== 'all') {
        $stmt->bindValue(':status', $filter);
    }
    $stmt->execute();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders-' . $filter . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #', 'Date', 'Customer', 'Email', 'Status', 'Total']);
    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'],
            $row['created_at'],
            $row['customer_name'],
            $row['customer_email'],
            $row['status'],
            number_format((float) $row['total'], 2),
        ]);
    }
    fclose($out);
    exit;
}

// List of orders, joined with item count.
$conditions = [];
$params     = [];
if ($filter !== 'all') {
    $conditions[] = 'o.status = ?';
    $params[]     = $filter;
}
if ($q !== '') {
    $conditions[] = '(o.customer_email LIKE ? OR o.customer_name LIKE ?)';
    $params[]     = '%' . $q . '%';
    $params[]     = '%' . $q . '%';
}
$where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "
    SELECT
        o.*,
        COALESCE(SUM(oi.quantity), 0) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    $where_sql
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders';
$activeNav = 'orders';
require __DIR__ . '/layout-top.php';
?>

<div class="container ui-page">
    <div class="ui-head">
        <div>
            <h1>Orders</h1>
            <p class="ui-head__sub"><?php echo $counts_by_status['all']; ?> in total<?php echo $counts_by_status['paid'] ? ', ' . $counts_by_status['paid'] . ' paid and waiting to ship' : ''; ?></p>
        </div>
        <div class="ui-head__actions">
            <form class="ui-search" method="get" role="search">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <label class="visually-hidden" for="order-search">Search orders</label>
                <input type="search" id="order-search" name="q" placeholder="Search name or email" value="<?php echo htmlspecialchars($q); ?>">
            </form>
            <?php if ($counts_by_status['all']): ?>
                <a href="/admin/orders.php?status=<?php echo $filter; ?>&export=1" class="btn btn-secondary">Export CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <nav class="ui-tabs" aria-label="Filter by status">
        <?php foreach ($allowed_statuses as $s):
            if ($s !== 'all' && !$counts_by_status[$s] && $filter !== $s) continue; ?>
            <a href="/admin/orders.php?status=<?php echo $s; ?><?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>"
               class="ui-tab <?php echo $filter === $s ? 'is-active' : ''; ?>"<?php echo $filter === $s ? ' aria-current="page"' : ''; ?>>
                <?php echo $s === 'all' ? 'All' : admin_order_status_label($s); ?>
                <span class="ui-tab__count"><?php echo $counts_by_status[$s]; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($q): ?>
        <p class="ui-filter-note">Showing orders matching “<strong><?php echo htmlspecialchars($q); ?></strong>”. <a href="/admin/orders.php?status=<?php echo htmlspecialchars($filter); ?>">Clear search</a></p>
    <?php endif; ?>

    <?php if (!$orders): ?>
        <div class="ui-card ui-empty">
            <p><strong>No <?php echo $filter === 'all' ? '' : strtolower(admin_order_status_label($filter)) . ' '; ?>orders<?php echo $q ? ' matching that search' : ''; ?>.</strong></p>
            <p>New orders show up here as soon as they're paid.</p>
        </div>
    <?php else: ?>
    <div class="ui-card ui-list">
        <?php $lastDay = null; foreach ($orders as $o):
            $day = admin_day_label($o['created_at']);
            if ($day !== $lastDay): $lastDay = $day; ?>
                <div class="ui-list__day"><?php echo $day; ?></div>
            <?php endif;
            $who = $o['customer_name'] ?: $o['customer_email'] ?: 'Customer'; ?>
            <a class="ui-row ui-row--order" href="/admin/order-detail.php?id=<?php echo (int) $o['id']; ?>">
                <?php echo admin_avatar($who); ?>
                <span class="ui-row__main">
                    <strong><?php echo htmlspecialchars($who); ?></strong>
                    <span><?php echo htmlspecialchars($o['customer_email']); ?></span>
                </span>
                <span class="ui-row__meta">
                    <strong><?php echo !empty($o['is_preorder']) ? 'Pre-order' : 'Order'; ?> #<?php echo (int) $o['id']; ?></strong>
                    <span><?php echo date('g:ia', strtotime($o['created_at'])); ?>, <?php echo (int) $o['item_count']; ?> <?php echo (int) $o['item_count'] === 1 ? 'item' : 'items'; ?></span>
                </span>
                <span class="ui-row__status"><?php echo admin_status_badge($o['status']); ?>
                    <?php if (!empty($o['is_preorder']) && in_array($o['status'], ['paid', 'deposit_paid'], true) && !empty($o['preorder_ship_date'])): ?>
                        <span class="ui-row__hint">Ships by <?php echo date('M j', strtotime($o['preorder_ship_date'])); ?></span>
                    <?php endif; ?>
                </span>
                <span class="ui-row__amount">$<?php echo number_format((float) $o['total'], 2); ?>
                    <?php if ((float) ($o['balance_due'] ?? 0) > 0): ?><span class="ui-row__hint">$<?php echo number_format((float) $o['balance_due'], 2); ?> owed</span><?php endif; ?>
                </span>
                <?php echo admin_chevron(); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
