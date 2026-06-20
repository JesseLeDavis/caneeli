-- Tracks when transactional emails were sent for an order, so we never
-- double-send. Confirmation fires from webhook.php; the shipping email fires
-- from the admin "Mark as fulfilled" flow when a tracking number is saved.
--
-- NOTE: MySQL 9.6 rejects `IF NOT EXISTS` on ADD COLUMN — run this once.

USE caneeli;

ALTER TABLE orders
    ADD COLUMN confirmation_email_sent_at TIMESTAMP NULL DEFAULT NULL AFTER fulfilled_at,
    ADD COLUMN shipping_email_sent_at     TIMESTAMP NULL DEFAULT NULL AFTER confirmation_email_sent_at;
