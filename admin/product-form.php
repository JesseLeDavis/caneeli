<?php
/**
 * The product editor, shared by add-product.php and edit-product.php.
 *
 * Expects:
 *   $mode            'add' or 'edit'
 *   $v               field values: name, description, price, stock_qty, status,
 *                    preorder_ship_date, preorder_deposit
 *   $checked_cats    categories to tick
 *   $checked_signals craft signals to tick (presets and custom)
 *   $categories, $preset_signals
 *   edit only: $product, $gallery, $retired_cats
 *
 * Photo actions on existing images (feature, delete) are separate forms at
 * the bottom of the page, reached with the form="" attribute, because forms
 * can't nest inside the main one.
 */
$isEdit   = $mode === 'edit';
$formId   = 'product-form';
$gallery  = $gallery ?? [];
$status   = $v['status'] ?: 'active';
$statusHelp = [
    'active'   => 'Visible in the shop and for sale.',
    'preorder' => 'Visible in the shop. Made to order and ships by a date you set.',
    'sold_out' => 'Visible in the shop, but can\'t be bought.',
    'draft'    => 'Hidden from the shop while you work on it.',
    'archived' => 'Hidden from the shop and kept for your records.',
];
$custom_signals = array_values(array_diff($checked_signals, $preset_signals));
?>

