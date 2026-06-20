<?php
/**
 * Shared admin chrome (header + sub-nav).
 * Expects $pageTitle (string) to be set before include.
 * Expects $activeNav (string) — one of: 'dashboard', 'products', 'orders', 'customers', 'insights', 'discounts', 'signups', 'messages', ''.
 */
$activeNav = $activeNav ?? '';
$pageTitle = $pageTitle ?? 'Admin';

// Unread contact-message count for the nav badge. Guarded so a missing table
// (migration not yet run) never breaks every admin page.
$unreadMessages = 0;
if (function_exists('getDB')) {
    try {
        $unreadMessages = (int) getDB()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    } catch (\Throwable $e) {
        $unreadMessages = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Caneeli Admin</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<div class="admin-header">
    <div class="admin-header__brand">
        <img src="/assets/images/logowi.svg" alt="Caneeli Designs" class="admin-header__logo">
        <span class="admin-header__admin-label">Admin</span>
    </div>
    <a href="/admin/logout.php" class="admin-header__logout">Log Out</a>
</div>

<nav class="admin-subnav">
    <div class="admin-subnav__inner">
        <a href="/admin/dashboard.php" class="admin-subnav__link <?php echo $activeNav === 'dashboard' ? 'is-active' : ''; ?>">Dashboard</a>
        <a href="/admin/products.php" class="admin-subnav__link <?php echo $activeNav === 'products' ? 'is-active' : ''; ?>">Products</a>
        <a href="/admin/orders.php" class="admin-subnav__link <?php echo $activeNav === 'orders' ? 'is-active' : ''; ?>">Orders</a>
        <a href="/admin/customers.php" class="admin-subnav__link <?php echo $activeNav === 'customers' ? 'is-active' : ''; ?>">Customers</a>
        <a href="/admin/insights.php" class="admin-subnav__link <?php echo $activeNav === 'insights' ? 'is-active' : ''; ?>">Insights</a>
        <a href="/admin/discounts.php" class="admin-subnav__link <?php echo $activeNav === 'discounts' ? 'is-active' : ''; ?>">Discounts</a>
        <a href="/admin/email-signups.php" class="admin-subnav__link <?php echo $activeNav === 'signups' ? 'is-active' : ''; ?>">Email Signups</a>
        <a href="/admin/messages.php" class="admin-subnav__link <?php echo $activeNav === 'messages' ? 'is-active' : ''; ?>">Messages<?php if ($unreadMessages): ?> <span style="display:inline-block;min-width:18px;padding:0 5px;border-radius:9px;background:#C25B32;color:#fff;font-size:11px;font-weight:700;text-align:center;line-height:18px"><?php echo $unreadMessages; ?></span><?php endif; ?></a>
    </div>
</nav>
