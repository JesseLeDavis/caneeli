<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/product_status.php';
require_once __DIR__ . '/../includes/product_categories.php';

$categories = PRODUCT_CATEGORIES;
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
    $selected_cats = sanitize_product_categories((array) ($_POST['categories'] ?? []));
    $status      = $_POST['status'] ?? 'active';
    if (!in_array($status, PRODUCT_STATUSES, true)) $status = 'active';
    $active      = product_active_for_status($status);
    // Pre-orders need a ship-by date: it's what the buyer agrees to.
    $ship_date   = trim($_POST['preorder_ship_date'] ?? '');
    $ship_date   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ship_date) ? $ship_date : null;
    // Optional deposit per piece; blank or 0 means pay in full up front.
    $deposit     = (float) ($_POST['preorder_deposit'] ?? 0);
    $deposit     = $deposit > 0 ? round($deposit, 2) : null;
    if ($status === 'preorder' && $deposit !== null && $deposit >= (float) $price) {
        $error = $error ?: 'The deposit has to be less than the price.';
    }
    if ($status === 'preorder' && !$ship_date) {
        $error = $error ?: 'Pre-orders need a ship-by date.';
    }

    // Craft signals — merge presets + custom, cap at 5, sanitize
    $preset_tags = $_POST['craft_signals_preset'] ?? [];
    $custom_tag  = trim($_POST['craft_signal_custom'] ?? '');
    $all_signals = array_values(array_unique(array_merge(
        $preset_tags,
        $custom_tag !== '' ? [$custom_tag] : []
    )));
    $all_signals = array_slice(array_map('strip_tags', $all_signals), 0, 5);
    $craft_signals_json = !empty($all_signals) ? json_encode($all_signals) : null;

    if (!$error && (!$name || !$price || !$selected_cats)) {
        $error = 'Name, price, and at least one category are required.';
    } elseif (!$error) {
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
                INSERT INTO products (name, description, craft_signals, price, stock_qty, category, image_path, active, status, preorder_ship_date, preorder_deposit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $craft_signals_json, $price, $stock_qty, $selected_cats[0], $featured_path, $active, $status, $ship_date, $deposit]);
            $product_id = (int) $pdo->lastInsertId();

            save_product_categories($pdo, $product_id, $selected_cats);

            // Insert all images into the gallery table
            $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
            foreach ($uploaded as $order => $path) {
                $img_stmt->execute([$product_id, $path, $order]);
            }

            header('Location: /admin/edit-product.php?id=' . $product_id . '&msg=' . urlencode('Product added. ' . (product_status_is_visible($status) ? 'It\'s live in the shop.' : 'It\'s hidden from the shop until you change its status.')));
            exit;
        }
    }
}
?>
<?php
$v = [
    'name' => $_POST['name'] ?? '', 'description' => $_POST['description'] ?? '',
    'price' => $_POST['price'] ?? '', 'stock_qty' => $_POST['stock_qty'] ?? '1',
    'status' => $_POST['status'] ?? 'draft',
    'preorder_ship_date' => $_POST['preorder_ship_date'] ?? '', 'preorder_deposit' => $_POST['preorder_deposit'] ?? '',
];
$checked_cats    = sanitize_product_categories((array) ($_POST['categories'] ?? []));
$checked_signals = array_values((array) ($_POST['craft_signals_preset'] ?? []));
$mode = 'add';

$pageTitle = 'Add product';
$activeNav = 'products';
require __DIR__ . '/layout-top.php';
require __DIR__ . '/product-form.php';
?>

</body>
</html>