<div class="container product-editor">
    <div class="product-editor__head">
        <div>
            <a class="product-editor__back" href="/admin/products.php">← Products</a>
            <h1><?php echo $isEdit ? htmlspecialchars($v['name'] ?: 'Untitled product') : 'New product'; ?></h1>
            <?php if ($isEdit): ?>
                <span class="badge-status badge-status--<?php echo htmlspecialchars($product['status']); ?>"><?php echo product_status_label($product['status']); ?></span>
            <?php endif; ?>
        </div>
        <div class="product-editor__actions">
            <?php if ($isEdit && product_status_is_visible($product['status'])): ?>
                <a class="btn btn-secondary" href="/pages/shop/product.php?id=<?php echo (int) $product['id']; ?>" target="_blank" rel="noopener">View in shop</a>
            <?php endif; ?>
            <button type="submit" form="<?php echo $formId; ?>" class="btn btn-primary"><?php echo $isEdit ? 'Save changes' : 'Add product'; ?></button>
        </div>
    </div>

    <?php if (!empty($error)): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
    <?php if (!empty($success)): ?><p class="success"><?php echo $success; ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="<?php echo $formId; ?>" class="product-editor__grid">
        <?php echo csrf_field(); ?>

        <div class="product-editor__main">
            <section class="pe-card pe-card--details">
                <h2 class="pe-card__title">Details</h2>
                <label for="pe-name">Name</label>
                <input type="text" id="pe-name" name="name" required maxlength="255" value="<?php echo htmlspecialchars($v['name']); ?>" placeholder="Salmon Lava Lamp">

                <label for="pe-desc">Description</label>
                <textarea id="pe-desc" name="description" rows="7" placeholder="What it's made of, its size, the bulb it takes…"><?php echo htmlspecialchars($v['description']); ?></textarea>
                <p class="pe-hint">A blank line starts a new paragraph on the product page. Single line breaks are kept, so specs can go one per line.</p>
            </section>

            <section class="pe-card pe-card--photos">
                <div class="pe-card__head">
                    <h2 class="pe-card__title">Photos</h2>
                    <?php if (count($gallery) > 1): ?><span class="pe-card__aside">Drag to reorder</span><?php endif; ?>
                </div>

                <?php if ($gallery): ?>
                <div class="gallery-grid" id="gallery-grid" data-product-id="<?php echo (int) $product['id']; ?>">
                    <?php foreach ($gallery as $img):
                        $is_featured = ($product['image_path'] === $img['image_path']); ?>
                        <div class="gallery-tile <?php echo $is_featured ? 'gallery-tile--featured' : ''; ?>" data-image-id="<?php echo (int) $img['id']; ?>">
                            <div class="gallery-tile__img-wrap">
                                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
                                <?php if ($is_featured): ?><span class="gallery-tile__badge">Main photo</span><?php endif; ?>
                            </div>
                            <div class="gallery-tile__actions">
                                <?php if (!$is_featured): ?>
                                    <button type="submit" form="img-feature-<?php echo (int) $img['id']; ?>" class="gallery-tile__btn gallery-tile__btn--feature">Make main</button>
                                <?php endif; ?>
                                <button type="submit" form="img-delete-<?php echo (int) $img['id']; ?>" class="gallery-tile__btn gallery-tile__btn--delete" aria-label="Delete this photo">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <label class="pe-drop" data-drop>
                    <input type="file" name="<?php echo $isEdit ? 'new_images[]' : 'images[]'; ?>" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-drop-input>
                    <span class="pe-drop__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                    </span>
                    <span class="pe-drop__text"><strong><?php echo $gallery ? 'Add more photos' : 'Add photos'; ?></strong>: drop them here, or click to choose</span>
                    <span class="pe-drop__sub">JPG, PNG, WebP or GIF, up to 10 MB each.<?php echo $gallery ? '' : ' The first one becomes the main photo.'; ?></span>
                </label>
                <div class="pe-drop__previews" data-drop-previews hidden></div>
            </section>

            <section class="pe-card pe-card--signals">
                <div class="pe-card__head">
                    <h2 class="pe-card__title">Craft signals</h2>
                    <span class="pe-card__aside"><span data-signal-count><?php echo count($checked_signals); ?></span> of 5</span>
                </div>
                <p class="pe-hint pe-hint--top">Short tags shown under the price on the product page.</p>
                <div class="pe-chips" data-signals>
                    <?php foreach ($preset_signals as $tag): ?>
                        <label class="pe-chip">
                            <input type="checkbox" name="craft_signals_preset[]" value="<?php echo htmlspecialchars($tag); ?>" <?php echo in_array($tag, $checked_signals, true) ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($tag); ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($custom_signals as $tag): ?>
                        <label class="pe-chip">
                            <input type="checkbox" name="craft_signals_preset[]" value="<?php echo htmlspecialchars($tag); ?>" checked>
                            <span><?php echo htmlspecialchars($tag); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label for="pe-custom" class="pe-sublabel">Your own tag</label>
                <input type="text" id="pe-custom" name="craft_signal_custom" maxlength="40" placeholder="e.g. Hand-sculpted stoneware">
            </section>
        </div>

        <aside class="product-editor__side">
            <section class="pe-card pe-card--status">
                <h2 class="pe-card__title">Status</h2>
                <label for="pe-status" class="visually-hidden">Status</label>
                <select id="pe-status" name="status" data-status>
                    <?php foreach (PRODUCT_STATUSES as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo product_status_label($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="pe-hint" data-status-help><?php echo $statusHelp[$status] ?? ''; ?></p>

                <div class="pe-preorder" data-preorder-fields <?php echo $status === 'preorder' ? '' : 'hidden'; ?>>
                    <label for="preorder_ship_date">Ships by</label>
                    <input type="date" name="preorder_ship_date" id="preorder_ship_date" value="<?php echo htmlspecialchars((string) ($v['preorder_ship_date'] ?? '')); ?>">
                    <p class="pe-hint">Buyers agree to this date. If you need to move it, use the Pre-orders page so they're told.</p>

                    <label for="preorder_deposit">Deposit per piece <span class="pe-optional">optional</span></label>
                    <div class="pe-money"><span>$</span><input type="number" name="preorder_deposit" id="preorder_deposit" step="0.01" min="0" placeholder="0.00" value="<?php echo htmlspecialchars((string) ($v['preorder_deposit'] ?? '')); ?>" data-deposit></div>
                    <p class="pe-hint" data-deposit-help>Leave blank to charge the full price up front.</p>
                </div>
            </section>

            <section class="pe-card pe-card--price">
                <h2 class="pe-card__title">Price and stock</h2>
                <div class="pe-pair">
                    <div>
                        <label for="pe-price">Price</label>
                        <div class="pe-money"><span>$</span><input type="number" id="pe-price" name="price" step="0.01" min="0" required placeholder="0.00" value="<?php echo htmlspecialchars((string) $v['price']); ?>" data-price></div>
                    </div>
                    <div>
                        <label for="pe-stock" data-stock-label><?php echo $status === 'preorder' ? 'Spots' : 'In stock'; ?></label>
                        <input type="number" id="pe-stock" name="stock_qty" min="0" value="<?php echo htmlspecialchars((string) $v['stock_qty']); ?>">
                    </div>
                </div>
                <p class="pe-hint" data-stock-help><?php echo $status === 'preorder'
                    ? 'Spots left in the batch. At 0 the page shows "Pre-orders full".'
                    : 'Shipping is included in the price. At 0 the piece shows as sold.'; ?></p>
            </section>

            <section class="pe-card pe-card--cats">
                <h2 class="pe-card__title">Categories</h2>
                <p class="pe-hint pe-hint--top">Pick one or more. The first one ticked is the main category.</p>
                <div class="pe-chips pe-chips--stack">
                    <?php foreach ($categories as $cat): ?>
                        <label class="pe-chip">
                            <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>" <?php echo in_array($cat, $checked_cats, true) ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($cat); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($retired_cats)): ?>
                    <p class="category-retired">Also filed under <strong><?php echo htmlspecialchars(implode(', ', $retired_cats)); ?></strong>, which isn't used any more. Saving removes it.</p>
                <?php endif; ?>
            </section>
        </aside>

        <div class="product-editor__savebar">
            <a href="/admin/products.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save changes' : 'Add product'; ?></button>
        </div>
    </form>

    <?php foreach ($gallery as $img): ?>
        <form method="POST" action="/admin/set-featured-image.php" id="img-feature-<?php echo (int) $img['id']; ?>" hidden>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="image_id" value="<?php echo (int) $img['id']; ?>">
            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
        </form>
        <form method="POST" action="/admin/delete-product-image.php" id="img-delete-<?php echo (int) $img['id']; ?>" hidden onsubmit="return confirm('Delete this photo?')">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="image_id" value="<?php echo (int) $img['id']; ?>">
            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
        </form>
    <?php endforeach; ?>
</div>

<script>
(function () {
    var help = <?php echo json_encode($statusHelp); ?>;
    var status = document.querySelector('[data-status]');
    var pre = document.querySelector('[data-preorder-fields]');
    var statusHelp = document.querySelector('[data-status-help]');
    var stockLabel = document.querySelector('[data-stock-label]');
    var stockHelp = document.querySelector('[data-stock-help]');
    function syncStatus() {
        var isPre = status.value === 'preorder';
        pre.hidden = !isPre;
        statusHelp.textContent = help[status.value] || '';
        stockLabel.textContent = isPre ? 'Spots' : 'In stock';
        stockHelp.textContent = isPre
            ? 'Spots left in the batch. At 0 the page shows "Pre-orders full".'
            : 'Shipping is included in the price. At 0 the piece shows as sold.';
        document.getElementById('preorder_ship_date').required = isPre;
    }
    status.addEventListener('change', syncStatus); syncStatus();

    // Deposit: show what's left to pay
    var price = document.querySelector('[data-price]');
    var dep = document.querySelector('[data-deposit]');
    var depHelp = document.querySelector('[data-deposit-help]');
    function syncDeposit() {
        var p = parseFloat(price.value) || 0, d = parseFloat(dep.value) || 0;
        if (!d) { depHelp.textContent = 'Leave blank to charge the full price up front.'; depHelp.classList.remove('pe-hint--warn'); return; }
        if (d >= p) { depHelp.textContent = 'The deposit has to be less than the price.'; depHelp.classList.add('pe-hint--warn'); return; }
        depHelp.classList.remove('pe-hint--warn');
        depHelp.textContent = 'Buyers pay $' + d.toFixed(2) + ' now and $' + (p - d).toFixed(2) + ' when you request balances.';
    }
    price.addEventListener('input', syncDeposit); dep.addEventListener('input', syncDeposit); syncDeposit();

    // Craft signals: at most 5
    var box = document.querySelector('[data-signals]');
    var count = document.querySelector('[data-signal-count]');
    function syncSignals() {
        var boxes = box.querySelectorAll('input'), n = box.querySelectorAll('input:checked').length;
        count.textContent = n;
        boxes.forEach(function (b) { b.disabled = !b.checked && n >= 5; });
    }
    box.addEventListener('change', syncSignals); syncSignals();

    // Photos: drag and drop, with previews of what's about to upload
    var drop = document.querySelector('[data-drop]');
    var input = document.querySelector('[data-drop-input]');
    var previews = document.querySelector('[data-drop-previews]');
    function showPreviews() {
        previews.innerHTML = '';
        Array.prototype.forEach.call(input.files, function (f) {
            if (!/^image\//.test(f.type)) return;
            var fig = document.createElement('figure');
            var img = document.createElement('img');
            img.src = URL.createObjectURL(f); img.alt = '';
            var cap = document.createElement('figcaption'); cap.textContent = f.name;
            fig.appendChild(img); fig.appendChild(cap); previews.appendChild(fig);
        });
        previews.hidden = !input.files.length;
    }
    input.addEventListener('change', showPreviews);
    ['dragenter', 'dragover'].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (e) { drop.addEventListener(e, function () { drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (ev) {
        ev.preventDefault();
        if (ev.dataTransfer && ev.dataTransfer.files.length) { input.files = ev.dataTransfer.files; showPreviews(); }
    });
})();
</script>
