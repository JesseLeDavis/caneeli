<?php
if (time() < strtotime('2026-05-01 18:00:00 America/Los_Angeles')) {
    header('Location: /coming-soon.php');
    exit;
}
$pageTitle = "Home";
include 'includes/header.php';
include 'includes/db.php';

$stmt = getDB()->query("SELECT * FROM products WHERE featured = 1 AND active = 1 ORDER BY updated_at DESC LIMIT 10");
$featured = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero home-hero">
    <div class="container">
        <h1 class="large_title">Make a Space Thats Actually Yours</h1>
        <p>Handmade pieces that turn "fine, I guess" into "wait, I love it here"</p>
        <div class="button-container">
            <a href="/pages/shop/"><div class="blue-button btn">SHOP NOW</div></a>
            <a href="/pages/about.php"><div class="red-button btn">ABOUT</div></a>
            <a href="/pages/contact.php"><div class="yellow-button btn">COMMISSION A PIECE</div></a>
        </div>
    </div>
</section>

<?php if (!empty($featured)): ?>
<!-- Hot Off the Shelf -->
<section class="hot-shelf">
    <div class="container">
        <div class="hot-shelf__header">
            <p class="hot-shelf__eyebrow">Featured Pieces</p>
            <h2 class="hot-shelf__title">Hot Off the Shelf</h2>
            <div class="hot-shelf__rule"></div>
        </div>

        <div class="spotlight">
            <div class="spotlight__stage">
                <?php foreach ($featured as $p): ?>
                <a href="/pages/shop/product.php?id=<?php echo $p['id']; ?>" class="product-card">
                    <?php if ($p['image_path']): ?>
                        <img class="product-card__bg"
                             src="<?php echo htmlspecialchars($p['image_path']); ?>"
                             alt="<?php echo htmlspecialchars($p['name']); ?>">
                    <?php else: ?>
                        <div class="product-card__placeholder"></div>
                    <?php endif; ?>
                    <div class="product-card__overlay">
                        <span class="product-card__name"><?php echo htmlspecialchars($p['name']); ?></span>
                        <span class="product-card__description"><?php echo htmlspecialchars($p['description']); ?></span>
                        <span class="product-card__price"><?php echo formatPrice($p['price']); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="spotlight__controls">
                <button class="spotlight__arrow spotlight__arrow--prev" aria-label="Previous">&#8249;</button>
                <div class="spotlight__dots">
                    <?php foreach ($featured as $i => $p): ?>
                    <button class="spotlight__dot" aria-label="Slide <?php echo $i + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <button class="spotlight__arrow spotlight__arrow--next" aria-label="Next">&#8250;</button>
            </div>
        </div>

        <div class="hot-shelf__cta">
            <a href="/pages/shop/" class="hot-shelf__viewall">View All Pieces</a>
        </div>

    </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
