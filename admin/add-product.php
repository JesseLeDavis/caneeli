<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

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
    $active      = isset($_POST['active']) ? 1 : 0;

    // Craft signals — merge presets + custom, cap at 5, sanitize
    $preset_tags = $_POST['craft_signals_preset'] ?? [];
    $custom_tag  = trim($_POST['craft_signal_custom'] ?? '');
    $all_signals = array_values(array_unique(array_merge(
        $preset_tags,
        $custom_tag !== '' ? [$custom_tag] : []
    )));
    $all_signals = array_slice(array_map('strip_tags', $all_signals), 0, 5);
    $craft_signals_json = !empty($all_signals) ? json_encode($all_signals) : null;

    if (!$error && !$name || !$price || !$category) {
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
                INSERT INTO products (name, description, craft_signals, price, stock_qty, category, image_path, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $craft_signals_json, $price, $stock_qty, $category, $featured_path, $active]);
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
    <div class="admin-header__brand">
        <img src="/assets/images/logowi.svg" alt="Caneeli Designs" class="admin-header__logo">
        <span class="admin-header__admin-label">Admin</span>
    </div>
    <a href="/admin/logout.php" class="admin-header__logout">Log Out</a>
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
            <?php echo csrf_field(); ?>

            <span class="form-section-label">Core Details</span>

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

            <span class="form-section-label">Photos</span>

            <label>Product Photos <span style="font-weight:400;font-size:12px;opacity:.6">(first photo becomes the featured image)</span></label>
            <div class="file-upload-area">
                <input type="file" name="images[]" accept="image/*" multiple>
            </div>

            <span class="form-section-label">Craft Signals</span>

            <label>Craft Signals <span style="font-weight:400;font-size:12px;opacity:.6">(up to 5 — shown on product page)</span></label>
            <div class="craft-signals-grid">
                <?php foreach ($preset_signals as $tag): ?>
                    <label class="craft-signal-option">
                        <input type="checkbox"
                               name="craft_signals_preset[]"
                               value="<?php echo htmlspecialchars($tag); ?>"
                               <?php echo in_array($tag, $_POST['craft_signals_preset'] ?? []) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($tag); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="craft_signal_custom"
                   placeholder="Custom tag (e.g. Reclaimed Oak)…"
                   maxlength="40"
                   value="<?php echo htmlspecialchars($_POST['craft_signal_custom'] ?? ''); ?>"
                   style="margin-bottom:24px">

            <span class="form-section-label">Visibility</span>

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
