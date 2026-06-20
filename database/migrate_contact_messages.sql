-- Stores contact-form submissions so Annie reads them in the admin panel
-- instead of relying on email delivery (PHP mail() was unreliable on shared
-- hosting). Viewed at /admin/messages.php.

USE caneeli;

CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_read (is_read),
    INDEX idx_created (created_at)
);
