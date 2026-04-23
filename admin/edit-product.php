<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/product_status.php';

$categories = ['Chairs', 'Tables', 'Shelves', 'Wall Decor', 'Lighting', 'Other'];
$preset_signals = [
    'Ready to Ship',
    'Ships in 3–5 Days',
    'Ships in 2–3 Weeks',
    'Ships in 4–6 Weeks',
    'Handcrafted to Order',
    'Made to Order',
    'Solid Wood',
    'Cane & Rattan',
    'Upholstered',
    'Hand-Painted Finish',
    'Limited Edition',
    'Signed by Maker',
];
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

    // Validate MIME via finfo (more reliable than mime_content_type)
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowed_ext  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_mime)) {
        $error = 'Images must be JPG, PNG, WebP, or GIF.';
        return null;
    }

    // Validate extension matches MIME
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        $error = 'Invalid file extension.';
        return null;
    }

    // Verify it's a real image
    if (!getimagesize($file['tmp_name'])) {
        $error = 'File does not appear to be a valid image.';
        return null;
    }

    // 10 MB limit
    if ($file['size'] ?? filesize($file['tmp_name']) > 10 * 1024 * 1024) {
        $error = 'Image must be under 10 MB.';
        return null;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest     = __DIR__ . '/../assets/uploads/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = 'Failed to upload image.';
        return null;
    }
    return '/assets/uploads/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    }

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $stock_qty   = intval($_POST['stock_qty'] ?? 1);
    $category    = $_POST['category'] ?? '';
    $status      = $_POST['status'] ?? 'active';
    if (!in_array($status, PRODUCT_STATUSES, true)) $status = 'active';
    $active      = product_active_for_status($status);

    // Craft signals — merge presets + custom, cap at 5, sanitize
    $preset_tags = $_POST['craft_signals_preset'] ?? [];
    $custom_tag  = trim($_POST['craft_signal_custom'] ?? '');
    $all_signals = array_values(array_unique(array_merge(
        $preset_tags,
        $custom_tag !== '' ? [$custom_tag] : []
    )));
    $all_signals = array_slice(array_map('strip_tags', $all_signals), 0, 5);
    $craft_signals_json = !empty($all_signals) ? json_encode($all_signals) : null;

    if (!$error && (!$name || !$price || !$category)) {
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
                SET name = ?, description = ?, craft_signals = ?, price = ?, stock_qty = ?,
                    category = ?, active = ?, status = ?
                WHERE id = ?
            ")->execute([$name, $description, $craft_signals_json, $price, $stock_qty, $category, $active, $status, $id]);

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
if (isset($_GET['msg']))     $success = htmlspecialchars((string) $_GET['msg']);

// Decode saved signals for pre-checking checkboxes
$current_signals = json_decode($product['craft_signals'] ?? '[]', true) ?? [];
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
    <div class="admin-header__brand">
        <img src="/assets/images/logowi.svg" alt="Caneeli Designs" class="admin-header__logo">
        <span class="admin-header__admin-label">Admin</span>
    </div>
    <a href="/admin/logout.php" class="admin-header__logout">Log Out</a>
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
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="image_id"   value="<?php echo $img['id']; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                    <button type="submit" class="gallery-tile__btn gallery-tile__btn--feature">★ Feature</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="/admin/delete-product-image.php"
                                  onsubmit="return confirm('Delete this photo?')">
                                <?php echo csrf_field(); ?>
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
            <?php echo csrf_field(); ?>

            <span class="form-section-label">Core Details</span>

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

            <span class="form-section-label">Photos</span>

            <label>Add more photos <span style="font-weight:400;font-size:12px;opacity:.6">(select multiple at once)</span></label>
            <div class="file-upload-area">
                <input type="file" name="new_images[]" accept="image/*" multiple>
            </div>

            <span class="form-section-label">Craft Signals</span>

            <label>Craft Signals <span style="font-weight:400;font-size:12px;opacity:.6">(up to 5 — shown on product page)</span></label>
            <div class="craft-signals-grid">
                <?php foreach ($preset_signals as $tag): ?>
                    <label class="craft-signal-option">
                        <input type="checkbox"
                               name="craft_signals_preset[]"
                               value="<?php echo htmlspecialchars($tag); ?>"
                               <?php echo in_array($tag, $current_signals) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($tag); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php
            // Render hidden inputs for any custom (non-preset) tags already saved
            foreach ($current_signals as $sig):
                if (!in_array($sig, $preset_signals)):
            ?>
                <div class="craft-signal-custom-chip">
                    <?php echo htmlspecialchars($sig); ?>
                    <input type="hidden" name="craft_signals_preset[]" value="<?php echo htmlspecialchars($sig); ?>">
                </div>
            <?php endif; endforeach; ?>
            <input type="text" name="craft_signal_custom"
                   placeholder="Custom tag (e.g. Reclaimed Oak)…"
                   maxlength="40"
                   style="margin-bottom:24px">

            <span class="form-section-label">Status</span>

            <label>Visibility</label>
            <select name="status">
                <?php foreach (PRODUCT_STATUSES as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo (($product['status'] ?? 'active') === $s) ? 'selected' : ''; ?>>
                        <?php echo product_status_label($s); ?>
                        <?php
                            echo match ($s) {
                                'active'   => ' — visible & for sale',
                                'sold_out' => ' — visible, cannot be bought',
                                'draft'    => ' — hidden from shop',
                                'archived' => ' — hidden, preserved for records',
                                default    => '',
                            };
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

</body>
</html>
