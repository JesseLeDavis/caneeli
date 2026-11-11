<?php
// Everything up to the header include runs before any HTML is sent, so the
// redirects below actually work and the <title> is the product's name.
// (header.php used to be included first: every product page was titled
// "Product | Caneeli" and a bad id gave a broken page instead of the shop.)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
include __DIR__ . '/../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /pages/shop/'); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) { header('Location: /pages/shop/'); exit; }

require_once __DIR__ . '/../../includes/product_categories.php';
$product_cats = product_categories_for($pdo, (int) $product['id']);

$pageTitle = $product['name'];

// Fetch gallery images
$img_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
$img_stmt->execute([$id]);
$gallery = $img_stmt->fetchAll();

// Search/link-preview tags (see includes/seo.php).
require_once __DIR__ . '/../../includes/seo.php';
$is_sold_out     = ($product['status'] ?? 'active') === 'sold_out' || $product['stock_qty'] <= 0;
$canonicalPath   = '/pages/shop/product?id=' . (int) $product['id'];
$pageType        = 'product';
$pageDescription = trim($product['description'] ?? '') !== ''
    ? $product['description']
    : 'Handmade ' . ($product['category'] ?: 'piece') . ' by Caneeli Designs, made by Annie in Oregon.';
$photos = array_values(array_unique(array_filter(array_merge(
    [$product['image_path'] ?? null],
    array_column($gallery, 'image_path')
))));
$pageImage = $photos[0] ?? null;
// Lets Google show price and availability in results.
$structuredData = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => seo_snippet($pageDescription, 500),
    'sku'         => (string) $product['id'],
    'brand'       => ['@type' => 'Brand', 'name' => SEO_BRAND],
    'offers'      => [
        '@type'         => 'Offer',
        'url'           => seo_url($canonicalPath),
        'priceCurrency' => 'USD',
        'price'         => number_format((float) $product['price'], 2, '.', ''),
        'availability'  => $is_sold_out ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
];
if ($photos) {
    $structuredData['image'] = array_map('seo_url', $photos);
}

include __DIR__ . '/../../includes/header.php';

// Record the view for the admin insights report (fire-and-forget). After the
// header include: it reads the session id, which header.php starts.
require_once __DIR__ . '/../../includes/tracking.php';
track_product_view((int) $product['id'], $_SERVER['HTTP_REFERER'] ?? null);
?>

<section class="hero">
    <div class="container">

        <nav class="breadcrumb">
            <a href="/pages/shop/">Shop</a>
            <span>/</span>
            <a href="/pages/shop/?category=<?php echo urlencode($product['category']); ?>"><?php echo sanitize($product['category']); ?></a>
            <span>/</span>
            <span><?php echo sanitize($product['name']); ?></span>
        </nav>

        <div class="product-detail">

            <div class="product-detail__image <?php echo count($gallery) > 1 ? 'product-detail__image--gallery' : ''; ?>">
                <?php if (count($gallery) > 1): ?>
                    <div class="product-gallery">
                        <div class="product-gallery__main">
                            <img id="gallery-main-img"
                                 src="<?php echo htmlspecialchars($product['image_path'] ?? $gallery[0]['image_path']); ?>"
                                 alt="<?php echo sanitize($product['name']); ?>">
                        </div>
                        <div class="product-gallery__thumbs">
                            <?php
                            $featured_src = $product['image_path'] ?? $gallery[0]['image_path'];
                            foreach ($gallery as $i => $img):
                                $is_active = ($img['image_path'] === $featured_src);
                            ?>
                                <button class="product-gallery__thumb <?php echo $is_active ? 'product-gallery__thumb--active' : ''; ?>"
                                        data-src="<?php echo htmlspecialchars($img['image_path']); ?>"
                                        aria-label="View photo <?php echo $i + 1; ?>">
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>"
                                         alt="Product photo <?php echo $i + 1; ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($product['image_path']): ?>
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>"
                         alt="<?php echo sanitize($product['name']); ?>">
                <?php else: ?>
                    <div class="product-detail__placeholder"></div>
                <?php endif; ?>
            </div>

            <div class="product-detail__info">
                <p class="product-detail__category">
                    <?php foreach ($product_cats as $i => $cat): ?>
                        <?php if ($i > 0): ?><span class="product-detail__category-sep">·</span><?php endif; ?>
                        <a href="/pages/shop/?category=<?php echo urlencode($cat); ?>"><?php echo sanitize($cat); ?></a>
                    <?php endforeach; ?>
                </p>
                <h1 class="large_title"><?php echo sanitize($product['name']); ?></h1>
                <p class="product-detail__price"><?php echo formatPrice($product['price']); ?></p>
                <div class="product-detail__divider"></div>
                <p class="product-detail__description"><?php echo nl2br(sanitize($product['description'])); ?></p>

                <?php
                $signals = json_decode($product['craft_signals'] ?? '[]', true) ?? [];
                if (!empty($signals)):
                ?>
                <div class="product-detail__craft-signals">
                    <?php foreach ($signals as $signal):
                        $signal = htmlspecialchars(strip_tags($signal));
                        if (str_contains($signal, 'Ship') || str_contains($signal, 'Order')) {
                            $mod = 'product-detail__signal--shipping';
                        } elseif (str_contains($signal, 'Edition') || str_contains($signal, 'Signed')) {
                            $mod = 'product-detail__signal--premium';
                        } else {
                            $mod = 'product-detail__signal--material';
                        }
                    ?>
                        <span class="product-detail__signal <?php echo $mod; ?>"><?php echo $signal; ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!$is_sold_out): ?>
                    <form method="POST" action="/cart.php">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <div class="product-detail__qty">
                            <label>Quantity</label>
                            <div class="qty-stepper">
                                <button type="button" class="qty-stepper__btn" data-dir="-1">−</button>
                                <input type="number" name="quantity" value="1"
                                       min="1" max="<?php echo $product['stock_qty']; ?>"
                                       class="qty-stepper__input" readonly>
                                <button type="button" class="qty-stepper__btn" data-dir="1">+</button>
                            </div>
                        </div>
                        <?php if ($product['stock_qty'] <= 5): ?>
                            <p class="product-detail__stock">Only <?php echo $product['stock_qty']; ?> left in stock</p>
                        <?php endif; ?>
                        <button type="submit" class="btn red-button product-detail__atc-btn">Add to Cart</button>
                    </form>
                <?php else: ?>
                    <p class="sold-out">Sold Out</p>
                    <a href="/pages/shop/?category=<?php echo urlencode($product['category']); ?>"
                       class="btn filter-btn" style="margin-top:12px">See Similar Pieces</a>
                <?php endif; ?>

                <a href="/pages/shop/" class="product-detail__back">← Back to Shop</a>
            </div>

        </div>
    </div>
</section>

<?php if (count($gallery) > 1): ?>
<script>
(function () {
    const mainImg = document.getElementById('gallery-main-img');
    const thumbs  = document.querySelectorAll('.product-gallery__thumb');
    if (!mainImg || !thumbs.length) return;

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            const newSrc = thumb.dataset.src;
            const currentSrc = mainImg.getAttribute('src');
            if (newSrc === currentSrc) return;

            mainImg.classList.add('is-transitioning');
            setTimeout(function () {
                mainImg.setAttribute('src', newSrc);
                mainImg.onload = function () { mainImg.classList.remove('is-transitioning'); };
                if (mainImg.complete) mainImg.classList.remove('is-transitioning');
            }, 250);

            thumbs.forEach(function (t) { t.classList.remove('product-gallery__thumb--active'); });
            thumb.classList.add('product-gallery__thumb--active');
        });
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
