<?php
$pageTitle = "Shop";
include __DIR__ . '/../../includes/header.php';

// Example products (replace with database query later)
$products = [
    ['id' => 1, 'name' => 'Product One', 'price' => 29.99, 'image' => 'https://placehold.co/400x400/e2e8f0/475569?text=Product'],
    ['id' => 2, 'name' => 'Product Two', 'price' => 49.99, 'image' => 'https://placehold.co/400x400/e2e8f0/475569?text=Product'],
    ['id' => 3, 'name' => 'Product Three', 'price' => 39.99, 'image' => 'https://placehold.co/400x400/e2e8f0/475569?text=Product'],
];
?>

<section class="hero">
<div class="container">
    <h2 class="large_title">Shop</h2>

    <div class="grid grid-3">
        <?php foreach ($products as $product): ?>
        <div class="card">
            <img src="<?php echo $product['image']; ?>" alt="<?php echo sanitize($product['name']); ?>" class="card-image">
            <div class="card-body">
                <h3 class="card-title"><?php echo sanitize($product['name']); ?></h3>
                <p class="card-price"><?php echo formatPrice($product['price']); ?></p>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary mt-1">View Details</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</section>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
