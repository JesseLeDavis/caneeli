-- Multi-category products.
--
-- Products used to carry exactly one category in `products.category`. This adds
-- a join table so a piece can sit under several at once (a stained glass panel
-- that's also wall decor, say).
--
-- `products.category` is deliberately KEPT and kept in sync with the first
-- assigned category. It still drives the product-page breadcrumb, the cart line
-- item, and the "See Similar Pieces" link, so nothing has to be rewritten and a
-- rollback is just "stop reading the join table".
--
-- Run:  mysql -u root -p caneeli < database/migrate_product_categories.sql

CREATE TABLE IF NOT EXISTS product_categories (
    product_id  INT           NOT NULL,
    category    VARCHAR(100)  NOT NULL,

    PRIMARY KEY (product_id, category),
    INDEX idx_pc_category (category),
    CONSTRAINT fk_pc_product FOREIGN KEY (product_id)
        REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill: every product's existing single category becomes its first
-- assignment. INSERT IGNORE makes this safe to re-run.
INSERT IGNORE INTO product_categories (product_id, category)
SELECT id, category
FROM products
WHERE category IS NOT NULL AND TRIM(category) <> '';

-- ── Post-run check ───────────────────────────────────────────────────────────
-- Chairs, Tables and Shelves were retired from the selectable list. Nothing is
-- deleted here on purpose: if any product still carries one, it stays live and
-- purchasable, and the admin filter still surfaces it for re-filing.
-- This should return zero rows. If it doesn't, those products need re-filing
-- in the admin before the retired categories disappear from the shop tabs.
SELECT id, name, category AS retired_category
FROM products
WHERE category IN ('Chairs', 'Tables', 'Shelves')
ORDER BY category, name;
