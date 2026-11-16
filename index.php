<?php
if (time() < strtotime('2026-05-01 18:00:00 America/Los_Angeles')) {
    header('Location: /coming-soon.php');
    exit;
}
require_once __DIR__ . '/config/config.php'; // SITE_URL, used below before header.php loads it
$pageTitle = "Home";
$fullTitle = 'Caneeli Designs | Handmade Stained Glass Lamps & Ceramic Lighting';
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
require_once 'includes/product_card.php';
require_once 'includes/series_drop.php';

// Pieces you can buy right now: featured first, then the newest. Sold pieces
// stay in the shop but don't take up this shelf.
$stmt = getDB()->query("SELECT * FROM products
    WHERE active = 1 AND status = 'active' AND stock_qty > 0
    ORDER BY featured DESC, updated_at DESC
    LIMIT 8");
$featured = $stmt->fetchAll();
?>

<!-- Hero -->
<section class="hero home-hero">
    <div class="container">
        <?php $dropState = series_state(); ?>
        <div class="home-hero__copy">
            <?php if ($dropState !== 'over'): [$dd, $dh, $dm, $ds] = series_time_left(); ?>
                <?php /* New series countdown while the release window is open
                         (includes/series_drop.php); the usual headline returns after. */ ?>
                <div class="hero-drop <?php echo $dropState === 'live' ? 'is-live' : ''; ?>" id="new-series"
                     data-drop-release="<?php echo series_release_ts() * 1000; ?>">
                    <p class="hero-drop__kicker"><?php echo $dropState === 'live' ? 'New series, out now' : 'New series, dropping ' . htmlspecialchars(series_release_label()); ?></p>
                    <h1 class="large_title hero-drop__title"><?php echo htmlspecialchars(SERIES_NAME); ?></h1>
                    <p class="hero-drop__tagline"><?php echo htmlspecialchars(SERIES_TAGLINE); ?></p>
                    <?php if ($dropState === 'soon'): ?>
                    <div class="hero-drop__clock" role="timer" aria-label="Time until release">
                        <?php foreach ([['d', $dd, 'Days'], ['h', $dh, 'Hours'], ['m', $dm, 'Minutes'], ['s', $ds, 'Seconds']] as [$k, $v, $label]): ?>
                            <div class="hero-drop__unit">
                                <span class="hero-drop__num" data-drop-<?php echo $k; ?>><?php echo str_pad((string) $v, 2, '0', STR_PAD_LEFT); ?></span>
                                <span class="hero-drop__label"><?php echo $label; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h1 class="large_title">Make a space<br class="home-hero__br"> that's actually<br class="home-hero__br"> yours</h1>
                <p class="home-hero__lede">Handmade pieces that turn "fine, I guess" into "wait, I love it here."</p>
            <?php endif; ?>
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

        <div class="shelf-row" data-shelf>
            <button type="button" class="shelf-row__arrow shelf-row__arrow--prev" data-shelf-prev aria-label="Previous pieces" disabled>&#8249;</button>
            <ul class="shelf-row__track" data-shelf-track>
                <?php foreach ($featured as $p): ?>
                    <li class="shelf-row__item"><?php render_product_card($p); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="shelf-row__arrow shelf-row__arrow--next" data-shelf-next aria-label="More pieces">&#8250;</button>
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
            <p>Annie traded 3D animation for a garage studio in Oregon. She makes stained glass lamps, salmon in every form, and hand-sculpted ceramic lighting like the salmon lava lamp that started it all.</p>
            <a href="/pages/about.php" class="btn">Meet Annie</a>
        </div>
        <div class="pane pane--blue">
            <span class="pane__mark"><?php echo $pelican; ?></span>
            <h2 class="pane__title">Have something in mind?</h2>
            <p>A lamp for a particular corner, a piece in your colors, a gift that means something. Tell Annie what you're picturing and she'll send you a quote.</p>
            <a href="/pages/contact.php" class="btn red-button">Start a commission</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
