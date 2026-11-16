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
        // Shipping and returns, from pages/terms.php. Search Console flags
        // product listings without these. Keep them in step with the terms
        // page and checkout.php: checkout charges no shipping, so the rate is
        // $0. In-stock items ship in 5–10 business days, U.S. only.
        'shippingDetails' => [
            '@type'        => 'OfferShippingDetails',
            'shippingRate' => [
                '@type'    => 'MonetaryAmount',
                'value'    => '0.00',
                'currency' => 'USD',
            ],
            'shippingDestination' => [
                '@type'          => 'DefinedRegion',
                'addressCountry' => 'US',
            ],
            'deliveryTime' => [
                '@type'        => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type'    => 'QuantitativeValue',
                    'minValue' => 5,
                    'maxValue' => 10,
                    'unitCode' => 'DAY',
                ],
            ],
        ],
        // Returns are only for production defects, handled one by one, so
        // the general policy is "no returns".
        'hasMerchantReturnPolicy' => [
            '@type'                => 'MerchantReturnPolicy',
            'applicableCountry'    => 'US',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
        ],
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

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/pages/shop/">Shop</a>
            <span>/</span>
            <a href="/pages/shop/?category=<?php echo urlencode($product['category']); ?>"><?php echo sanitize($product['category']); ?></a>
            <span>/</span>
            <span aria-current="page"><?php echo sanitize($product['name']); ?></span>
        </nav>

        <div class="product-detail">

            <div class="product-detail__image <?php echo count($gallery) > 1 ? 'product-detail__image--gallery' : ''; ?>">
                <?php if ($is_sold_out): ?><span class="product-detail__sold-pill">Sold</span><?php endif; ?>
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
                <h1 class="large_title"><?php echo sanitize($product['name']); ?></h1>
                <p class="product-detail__price"><?php echo formatPrice($product['price']); ?></p>
                <?php
                // Free text from the admin. Blank lines become paragraphs and
                // single line breaks are kept, so spec lists (glass, size, cord,
                // bulb) read as lines instead of one long run.
                $desc_paras = array_filter(array_map('trim', preg_split('/\R\s*\R/', trim($product['description'] ?? ''))));
                if ($desc_paras):
                ?>
                <div class="product-detail__description">
                    <?php foreach ($desc_paras as $para): ?>
                        <p><?php echo nl2br(sanitize($para)); ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php
                $signals = json_decode($product['craft_signals'] ?? '[]', true) ?? [];
                $signals = array_values(array_filter(array_map(fn($sig) => trim(strip_tags((string) $sig)), $signals), 'strlen'));
                // Signals about availability or timing ("Ready to Ship",
                // "Made to Order", "Ships in…") would contradict a sold-out page.
                $is_availability_signal = fn(string $sig): bool => (bool) preg_match('/\bships?\b|\bto order\b/i', $sig);
                // Made-to-order pieces have their own timeline, so the generic
                // in-stock shipping line is skipped for them ("Ready to Ship" keeps it).
                $is_made_to_order_signal = fn(string $sig): bool => (bool) preg_match('/\bto order\b|^ships in\b/i', $sig);
                if ($is_sold_out) {
                    $signals = array_values(array_filter($signals, fn($sig) => !$is_availability_signal($sig)));
                }
                if (!empty($signals)):
                ?>
                <div class="product-detail__craft-signals">
                    <?php foreach ($signals as $signal):
                        $is_ship = $is_availability_signal($signal);
                        $signal  = htmlspecialchars($signal);
                        if ($is_ship) {
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
                    <?php $stock = (int) $product['stock_qty']; ?>
                    <form method="POST" action="/cart.php" id="atc-form">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <?php if ($stock <= 1): ?>
                        <input type="hidden" name="quantity" value="1">
                        <?php else: ?>
                        <div class="product-detail__qty">
                            <label for="qty-input">Quantity</label>
                            <div class="qty-stepper">
                                <button type="button" class="qty-stepper__btn" data-dir="-1" aria-label="One fewer">−</button>
                                <input type="number" name="quantity" value="1" id="qty-input"
                                       min="1" max="<?php echo $stock; ?>"
                                       class="qty-stepper__input" readonly>
                                <button type="button" class="qty-stepper__btn" data-dir="1" aria-label="One more">+</button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn red-button product-detail__atc-btn">Add to cart</button>
                        <?php if ($stock === 1): ?>
                            <p class="product-detail__stock">Last one</p>
                        <?php elseif ($stock <= 5): ?>
                            <p class="product-detail__stock"><?php echo $stock; ?> available</p>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <div class="product-detail__soldout">
                        <p><strong>Sold out.</strong> This one's found a home. Annie can make something similar as a commission.</p>
                        <div class="product-detail__soldout-actions">
                            <a href="/pages/contact.php?about=<?php echo urlencode($product['name']); ?>" class="btn red-button">Ask about a commission</a>
                            <a href="/pages/shop/?category=<?php echo urlencode($product['category']); ?>" class="btn">More <?php echo sanitize(strtolower($product['category'])); ?></a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                // Facts from the terms page. A piece with its own shipping signal
                // (e.g. "Made to Order") skips the generic in-stock timeline.
                $has_ship_signal = (bool) array_filter($signals, $is_made_to_order_signal);
                ?>
                <ul class="product-detail__facts">
                    <li>Shipping is included in the price (U.S. only).</li>
                    <?php if (!$has_ship_signal && !$is_sold_out): ?><li>In-stock pieces ship within 5–10 business days.</li><?php endif; ?>
                    <li>Made by hand, so color and texture vary a little from the photos.</li>
                    <li><a href="/pages/terms.php#returns">Returns and full terms</a></li>
                </ul>

                <?php if ($product_cats): ?>
                <p class="product-detail__category">Filed under
                    <?php foreach ($product_cats as $i => $cat): ?><?php echo $i ? ', ' : ' '; ?><a href="/pages/shop/?category=<?php echo urlencode($cat); ?>"><?php echo sanitize($cat); ?></a><?php endforeach; ?>
                </p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<?php
// "More from the studio": up to four other pieces that can be bought now,
// same category first, then featured, then the admin's drag order.
$more_stmt = $pdo->prepare("SELECT * FROM products
    WHERE active = 1 AND status = 'active' AND stock_qty > 0 AND id != ?
    ORDER BY (category = ?) DESC, featured DESC, sort_order ASC, created_at DESC
    LIMIT 4");
$more_stmt->execute([(int) $product['id'], (string) $product['category']]);
$more = $more_stmt->fetchAll();
require_once __DIR__ . '/../../includes/product_card.php';
$pelican = file_get_contents(__DIR__ . '/../../assets/images/pelican.svg');
?>
<section class="product-more" aria-labelledby="product-more-title">
    <div class="container">
        <?php if ($more): ?>
        <div class="product-more__header">
            <h2 class="product-more__title" id="product-more-title">More from the studio</h2>
            <a class="product-more__all" href="/pages/shop/">See every piece</a>
        </div>
        <div class="product-grid product-grid--strip">
            <?php foreach ($more as $m) render_product_card($m); ?>
        </div>
        <?php else: ?>
        <h2 class="visually-hidden" id="product-more-title">Commissions</h2>
        <?php endif; ?>

        <div class="pane pane--blue product-more__pane">
            <span class="pane__mark"><?php echo $pelican; ?></span>
            <h2 class="pane__title">Want one made for you?</h2>
            <p>A piece like the <?php echo sanitize($product['name']); ?> in your colors, or something new for a particular corner. Tell Annie what you're picturing and she'll send you a quote.</p>
            <a href="/pages/contact.php?about=<?php echo urlencode($product['name']); ?>" class="btn red-button">Start a commission</a>
        </div>
    </div>
</section>

<?php if (!$is_sold_out): ?>
<?php /* Phone only: once the main Add to cart button scrolls away, a slim bar
         keeps price + Add to cart in reach. It submits the same form above. */ ?>
<div class="atc-bar" id="atc-bar" role="region" aria-label="Add to cart" hidden>
    <div class="atc-bar__info">
        <span class="atc-bar__name"><?php echo sanitize($product['name']); ?></span>
        <span class="atc-bar__price"><?php echo formatPrice($product['price']); ?></span>
    </div>
    <button type="submit" form="atc-form" class="btn red-button atc-bar__btn">Add to cart</button>
</div>
<script>
// Runs after the footer (included below) is in the DOM.
document.addEventListener('DOMContentLoaded', function () {
    const bar    = document.getElementById('atc-bar');
    const btn    = document.querySelector('.product-detail__atc-btn');
    const price  = document.querySelector('.product-detail__price');
    const footer = document.querySelector('.site-footer');
    if (!bar || !btn || !price || !('IntersectionObserver' in window)) return;

    // Shown while the visitor is past the price (reading the description or
    // facts) and the main button isn't on screen; hidden near the footer.
    // Positions are read live each time: a fast fling can jump an element
    // across the screen without its observer reporting a change.
    const phone = matchMedia('(max-width: 767px)');
    const onScreen = function (el) {
        const r = el.getBoundingClientRect();
        return r.bottom > 0 && r.top < innerHeight;
    };

    function render() {
        const pastPrice = price.getBoundingClientRect().bottom < 0;
        const show = phone.matches && pastPrice && !onScreen(btn) && !(footer && onScreen(footer));
        bar.hidden = !show;
    }

    const io = new IntersectionObserver(render);
    io.observe(price);
    io.observe(btn);
    if (footer) io.observe(footer);

    let ticking = false;
    addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () { ticking = false; render(); });
    }, { passive: true });
    phone.addEventListener('change', render);
    render();
});
</script>
<?php endif; ?>

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
