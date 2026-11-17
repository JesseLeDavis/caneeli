-- Pre-orders: a product Annie hasn't made yet, sold ahead of a ship date.
--
-- A pre-order product has status 'preorder'. Its stock_qty is the number of
-- spots left in the batch, so the batch can't be oversold; when it reaches 0
-- the product page shows "Pre-orders full" (the status stays 'preorder').
--
-- Payment: in full at checkout, or, when the product has a preorder_deposit,
-- the deposit at checkout and the balance when the batch is ready. A deposit
-- order starts as 'deposit_paid' with balance_due set (the same columns custom
-- orders use), and can't be marked shipped until the balance is paid. Annie
-- requests balances from admin/preorders.php; buyers pay at
-- preorder-pay.php?t=<preorder_token>. If the batch runs late,
-- admin/preorders.php moves the date and emails everyone waiting (US mail-order
-- rule: tell buyers about a delay and offer a refund).
--
-- Run once. Verify first with `DESCRIBE products;` / `DESCRIBE orders;`.
-- 'preorder' is APPENDED to the enum so existing rows keep their values.

ALTER TABLE products
    MODIFY COLUMN status ENUM('active','sold_out','draft','archived','preorder')
        NOT NULL DEFAULT 'active',
    ADD COLUMN preorder_ship_date DATE NULL AFTER status,
    -- Per piece. NULL = pay in full up front.
    ADD COLUMN preorder_deposit DECIMAL(10,2) NULL AFTER preorder_ship_date;

ALTER TABLE orders
    ADD COLUMN is_preorder TINYINT(1) NOT NULL DEFAULT 0 AFTER quote_id,
    ADD COLUMN preorder_ship_date DATE NULL AFTER is_preorder,
    -- When the buyer ticked "I understand this ships by <date>", just
    -- before they were sent to Stripe.
    ADD COLUMN preorder_terms_accepted_at TIMESTAMP NULL DEFAULT NULL AFTER preorder_ship_date,
    -- Last time this buyer was emailed about a delay.
    ADD COLUMN preorder_delay_email_sent_at TIMESTAMP NULL DEFAULT NULL AFTER preorder_terms_accepted_at,
    -- Deposit pre-orders: the private link for paying the balance, when it
    -- was last requested, and the balance's own Stripe payment (Stripe
    -- doesn't link it to the deposit; the order keeps the deposit's intent).
    ADD COLUMN preorder_token VARCHAR(64) NULL AFTER preorder_delay_email_sent_at,
    ADD COLUMN preorder_balance_requested_at TIMESTAMP NULL DEFAULT NULL AFTER preorder_token,
    ADD COLUMN preorder_balance_payment_intent VARCHAR(255) NULL AFTER preorder_balance_requested_at,
    ADD UNIQUE INDEX idx_preorder_token (preorder_token),
    ADD INDEX idx_preorder (is_preorder);
