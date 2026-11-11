<?php
// Served at /robots.txt (rewrite in .htaccess). PHP rather than a static
// file so the sitemap line uses SITE_URL instead of a hard-coded domain.
require_once __DIR__ . '/config/config.php';
header('Content-Type: text/plain; charset=utf-8');

// Only block what crawlers can't usefully reach. Cart, success, quote etc.
// stay crawlable on purpose: they carry <meta name="robots" content="noindex">,
// which Google can only see if it's allowed to fetch the page.
$base = rtrim(SITE_URL, '/');
echo "User-agent: *\n";
foreach (['/admin/', '/checkout', '/quote-checkout', '/webhook', '/coming-soon-signup'] as $path) {
    echo "Disallow: $path\n";
}
echo "\nSitemap: $base/sitemap.xml\n";
