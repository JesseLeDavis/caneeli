<?php
if (time() < strtotime('2026-05-01 18:00:00 America/Los_Angeles')) {
    header('Location: /coming-soon.php');
    exit;
}
require_once __DIR__ . '/config/config.php'; // SITE_URL, used below before header.php loads it
$pageTitle = "Home";
$fullTitle = 'Caneeli Designs | Handmade Stained Glass & Ceramics';
// Lets Google show the brand name and logo for the site.
$structuredData = [
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => 'Caneeli Designs',
    'url'      => rtrim(SITE_URL, '/') . '/',
    'logo'     => rtrim(SITE_URL, '/') . '/assets/images/favicon-512.png',
    'sameAs'   => ['https://www.instagram.com/caneelidesigns/', 'https://www.tiktok.com/@caneeli.designs'],
];
include 'includes/header.php';
include 'includes/db.php';

$stmt = getDB()->query("SELECT * FROM products WHERE featured = 1 AND active = 1 ORDER BY updated_at DESC LIMIT 10");
$featured = $stmt->fetchAll();
?>

<!-- Hero -->
<section class="hero home-hero">
    <div class="container">
        <div class="home-hero__copy">
            <h1 class="large_title">Make a space that's actually yours</h1>
            <p class="home-hero__lede">Handmade pieces that turn "fine, I guess" into "wait, I love it here."</p>
            <div class="button-container">
                <a href="/pages/shop/" class="btn red-button">Shop the collection</a>
                <a href="/pages/contact.php" class="btn">Commission a piece</a>
            </div>
        </div>
        <?php /* Procreate animation with a transparent background. Safari only
                 keeps the transparency in HEVC (.mov); Chrome and Firefox get
                 VP9 WebM. The .mov must come first: its video/quicktime type is
                 one only Safari claims, so other browsers skip to the WebM. */ ?>
        <video class="hero-fish" autoplay muted loop playsinline preload="auto"
               poster="/assets/images/hero/hero_fish.webp" aria-hidden="true">
            <source src="/assets/images/hero/hero_fish.mov" type='video/quicktime; codecs="hvc1"'>
            <source src="/assets/images/hero/hero_fish.webm" type="video/webm">
        </video>
    </div>
</section>

<?php if (!empty($featured)): ?>
<!-- Hot off the shelf -->
<section class="hot-shelf" aria-labelledby="hot-shelf-title">
    <div class="container">
        <div class="hot-shelf__header">
            <h2 class="hot-shelf__title" id="hot-shelf-title">Hot off the shelf</h2>
            <p class="hot-shelf__sub">New and favorite pieces from the studio.</p>
        </div>

        <div class="spotlight">
            <div class="spotlight__stage">
                <?php foreach ($featured as $p): ?>
                <a href="/pages/shop/product.php?id=<?php echo $p['id']; ?>" class="product-card">
                    <div class="product-card__media">
                        <?php if ($p['image_path']): ?>
                            <img class="product-card__bg" src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
                        <?php else: ?>
                            <span class="product-card__bg product-card__placeholder"></span>
                        <?php endif; ?>
                    </div>
                    <div class="product-card__caption">
                        <span class="product-card__name"><?php echo htmlspecialchars($p['name']); ?></span>
                        <span class="product-card__price"><?php echo formatPrice($p['price']); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="spotlight__controls">
                <button class="spotlight__arrow spotlight__arrow--prev" aria-label="Previous piece">&#8249;</button>
                <div class="spotlight__dots">
                    <?php foreach ($featured as $i => $p): ?>
                    <button class="spotlight__dot" aria-label="Show <?php echo htmlspecialchars($p['name']); ?>"></button>
                    <?php endforeach; ?>
                </div>
                <button class="spotlight__arrow spotlight__arrow--next" aria-label="Next piece">&#8250;</button>
            </div>
        </div>

        <div class="hot-shelf__cta">
            <a href="/pages/shop/" class="btn btn--light">See every piece</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Studio + commissions -->
<?php $pelican = file_get_contents(__DIR__ . '/assets/images/pelican.svg'); ?>
<section class="home-panes">
    <div class="container home-panes__grid">
        <div class="pane pane--mustard">
            <span class="pane__mark"><?php echo $pelican; ?></span>
            <h2 class="pane__title">Made by hand in Oregon</h2>
            <p>Annie traded 3D animation for a garage studio full of glass and clay. Every lamp, suncatcher and mug is cut, soldered or thrown by hand, so no two come out quite the same.</p>
            <a href="/pages/about.php" class="btn">Meet Annie</a>
        </div>
        <div class="pane pane--blue">
            <span class="pane__mark"><?php echo $pelican; ?></span>
            <h2 class="pane__title">Have something in mind?</h2>
            <p>Window panels, lamps for a particular corner, a gift that means something. Tell Annie what you're picturing and she'll send you a quote.</p>
            <a href="/pages/contact.php" class="btn red-button">Start a commission</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
