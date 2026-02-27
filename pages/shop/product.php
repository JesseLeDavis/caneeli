<?php
$pageTitle = "Product";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /pages/shop/'); exit; }

$stmt = getDB()->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) { header('Location: /pages/shop/'); exit; }

$pageTitle = sanitize($product['name']);
?>

<section class="hero">
    <div class="container">
        <div class="product-detail">

            <div class="product-detail__image">
                <?php if ($product['image_path']): ?>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
