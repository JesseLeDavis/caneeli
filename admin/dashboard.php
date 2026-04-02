<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();
$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Caneeli Admin</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<div class="admin-header">
    <div class="admin-header__brand">
        <img src="/assets/images/logowi.svg" alt="Caneeli Designs" class="admin-header__logo">
        <span class="admin-header__admin-label">Admin</span>
    </div>
    <a href="/admin/logout.php" class="admin-header__logout">Log Out</a>
</div>

<div class="container">
    <div class="page-header">
        <h1>Products</h1>
        <div style="display:flex;gap:10px">
            <a href="/admin/email-signups.php" class="btn btn-secondary">Email Signups</a>
            <a href="/admin/add-product.php" class="btn btn-primary">+ Add Product</a>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <p>No products yet. <a href="/admin/add-product.php">Add your first one.</a></p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr>
                <td>
                    <?php if ($product['image_path']): ?>
                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($product['name']); ?></td>
                <td><?php echo htmlspecialchars($product['category']); ?></td>
                <td>$<?php echo number_format($product['price'], 2); ?></td>
                <td><?php echo $product['stock_qty']; ?></td>
                <td>
                    <?php if ($product['active']): ?>
                        <span class="badge-active">Active</span>
                    <?php else: ?>
                        <span class="badge-inactive">Hidden</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" action="/admin/toggle-featured.php" style="display:inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                        <button type="submit" class="btn-star <?php echo $product['featured'] ? 'btn-star--on' : ''; ?>" title="<?php echo $product['featured'] ? 'Remove from Hot Off the Shelf' : 'Add to Hot Off the Shelf'; ?>">
                            <?php echo $product['featured'] ? '★' : '☆'; ?>
                        </button>
                    </form>
                </td>
                <td>
                    <div class="td-actions">
                        <a href="/admin/edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary">Edit</a>
                        <form method="POST" action="/admin/delete-product.php" onsubmit="return confirm('Delete this product?')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</body>
</html>
