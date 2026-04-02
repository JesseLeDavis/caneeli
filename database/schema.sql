-- Caneeli Designs Database Schema

CREATE DATABASE IF NOT EXISTS caneeli CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE caneeli;

-- Products
CREATE TABLE products (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(255)   NOT NULL,
    description      TEXT,
    craft_signals    JSON           DEFAULT NULL,
    price            DECIMAL(10,2)  NOT NULL,
    stock_qty        INT            NOT NULL DEFAULT 1,
    category         VARCHAR(100)   NOT NULL,
    image_path       VARCHAR(255),
    stripe_product_id VARCHAR(255),
    stripe_price_id  VARCHAR(255),
    active           TINYINT(1)     NOT NULL DEFAULT 1,
    featured         TINYINT(1)     NOT NULL DEFAULT 0,
    created_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category (category),
    INDEX idx_active   (active),
    INDEX idx_price    (price),
    INDEX idx_featured (featured)
);

-- Orders
CREATE TABLE orders (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    stripe_session_id VARCHAR(255)   NOT NULL UNIQUE,
    customer_name     VARCHAR(255),
    customer_email    VARCHAR(255)   NOT NULL,
    status            ENUM('pending', 'paid', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
    total             DECIMAL(10,2)  NOT NULL,
    created_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_email  (customer_email)
);

-- Product Images (gallery — products.image_path stays as the featured/primary image)
CREATE TABLE product_images (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT            NOT NULL,
    image_path  VARCHAR(255)   NOT NULL,
    sort_order  INT            NOT NULL DEFAULT 0,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id)
);

-- Order Items
CREATE TABLE order_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    order_id            INT            NOT NULL,
    product_id          INT,
    product_name        VARCHAR(255)   NOT NULL,
    quantity            INT            NOT NULL,
    price_at_purchase   DECIMAL(10,2)  NOT NULL,

    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Email signups (coming-soon landing page)
CREATE TABLE IF NOT EXISTS email_signups (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
