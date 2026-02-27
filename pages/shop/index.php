<?php
$pageTitle = "Shop";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/db.php';

$categories = ['Chairs', 'Tables', 'Shelves', 'Wall Decor', 'Lighting', 'Other'];

$category    = $_GET['category'] ?? '';
$page        = max(1, intval($_GET['page'] ?? 1));
$per_page    = 8;
$offset      = ($page - 1) * $per_page;

if (!in_array($category, $categories)) $category = '';

$pdo        = getDB();
$where      = "WHERE active = 1" . ($category ? " AND category = ?" : "");
$params     = $category ? [$category] : [];

// Total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products $where");
$count_stmt->execute($params);
$total      = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);

// Fetch page of products
$stmt = $pdo->prepare("SELECT * FROM products $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

function page_url($p, $category) {
    $params = ['page' => $p];
    if ($category) $params['category'] = $category;
    return '?' . http_build_query($params);
}
?>

<section class="hero">
    <div class="container">

        <!-- Category filters -->
        <div class="filter-bar">
            <a href="?page=1" class="btn <?php echo !$category ? 'red-button' : 'filter-btn'; ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?php echo urlencode($cat); ?>&page=1"
                   class="btn <?php echo $category === $cat ? 'red-button' : 'filter-btn'; ?>">
                    <?php echo $cat; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Product grid -->
        <?php if (empty($products)): ?>
            <p class="shop-empty">No products found. Check back soon!</p>
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
                    <a href="<?php echo page_url($page - 1, $category); ?>" class="pagination__arrow">←</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="pagination__current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo page_url($i, $category); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo page_url($page + 1, $category); ?>" class="pagination__arrow">→</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
