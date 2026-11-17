<?php
/**
 * Shared admin chrome: sidebar (desktop), top bar + drawer (tablet/phone) and
 * a bottom tab bar (phone).
 * Expects $pageTitle (string) to be set before include.
 * Expects $activeNav (string) — one of: 'dashboard', 'products', 'orders', 'custom', 'preorders', 'customers', 'insights', 'discounts', 'signups', 'messages', ''.
 *
 * Pages close with just </body></html>, so nothing here opens a wrapper that
 * needs closing: the sidebar and tab bar are fixed, and body padding makes
 * room for them.
 */
$activeNav = $activeNav ?? '';
// Loop variables below are prefixed ($nav*) because this file runs in the
// including page's scope: a plain $items or $label would overwrite the page's.
$pageTitle = $pageTitle ?? 'Admin';

// Unread contact-message count for the nav badge. Guarded so a missing table
// (migration not yet run) never breaks every admin page.
require_once __DIR__ . '/ui.php';
$unreadMessages = 0;
if (function_exists('getDB')) {
    try {
        $unreadMessages = (int) getDB()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0 AND is_spam = 0")->fetchColumn();
    } catch (\Throwable $e) {
        // Before migrate_contact_spam.sql there's no is_spam column.
        try {
            $unreadMessages = (int) getDB()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
        } catch (\Throwable $e) {
            $unreadMessages = 0;
        }
    }
}

// Stroke icons (24×24, drawn with currentColor) for the nav.
$adminIcons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'orders'    => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'products'  => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
    'custom'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    'preorders' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'customers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'insights'  => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
    'discounts' => '<path d="M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.42l8.7 8.7a2.43 2.43 0 0 0 3.42 0l6.58-6.58a2.43 2.43 0 0 0 0-3.42Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    'signups'   => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
    'messages'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
    'store'     => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/>',
    'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    'menu'      => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
    'close'     => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
];
function admin_icon(string $name): string {
    global $adminIcons;
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($adminIcons[$name] ?? '') . '</svg>';
}

