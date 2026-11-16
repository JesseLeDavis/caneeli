<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/product_categories.php';
require_once __DIR__ . '/../../includes/product_card.php';

$categories = PRODUCT_CATEGORIES;
$pdo        = getDB();

// Actual price range from the DB, used to clamp min_price/max_price.
$bounds     = $pdo->query("SELECT MIN(price) as min_p, MAX(price) as max_p FROM products WHERE active = 1")->fetch();
// Rounded outward to whole $5, so the priciest piece can't fall just outside
// the range (e.g. $285 vs $284).
$bound_min  = (int) (floor(($bounds['min_p'] ?? 0) / 5) * 5);
$bound_max  = (int) (ceil(($bounds['max_p'] ?? 1000) / 5) * 5);

// Price chips. Each sets the same min_price/max_price GET params the query
// below already understands (null = no limit on that side).
$price_presets = [
    ''        => ['label' => 'Any price',   'min' => null, 'max' => null],
    'under100'=> ['label' => 'Under $100',  'min' => null, 'max' => 99],
    '100-300' => ['label' => '$100–$300',   'min' => 100,  'max' => 300],
    '300plus' => ['label' => '$300+',       'min' => 300,  'max' => null],
];
$raw_min = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? intval($_GET['min_price']) : null;
$raw_max = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? intval($_GET['max_price']) : null;
$active_price = null; // null = a custom range from an old slider link
foreach ($price_presets as $key => $preset) {
    if ($preset['min'] === $raw_min && $preset['max'] === $raw_max) { $active_price = $key; break; }
}

// Filter params
$category  = $_GET['category'] ?? '';
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : $bound_min;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : $bound_max;
$page      = max(1, intval($_GET['page'] ?? 1));
$per_page  = 12; // fills full rows at 2, 3 and 4 columns

if (!in_array($category, $categories)) $category = '';
// Clamp only on the outside: "Under $100" with nothing under $100 should show
// the empty state, not get pulled up to the cheapest piece.
$min_price = max($bound_min, $min_price);
$max_price = min($max_price, $bound_max);

// Build query
$where  = "WHERE active = 1 AND price >= ? AND price <= ?";
$params = [$min_price, $max_price];
// Match against the join table so a piece filed under several categories shows
// up under each of them.
if ($category) { $where .= " AND " . product_category_exists_sql('products'); $params[] = $category; }

// Total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products $where");
$count_stmt->execute($params);
$total       = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// Fetch products
// Sold-out pieces go after everything still for sale, using the same test as
// the "Sold Out" card badge below (status or no stock). Within each group,
// sort_order is what the admin drags; created_at breaks ties, which keeps
// brand-new products (sort_order 0) surfacing newest-first until positioned.
$stmt = $pdo->prepare("SELECT * FROM products $where
    ORDER BY (status = 'sold_out' OR stock_qty <= 0) ASC, sort_order ASC, created_at DESC
    LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$products = $stmt->fetchAll();

// URL builder — keeps all active filters
function shop_url($overrides = []) {
    global $category, $min_price, $max_price, $bound_min, $bound_max;
    $p = ['page' => 1];
    if ($category)                $p['category']  = $category;
    if ($min_price > $bound_min)  $p['min_price'] = $min_price;
    if ($max_price < $bound_max)  $p['max_price'] = $max_price;
    return '?' . http_build_query(array_merge($p, $overrides));
}

// Each category is its own indexable page ("Ceramics | Shop"); price
// filters aren't, so they're left out of the canonical URL.
$pageTitle       = $category ? "$category | Shop" : 'Shop';
$pageDescription = $category
    ? 'Handmade ' . ($category === 'Other' ? 'pieces' : strtolower($category)) . ' by Caneeli Designs: stained glass lamps, ceramic lighting and the salmon series, made by Annie in Oregon.'
    : 'Shop stained glass lamps, ceramic lighting and the salmon series by Caneeli Designs. Made by hand by Annie in Oregon, no two pieces alike.';
// Price chip links: keep the category, reset to page 1.
function shop_price_url(array $preset): string {
    global $category;
    $p = ['page' => 1];
    if ($category)                $p['category']  = $category;
    if ($preset['min'] !== null)  $p['min_price'] = $preset['min'];
    if ($preset['max'] !== null)  $p['max_price'] = $preset['max'];
    return '?' . http_build_query($p);
}
$canon = array_filter(['category' => $category ?: null, 'page' => $page > 1 ? $page : null]);
$canonicalPath = '/pages/shop/' . ($canon ? '?' . http_build_query($canon) : '');
include __DIR__ . '/../../includes/header.php';
?>

<section class="hero shop-page">
    <div class="container">

        <!-- Page header -->
        <div class="shop-header">
            <h1 class="large_title"><?php echo $category ? htmlspecialchars($category) : 'Shop'; ?></h1>
            <p class="shop-tagline">Made by hand in small batches, so no two pieces are exactly alike.</p>
        </div>

        <!-- Filters -->
        <div class="shop-filters">

        <!-- Category filters -->
        <nav class="filter-bar" aria-label="Categories">
            <a href="<?php echo shop_url(['category' => '', 'page' => 1]); ?>"
               class="filter-chip <?php echo !$category ? 'is-active' : ''; ?>"<?php echo !$category ? ' aria-current="page"' : ''; ?>>All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?php echo shop_url(['category' => $cat, 'page' => 1]); ?>"
                   class="filter-chip <?php echo $category === $cat ? 'is-active' : ''; ?>"<?php echo $category === $cat ? ' aria-current="page"' : ''; ?>><?php echo $cat; ?></a>
            <?php endforeach; ?>
            <span class="shop-count"><?php echo $total; ?> piece<?php echo $total !== 1 ? 's' : ''; ?></span>
        </nav>

        <!-- Price chips -->
        <nav class="filter-bar filter-bar--price" aria-label="Price">
            <span class="filter-bar__label" aria-hidden="true">Price</span>
            <?php foreach ($price_presets as $key => $preset): $on = $active_price === $key; ?>
                <a href="<?php echo shop_price_url($preset); ?>"
                   class="filter-chip filter-chip--small <?php echo $on ? 'is-active' : ''; ?>"<?php echo $on ? ' aria-current="true"' : ''; ?>><?php echo $preset['label']; ?></a>
            <?php endforeach; ?>
        </nav>

        </div><!-- /.shop-filters -->

        <!-- Product grid -->
        <?php if (empty($products)): ?>
            <div class="shop-empty">
                <p class="shop-empty__heading">No pieces match these filters</p>
                <p class="shop-empty__text">Try another price or category to see more pieces.</p>
                <a href="/pages/shop/" class="btn">Show all pieces</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product) render_product_card($product, 'h2'); ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo shop_url(['page' => $page - 1]); ?>" class="pagination__arrow" aria-label="Previous page">←</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="pagination__current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo shop_url(['page' => $i]); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo shop_url(['page' => $page + 1]); ?>" class="pagination__arrow" aria-label="Next page">→</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
