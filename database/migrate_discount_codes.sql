CREATE TABLE IF NOT EXISTS discount_codes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(40)   NOT NULL UNIQUE,
    type        ENUM('percent','flat') NOT NULL,
    value       DECIMAL(10,2) NOT NULL,
    max_uses    INT           DEFAULT NULL,
    times_used  INT           NOT NULL DEFAULT 0,
    active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (active)
);
