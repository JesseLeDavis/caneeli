-- Adds fields needed by the admin orders page and fulfillment flow.
-- Safe to run once; MySQL 8+ supports IF NOT EXISTS on ADD COLUMN.

USE caneeli;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS shipping_address     TEXT         DEFAULT NULL AFTER total,
    ADD COLUMN IF NOT EXISTS stripe_payment_intent VARCHAR(255) DEFAULT NULL AFTER stripe_session_id,
    ADD COLUMN IF NOT EXISTS fulfilled_at         TIMESTAMP    NULL DEFAULT NULL AFTER updated_at;
