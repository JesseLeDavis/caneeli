<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$categories = ['Chairs', 'Tables', 'Shelves', 'Wall Decor', 'Lighting', 'Other'];
$error   = '';
$success = '';

// Upload a single file and return its web path, or null on skip/failure
function upload_image(array $file, string &$error): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) return null;
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime    = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) {
        $error = 'Images must be JPG, PNG, WebP, or GIF.';
        return null;
    }
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('product_') . '.' . $ext;
    $dest     = __DIR__ . '/../assets/uploads/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = 'Failed to upload image.';
        return null;
    }
    return '/assets/uploads/' . $filename;
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
        // Collect uploaded files from the multi-file input
        $uploaded = [];
        if (!empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $single = [
                    'name'     => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                ];
                $path = upload_image($single, $error);
                if ($error) break;
                if ($path) $uploaded[] = $path;
            }
        }

        if (!$error) {
            $pdo = getDB();
            // First uploaded image becomes the featured image; null if none
            $featured_path = $uploaded[0] ?? null;

            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, stock_qty, category, image_path, active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $stock_qty, $category, $featured_path, $active]);
            $product_id = $pdo->lastInsertId();

            // Insert all images into the gallery table
            $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
            foreach ($uploaded as $order => $path) {
                $img_stmt->execute([$product_id, $path, $order]);
            }

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

            <label>Product Photos <span style="font-weight:400;font-size:12px;opacity:.6">(first photo becomes the featured image)</span></label>
            <input type="file" name="images[]" accept="image/*" multiple>

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
