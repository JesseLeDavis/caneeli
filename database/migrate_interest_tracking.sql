-- Interest tracking: which products are people looking at / adding to cart?
-- Used by /admin/insights.php.
--
-- Design notes:
--   - session_id is the PHP session id (opaque, no PII)
--   - no IP, no user agent — keep the footprint small
--   - cart_events.event_type tracks the funnel; orders is the source of truth
--     for purchases (so we don't duplicate that data)
-- Tables are append-only — no updates, just inserts on each user action.

CREATE TABLE IF NOT EXISTS product_views (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    session_id  VARCHAR(128)  DEFAULT NULL,
    referrer    VARCHAR(500)  DEFAULT NULL,
    viewed_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_viewed (product_id, viewed_at),
    INDEX idx_session         (session_id)
);

CREATE TABLE IF NOT EXISTS cart_events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    event_type  ENUM('add','remove','checkout_start') NOT NULL,
    session_id  VARCHAR(128) DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_event (product_id, event_type),
    INDEX idx_session       (session_id),
    INDEX idx_created       (created_at)
);
