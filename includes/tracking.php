<?php
/**
 * Lightweight interest tracking.
 * Writes are fire-and-forget: any DB error is swallowed so the shop stays up
 * even if the tracking tables are missing or locked.
 *
 * Session cookie is the identity we use — no IP, no user agent.
 */

require_once __DIR__ . '/db.php';

/** Record that someone viewed a product. Call from product detail page. */
function track_product_view(int $product_id, ?string $referrer = null): void {
    if (!$product_id) return;
    try {
        $sid = session_id() ?: null;
        $stmt = getDB()->prepare("
            INSERT INTO product_views (product_id, session_id, referrer)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$product_id, $sid, $referrer ? mb_substr($referrer, 0, 500) : null]);
    } catch (\Throwable $e) {
        // swallow — tracking failures must not break the shop
    }
}

/** Record a cart funnel event. event_type: 'add' | 'remove' | 'checkout_start'. */
function track_cart_event(int $product_id, string $event_type): void {
    if (!$product_id) return;
    if (!in_array($event_type, ['add', 'remove', 'checkout_start'], true)) return;
    try {
        $sid = session_id() ?: null;
        $stmt = getDB()->prepare("
            INSERT INTO cart_events (product_id, event_type, session_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$product_id, $event_type, $sid]);
    } catch (\Throwable $e) {
        // swallow
    }
}
