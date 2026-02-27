<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$categories = ['Chairs', 'Tables', 'Shelves', 'Wall Decor', 'Lighting', 'Other'];
$error  = '';
$success = '';
$pdo = getDB();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin/dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: /admin/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $stock_qty   = intval($_POST['stock_qty'] ?? 1);
    $category    = $_POST['category'] ?? '';
    $active      = isset($_POST['active']) ? 1 : 0;

    if (!$name || !$price || !$category) {
        $error = 'Name, price, and category are required.';
    } elseif (!in_array($category, $categories)) {
        $error = 'Invalid category.';
    } else {
        $image_path = $product['image_path'];

        // Handle new image upload
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime    = mime_content_type($_FILES['image']['tmp_name']);

            if (!in_array($mime, $allowed)) {
                $error = 'Image must be a JPG, PNG, WebP, or GIF.';
            } else {
                $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('product_') . '.' . strtolower($ext);
                $dest     = __DIR__ . '/../assets/uploads/' . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    // Delete old image if it exists
                    if ($product['image_path']) {
                        $old = __DIR__ . '/..' . $product['image_path'];
                        if (file_exists($old)) unlink($old);
                    }
                    $image_path = '/assets/uploads/' . $filename;
                } else {
                    $error = 'Failed to upload image.';
                }
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("
                UPDATE products
                SET name = ?, description = ?, price = ?, stock_qty = ?,
                    category = ?, image_path = ?, active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $price, $stock_qty, $category, $image_path, $active, $id]);
            $product = array_merge($product, compact('name', 'description', 'price', 'stock_qty', 'category', 'active'));
            $product['image_path'] = $image_path;
            $success = 'Product updated successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product | Caneeli Admin</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<div class="admin-header">
    <strong>Caneeli Admin</strong>
    <a href="/admin/logout.php">Log Out</a>
</div>

<div class="container">
    <div class="page-header">
        <h1>Edit Product</h1>
        <a href="/admin/dashboard.php" class="btn btn-secondary">← Back</a>
    </div>

    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" enctype="multipart/form-data">

            <label>Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>

            <label>Description</label>
            <textarea name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>

            <label>Price ($)</label>
            <input type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>

            <label>Stock Quantity</label>
            <input type="number" name="stock_qty" min="0" value="<?php echo htmlspecialchars($product['stock_qty']); ?>">

            <label>Category</label>
            <select name="category" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo ($product['category'] === $cat) ? 'selected' : ''; ?>>
                        <?php echo $cat; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Product Image</label>
            <?php if ($product['image_path']): ?>
                <div style="margin-bottom:12px">
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" style="width:100px;height:100px;object-fit:cover;border-radius:4px">
                    <p style="font-size:13px;color:#666;margin-top:4px">Upload a new image to replace this one.</p>
                </div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">

            <label>
                <input type="checkbox" name="active" value="1" <?php echo $product['active'] ? 'checked' : ''; ?>>
                Active (visible in shop)
            </label>
            <br>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

</body>
</html>
