<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

$q = trim($_GET['q'] ?? '');

$where  = "WHERE status IN ('paid','fulfilled') AND customer_email <> ''";
$params = [];
if ($q !== '') {
    $where   .= " AND (customer_email LIKE ? OR customer_name LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$sql = "
    SELECT
        customer_email,
        MAX(customer_name)  AS name,
        COUNT(*)            AS order_count,
        SUM(total)          AS total_spent,
        MIN(created_at)     AS first_order,
        MAX(created_at)     AS latest_order
    FROM orders
    $where
    GROUP BY customer_email
    ORDER BY total_spent DESC, latest_order DESC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$summary = $pdo->query("
    SELECT
        COUNT(DISTINCT customer_email) AS total,
        SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) AS repeat_buyers
    FROM (
        SELECT customer_email, COUNT(*) AS order_count
        FROM orders
        WHERE status IN ('paid','fulfilled') AND customer_email <> ''
        GROUP BY customer_email
    ) c
")->fetch();

$pageTitle = 'Customers';
$activeNav = 'customers';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Customers (<?php echo (int) $summary['total']; ?>)</h1>
        <div style="font-size:13px;color:rgba(45,45,45,0.55)">
            <strong><?php echo (int) $summary['repeat_buyers']; ?></strong> repeat buyer<?php echo (int) $summary['repeat_buyers'] === 1 ? '' : 's'; ?>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by name or email…" class="filter-bar__search">
        <button type="submit" class="btn btn-secondary" style="padding:10px 18px">Search</button>
        <?php if ($q): ?><a href="/admin/customers.php" class="filter-bar__clear">Clear</a><?php endif; ?>
    </form>

    <?php if (!$customers): ?>
        <p style="margin-top:20px">No customers yet. Once someone orders, they'll show up here.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th>Orders</th>
                <th>Total spent</th>
                <th>First order</th>
                <th>Latest</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $c): ?>
                <?php $is_repeat = (int) $c['order_count'] > 1; ?>
                <tr>
                    <td>
                        <div style="font-weight:600"><?php echo htmlspecialchars($c['name'] ?: '—'); ?></div>
                        <div style="font-size:12px;color:rgba(45,45,45,0.55)">
                            <a href="mailto:<?php echo htmlspecialchars($c['customer_email']); ?>"><?php echo htmlspecialchars($c['customer_email']); ?></a>
                        </div>
                    </td>
                    <td>
                        <?php if ($is_repeat): ?>
                            <span class="badge-status badge-status--fulfilled"><?php echo $c['order_count']; ?>×</span>
                        <?php else: ?>
                            <?php echo $c['order_count']; ?>
                        <?php endif; ?>
                    </td>
                    <td>$<?php echo number_format((float) $c['total_spent'], 2); ?></td>
                    <td><?php echo date('M j, Y', strtotime($c['first_order'])); ?></td>
                    <td><?php echo date('M j, Y', strtotime($c['latest_order'])); ?></td>
                    <td>
                        <a href="/admin/orders.php?status=all&q=<?php echo urlencode($c['customer_email']); ?>" class="btn btn-secondary" style="padding:8px 14px;font-size:12px">Orders</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</body>
</html>
