<?php
/**
 * Search and link-preview tags, printed in <head> by header.php.
 *
 * A page can set any of these before including header.php; all optional:
 *
 *   $pageTitle        "About" → <title>About | Caneeli Designs</title>
 *   $fullTitle        the whole <title>, when the "| brand" suffix doesn't fit (home)
 *   $pageDescription  ~150 characters for search results and link previews
 *   $pageImage        site-relative or absolute image for link previews
 *   $pageType         Open Graph type: 'website' (default) or 'product'
 *   $canonicalPath    the page's one true URL path, query included, when it
 *                     depends on a query string (product?id=…); otherwise
 *                     worked out from the request
 *   $noindex          true for pages that shouldn't be in search (cart, checkout…)
 *   $structuredData   array, printed as JSON-LD (e.g. a Product for Google)
 */

const SEO_BRAND               = 'Caneeli Designs';
const SEO_DEFAULT_DESCRIPTION = 'Playful, functional stained glass and ceramic pieces, handmade by Annie in her Oregon studio. Shop one-of-a-kind pieces or commission your own.';
const SEO_DEFAULT_IMAGE       = '/assets/images/og-default.jpg'; // 1200×630

/** Absolute URL for a site path ("/x") or an already-absolute URL. */
function seo_url(string $path): string {
    return preg_match('~^https?://~i', $path) ? $path : rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * The page's canonical path. .htaccess 301s "/foo.php" to "/foo", so the
 * extensionless form is the real address; the query string is dropped
 * unless the page passes one in through $canonicalPath.
 */
function seo_canonical_path(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = preg_replace('~/index\.php$~', '/', $path);
    return preg_replace('~\.php$~', '', $path);
}

/** Plain-text snippet: tags stripped, whitespace collapsed, cut at a word. */
function seo_snippet(string $text, int $max = 155): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    if (mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:—-') . '…';
}

function seo_head(array $page): void {
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    // $pageTitle has historically been echoed raw, and some pages pass it
    // already escaped; decode first so nothing is double-escaped.
    $short = isset($page['pageTitle']) ? html_entity_decode($page['pageTitle'], ENT_QUOTES, 'UTF-8') : null;
    $title = $page['fullTitle'] ?? ($short ? $short . ' | ' . SEO_BRAND : SEO_BRAND);
    $desc  = seo_snippet($page['pageDescription'] ?? SEO_DEFAULT_DESCRIPTION);
    $url   = seo_url($page['canonicalPath'] ?? seo_canonical_path());
    $image = seo_url($page['pageImage'] ?? SEO_DEFAULT_IMAGE);
    $type  = $page['pageType'] ?? 'website';

    echo '<title>' . $e($title) . "</title>\n";
    echo '    <meta name="description" content="' . $e($desc) . "\">\n";
    if (!empty($page['noindex'])) {
        echo "    <meta name=\"robots\" content=\"noindex, follow\">\n";
    } else {
        echo '    <link rel="canonical" href="' . $e($url) . "\">\n";
    }
    foreach ([
        'og:site_name'   => SEO_BRAND,
        'og:title'       => $short ?? $title,
        'og:description' => $desc,
        'og:type'        => $type,
        'og:url'         => $url,
        'og:image'       => $image,
    ] as $prop => $val) {
        echo '    <meta property="' . $prop . '" content="' . $e($val) . "\">\n";
    }
    echo "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";

    if (!empty($page['structuredData'])) {
        // JSON_HEX_TAG keeps a "</script>" inside a product description inert.
        echo '    <script type="application/ld+json">'
            . json_encode($page['structuredData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
            . "</script>\n";
    }
}
