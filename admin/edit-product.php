<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$categories = ['Chairs', 'Tables', 'Shelves', 'Wall Decor', 'Lighting', 'Other'];
$error   = '';
$success = '';
$pdo     = getDB();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/dashboard.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { header('Location: /admin/dashboard.php'); exit; }

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
        // Process any new image uploads
        $new_paths = [];
        if (!empty($_FILES['new_images']['name'][0])) {
            $files = $_FILES['new_images'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $single = [
                    'name'     => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                ];
                $path = upload_image($single, $error);
                if ($error) break;
                if ($path) $new_paths[] = $path;
            }
        }

        if (!$error) {
            // Update core product fields
            $pdo->prepare("
                UPDATE products
                SET name = ?, description = ?, price = ?, stock_qty = ?,
                    category = ?, active = ?
                WHERE id = ?
            ")->execute([$name, $description, $price, $stock_qty, $category, $active, $id]);

            // Insert new gallery images
            if ($new_paths) {
                // Get current max sort_order
                $max_q = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?");
                $max_q->execute([$id]);
                $order = (int) $max_q->fetchColumn() + 1;

                $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                foreach ($new_paths as $path) {
                    $img_stmt->execute([$id, $path, $order++]);
                }

                // If no featured image yet, set the first new upload
                if (!$product['image_path']) {
                    $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?")
                        ->execute([$new_paths[0], $id]);
                }
            }

            // Reload product
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            $success = 'Product updated successfully.';
        }
    }
}

// Fetch gallery images
$gallery_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
$gallery_stmt->execute([$id]);
$gallery = $gallery_stmt->fetchAll();

// Flash messages from redirect
if (isset($_GET['deleted'])) $success = 'Image deleted.';
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

    <!-- Gallery management lives OUTSIDE the main form to avoid nested <form> -->
    <div class="form-card" style="margin-bottom:16px">
        <label>Photos</label>
        <?php if ($gallery): ?>
            <div class="gallery-grid">
                <?php foreach ($gallery as $img): ?>
                    <?php $is_featured = ($product['image_path'] === $img['image_path']); ?>
                    <div class="gallery-tile <?php echo $is_featured ? 'gallery-tile--featured' : ''; ?>">
                        <div class="gallery-tile__img-wrap">
                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Product photo">
                        </div>
                        <?php if ($is_featured): ?>
                            <span class="gallery-tile__badge">Featured</span>
                        <?php endif; ?>
                        <div class="gallery-tile__actions">
                            <?php if (!$is_featured): ?>
                                <form method="POST" action="/admin/set-featured-image.php">
                                    <input type="hidden" name="image_id"   value="<?php echo $img['id']; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                    <button type="submit" class="gallery-tile__btn gallery-tile__btn--feature">★ Feature</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="/admin/delete-product-image.php"
                                  onsubmit="return confirm('Delete this photo?')">
                                <input type="hidden" name="image_id"   value="<?php echo $img['id']; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <button type="submit" class="gallery-tile__btn gallery-tile__btn--delete">× Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="font-size:13px;color:#888;margin-bottom:8px">No photos yet.</p>
        <?php endif; ?>
    </div>

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

            <label>Add more photos <span style="font-weight:400;font-size:12px;opacity:.6">(select multiple at once)</span></label>
            <input type="file" name="new_images[]" accept="image/*" multiple>

            <label style="margin-top:16px">
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
