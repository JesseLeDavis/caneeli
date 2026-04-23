<?php
/**
 * Single source of truth for product status → visibility rules.
 * Keep this file in sync with the admin UI copies.
 */

const PRODUCT_STATUSES = ['active', 'sold_out', 'draft', 'archived'];

function product_status_label(string $status): string {
    return [
        'active'   => 'Active',
        'sold_out' => 'Sold Out',
        'draft'    => 'Draft',
        'archived' => 'Archived',
    ][$status] ?? ucfirst($status);
}

/** Whether a product with this status should be visible in the shop at all. */
function product_status_is_visible(string $status): bool {
    return in_array($status, ['active', 'sold_out'], true);
}

/** Whether a product with this status + stock can be added to the cart. */
function product_is_purchasable(array $product): bool {
    $status = $product['status'] ?? 'active';
    $stock  = (int) ($product['stock_qty'] ?? 0);
    return $status === 'active' && $stock > 0;
}

/**
 * Returns the `active` flag value that should accompany a given status.
 * Callers should write both columns to keep the legacy `active` queries working.
 */
function product_active_for_status(string $status): int {
    return product_status_is_visible($status) ? 1 : 0;
}
