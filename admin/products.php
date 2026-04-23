<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/product_status.php';

$pdo = getDB();

// ── Filters / sort / search ─────────────────────────────────────────────
$q           = trim($_GET['q']        ?? '');
$status_f    = $_GET['status']        ?? 'all';
$category_f  = $_GET['category']      ?? 'all';
$sort        = $_GET['sort']          ?? 'newest';

$allowed_sort = [
    'newest'      => 'created_at DESC',
    'oldest'      => 'created_at ASC',
    'name_asc'    => 'name ASC',
    'name_desc'   => 'name DESC',
    'price_asc'   => 'price ASC',
    'price_desc'  => 'price DESC',
    'stock_asc'   => 'stock_qty ASC',
    'stock_desc'  => 'stock_qty DESC',
];
$order_by = $allowed_sort[$sort] ?? $allowed_sort['newest'];

$where   = [];
$params  = [];

if ($status_f !== 'all' && in_array($status_f, PRODUCT_STATUSES, true)) {
    $where[]   = 'status = ?';
    $params[]  = $status_f;
}
if ($category_f !== 'all' && $category_f !== '') {
    $where[]   = 'category = ?';
    $params[]  = $category_f;
}
if ($q !== '') {
    $where[]   = '(name LIKE ? OR description LIKE ?)';
    $params[]  = '%' . $q . '%';
    $params[]  = '%' . $q . '%';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT * FROM products $where_sql ORDER BY $order_by";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$all_categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Status counts (ignoring other filters so the tabs show total of each)
$status_counts = array_fill_keys(PRODUCT_STATUSES, 0);
$status_counts['all'] = 0;
foreach ($pdo->query("SELECT status, COUNT(*) c FROM products GROUP BY status") as $r) {
    $status_counts[$r['status']] = (int) $r['c'];
    $status_counts['all']       += (int) $r['c'];
}

// Flash message from redirects
$flash = $_GET['msg'] ?? '';

$pageTitle = 'Products';
$activeNav = 'products';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Products (<?php echo $status_counts['all']; ?>)</h1>
        <a href="/admin/add-product.php" class="btn btn-primary">+ Add Product</a>
    </div>

    <?php if ($flash): ?>
        <p class="success"><?php echo htmlspecialchars($flash); ?></p>
    <?php endif; ?>

    <!-- Status tabs -->
    <div class="status-tabs">
        <?php foreach (['all', 'active', 'sold_out', 'draft', 'archived'] as $s): ?>
            <?php
                $query = $_GET;
                $query['status'] = $s;
                $href = '/admin/products.php?' . http_build_query($query);
            ?>
            <a href="<?php echo htmlspecialchars($href); ?>" class="status-tab <?php echo $status_f === $s ? 'is-active' : ''; ?>">
                <?php echo $s === 'all' ? 'All' : product_status_label($s); ?>
                <span class="status-tab__count"><?php echo $status_counts[$s] ?? 0; ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter bar -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_f); ?>">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search name or description…" class="filter-bar__search">
        <select name="category" class="filter-bar__select">
            <option value="all">All categories</option>
            <?php foreach ($all_categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_f === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="sort" class="filter-bar__select">
            <option value="newest"     <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
            <option value="oldest"     <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
            <option value="name_asc"   <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A–Z</option>
            <option value="name_desc"  <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z–A</option>
            <option value="price_asc"  <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price low–high</option>
            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price high–low</option>
            <option value="stock_asc"  <?php echo $sort === 'stock_asc' ? 'selected' : ''; ?>>Stock low–high</option>
            <option value="stock_desc" <?php echo $sort === 'stock_desc' ? 'selected' : ''; ?>>Stock high–low</option>
        </select>
        <button type="submit" class="btn btn-secondary" style="padding:10px 18px">Apply</button>
        <?php if ($q || $category_f !== 'all' || $sort !== 'newest'): ?>
            <a href="/admin/products.php?status=<?php echo htmlspecialchars($status_f); ?>" class="filter-bar__clear">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($products)): ?>
        <p style="margin-top:20px">No products match these filters.</p>
    <?php else: ?>

    <form method="POST" action="/admin/bulk-action.php" id="bulk-form">
        <?php echo csrf_field(); ?>

        <!-- Bulk action bar (appears when anything is selected) -->
        <div class="bulk-bar" id="bulk-bar" aria-hidden="true">
            <span class="bulk-bar__count"><span id="bulk-count">0</span> selected</span>
            <select name="action" required>
                <option value="">Bulk action…</option>
                <option value="status_active">Mark as Active</option>
                <option value="status_sold_out">Mark as Sold Out</option>
                <option value="status_draft">Mark as Draft</option>
                <option value="status_archived">Archive</option>
                <option value="feature">Feature</option>
                <option value="unfeature">Unfeature</option>
                <option value="delete">Delete permanently</option>
            </select>
            <button type="submit" class="btn btn-secondary" style="padding:8px 16px">Apply</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:30px"><input type="checkbox" id="bulk-select-all" aria-label="Select all"></th>
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
                <tr class="product-row product-row--<?php echo htmlspecialchars($product['status']); ?>">
                    <td><input type="checkbox" name="ids[]" value="<?php echo $product['id']; ?>" class="bulk-check"></td>
                    <td>
                        <?php if ($product['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="">
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:600"><?php echo htmlspecialchars($product['name']); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                    <td>
                        <input type="number"
                               step="0.01" min="0"
                               value="<?php echo $product['price']; ?>"
                               class="inline-edit"
                               data-id="<?php echo $product['id']; ?>"
                               data-field="price">
                    </td>
                    <td>
                        <input type="number"
                               min="0"
                               value="<?php echo $product['stock_qty']; ?>"
                               class="inline-edit inline-edit--stock"
                               data-id="<?php echo $product['id']; ?>"
                               data-field="stock_qty">
                    </td>
                    <td>
                        <select class="inline-edit inline-status"
                                data-id="<?php echo $product['id']; ?>"
                                data-field="status">
                            <?php foreach (PRODUCT_STATUSES as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $product['status'] === $s ? 'selected' : ''; ?>>
                                    <?php echo product_status_label($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button type="button"
                                class="btn-star <?php echo $product['featured'] ? 'btn-star--on' : ''; ?>"
                                data-id="<?php echo $product['id']; ?>"
                                data-toggle-featured
                                title="<?php echo $product['featured'] ? 'Remove from Hot Off the Shelf' : 'Add to Hot Off the Shelf'; ?>">
                            <?php echo $product['featured'] ? '★' : '☆'; ?>
                        </button>
                    </td>
                    <td>
                        <div class="td-actions">
                            <a href="/admin/edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary" style="padding:8px 14px;font-size:12px">Edit</a>
                            <a href="/admin/duplicate-product.php?id=<?php echo $product['id']; ?>"
                               class="btn btn-secondary"
                               style="padding:8px 14px;font-size:12px"
                               onclick="return confirm('Duplicate this product?')">Duplicate</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    <?php endif; ?>
</div>

<script>
(function () {
    // ── Bulk select ──────────────────────────────────────────────────────
    const form    = document.getElementById('bulk-form');
    if (!form) return;
    const bar     = document.getElementById('bulk-bar');
    const count   = document.getElementById('bulk-count');
    const all     = document.getElementById('bulk-select-all');
    const checks  = form.querySelectorAll('.bulk-check');

    function refreshBar() {
        const n = form.querySelectorAll('.bulk-check:checked').length;
        count.textContent = n;
        bar.classList.toggle('is-visible', n > 0);
    }
    all.addEventListener('change', function () {
        checks.forEach(c => c.checked = all.checked);
        refreshBar();
    });
    checks.forEach(c => c.addEventListener('change', refreshBar));
    form.addEventListener('submit', function (e) {
        const n = form.querySelectorAll('.bulk-check:checked').length;
        const action = form.querySelector('[name=action]').value;
        if (!n || !action) {
            e.preventDefault();
            return;
        }
        if (action === 'delete' && !confirm('Delete ' + n + ' product(s) permanently? This cannot be undone.')) {
            e.preventDefault();
        }
    });

    // ── Inline edit (price / stock / status) ─────────────────────────────
    const csrfToken = <?php echo json_encode(csrf_token()); ?>;

    function saveField(el) {
        const id = el.dataset.id;
        const field = el.dataset.field;
        const value = el.value;

        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('id', id);
        fd.append('field', field);
        fd.append('value', value);

        el.classList.add('is-saving');
        fetch('/admin/inline-update.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(r => r.json())
            .then(data => {
                el.classList.remove('is-saving');
                if (data.ok) {
                    el.classList.add('is-saved');
                    setTimeout(() => el.classList.remove('is-saved'), 900);
                    // If status was updated, reflect it on the row tint.
                    if (field === 'status') {
                        const row = el.closest('tr');
                        row.className = row.className.replace(/product-row--\w+/, 'product-row--' + value);
                    }
                } else {
                    el.classList.add('is-error');
                    setTimeout(() => el.classList.remove('is-error'), 1500);
                }
            })
            .catch(() => {
                el.classList.remove('is-saving');
                el.classList.add('is-error');
                setTimeout(() => el.classList.remove('is-error'), 1500);
            });
    }

    document.querySelectorAll('.inline-edit').forEach(function (el) {
        if (el.tagName === 'SELECT') {
            el.addEventListener('change', function () { saveField(el); });
        } else {
            // Debounce numeric fields — save on blur + on Enter
            el.addEventListener('blur',   function () { saveField(el); });
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
            });
        }
    });

    // ── Star toggle (AJAX) ───────────────────────────────────────────────
    document.querySelectorAll('[data-toggle-featured]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.dataset.id;
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('id', id);
            fetch('/admin/toggle-featured.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch' }
            })
                .then(() => {
                    btn.classList.toggle('btn-star--on');
                    btn.textContent = btn.classList.contains('btn-star--on') ? '★' : '☆';
                });
        });
    });
})();
</script>

</body>
</html>