$adminNavGroups = [
    'Store' => [
        'dashboard' => ['Dashboard', '/admin/dashboard.php'],
        'orders'    => ['Orders', '/admin/orders.php'],
        'custom'    => ['Custom orders', '/admin/custom-orders.php'],
        'preorders' => ['Pre-orders', '/admin/preorders.php'],
        'products'  => ['Products', '/admin/products.php'],
        'customers' => ['Customers', '/admin/customers.php'],
    ],
    'Marketing' => [
        'insights'  => ['Insights', '/admin/insights.php'],
        'discounts' => ['Discounts', '/admin/discounts.php'],
        'signups'   => ['Email signups', '/admin/email-signups.php'],
        'messages'  => ['Messages', '/admin/messages.php'],
    ],
];
// Phone tab bar: the four screens used day to day, plus the full menu.
$adminTabs = ['dashboard' => 'Home', 'orders' => 'Orders', 'products' => 'Products', 'messages' => 'Messages'];
$adminTabHrefs = ['dashboard' => '/admin/dashboard.php', 'orders' => '/admin/orders.php', 'products' => '/admin/products.php', 'messages' => '/admin/messages.php'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2D2D2D">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Caneeli Admin</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    <link rel="stylesheet" href="<?php echo function_exists('asset') ? asset('admin/admin.css') : '/admin/admin.css'; ?>">
</head>
<body class="admin-app">
<?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
    <div style="position:fixed;left:12px;bottom:84px;left:auto;right:12px;z-index:9999;padding:6px 12px;border:2px solid #2D2D2D;border-radius:999px;background:#D4C14B;color:#2D2D2D;font:700 13px/1.2 Urbanist,system-ui,sans-serif;box-shadow:3px 3px 0 #2D2D2D;pointer-events:none">Test site: sample data</div>
<?php endif; ?>

<a class="skip-link" href="#admin-main">Skip to content</a>

<!-- Top bar: tablet and phone only -->
<header class="admin-topbar">
    <button type="button" class="admin-topbar__menu" data-nav-open aria-label="Open menu" aria-controls="admin-sidebar" aria-expanded="false">
        <?php echo admin_icon('menu'); ?>
    </button>
    <a href="/admin/dashboard.php" class="admin-topbar__brand">
        <img src="/assets/images/logowi.svg" alt="Caneeli Designs">
    </a>
    <span class="admin-topbar__title"><?php echo htmlspecialchars($pageTitle); ?></span>
</header>

<div class="admin-scrim" data-nav-close hidden></div>

<aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
    <div class="admin-sidebar__head">
        <a href="/admin/dashboard.php" class="admin-sidebar__brand">
            <img src="/assets/images/logowi.svg" alt="Caneeli Designs">
        </a>
        <button type="button" class="admin-sidebar__close" data-nav-close aria-label="Close menu">
            <?php echo admin_icon('close'); ?>
        </button>
    </div>

    <nav class="admin-nav">
        <?php foreach ($adminNavGroups as $navGroup => $navLinks): ?>
            <div class="admin-nav__group">
                <div class="admin-nav__heading"><?php echo $navGroup; ?></div>
                <?php foreach ($navLinks as $navKey => [$navLabel, $navHref]): ?>
                    <a href="<?php echo $navHref; ?>" class="admin-nav__link <?php echo $activeNav === $navKey ? 'is-active' : ''; ?>"<?php echo $activeNav === $navKey ? ' aria-current="page"' : ''; ?>>
                        <?php echo admin_icon($navKey); ?>
                        <span><?php echo $navLabel; ?></span>
                        <?php if ($navKey === 'messages' && $unreadMessages): ?>
                            <span class="admin-nav__badge" aria-label="<?php echo $unreadMessages; ?> unread"><?php echo $unreadMessages; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar__foot">
        <a href="/" class="admin-nav__link" target="_blank" rel="noopener">
            <?php echo admin_icon('store'); ?><span>View store</span>
        </a>
        <a href="/admin/logout.php" class="admin-nav__link">
            <?php echo admin_icon('logout'); ?><span>Log out</span>
        </a>
    </div>
</aside>

<!-- Bottom tab bar: phone only -->
<nav class="admin-tabbar" aria-label="Quick navigation">
    <?php foreach ($adminTabs as $navKey => $navLabel): ?>
        <a href="<?php echo $adminTabHrefs[$navKey]; ?>" class="admin-tabbar__item <?php echo $activeNav === $navKey ? 'is-active' : ''; ?>"<?php echo $activeNav === $navKey ? ' aria-current="page"' : ''; ?>>
            <span class="admin-tabbar__icon">
                <?php echo admin_icon($navKey); ?>
                <?php if ($navKey === 'messages' && $unreadMessages): ?><span class="admin-tabbar__dot"><?php echo $unreadMessages; ?></span><?php endif; ?>
            </span>
            <span><?php echo $navLabel; ?></span>
        </a>
    <?php endforeach; ?>
    <button type="button" class="admin-tabbar__item <?php echo !isset($adminTabs[$activeNav]) && $activeNav !== '' ? 'is-active' : ''; ?>" data-nav-open aria-controls="admin-sidebar" aria-expanded="false">
        <span class="admin-tabbar__icon"><?php echo admin_icon('menu'); ?></span>
        <span>More</span>
    </button>
</nav>

<script>
(function () {
    var body = document.body;
    var scrim = document.querySelector('.admin-scrim');
    var openers = document.querySelectorAll('[data-nav-open]');
    function setOpen(open) {
        body.classList.toggle('nav-open', open);
        scrim.hidden = !open;
        openers.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
    }
    openers.forEach(function (b) { b.addEventListener('click', function () { setOpen(true); }); });
    document.querySelectorAll('[data-nav-close]').forEach(function (b) {
        b.addEventListener('click', function () { setOpen(false); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();
</script>

<span id="admin-main" tabindex="-1"></span>
