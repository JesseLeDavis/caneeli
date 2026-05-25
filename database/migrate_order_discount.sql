-- Records the discount code (if any) that was applied to each order,
-- so the admin order detail page can show the savings line.

USE caneeli;

ALTER TABLE orders
    ADD COLUMN discount_code   VARCHAR(40)    DEFAULT NULL AFTER notes,
    ADD COLUMN discount_amount DECIMAL(10,2)  DEFAULT NULL AFTER discount_code;
