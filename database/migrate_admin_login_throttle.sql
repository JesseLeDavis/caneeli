-- Rate-limits failed logins at /admin. Before this, the login form accepted
-- unlimited password attempts, which (with .env no longer exposed and the
-- file leaks closed) was the most realistic remaining way into the site.
--
-- Rows are pruned automatically on write, so this table stays small — it is
-- a sliding window of recent failures, not an audit log.
--
-- NOTE: no `USE` statement here on purpose. Local dev is `caneeli` but
-- Hostinger's database is `u891906320_8GdFY`; hardcoding either breaks the
-- other. Run this against the database already selected in phpMyAdmin.

CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,          -- 45 chars fits IPv6
    attempted_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at),
    INDEX idx_time (attempted_at)
);
