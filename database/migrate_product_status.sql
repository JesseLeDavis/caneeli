-- Adds an explicit status enum to products so admin can mark items
-- sold_out / draft / archived independently of stock_qty or the `active` flag.
--
-- Mapping rules the admin UI enforces:
--   status = 'active'    → active = 1, purchasable
--   status = 'sold_out'  → active = 1, visible but not purchasable
--   status = 'draft'     → active = 0, hidden from shop
--   status = 'archived'  → active = 0, hidden (soft-deleted)
--
-- `active` is kept as a derived cache so existing shop queries keep working.

USE caneeli;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS status ENUM('active','sold_out','draft','archived')
        NOT NULL DEFAULT 'active' AFTER active,
    ADD INDEX IF NOT EXISTS idx_status (status);

-- Seed status from existing active flag (only for rows still at default).
UPDATE products SET status = 'active' WHERE active = 1 AND status = 'active';
UPDATE products SET status = 'draft'  WHERE active = 0 AND status = 'active';
