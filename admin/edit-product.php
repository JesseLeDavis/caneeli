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
                    category = ?, active = ?, status = ?, preorder_ship_date = ?, preorder_deposit = ?
                WHERE id = ?
            ")->execute([$name, $description, $craft_signals_json, $price, $stock_qty, $selected_cats[0], $active, $status, $ship_date, $deposit, $id]);

            save_product_categories($pdo, $id, $selected_cats);

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

// Current category assignments. Read after the POST block so a just-saved
// change is reflected. May include a retired category the product still holds.
$product_cats = product_categories_for($pdo, $id);
$retired_cats = array_values(array_diff($product_cats, PRODUCT_CATEGORIES));

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
<?php
// After a failed save, show what was typed rather than the saved values.
$failed = $_SERVER['REQUEST_METHOD'] === 'POST' && $error;
$v = $failed ? [
    'name' => $_POST['name'] ?? '', 'description' => $_POST['description'] ?? '',
    'price' => $_POST['price'] ?? '', 'stock_qty' => $_POST['stock_qty'] ?? '',
    'status' => $_POST['status'] ?? $product['status'],
    'preorder_ship_date' => $_POST['preorder_ship_date'] ?? '', 'preorder_deposit' => $_POST['preorder_deposit'] ?? '',
] : $product;
$checked_cats    = $failed ? sanitize_product_categories((array) ($_POST['categories'] ?? [])) : $product_cats;
$checked_signals = $failed ? array_values((array) ($_POST['craft_signals_preset'] ?? [])) : $current_signals;
$mode = 'edit';

$pageTitle = 'Edit product';
$activeNav = 'products';
require __DIR__ . '/layout-top.php';
require __DIR__ . '/product-form.php';
?>

<?php if ($gallery && count($gallery) > 1): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const grid = document.getElementById('gallery-grid');
    if (!grid || typeof Sortable === 'undefined') return;

    const csrfToken = <?php echo json_encode(csrf_token()); ?>;
    const productId = grid.dataset.productId;

    function showToast(msg, isError) {
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText =
            'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);' +
            'background:' + (isError ? '#e53e3e' : '#2D2D2D') + ';color:#F2ECDE;' +
            'padding:10px 18px;border-radius:30px;font-size:13px;font-weight:600;' +
            'box-shadow:0 4px 20px rgba(0,0,0,0.2);z-index:1000;';
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 1500);
    }

    Sortable.create(grid, {
        animation: 180,
        ghostClass: 'gallery-tile--ghost',
        chosenClass: 'gallery-tile--chosen',
        dragClass:   'gallery-tile--drag',
        onEnd: function () {
            const ids = Array.from(grid.querySelectorAll('.gallery-tile'))
                .map(el => el.dataset.imageId);
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('product_id', productId);
            ids.forEach(id => fd.append('order[]', id));
            fetch('/admin/reorder-images.php', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch' }
            })
                .then(r => r.json())
                .then(d => showToast(d.ok ? 'Order saved' : 'Save failed', !d.ok))
                .catch(() => showToast('Save failed', true));
        }
    });
})();
</script>
<?php endif; ?>

</body>
</html>
