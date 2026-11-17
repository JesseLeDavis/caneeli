<?php
/**
 * Small view helpers shared by the admin pages (loaded by layout-top.php).
 */

/** Plain-English label for an order status. */
function admin_order_status_label(string $status): string {
    return [
        'pending'      => 'Pending',
        'paid'         => 'Paid',
        'deposit_paid' => 'Deposit paid',
        'fulfilled'    => 'Shipped',
        'cancelled'    => 'Cancelled',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/** Status pill. */
function admin_status_badge(string $status, ?string $label = null): string {
    return '<span class="badge-status badge-status--' . htmlspecialchars($status) . '">'
        . htmlspecialchars($label ?? admin_order_status_label($status)) . '</span>';
}

/** One or two initials for an avatar. */
function admin_initials(string $name): string {
    $parts = preg_split('/\s+/', trim(preg_replace('/@.*$/', '', $name))) ?: [];
    $i = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    return $i !== '' ? $i : '?';
}

function admin_avatar(string $name, string $extra = ''): string {
    return '<span class="ui-avatar ' . $extra . '" aria-hidden="true">' . htmlspecialchars(admin_initials($name)) . '</span>';
}

/** "Today", "Yesterday", or "Mon, Nov 14" for grouping lists by day. */
function admin_day_label(string $ts): string {
    $d = date('Y-m-d', strtotime($ts));
    if ($d === date('Y-m-d')) return 'Today';
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
    return date($d >= date('Y-01-01') ? 'D, M j' : 'D, M j, Y', strtotime($ts));
}

function admin_chevron(): string {
    return '<svg class="ui-chevron" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>';
}
