-- Lets Annie reply to a contact message from inside the admin (sent via SMTP).
-- We keep a record of the reply she sent and when, shown back on the message.
--
-- NOTE: MySQL 9.6 rejects `IF NOT EXISTS` on ADD COLUMN — run this once.

USE caneeli;

ALTER TABLE contact_messages
    ADD COLUMN reply      TEXT      DEFAULT NULL AFTER message,
    ADD COLUMN replied_at TIMESTAMP NULL DEFAULT NULL AFTER reply;
