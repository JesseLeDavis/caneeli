<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

$allowed_statuses = ['all', 'pending', 'paid', 'fulfilled', 'cancelled'];
$filter = $_GET['status'] ?? 'all';
if (!in_array($filter, $allowed_statuses, true)) {
    $filter = 'all';
}

// Status counts for the tabs.
$counts_by_status = [
    'all' => 0, 'pending' => 0, 'paid' => 0, 'fulfilled' => 0, 'cancelled' => 0,
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
$where = $filter === 'all' ? '' : 'WHERE o.status = :status';
$sql = "
    SELECT
        o.*,
        COALESCE(SUM(oi.quantity), 0) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    $where
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sql);
if ($filter !== 'all') {
    $stmt->bindValue(':status', $filter);
}
$stmt->execute();
$orders = $stmt->fetchAll();

$pageTitle = 'Orders';
$activeNav = 'orders';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Orders (<?php echo $counts_by_status['all']; ?>)</h1>
        <?php if ($counts_by_status['all']): ?>
            <a href="/admin/orders.php?status=<?php echo $filter; ?>&export=1" class="btn btn-primary">Export CSV</a>
        <?php endif; ?>
    </div>

    <div class="status-tabs">
        <?php foreach ($allowed_statuses as $s): ?>
            <a href="/admin/orders.php?status=<?php echo $s; ?>"
               class="status-tab <?php echo $filter === $s ? 'is-active' : ''; ?>">
                <?php echo ucfirst($s); ?>
                <span class="status-tab__count"><?php echo $counts_by_status[$s]; ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$orders): ?>
        <p style="margin-top:20px">No <?php echo $filter === 'all' ? '' : $filter . ' '; ?>orders yet.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong>#<?php echo $o['id']; ?></strong></td>
                <td><?php echo date('M j, Y g:ia', strtotime($o['created_at'])); ?></td>
                <td>
                    <div style="font-weight:600"><?php echo htmlspecialchars($o['customer_name'] ?: '—'); ?></div>
                    <div style="font-size:12px;color:rgba(45,45,45,0.55)"><?php echo htmlspecialchars($o['customer_email']); ?></div>
                </td>
                <td><?php echo (int) $o['item_count']; ?></td>
                <td>$<?php echo number_format((float) $o['total'], 2); ?></td>
                <td><span class="badge-status badge-status--<?php echo htmlspecialchars($o['status']); ?>"><?php echo ucfirst($o['status']); ?></span></td>
                <td>
                    <a href="/admin/order-detail.php?id=<?php echo $o['id']; ?>" class="btn btn-secondary" style="padding:8px 14px;font-size:13px">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</body>
</html>
