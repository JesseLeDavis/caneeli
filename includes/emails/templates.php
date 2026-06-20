<?php
// HTML builders for transactional emails, styled to match the Caneeli site:
//   - Fonts: Syne (headings) + Urbanist (body), loaded from Google Fonts.
//     Apple Mail / iOS Mail render them; other clients fall back to the system
//     sans stack — that's expected and fine.
//   - Palette: neutral #F2ECDE bg, dark #2D2D2D, rust #C25B32, mustard #D4C14B,
//     blue-400 #6497D6, blue-200 #C1E1FA.
//   - Signature brand touch: offset "hard" drop shadows (box-shadow Xpx Ypx 0)
//     and pill shapes, echoing the site's buttons/cards. box-shadow degrades to
//     nothing in Outlook, which is acceptable.
//
// Everything is table-based with inline styles — the only thing that survives
// across email clients.

require_once __DIR__ . '/../../config/config.php';

// Brand palette (kept here so the templates read cleanly).
const EMAIL_NEUTRAL  = '#F2ECDE';
const EMAIL_DARK     = '#2D2D2D';
const EMAIL_RUST     = '#C25B32';
const EMAIL_MUSTARD  = '#D4C14B';
const EMAIL_BLUE_400 = '#6497D6';
const EMAIL_BLUE_200 = '#C1E1FA';

const EMAIL_FONT_HEADING = "'Syne','Trebuchet MS',Helvetica,Arial,sans-serif";
const EMAIL_FONT_BODY    = "'Urbanist',Helvetica,Arial,sans-serif";

/**
 * Wrap body HTML in the shared branded shell (header + footer).
 *
 * @param string $title  <title> + preheader
 * @param string $body   inner HTML
 * @param string $accent brand accent used for the card shadow + header rule
 * @param string $shadow color of the offset drop shadow behind the card
 */
