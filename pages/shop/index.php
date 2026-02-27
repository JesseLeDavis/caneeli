<?php
$pageTitle = "Shop";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/db.php';

$categories = ['Chairs', 'Tables', 'Shelves', 'Wall Decor', 'Lighting', 'Other'];
$pdo        = getDB();

// Get actual price range from DB for slider bounds
$bounds     = $pdo->query("SELECT MIN(price) as min_p, MAX(price) as max_p FROM products WHERE active = 1")->fetch();
$bound_min  = (int) floor($bounds['min_p'] ?? 0);
$bound_max  = (int) ceil($bounds['max_p'] ?? 1000);

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
if ($category) { $where .= " AND category = ?"; $params[] = $category; }

// Total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products $where");
$count_stmt->execute($params);
$total       = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// Fetch products
$stmt = $pdo->prepare("SELECT * FROM products $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
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
?>

<section class="hero">
    <div class="container">

        <!-- Category filters -->
        <div class="filter-bar">
            <a href="<?php echo shop_url(['category' => '', 'page' => 1]); ?>"
               class="btn <?php echo !$category ? 'red-button' : 'filter-btn'; ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?php echo shop_url(['category' => $cat, 'page' => 1]); ?>"
                   class="btn <?php echo $category === $cat ? 'red-button' : 'filter-btn'; ?>">
                    <?php echo $cat; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Price slider -->
        <form class="price-slider" id="price-form" method="GET">
            <?php if ($category): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <?php endif; ?>
            <input type="hidden" name="page" value="1">

            <div class="price-slider__header">
                <span class="price-slider__title">Price Range</span>
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

        <!-- Product grid -->
        <?php if (empty($products)): ?>
            <p class="shop-empty">No products found in this range.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                <a href="/pages/shop/product.php?id=<?php echo $product['id']; ?>" class="product-card">
                    <?php if ($product['image_path']): ?>
                        <img class="product-card__bg" src="<?php echo htmlspecialchars($product['image_path']); ?>"
                             alt="<?php echo sanitize($product['name']); ?>">
                    <?php else: ?>
                        <div class="product-card__bg product-card__placeholder"></div>
                    <?php endif; ?>
                    <div class="product-card__overlay">
                        <h3 class="product-card__name"><?php echo sanitize($product['name']); ?></h3>
                        <p class="product-card__description"><?php echo sanitize($product['description']); ?></p>
                        <p class="product-card__price"><?php echo formatPrice($product['price']); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo shop_url(['page' => $page - 1]); ?>" class="pagination__arrow">←</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="pagination__current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo shop_url(['page' => $i]); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo shop_url(['page' => $page + 1]); ?>" class="pagination__arrow">→</a>
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
