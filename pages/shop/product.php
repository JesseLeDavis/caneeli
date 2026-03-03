<?php
$pageTitle = "Product";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /pages/shop/'); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) { header('Location: /pages/shop/'); exit; }

$pageTitle = sanitize($product['name']);

// Fetch gallery images
$img_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
$img_stmt->execute([$id]);
$gallery = $img_stmt->fetchAll();
?>

<section class="hero">
    <div class="container">
        <div class="product-detail">

            <div class="product-detail__image <?php echo count($gallery) > 1 ? 'product-detail__image--gallery' : ''; ?>">
                <?php if (count($gallery) > 1): ?>
                    <!-- Multi-image gallery -->
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
                    <!-- Single image (original behaviour) -->
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>"
                         alt="<?php echo sanitize($product['name']); ?>">
                <?php else: ?>
                    <div class="product-detail__placeholder"></div>
                <?php endif; ?>
            </div>

            <div class="product-detail__info">
                <p class="product-detail__category"><?php echo sanitize($product['category']); ?></p>
                <h1 class="large_title"><?php echo sanitize($product['name']); ?></h1>
                <p class="product-detail__price"><?php echo formatPrice($product['price']); ?></p>
                <p class="product-detail__description"><?php echo nl2br(sanitize($product['description'])); ?></p>

                <?php if ($product['stock_qty'] > 0): ?>
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
                        <button type="submit" class="btn blue-button">Add to Cart</button>
                    </form>
                <?php else: ?>
                    <p class="sold-out">Sold Out</p>
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
            // Normalise for comparison — strip origin prefix from img.src
            const currentSrc = mainImg.getAttribute('src');
            if (newSrc === currentSrc) return;

            // Crossfade: fade out → swap src → fade in
            mainImg.classList.add('is-transitioning');
            setTimeout(function () {
                mainImg.setAttribute('src', newSrc);
                mainImg.onload = function () {
                    mainImg.classList.remove('is-transitioning');
                };
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
