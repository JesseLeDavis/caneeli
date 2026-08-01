-- Manual shop ordering.
--
-- Adds products.sort_order so the admin can drag pieces into the order
-- customers see, instead of the shop being locked to newest-first.
--
-- Backfill starts at 1 and follows the CURRENT shop order (created_at DESC),
-- so running this changes nothing visible until someone actually drags.
--
-- New products keep the DEFAULT of 0, which places them above everything
-- backfilled — i.e. new pieces still surface at the top on their own, and
-- ties among them fall back to newest-first. That preserves today's behaviour
-- for anything Annie hasn't deliberately positioned.
--
-- NOT re-runnable: MySQL rejects IF NOT EXISTS on ADD COLUMN, so a second run
-- fails with "Duplicate column name 'sort_order'". That error is harmless — it
-- means the migration already ran.
--
-- Run:  mysql -u root -p caneeli < database/migrate_product_sort_order.sql

ALTER TABLE products ADD COLUMN sort_order INT NOT NULL DEFAULT 0;

UPDATE products p
JOIN (
    SELECT id, ROW_NUMBER() OVER (ORDER BY created_at DESC, id DESC) AS rn
    FROM products
) t ON t.id = p.id
SET p.sort_order = t.rn;

CREATE INDEX idx_sort_order ON products (sort_order);

-- Should list every product, lowest sort_order first — i.e. the exact order
-- the shop shows today.
SELECT id, sort_order, name FROM products ORDER BY sort_order, created_at DESC;
