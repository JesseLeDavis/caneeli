<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$categories = ['Chairs', 'Tables', 'Shelves', 'Wall Decor', 'Lighting', 'Other'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $stock_qty   = intval($_POST['stock_qty'] ?? 1);
    $category    = $_POST['category'] ?? '';
    $active      = isset($_POST['active']) ? 1 : 0;

    // Basic validation
    if (!$name || !$price || !$category) {
        $error = 'Name, price, and category are required.';
    } elseif (!in_array($category, $categories)) {
        $error = 'Invalid category.';
    } else {
        // Handle image upload
        $image_path = null;
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime    = mime_content_type($_FILES['image']['tmp_name']);

            if (!in_array($mime, $allowed)) {
                $error = 'Image must be a JPG, PNG, WebP, or GIF.';
            } else {
                $ext        = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename   = uniqid('product_') . '.' . strtolower($ext);
                $dest       = __DIR__ . '/../assets/uploads/' . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $image_path = '/assets/uploads/' . $filename;
                } else {
                    $error = 'Failed to upload image.';
                }
            }
        }

        if (!$error) {
            $pdo  = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, stock_qty, category, image_path, active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $stock_qty, $category, $image_path, $active]);
            $success = 'Product added successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product | Caneeli Admin</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<div class="admin-header">
    <strong>Caneeli Admin</strong>
    <a href="/admin/logout.php">Log Out</a>
</div>

<div class="container">
    <div class="page-header">
        <h1>Add Product</h1>
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
            <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>

            <label>Description</label>
            <textarea name="description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

            <label>Price ($)</label>
            <input type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>

            <label>Stock Quantity</label>
            <input type="number" name="stock_qty" min="0" value="<?php echo htmlspecialchars($_POST['stock_qty'] ?? '1'); ?>">

            <label>Category</label>
            <select name="category" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo (($_POST['category'] ?? '') === $cat) ? 'selected' : ''; ?>>
                        <?php echo $cat; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Product Image</label>
            <input type="file" name="image" accept="image/*">

            <label>
                <input type="checkbox" name="active" value="1" <?php echo (!isset($_POST['active']) || $_POST['active']) ? 'checked' : ''; ?>>
                Active (visible in shop)
            </label>
            <br>

            <button type="submit" class="btn btn-primary">Add Product</button>
        </form>
    </div>
</div>

</body>
</html>
