-- Custom orders / commissions with a non-refundable deposit.
--
-- A quote lives in custom_quotes until the deposit is paid. Only then does it
-- become a real `orders` row, so unaccepted quotes never pollute the Orders
-- list and quote states don't have to fit the orders status enum.
--
-- NOTE: MySQL 9.6 rejects `IF NOT EXISTS` on ADD COLUMN — run this once.
-- Verify first with `DESCRIBE orders;` / `SHOW TABLES LIKE 'custom_quotes';`

USE caneeli;

CREATE TABLE custom_quotes (
    id                    INT AUTO_INCREMENT PRIMARY KEY,

    -- Random token for the private quote link. No customer login; whoever
    -- holds the link can pay, same model as a Stripe payment link.
    token                 VARCHAR(64)  NOT NULL UNIQUE,

    customer_name         VARCHAR(255) NOT NULL,
    customer_email        VARCHAR(255) NOT NULL,

    title                 VARCHAR(255) NOT NULL,
    description           TEXT,
    lead_time             VARCHAR(120),

    total                 DECIMAL(10,2) NOT NULL,
    deposit_amount        DECIMAL(10,2) NOT NULL,

    status                ENUM('draft','sent','deposit_paid','balance_requested','paid','cancelled')
                          NOT NULL DEFAULT 'draft',

    -- Written when the customer ticks the non-refundable box, immediately
    -- before the deposit Checkout session is created. This timestamp is the
    -- evidence that terms were shown and accepted prior to payment.
    terms_accepted_at     TIMESTAMP NULL DEFAULT NULL,

    -- Email dedup, mirroring the orders.*_email_sent_at convention.
    quote_email_sent_at   TIMESTAMP NULL DEFAULT NULL,
    deposit_email_sent_at TIMESTAMP NULL DEFAULT NULL,
    balance_email_sent_at TIMESTAMP NULL DEFAULT NULL,

    -- Stripe sessions for each of the two payments.
    deposit_session_id    VARCHAR(255) NULL,
    balance_session_id    VARCHAR(255) NULL,

    -- Set once the deposit converts this quote into a real order.
    order_id              INT NULL,

    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_order (order_id)
);

-- Link an order back to its quote, and track the split payment. Regular cart
-- orders leave all three NULL — amount_paid/balance_due are only meaningful
-- for deposit orders.
ALTER TABLE orders
    ADD COLUMN quote_id    INT           NULL AFTER id,
    ADD COLUMN amount_paid DECIMAL(10,2) NULL AFTER total,
    ADD COLUMN balance_due DECIMAL(10,2) NULL AFTER amount_paid,
    -- Set when Annie marks a balance paid outside Stripe (cash or check at
    -- pickup). Kept distinct from a Stripe-settled balance so "we have the
    -- money" and "Stripe says we have the money" never look identical.
    ADD COLUMN balance_paid_manually_at TIMESTAMP NULL DEFAULT NULL AFTER balance_due;

-- New state: deposit received, balance still owed. Fulfillment is blocked
-- while balance_due > 0.
--
-- IMPORTANT: 'deposit_paid' is APPENDED, not inserted in logical position.
-- MySQL stores ENUMs internally by index. Converting by string value is the
-- documented behaviour and was verified safe locally, but appending leaves
-- indices 1–4 untouched, so existing rows cannot be remapped even in the
-- worst case. Nothing sorts by status, so the ordering is cosmetic anyway.
-- Do not "tidy" this into logical order — it runs against live paid orders.
ALTER TABLE orders
    MODIFY COLUMN status ENUM('pending','paid','fulfilled','cancelled','deposit_paid')
    NOT NULL DEFAULT 'pending';
