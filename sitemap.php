<?php
// Served at /sitemap.xml (rewrite in .htaccess). Lists every public page and
// product so search engines find new pieces without waiting to crawl to them.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/product_categories.php';
header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(SITE_URL, '/');
$urls = [
    ['/', null],
    ['/pages/shop/', null],
    ['/pages/about', null],
    ['/pages/contact', null],
    ['/pages/terms', null],
    ['/pages/privacy', null],
];
foreach (PRODUCT_CATEGORIES as $cat) {
    $urls[] = ['/pages/shop/?' . http_build_query(['category' => $cat]), null];
}
try {
    foreach (getDB()->query("SELECT id, updated_at FROM products WHERE active = 1 ORDER BY id") as $p) {
        $urls[] = ['/pages/shop/product?id=' . (int) $p['id'], $p['updated_at'] ? date('Y-m-d', strtotime($p['updated_at'])) : null];
    }
} catch (\Throwable $e) {
    error_log('sitemap: ' . $e->getMessage()); // still serve the static pages
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$path, $lastmod]) {
    echo '  <url><loc>' . htmlspecialchars($base . $path, ENT_XML1) . '</loc>'
        . ($lastmod ? "<lastmod>$lastmod</lastmod>" : '') . "</url>\n";
}
echo "</urlset>\n";
