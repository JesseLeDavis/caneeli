-- Spam filtering for the contact form (pages/contact.php).
--
-- Suspicious submissions are kept and flagged rather than thrown away, so a
-- real message that trips a check lands in the admin's Spam tab instead of
-- vanishing. `ip` powers the per-address rate limit.
--
-- Run this BEFORE deploying the matching code. (The code falls back to the
-- old columns if it's missing, but spam then lands in the normal inbox.)
--
-- NOTE: no `USE` statement on purpose (local is `caneeli`, Hostinger's
-- database is named differently). Run against the database selected in
-- phpMyAdmin. MySQL 9.6 rejects `IF NOT EXISTS` on ADD COLUMN — run once.

ALTER TABLE contact_messages
    ADD COLUMN is_spam     TINYINT(1)   NOT NULL DEFAULT 0 AFTER is_read,
    ADD COLUMN spam_reason VARCHAR(255) DEFAULT NULL       AFTER is_spam,
    ADD COLUMN ip          VARCHAR(45)  DEFAULT NULL       AFTER spam_reason,
    ADD INDEX idx_spam (is_spam),
    ADD INDEX idx_ip_created (ip, created_at);
