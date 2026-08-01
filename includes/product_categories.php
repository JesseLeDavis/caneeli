<?php
/**
 * Single source of truth for product categories.
 *
 * The `products.category` column is a plain VARCHAR, so this list is what
 * actually constrains it — the shop tabs, the admin filter dropdown, and the
 * add/edit form validation all read from here. Add a category once, here.
 *
 * Order matters: it's the order the shop filter tabs render in. Keep 'Other'
 * last.
 */

const PRODUCT_CATEGORIES = [
    'Chairs',
    'Tables',
    'Shelves',
    'Wall Decor',
    'Lighting',
    'Stained Glass',
    'Other',
];

/**
 * Categories to offer in an admin filter dropdown: the canonical list, plus
 * any value already sitting in the DB that isn't on it. Without the union, a
 * product saved under a since-renamed category would become unfilterable.
 *
 * @param string[] $used Distinct category values from the products table.
 * @return string[]
 */
function product_filter_categories(array $used): array {
    $extra = array_diff($used, PRODUCT_CATEGORIES);
    sort($extra);
    return array_merge(PRODUCT_CATEGORIES, $extra);
}
