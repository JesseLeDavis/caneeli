-- Adds tracking number + freeform notes to orders so Annie can record
-- shipment info and internal comments from the admin order detail page.

USE caneeli;

ALTER TABLE orders
    ADD COLUMN tracking_number VARCHAR(255) DEFAULT NULL AFTER shipping_address,
    ADD COLUMN notes           TEXT         DEFAULT NULL AFTER tracking_number;
