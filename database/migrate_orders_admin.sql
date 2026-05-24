-- Adds fields needed by the admin orders page and fulfillment flow.

USE caneeli;

ALTER TABLE orders
    ADD COLUMN shipping_address      TEXT         DEFAULT NULL AFTER total,
    ADD COLUMN stripe_payment_intent VARCHAR(255) DEFAULT NULL AFTER stripe_session_id,
    ADD COLUMN fulfilled_at          TIMESTAMP    NULL DEFAULT NULL AFTER updated_at;
