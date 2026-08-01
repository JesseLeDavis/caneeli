<?php
/**
 * Single source of truth for product categories.
 *
 * A product can belong to several categories at once. The assignments live in
 * the `product_categories` join table; `products.category` is kept in sync with
 * the FIRST assigned category and acts as the "primary" one — it still drives
 * the product-page breadcrumb, the cart line item, and "See Similar Pieces".
 *
 * Order matters: it's the order the shop filter tabs and the admin checkboxes
 * render in, and the first match is what becomes a product's primary category.
 * Keep 'Other' last.
 */

const PRODUCT_CATEGORIES = [
    'Wall Decor',
    'Lighting',
    'Stained Glass',
    'Other',
];

/**
 * Retired from the selectable list, but not deleted from existing products.
 * Kept here so the admin can label a stale assignment instead of showing it as
 * an ordinary category.
 */
const RETIRED_PRODUCT_CATEGORIES = ['Chairs', 'Tables', 'Shelves'];

/**
 * Categories to offer in an admin filter dropdown: the canonical list, plus any
 * value already sitting in the DB that isn't on it. Without the union, a
 * product under a retired category would become unfilterable — i.e. invisible
 * to the person who needs to re-file it.
 *
 * @param string[] $used Distinct category values currently in use.
 * @return string[]
 */
function product_filter_categories(array $used): array {
    $extra = array_values(array_diff($used, PRODUCT_CATEGORIES));
    sort($extra);
    return array_merge(PRODUCT_CATEGORIES, $extra);
}

/**
 * Reduce submitted checkbox values to a valid, deduped set in canonical order.
 * Anything not on the canonical list is dropped, so a hand-crafted POST can't
 * invent a category.
 *
 * @return string[] Possibly empty — callers must treat that as a validation error.
 */
function sanitize_product_categories(array $input): array {
    return array_values(array_intersect(PRODUCT_CATEGORIES, $input));
}

/** All categories assigned to one product, in canonical order. */
function product_categories_for(PDO $pdo, int $product_id): array {
    $stmt = $pdo->prepare("SELECT category FROM product_categories WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return order_product_categories($rows);
}

/**
 * Categories for many products at once, as [product_id => string[]]. Used by
 * list views so rendering N products doesn't fire N queries.
 *
 * @param int[] $product_ids
 */
function product_categories_for_many(PDO $pdo, array $product_ids): array {
    if (!$product_ids) return [];

    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT product_id, category FROM product_categories WHERE product_id IN ($placeholders)");
    $stmt->execute($product_ids);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['product_id']][] = $row['category'];
    }
    foreach ($map as $id => $cats) {
        $map[$id] = order_product_categories($cats);
    }
    return $map;
}

/**
 * Canonical order first, then anything retired/unknown alphabetically, so a
 * retired assignment sorts last instead of disappearing.
 */
function order_product_categories(array $cats): array {
    $known = array_values(array_intersect(PRODUCT_CATEGORIES, $cats));
    $rest  = array_values(array_diff($cats, PRODUCT_CATEGORIES));
    sort($rest);
    return array_merge($known, $rest);
}

/**
 * Replace a product's category assignments and resync its primary category.
 *
 * Wrapped in a transaction: a product left with zero categories would vanish
 * from every filter at once, so the delete and the re-insert have to land
 * together.
 *
 * @param string[] $categories Already passed through sanitize_product_categories().
 */
function save_product_categories(PDO $pdo, int $product_id, array $categories): void {
    $categories = order_product_categories($categories);

    $own_transaction = !$pdo->inTransaction();
    if ($own_transaction) $pdo->beginTransaction();

    try {
        $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$product_id]);

        $ins = $pdo->prepare("INSERT INTO product_categories (product_id, category) VALUES (?, ?)");
        foreach ($categories as $cat) {
            $ins->execute([$product_id, $cat]);
        }

        // Keep the legacy single-category column pointing at the primary.
        $pdo->prepare("UPDATE products SET category = ? WHERE id = ?")
            ->execute([$categories[0] ?? '', $product_id]);

        if ($own_transaction) $pdo->commit();
    } catch (\Throwable $e) {
        if ($own_transaction) $pdo->rollBack();
        throw $e;
    }
}

/**
 * SQL fragment matching products assigned to a given category. EXISTS rather
 * than a JOIN so a multi-category product isn't returned twice.
 *
 * @param string $products_alias Table/alias holding the product id.
 */
function product_category_exists_sql(string $products_alias = 'products'): string {
    return "EXISTS (SELECT 1 FROM product_categories pc
                    WHERE pc.product_id = {$products_alias}.id AND pc.category = ?)";
}
