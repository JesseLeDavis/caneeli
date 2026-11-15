<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/product_categories.php';

$categories = PRODUCT_CATEGORIES;
$pdo        = getDB();

// Get actual price range from DB for slider bounds
$bounds     = $pdo->query("SELECT MIN(price) as min_p, MAX(price) as max_p FROM products WHERE active = 1")->fetch();
// Snapped to the slider's $5 step, so its ends land exactly on the bounds and
// the priciest piece can't fall just outside the range (e.g. $285 vs $284).
$bound_min  = (int) (floor(($bounds['min_p'] ?? 0) / 5) * 5);
$bound_max  = (int) (ceil(($bounds['max_p'] ?? 1000) / 5) * 5);

// Filter params
$category  = $_GET['category'] ?? '';
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : $bound_min;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : $bound_max;
$page      = max(1, intval($_GET['page'] ?? 1));
$per_page  = 8;

if (!in_array($category, $categories)) $category = '';
$min_price = max($bound_min, min($min_price, $bound_max));
$max_price = max($bound_min, min($max_price, $bound_max));
if ($min_price > $max_price) $min_price = $max_price;

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

// Each category is its own indexable page ("Ceramics | Shop"); price-slider
// filters aren't, so they're left out of the canonical URL.
$pageTitle       = $category ? "$category | Shop" : 'Shop';
$pageDescription = $category
    ? 'Handmade ' . ($category === 'Other' ? 'pieces' : strtolower($category)) . ' by Caneeli Designs. One-of-a-kind stained glass and ceramic work, made by Annie in Oregon.'
    : 'Shop handmade stained glass, ceramics, lighting and wall decor by Caneeli Designs. Small batches, made by hand in Oregon — no two pieces alike.';
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

        <!-- Price slider -->
        <form class="price-slider" id="price-form" method="GET">
            <?php if ($category): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <?php endif; ?>
            <input type="hidden" name="page" value="1">

            <div class="price-slider__header">
                <span class="price-slider__title">Price</span>
                <div class="price-slider__bubbles">
                    <span class="price-bubble" id="min-bubble">$<?php echo $min_price; ?></span>
                    <span class="price-slider__dash">—</span>
                    <span class="price-bubble" id="max-bubble">$<?php echo $max_price; ?></span>
                </div>
            </div>

            <div class="price-slider__wrap">
                <div class="price-slider__track">
                    <div class="price-slider__fill" id="slider-fill"></div>
                </div>
                <input type="range" name="min_price" id="min-price"
                       min="<?php echo $bound_min; ?>" max="<?php echo $bound_max; ?>"
                       value="<?php echo $min_price; ?>" step="5">
                <input type="range" name="max_price" id="max-price"
                       min="<?php echo $bound_min; ?>" max="<?php echo $bound_max; ?>"
                       value="<?php echo $max_price; ?>" step="5">
            </div>
        </form>

        </div><!-- /.shop-filters -->

        <!-- Product grid -->
        <?php if (empty($products)): ?>
            <div class="shop-empty">
                <p class="shop-empty__heading">No pieces match these filters</p>
                <p class="shop-empty__text">Widen the price range or pick another category to see more pieces.</p>
                <a href="/pages/shop/" class="btn">Show all pieces</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                <?php $card_sold_out = ($product['status'] ?? 'active') === 'sold_out' || (int) $product['stock_qty'] <= 0; ?>
                <a href="/pages/shop/product.php?id=<?php echo $product['id']; ?>" class="product-card <?php echo $card_sold_out ? 'product-card--sold-out' : ''; ?>">
                    <div class="product-card__media">
                        <?php if ($product['image_path']): ?>
                            <img class="product-card__bg" src="<?php echo htmlspecialchars($product['image_path']); ?>"
                                 alt="" loading="lazy">
                        <?php else: ?>
                            <span class="product-card__bg product-card__placeholder"></span>
                        <?php endif; ?>
                        <?php if ($card_sold_out): ?>
                            <span class="product-card__sold-out">Sold out</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-card__caption">
                        <h3 class="product-card__name"><?php echo sanitize($product['name']); ?></h3>
                        <span class="product-card__price"><?php echo formatPrice($product['price']); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
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

<script>
(function () {
    const minSlider  = document.getElementById('min-price');
    const maxSlider  = document.getElementById('max-price');
    const minBubble  = document.getElementById('min-bubble');
    const maxBubble  = document.getElementById('max-bubble');
    const fill       = document.getElementById('slider-fill');
    const form       = document.getElementById('price-form');

    if (!minSlider || !maxSlider) return;

    function update() {
        const min    = parseInt(minSlider.value);
        const max    = parseInt(maxSlider.value);
        const lo     = parseInt(minSlider.min);
        const hi     = parseInt(minSlider.max);
        const range  = hi - lo;

        minBubble.textContent = '$' + min;
        maxBubble.textContent = '$' + max;

        const leftPct  = ((min - lo) / range) * 100;
        const rightPct = ((hi - max) / range) * 100;
        fill.style.left  = leftPct + '%';
        fill.style.right = rightPct + '%';
    }

    minSlider.addEventListener('input', function () {
        if (parseInt(minSlider.value) > parseInt(maxSlider.value) - 5) {
            minSlider.value = parseInt(maxSlider.value) - 5;
        }
        update();
    });

    maxSlider.addEventListener('input', function () {
        if (parseInt(maxSlider.value) < parseInt(minSlider.value) + 5) {
            maxSlider.value = parseInt(minSlider.value) + 5;
        }
        update();
    });

    minSlider.addEventListener('change', function () { form.submit(); });
    maxSlider.addEventListener('change', function () { form.submit(); });

    update();
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
