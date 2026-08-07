<?php
/**
 * Shared helpers for custom-order quotes.
 *
 * A quote lives in `custom_quotes` until its deposit is paid; only then does it
 * become a real `orders` row. See database/migrate_custom_orders.sql.
 */

/** Human labels + badge modifiers for each quote status. */
function quote_status_meta(string $status): array
{
    return [
        'draft'             => ['Draft',             'draft'],
        'sent'              => ['Sent',              'pending'],
        'deposit_paid'      => ['Deposit paid',      'deposit'],
        'balance_requested' => ['Balance requested', 'balance'],
        'paid'              => ['Paid in full',      'paid'],
        'cancelled'         => ['Cancelled',         'cancelled'],
    ][$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'draft'];
}

/** Public URL for a quote's private link. */
function quote_url(array $quote): string
{
    return SITE_URL . '/quote.php?t=' . urlencode($quote['token']);
}

/**
 * A quote's money is locked once the customer has paid anything against it —
 * changing the total after a deposit would silently change what they owe.
 */
function quote_is_editable(array $quote): bool
{
    return in_array($quote['status'], ['draft', 'sent'], true);
}

/** Generate a link token that isn't guessable. */
function quote_new_token(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Validate quote form input. Returns [errors[], clean[]].
 */
function quote_validate(array $in): array
{
    $errors = [];

    $name  = trim($in['customer_name'] ?? '');
    $email = trim($in['customer_email'] ?? '');
    $title = trim($in['title'] ?? '');
    $desc  = trim($in['description'] ?? '');
    $lead  = trim($in['lead_time'] ?? '');
    $total   = (float) ($in['total'] ?? 0);
    $deposit = (float) ($in['deposit_amount'] ?? 0);

    if ($name === '')  { $errors[] = 'Customer name is required.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'A valid customer email is required.'; }
    if ($title === '') { $errors[] = 'Give the piece a title.'; }
    if ($total <= 0)   { $errors[] = 'Total must be greater than zero.'; }
    if ($deposit <= 0) { $errors[] = 'Deposit must be greater than zero.'; }
    if ($total > 0 && $deposit > $total) {
        $errors[] = 'Deposit cannot be more than the total.';
    }

    return [$errors, [
        'customer_name'  => $name,
        'customer_email' => $email,
        'title'          => $title,
        'description'    => $desc !== '' ? $desc : null,
        'lead_time'      => $lead !== '' ? $lead : null,
        'total'          => $total,
        'deposit_amount' => $deposit,
    ]];
}
