<?php
/**
 * Shared admin chrome (header + sub-nav).
 * Expects $pageTitle (string) to be set before include.
 * Expects $activeNav (string) — one of: 'dashboard', 'products', 'orders', 'signups', ''.
 */
$activeNav = $activeNav ?? '';
$pageTitle = $pageTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
        <a href="/admin/email-signups.php" class="admin-subnav__link <?php echo $activeNav === 'signups' ? 'is-active' : ''; ?>">Email Signups</a>
    </div>
</nav>
