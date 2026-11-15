<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
if (session_status() === PHP_SESSION_NONE) {
    // SameSite=Lax so the cart survives the Stripe checkout round-trip
    // (Strict would block the cookie on Stripe's return redirect).
    session_set_cookie_params([
        'httponly'  => true,
        'samesite'  => 'Lax',
        // Only over HTTPS in production; plain-HTTP localhost would otherwise
        // never receive the cookie and the cart would silently reset.
        'secure'    => is_https(),
    ]);
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // <title>, description, canonical, link-preview tags, structured data.
    // Pages set these variables before including this file; see includes/seo.php.
    require_once __DIR__ . '/seo.php';
    seo_head([
        'pageTitle'       => $pageTitle ?? null,
        'fullTitle'       => $fullTitle ?? null,
        'pageDescription' => $pageDescription ?? null,
        'pageImage'       => $pageImage ?? null,
        'pageType'        => $pageType ?? null,
        'canonicalPath'   => $canonicalPath ?? null,
        'noindex'         => $noindex ?? false,
        'structuredData'  => $structuredData ?? null,
    ]);
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400..800&family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon-96x96.png" type="image/png" sizes="96x96">
    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/images/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#C25B32">
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">
</head>
<body>
    <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
    <div style="position:fixed;left:12px;bottom:12px;z-index:9999;padding:6px 12px;border:2px solid #2D2D2D;border-radius:999px;background:#D4C14B;color:#2D2D2D;font:700 13px/1.2 Urbanist,system-ui,sans-serif;box-shadow:3px 3px 0 #2D2D2D;pointer-events:none">Test site: sample data</div>
<?php endif; ?>
    <header>
        <div class="container header_container">
            <a href="<?php echo SITE_URL; ?>" class="logo"><img src="<?php echo SITE_URL; ?>/assets/images/logoni.svg" alt="Caneeli Designs"></a>

            <button class="menu-toggle" aria-label="Menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="nav">
                <a href="<?php echo SITE_URL; ?>" class="nav-logo"><img src="<?php echo SITE_URL; ?>/assets/images/logoni.svg" alt="Caneeli Designs"></a>
                <ul>
                    <li><a class="nav_title <?php echo isActivePage('/pages/shop'); ?>" href="<?php echo SITE_URL; ?>/pages/shop/">Shop</a></li>
                    <li><a class="nav_title <?php echo isActivePage('/pages/about'); ?>" href="<?php echo SITE_URL; ?>/pages/about.php">About</a></li>
                    <li><a class="nav_title <?php echo isActivePage('/pages/contact'); ?>" href="<?php echo SITE_URL; ?>/pages/contact.php">Contact</a></li>
                    <?php $cartCount = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?>
                    <li><a class="nav_title nav_cart <?php echo isActivePage('/cart'); ?>" href="<?php echo SITE_URL; ?>/cart.php"<?php echo $cartCount ? ' aria-label="Cart, ' . $cartCount . ' item' . ($cartCount === 1 ? '' : 's') . '"' : ''; ?>>Cart<?php if ($cartCount): ?> <span class="nav_cart__count" aria-hidden="true"><?php echo $cartCount; ?></span><?php endif; ?></a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main>
