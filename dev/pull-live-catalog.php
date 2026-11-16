<?php
/**
 * Copies the live shop's catalog into the test environment, so designs are
 * judged against Annie's real pieces instead of drawn stand-ins.
 *
 *   php dev/pull-live-catalog.php            then   dev/serve-test.sh --reset
 *
 * Reads the public pages only (shop listing, product pages, home page) and
 * writes:
 *   dev/live-catalog.json           names, prices, stock, categories, text
 *   assets/uploads/live/*.jpg|png   product photos
 * Both are gitignored. setup-test.php uses the catalog when it exists and
 * falls back to the drawn sample products when it doesn't.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$root = dirname(__DIR__);
$env  = parse_ini_file("$root/.env");
// The live site's address: the production SITE_URL, not the local one.
$live = rtrim($argv[1] ?? 'https://caneelidesigns.com', '/');

function fetch(string $url): string {
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: CaneeliTestSync/1.0\r\n", 'timeout' => 20]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) throw new RuntimeException("Couldn't fetch $url");
    return $body;
}

// 1. Product ids from every shop page
$ids = [];
for ($page = 1; $page <= 20; $page++) {
    $html = fetch("$live/pages/shop/?page=$page");
    preg_match_all('#/pages/shop/product(?:\.php)?\?id=(\d+)#', $html, $m);
    $new = array_diff(array_map('intval', $m[1]), $ids);
    if (!$new) break;
    $ids = array_merge($ids, $new);
}
$ids = array_values(array_unique($ids));
echo 'Found ' . count($ids) . " products\n";

// 2. Featured pieces, in home page order
preg_match_all('#/pages/shop/product(?:\.php)?\?id=(\d+)#', fetch("$live/"), $m);
$featured = array_values(array_unique(array_map('intval', $m[1])));

// 3. Each product page
$imgDir = "$root/assets/uploads/live";
if (!is_dir($imgDir)) mkdir($imgDir, 0775, true);
$catalog = [];
foreach ($ids as $id) {
    $html = fetch("$live/pages/shop/product?id=$id");
    $ld = [];
    if (preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m)) {
        $ld = json_decode($m[1], true) ?: [];
    }
    $desc = preg_match('#product-detail__description">(.*?)</p>#s', $html, $m)
        ? trim(html_entity_decode(preg_replace('#<br\s*/?>#', '', $m[1]), ENT_QUOTES | ENT_HTML5))
        : ($ld['description'] ?? '');
    preg_match_all('#href="/pages/shop/\?category=[^"]*">([^<]+)</a>#', $html, $m);
    $cats = array_values(array_unique(array_map(fn($c) => trim(html_entity_decode($c)), $m[1])));
    preg_match_all('#product-detail__signal[^"]*">([^<]+)<#', $html, $m);
    $signals = array_map(fn($s) => trim(html_entity_decode($s)), $m[1]);
    preg_match_all('#data-src="([^"]+)"#', $html, $m);
    $images = $m[1] ?: (array) ($ld['image'] ?? []);
    $soldOut = str_contains($ld['offers']['availability'] ?? '', 'SoldOut') || str_contains($html, 'class="sold-out"');
    $stock = preg_match('#Only (\d+) left#', $html, $m) ? (int) $m[1] : ($soldOut ? 0 : 5);

    $local = [];
    foreach (array_unique($images) as $n => $src) {
        $path = parse_url($src, PHP_URL_PATH);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;
        $file = "p{$id}-" . ($n + 1) . ".$ext";
        if (!is_file("$imgDir/$file")) {
            file_put_contents("$imgDir/$file", fetch($live . $path));
        }
        $local[] = "/assets/uploads/live/$file";
    }

    $catalog[] = [
        'name' => html_entity_decode($ld['name'] ?? "Product $id", ENT_QUOTES | ENT_HTML5),
        'description' => $desc,
        'price' => (float) ($ld['offers']['price'] ?? 0),
        'stock' => $soldOut ? 0 : $stock,
        'status' => $soldOut ? 'sold_out' : 'active',
        'featured' => in_array($id, $featured, true),
        'categories' => $cats ?: ['Other'],
        'signals' => $signals,
        'images' => $local,
    ];
    echo "  {$catalog[count($catalog) - 1]['name']}: " . count($local) . " photos\n";
}

file_put_contents(__DIR__ . '/live-catalog.json', json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "\nWrote dev/live-catalog.json. Now run: dev/serve-test.sh --reset\n";
