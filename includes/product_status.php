<?php
/**
 * Single source of truth for product status → visibility rules.
 * Keep this file in sync with the admin UI copies.
 */

const PRODUCT_STATUSES = ['active', 'preorder', 'sold_out', 'draft', 'archived'];

function product_status_label(string $status): string {
    return [
        'active'   => 'Active',
        'preorder' => 'Pre-order',
        'sold_out' => 'Sold Out',
        'draft'    => 'Draft',
        'archived' => 'Archived',
    ][$status] ?? ucfirst($status);
}

/** Whether a product with this status should be visible in the shop at all. */
function product_status_is_visible(string $status): bool {
    return in_array($status, ['active', 'sold_out', 'preorder'], true);
}

/**
 * Whether a product with this status + stock can be added to the cart.
 * Pre-orders never go in the cart; they check out on their own
 * (see preorder-checkout.php).
 */
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

/** A pre-order product that still has spots left in the batch. */
function product_is_preorderable(array $product): bool {
    return ($product['status'] ?? '') === 'preorder' && (int) ($product['stock_qty'] ?? 0) > 0;
}

/** "Jan 31" style ship-by date, or null if none is set. */
function preorder_ship_label(?string $date, string $format = 'M j'): ?string {
    if (!$date) return null;
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : null;
}
