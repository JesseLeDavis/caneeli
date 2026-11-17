<?php
// Shop product card: photo with a colored offset shadow and a plain caption
// underneath, like a gallery label. Used by the shop grid, the "More from the
// studio" strip on product pages and the empty cart. (The home carousel has
// its own variant in index.php.)
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/product_status.php';

/** True when a product row can't be bought (status or no stock). */
function product_is_sold_out(array $product): bool {
    return ($product['status'] ?? 'active') === 'sold_out' || (int) ($product['stock_qty'] ?? 0) <= 0;
}

/**
 * Prints one card: a full-bleed photo with name and price over a dark
 * gradient at the bottom. On hover-capable screens a short description and a
 * "View piece" pill slide in; on touch the name and price are simply there.
 * $heading is the name's tag: h2 when the cards sit straight under the
 * page's h1, h3 under a section heading.
 */
function render_product_card(array $product, string $heading = 'h3'): void {
    $sold = product_is_sold_out($product);
    $tag  = in_array($heading, ['h2', 'h3', 'h4'], true) ? $heading : 'h3';
    $desc = trim(preg_replace('/\s+/', ' ', (string) ($product['description'] ?? '')));
    $desc = $desc !== '' ? mb_strimwidth($desc, 0, 72, '…') : '';
    ?>
    <a href="/pages/shop/product.php?id=<?php echo (int) $product['id']; ?>" class="product-card<?php echo $sold ? ' product-card--sold-out' : ''; ?>">
        <?php if (!empty($product['image_path'])): ?>
            <img class="product-card__bg" src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="" loading="lazy">
        <?php else: ?>
            <span class="product-card__bg product-card__placeholder"></span>
        <?php endif; ?>
        <?php if (($product['status'] ?? '') === 'preorder'): ?>
            <span class="product-card__sold-out product-card__preorder"><?php echo $sold ? 'Pre-orders full' : 'Pre-order'; ?></span>
        <?php elseif ($sold): ?>
            <span class="product-card__sold-out">Sold</span>
        <?php endif; ?>
        <div class="product-card__overlay">
            <<?php echo $tag; ?> class="product-card__name"><?php echo sanitize($product['name']); ?></<?php echo $tag; ?>>
            <?php if ($desc !== ''): ?><span class="product-card__description" aria-hidden="true"><?php echo sanitize($desc); ?></span><?php endif; ?>
            <span class="product-card__price"><?php echo formatPrice($product['price']); ?><?php if (product_is_preorderable($product)): ?><span class="visually-hidden">, available to pre-order</span><?php elseif ($sold): ?><span class="visually-hidden">, sold out</span><?php endif; ?></span>
            <span class="product-card__cta" aria-hidden="true">View piece</span>
        </div>
    </a>
    <?php
}
