-- Run this once to add multi-image support
-- Safe to run on an existing database — does not modify the products table

CREATE TABLE IF NOT EXISTS product_images (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT            NOT NULL,
    image_path  VARCHAR(255)   NOT NULL,
    sort_order  INT            NOT NULL DEFAULT 0,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id)
);

-- Backfill existing products: copy their current image_path into the gallery table
-- so old products show up in the gallery on the product page
INSERT IGNORE INTO product_images (product_id, image_path, sort_order)
SELECT id, image_path, 0
FROM products
WHERE image_path IS NOT NULL
  AND id NOT IN (SELECT product_id FROM product_images);