function email_wrap($title, $body, $accent = EMAIL_RUST, $shadow = EMAIL_BLUE_200)
{
    $site = htmlspecialchars(SITE_NAME);
    $url  = htmlspecialchars(SITE_URL);
    $year = date('Y');
    $hf   = EMAIL_FONT_HEADING;
    $bf   = EMAIL_FONT_BODY;

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . htmlspecialchars($title) . '</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Urbanist:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  @import url("https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Urbanist:wght@400;500;600;700&display=swap");
  body { margin:0; padding:0; }
  a { color:' . EMAIL_RUST . '; }
  @media only screen and (max-width:600px) {
    .email-card { width:100% !important; border-radius:14px !important; }
    .email-pad  { padding:24px !important; }
    .email-h1   { font-size:24px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:' . EMAIL_NEUTRAL . ';font-family:' . $bf . ';color:' . EMAIL_DARK . ';">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . htmlspecialchars($title) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . EMAIL_NEUTRAL . ';padding:32px 14px;">
<tr><td align="center">

<table role="presentation" class="email-card" width="560" cellpadding="0" cellspacing="0" style="width:560px;max-width:560px;background:#ffffff;border:2px solid ' . EMAIL_DARK . ';border-radius:18px;box-shadow:7px 7px 0 ' . $shadow . ';overflow:hidden;">
  <tr>
    <td style="background:' . EMAIL_DARK . ';padding:22px 36px 24px;">
      <a href="' . $url . '" style="font-family:' . $hf . ';color:' . EMAIL_NEUTRAL . ';font-size:24px;font-weight:800;letter-spacing:2px;text-decoration:none;text-transform:uppercase;">' . $site . '</a>
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:12px;">
        <tr>
          <td style="height:5px;width:34px;background:' . EMAIL_RUST . ';border-radius:3px;"></td>
          <td style="width:6px;"></td>
          <td style="height:5px;width:34px;background:' . EMAIL_MUSTARD . ';border-radius:3px;"></td>
          <td style="width:6px;"></td>
          <td style="height:5px;width:34px;background:' . EMAIL_BLUE_400 . ';border-radius:3px;"></td>
        </tr>
      </table>
    </td>
  </tr>
  <tr><td style="height:6px;background:' . $accent . ';"></td></tr>'/* thin accent stripe keyed to the email type */ . '
  <tr>
    <td class="email-pad" style="padding:36px;">' . $body . '</td>
  </tr>
  <tr>
    <td style="padding:24px 36px;background:' . EMAIL_NEUTRAL . ';border-top:2px solid ' . EMAIL_DARK . ';font-family:' . $bf . ';font-size:12px;color:#7a756a;text-align:center;line-height:1.7;">
      <span style="font-family:' . $hf . ';font-weight:700;color:' . EMAIL_DARK . ';letter-spacing:1px;">' . $site . '</span><br>
      Handmade furniture &amp; decor, made to order<br>
      <a href="' . $url . '" style="color:' . EMAIL_RUST . ';text-decoration:none;font-weight:600;">' . preg_replace('#^https?://#', '', $url) . '</a><br>
      <span style="color:#a59f93;">&copy; ' . $year . ' ' . $site . '</span>
    </td>
  </tr>
</table>

</td></tr>
</table>
</body>
</html>';
}

/** Shared heading style (Syne). */
function email_h1($text)
{
    return '<h1 class="email-h1" style="margin:0 0 10px;font-family:' . EMAIL_FONT_HEADING
        . ';font-size:28px;font-weight:800;line-height:1.15;color:' . EMAIL_DARK . ';">' . $text . '</h1>';
}

/** Shared body paragraph (Urbanist). */
function email_p($html, $color = '#4a4a4a', $size = '15px')
{
    return '<p style="margin:0 0 14px;font-family:' . EMAIL_FONT_BODY . ';font-size:' . $size
        . ';line-height:1.65;color:' . $color . ';">' . $html . '</p>';
}

/** Small uppercase section label. */
function email_label($text)
{
    return '<div style="font-family:' . EMAIL_FONT_BODY . ';font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#7a756a;margin:0 0 8px;">'
        . $text . '</div>';
}

/**
 * Order confirmation / receipt. Accent: rust, shadow: blue-200.
 *
 * @param array $order  orders row
 * @param array $items  order_items rows
 */
function build_order_confirmation_email(array $order, array $items)
{
    $name    = htmlspecialchars($order['customer_name'] ?: 'there');
    $orderNo = (int) $order['id'];
    $bf      = EMAIL_FONT_BODY;
    $hf      = EMAIL_FONT_HEADING;

    $rows = '';
    $subtotal = 0;
    foreach ($items as $it) {
        $line = ((float) $it['price_at_purchase']) * (int) $it['quantity'];
        $subtotal += $line;
        $rows .= '<tr>
            <td style="padding:12px 0;border-bottom:1px solid #ece6d8;font-family:' . $bf . ';font-size:14px;color:' . EMAIL_DARK . ';">'
                . htmlspecialchars($it['product_name'])
                . ' <span style="color:#a59f93;">&times;&nbsp;' . (int) $it['quantity'] . '</span></td>
            <td style="padding:12px 0;border-bottom:1px solid #ece6d8;font-family:' . $bf . ';font-size:14px;text-align:right;white-space:nowrap;color:' . EMAIL_DARK . ';">$'
                . number_format($line, 2) . '</td>
        </tr>';
    }

    $discount = (float) ($order['discount_amount'] ?? 0);
    $totals = '<tr><td style="padding:8px 0;font-family:' . $bf . ';font-size:14px;color:#7a756a;">Subtotal</td>
        <td style="padding:8px 0;font-family:' . $bf . ';font-size:14px;text-align:right;color:#7a756a;">$' . number_format($subtotal, 2) . '</td></tr>';
    if ($discount > 0) {
        $code = !empty($order['discount_code']) ? ' (' . htmlspecialchars($order['discount_code']) . ')' : '';
        $totals .= '<tr><td style="padding:8px 0;font-family:' . $bf . ';font-size:14px;color:' . EMAIL_RUST . ';">Discount' . $code . '</td>
            <td style="padding:8px 0;font-family:' . $bf . ';font-size:14px;text-align:right;color:' . EMAIL_RUST . ';">&minus;$' . number_format($discount, 2) . '</td></tr>';
    }
    $totals .= '<tr>
        <td style="padding:14px 0 0;font-family:' . $hf . ';font-size:18px;font-weight:800;color:' . EMAIL_DARK . ';border-top:2px solid ' . EMAIL_DARK . ';">Total</td>
        <td style="padding:14px 0 0;font-family:' . $hf . ';font-size:18px;font-weight:800;text-align:right;color:' . EMAIL_DARK . ';border-top:2px solid ' . EMAIL_DARK . ';">$'
        . number_format((float) $order['total'], 2) . '</td></tr>';

    // Order-number pill
    $pill = '<span style="display:inline-block;font-family:' . $bf . ';font-size:13px;font-weight:700;color:' . EMAIL_DARK
        . ';background:' . EMAIL_MUSTARD . ';border-radius:30px;padding:6px 16px;">Order #' . $orderNo . '</span>';

    $shipBlock = '';
    if (!empty($order['shipping_address'])) {
        $shipBlock = '<div style="margin-top:28px;">' . email_label('Shipping to')
            . '<p style="margin:0;font-family:' . $bf . ';font-size:14px;line-height:1.6;white-space:pre-line;color:' . EMAIL_DARK . ';">'
            . htmlspecialchars($order['shipping_address']) . '</p></div>';
    }

    $body = email_h1('Thank you, ' . $name . '!')
        . email_p('Your order is confirmed. I make each piece by hand, so I&rsquo;ll be in touch with shipping details once yours is on its way.')
        . '<div style="margin:0 0 24px;">' . $pill . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;">' . $totals . '</table>'
        . $shipBlock;

    return email_wrap('Your ' . SITE_NAME . ' order #' . $orderNo, $body, EMAIL_RUST, EMAIL_BLUE_400);
}

/**
 * Personal reply from Annie to a contact-form message. Accent: mustard,
 * shadow: rust. Quotes the customer's original message underneath.
 *
 * @param array  $message   contact_messages row (name, email, message, created_at)
 * @param string $replyText Annie's reply (plain text)
 */
function build_reply_email(array $message, $replyText)
{
    $name = htmlspecialchars($message['name'] ?: 'there');
    $bf   = EMAIL_FONT_BODY;

    // Preserve the writer's line breaks; escape everything.
    $reply = nl2br(htmlspecialchars(trim($replyText)));

    $quoted = '';
    if (!empty($message['message'])) {
        $when = !empty($message['created_at'])
            ? ' on ' . date('M j, Y', strtotime($message['created_at']))
            : '';
        $quoted = '<div style="margin-top:28px;border-left:3px solid ' . EMAIL_MUSTARD . ';padding:4px 0 4px 16px;">'
            . '<div style="font-family:' . $bf . ';font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:#a59f93;margin-bottom:6px;">You wrote' . $when . '</div>'
            . '<p style="margin:0;font-family:' . $bf . ';font-size:14px;line-height:1.6;color:#7a756a;white-space:pre-line;">'
            . htmlspecialchars($message['message']) . '</p></div>';
    }

    // No auto-appended signature — Annie signs off however she likes in her reply.
    $body = email_h1('Hi ' . $name . ',')
        . '<div style="font-family:' . $bf . ';font-size:15px;line-height:1.65;color:' . EMAIL_DARK . ';">' . $reply . '</div>'
        . $quoted;

    return email_wrap('Re: your message to ' . SITE_NAME, $body, EMAIL_MUSTARD, EMAIL_RUST);
}

/**
 * Shipping notification with tracking number. Accent: blue-400, shadow: mustard.
 */
function build_shipping_email(array $order)
{
    $name     = htmlspecialchars($order['customer_name'] ?: 'there');
    $orderNo  = (int) $order['id'];
    $tracking = htmlspecialchars($order['tracking_number'] ?? '');
    $bf       = EMAIL_FONT_BODY;
    $hf       = EMAIL_FONT_HEADING;

    $trackingBlock = '';
    if ($tracking !== '') {
        $trackingBlock = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:26px 0;">
             <tr><td style="background:' . EMAIL_NEUTRAL . ';border:2px solid ' . EMAIL_DARK . ';border-radius:14px;box-shadow:5px 5px 0 ' . EMAIL_BLUE_400 . ';padding:22px 24px;text-align:center;">
               <div style="font-family:' . $bf . ';font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#7a756a;margin-bottom:8px;">Tracking number</div>
               <div style="font-family:' . $hf . ';font-size:20px;font-weight:800;color:' . EMAIL_DARK . ';word-break:break-all;">' . $tracking . '</div>
             </td></tr>
           </table>';
    }

    $shipBlock = '';
    if (!empty($order['shipping_address'])) {
        $shipBlock = '<div style="margin-top:8px;">' . email_label('Shipping to')
            . '<p style="margin:0;font-family:' . $bf . ';font-size:14px;line-height:1.6;white-space:pre-line;color:' . EMAIL_DARK . ';">'
            . htmlspecialchars($order['shipping_address']) . '</p></div>';
    }

    $body = email_h1('Your order is on its way!')
        . email_p('Good news, ' . $name . ' &mdash; order <strong>#' . $orderNo . '</strong> has shipped. Use the tracking number below to follow along with the carrier.')
        . $trackingBlock
        . $shipBlock;

    return email_wrap('Your ' . SITE_NAME . ' order has shipped', $body, EMAIL_BLUE_400, EMAIL_MUSTARD);
}
