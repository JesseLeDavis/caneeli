<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400..800&family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body>
    <header>
        <div class="container header_container">
            <a href="<?php echo SITE_URL; ?>" class="logo"><img src="<?php echo SITE_URL; ?>/assets/images/logoni.svg" alt="Caneeli Designs"></a>

            <button class="menu-toggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="nav">
                <a href="<?php echo SITE_URL; ?>" class="nav-logo"><img src="<?php echo SITE_URL; ?>/assets/images/logoni.svg" alt="Caneeli Designs"></a>
                <ul>
                    <li><a class="nav_title <?php echo isActivePage('/pages/shop'); ?>" href="<?php echo SITE_URL; ?>/pages/shop/">Shop</a></li>
                    <li><a class="nav_title <?php echo isActivePage('/pages/about'); ?>" href="<?php echo SITE_URL; ?>/pages/about.php">About Me</a></li>
                    <li><a class="nav_title <?php echo isActivePage('/pages/contact'); ?>" href="<?php echo SITE_URL; ?>/pages/contact.php">Contact me</a></li>
                    <li><a class="nav_title <?php echo isActivePage('/cart'); ?>" href="<?php echo SITE_URL; ?>/cart.php">Cart <?php if (!empty($_SESSION['cart'])): ?>(<?php echo array_sum($_SESSION['cart']); ?>)<?php endif; ?></a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main>
