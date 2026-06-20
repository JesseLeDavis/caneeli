<?php
// HTML builders for transactional emails. Table-based, inline styles only —
// that's what survives across email clients (Gmail, Apple Mail, Outlook).
// Brand palette mirrors the site: neutral #F2ECDE, rust #C25B32, dark #2D2D2D.

require_once __DIR__ . '/../../config/config.php';

/**
 * Wrap body HTML in the shared branded shell (header + footer).
 */
function email_wrap($title, $bodyHtml)
{
    $site = htmlspecialchars(SITE_NAME);
    $url  = htmlspecialchars(SITE_URL);
    $year = date('Y');

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#F2ECDE;font-family:Helvetica,Arial,sans-serif;color:#2D2D2D;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F2ECDE;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(45,45,45,0.08);">
  <tr>
    <td style="background:#2D2D2D;padding:24px 32px;">
      <a href="' . $url . '" style="color:#F2ECDE;font-size:22px;font-weight:700;letter-spacing:1px;text-decoration:none;">' . $site . '</a>
    </td>
  </tr>
  <tr>
    <td style="padding:32px;">' . $bodyHtml . '</td>
  </tr>
  <tr>
    <td style="padding:20px 32px;background:#F2ECDE;font-size:12px;color:#7a756a;text-align:center;">
      Handmade with care &middot; ' . $site . '<br>
      <a href="' . $url . '" style="color:#C25B32;text-decoration:none;">' . $url . '</a><br>
      <span style="color:#a59f93;">&copy; ' . $year . ' ' . $site . '</span>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

/**
 * Order confirmation / receipt.
 *
 * @param array $order  orders row (customer_name, total, shipping_address, discount_*, etc.)
 * @param array $items  order_items rows (product_name, quantity, price_at_purchase)
 */
function build_order_confirmation_email(array $order, array $items)
{
    $name   = htmlspecialchars($order['customer_name'] ?: 'there');
    $orderNo = (int) $order['id'];

    $rows = '';
    $subtotal = 0;
    foreach ($items as $it) {
        $line = ((float) $it['price_at_purchase']) * (int) $it['quantity'];
        $subtotal += $line;
        $rows .= '<tr>
            <td style="padding:10px 0;border-bottom:1px solid #eee;font-size:14px;">'
                . htmlspecialchars($it['product_name'])
                . ' <span style="color:#7a756a;">&times; ' . (int) $it['quantity'] . '</span></td>
            <td style="padding:10px 0;border-bottom:1px solid #eee;font-size:14px;text-align:right;white-space:nowrap;">$'
                . number_format($line, 2) . '</td>
        </tr>';
    }

    $discount = (float) ($order['discount_amount'] ?? 0);
    $totals = '<tr><td style="padding:8px 0;font-size:14px;color:#7a756a;">Subtotal</td>
        <td style="padding:8px 0;font-size:14px;text-align:right;">$' . number_format($subtotal, 2) . '</td></tr>';
    if ($discount > 0) {
        $code = !empty($order['discount_code']) ? ' (' . htmlspecialchars($order['discount_code']) . ')' : '';
        $totals .= '<tr><td style="padding:8px 0;font-size:14px;color:#C25B32;">Discount' . $code . '</td>
            <td style="padding:8px 0;font-size:14px;text-align:right;color:#C25B32;">&minus;$' . number_format($discount, 2) . '</td></tr>';
    }
    $totals .= '<tr><td style="padding:12px 0 0;font-size:16px;font-weight:700;border-top:2px solid #2D2D2D;">Total</td>
        <td style="padding:12px 0 0;font-size:16px;font-weight:700;text-align:right;border-top:2px solid #2D2D2D;">$'
        . number_format((float) $order['total'], 2) . '</td></tr>';

    $shipBlock = '';
    if (!empty($order['shipping_address'])) {
        $shipBlock = '<h3 style="font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#7a756a;margin:28px 0 8px;">Shipping to</h3>
            <p style="margin:0;font-size:14px;line-height:1.6;white-space:pre-line;">'
            . htmlspecialchars($order['shipping_address']) . '</p>';
    }

    $body = '<h1 style="margin:0 0 8px;font-size:24px;color:#2D2D2D;">Thank you, ' . $name . '!</h1>
        <p style="margin:0 0 4px;font-size:15px;line-height:1.6;color:#4a4a4a;">
            Your order is confirmed. I&rsquo;ll be in touch with shipping details once it&rsquo;s on its way.</p>
        <p style="margin:0 0 24px;font-size:14px;color:#7a756a;">Order #' . $orderNo . '</p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;">' . $totals . '</table>'
        . $shipBlock;

    return email_wrap('Your ' . SITE_NAME . ' order #' . $orderNo, $body);
}

/**
 * Shipping notification with tracking number.
 */
function build_shipping_email(array $order)
{
    $name    = htmlspecialchars($order['customer_name'] ?: 'there');
    $orderNo = (int) $order['id'];
    $tracking = htmlspecialchars($order['tracking_number'] ?? '');

    $trackingBlock = $tracking !== ''
        ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
             <tr><td style="background:#F2ECDE;border-radius:10px;padding:18px 20px;text-align:center;">
               <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#7a756a;margin-bottom:6px;">Tracking number</div>
               <div style="font-size:18px;font-weight:700;color:#2D2D2D;word-break:break-all;">' . $tracking . '</div>
             </td></tr>
           </table>'
        : '';

    $shipBlock = '';
    if (!empty($order['shipping_address'])) {
        $shipBlock = '<h3 style="font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#7a756a;margin:8px 0 8px;">Shipping to</h3>
            <p style="margin:0;font-size:14px;line-height:1.6;white-space:pre-line;">'
            . htmlspecialchars($order['shipping_address']) . '</p>';
    }

    $body = '<h1 style="margin:0 0 8px;font-size:24px;color:#2D2D2D;">Your order is on its way! &#128230;</h1>
        <p style="margin:0 0 4px;font-size:15px;line-height:1.6;color:#4a4a4a;">
            Good news, ' . $name . ' &mdash; order #' . $orderNo . ' has shipped.</p>
        <p style="margin:0 0 8px;font-size:14px;color:#7a756a;">Use the tracking number below to follow along with your carrier.</p>'
        . $trackingBlock
        . $shipBlock;

    return email_wrap('Your ' . SITE_NAME . ' order has shipped', $body);
}
