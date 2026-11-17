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
const EMAIL_RUST_DK  = '#A84B27'; // rust text on light grounds
const EMAIL_PAPER    = '#FFFDF8'; // the card, same as the site's receipt
const EMAIL_MUTED    = '#625C53';
const EMAIL_LINE     = '#E8E2D4';

const EMAIL_FONT_HEADING = "'Syne','Trebuchet MS',Helvetica,Arial,sans-serif";
const EMAIL_FONT_BODY    = "'Urbanist',Helvetica,Arial,sans-serif";

/**
 * Wrap body HTML in the shared shell: logo on paper, a white card with a
 * colored offset shadow (like the site's receipt), and a quiet footer.
 *
 * @param string $title  <title> + preheader
 * @param string $body   inner HTML
 * @param string $accent kept for older callers; unused
 * @param string $shadow color of the offset shadow behind the card
 */
function email_wrap($title, $body, $accent = EMAIL_RUST, $shadow = EMAIL_BLUE_200)
{
    $site = htmlspecialchars(SITE_NAME);
    $url  = htmlspecialchars(SITE_URL);
    $year = date('Y');
    $bf   = EMAIL_FONT_BODY;

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<title>' . htmlspecialchars($title) . '</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Urbanist:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  body { margin:0; padding:0; }
  a { color:' . EMAIL_RUST_DK . '; }
  @media only screen and (max-width:600px) {
    .email-card { width:100% !important; }
    .email-pad  { padding:26px 20px !important; }
    .email-h1   { font-size:28px !important; }
    .email-hide-sm { display:none !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:' . EMAIL_NEUTRAL . ';font-family:' . $bf . ';color:' . EMAIL_DARK . ';">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . htmlspecialchars($title) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . EMAIL_NEUTRAL . ';">
<tr><td align="center" style="padding:32px 14px 40px;">

<table role="presentation" class="email-card" width="560" cellpadding="0" cellspacing="0" style="width:560px;max-width:560px;">
  <tr>
    <td align="center" style="padding:0 0 26px;">
      <a href="' . $url . '" style="text-decoration:none;"><img src="' . $url . '/assets/images/email-logo.png" width="223" height="41" alt="' . $site . '" style="display:block;border:0;width:223px;height:auto;font-family:' . EMAIL_FONT_HEADING . ';font-size:22px;font-weight:800;color:' . EMAIL_RUST . ';"></a>
    </td>
  </tr>
  <tr>
    <td class="email-pad" style="padding:36px 36px 32px;background:' . EMAIL_PAPER . ';border-radius:6px;box-shadow:8px 8px 0 ' . $shadow . ';">' . $body . '</td>
  </tr>
  <tr>
    <td style="padding:30px 20px 0;font-family:' . $bf . ';font-size:12.5px;line-height:1.7;color:' . EMAIL_MUTED . ';text-align:center;">
      Stained glass lamps, ceramic lighting and salmon in every form,<br class="email-hide-sm"> made by hand by Annie in Oregon.<br>
      <a href="' . $url . '" style="color:' . EMAIL_RUST_DK . ';text-decoration:none;font-weight:600;">' . preg_replace('#^https?://#', '', $url) . '</a>
      &nbsp;&middot;&nbsp; &copy; ' . $year . ' ' . $site . '
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
    return '<h1 class="email-h1" style="margin:0 0 12px;font-family:' . EMAIL_FONT_HEADING
        . ';font-size:32px;font-weight:800;line-height:1.08;color:' . EMAIL_DARK . ';">' . $text . '</h1>';
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
 * Order confirmation, laid out like the receipt page (success.php): a thank
 * you, the receipt itself, then what happens next.
 *
 * @param array $order  orders row
 * @param array $items  order_items rows (image_path optional, from products)
 */
function build_order_confirmation_email(array $order, array $items)
{
    $bf = EMAIL_FONT_BODY;
    $hf = EMAIL_FONT_HEADING;
    $first   = trim(explode(' ', (string) ($order['customer_name'] ?? ''))[0]);
    $orderNo = (int) $order['id'];
    $isPre   = !empty($order['is_preorder']);
    $balance = (float) ($order['balance_due'] ?? 0);
    $ship    = $isPre && !empty($order['preorder_ship_date']) ? date('F j', strtotime($order['preorder_ship_date'])) : null;
    $money   = fn($n) => '$' . number_format((float) $n, 2);
    $td      = 'font-family:' . $bf . ';font-size:15px;line-height:1.4;color:' . EMAIL_DARK . ';';
    $muted   = 'font-family:' . $bf . ';font-size:13px;line-height:1.4;color:' . EMAIL_MUTED . ';';

    // Thank you
    $html = email_h1('Thank you' . ($first !== '' ? ', ' . htmlspecialchars($first) : '') . '.')
        . email_p($isPre
            ? 'Your pre-order is in. I&rsquo;m making this batch now' . ($ship ? ', and yours ships by <strong>' . $ship . '</strong>' : '') . '.'
            : 'Your order is in, and I&rsquo;ll pack it up by hand.', EMAIL_DARK, '16px');

    // Receipt header
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;border-bottom:2px solid ' . EMAIL_DARK . ';">
        <tr><td style="padding:0 0 14px;font-family:' . $hf . ';font-size:22px;font-weight:800;color:' . EMAIL_DARK . ';">Receipt</td>
        <td align="right" style="padding:0 0 14px;' . $td . 'font-weight:700;white-space:nowrap;">' . ($isPre ? 'Pre-order' : 'Order') . ' #' . $orderNo . '</td></tr></table>';

    // Date / status
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1px solid ' . EMAIL_LINE . ';">
        <tr><td width="50%" style="padding:14px 0;"><div style="' . $muted . '">Date</div><div style="' . $td . 'font-weight:600;">' . date('M j, Y', strtotime($order['created_at'] ?? 'now')) . '</div></td>
        <td width="50%" style="padding:14px 0;"><div style="' . $muted . '">Status</div><div style="' . $td . 'font-weight:600;">' . ($balance > 0 ? 'Deposit paid' : 'Paid') . '</div></td></tr></table>';

    // Items
    $subtotal = 0;
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1px solid ' . EMAIL_LINE . ';">';
    foreach ($items as $it) {
        $qty  = (int) $it['quantity'];
        $line = (float) $it['price_at_purchase'] * $qty;
        $subtotal += $line;
        $name = htmlspecialchars(preg_replace('/^(Pre-order|Deposit): /', '', (string) $it['product_name']));
        $img  = !empty($it['image_path'])
            ? '<img src="' . htmlspecialchars(SITE_URL . $it['image_path']) . '" width="52" height="52" alt="" style="display:block;width:52px;height:52px;object-fit:cover;border-radius:4px;border:0;">'
            : '<div style="width:52px;height:52px;border-radius:4px;background:#EFE8D8;"></div>';
        $html .= '<tr>
            <td width="52" style="padding:12px 14px 12px 0;vertical-align:middle;">' . $img . '</td>
            <td style="padding:12px 0;vertical-align:middle;"><div style="' . $td . 'font-weight:600;">' . $name . '</div>
                <div style="' . $muted . 'margin-top:2px;">' . ($isPre ? 'Pre-order, ' : '') . $qty . ' &times; ' . $money($it['price_at_purchase']) . '</div></td>
            <td align="right" style="padding:12px 0 12px 12px;vertical-align:middle;' . $td . 'font-weight:600;white-space:nowrap;">' . $money($line) . '</td>
        </tr>';
    }
    $html .= '</table>';

    // Totals
    $row = fn($label, $value, $style = '') => '<tr><td style="padding:4px 0;' . $td . $style . '">' . $label . '</td><td align="right" style="padding:4px 0;' . $td . 'font-weight:600;white-space:nowrap;' . $style . '">' . $value . '</td></tr>';
    $discount = (float) ($order['discount_amount'] ?? 0);
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:12px 0 0;">'
        . $row('Subtotal', $money($subtotal))
        . ($discount > 0 ? $row('Discount' . (!empty($order['discount_code']) ? ' (' . htmlspecialchars($order['discount_code']) . ')' : ''), '&minus;' . $money($discount), 'color:' . EMAIL_RUST_DK . ';') : '')
        . $row('Shipping', 'Included')
        . '<tr><td style="padding:12px 0 4px;border-top:2px solid ' . EMAIL_DARK . ';font-family:' . $bf . ';font-size:18px;font-weight:700;color:' . EMAIL_DARK . ';">Total</td>
            <td align="right" style="padding:12px 0 4px;border-top:2px solid ' . EMAIL_DARK . ';font-family:' . $bf . ';font-size:18px;font-weight:800;color:' . EMAIL_DARK . ';">' . $money($order['total']) . '</td></tr>'
        . ($balance > 0
            ? $row('Deposit paid', $money($order['amount_paid']), 'color:' . EMAIL_MUTED . ';')
              . $row('<strong>Due before it ships</strong>', $money($balance), 'color:' . EMAIL_RUST_DK . ';')
            : '')
        . '</table>';

    // Shipping address
    if (!empty($order['shipping_address'])) {
        $html .= '<div style="margin-top:18px;padding-top:16px;border-top:1px solid ' . EMAIL_LINE . ';">
            <div style="' . $muted . 'font-weight:600;margin-bottom:4px;">Shipping to</div>
            <div style="' . $td . 'line-height:1.55;">' . nl2br(htmlspecialchars($order['shipping_address'])) . '</div></div>';
    }

    // What happens next
    $steps = [
        ['Confirmation email', 'This is it. Keep it for your records.'],
        $isPre
            ? ['Made to order', $balance > 0
                ? 'When your piece is ready, I&rsquo;ll email you a link to pay the remaining ' . $money($balance) . '.'
                : 'If anything holds the batch up, I&rsquo;ll tell you right away and you can cancel for a full refund.']
            : ['Packed by hand', 'In-stock pieces ship within 5&ndash;10 business days.'],
        ['Tracking', 'You&rsquo;ll get a tracking number by email as soon as it ships.'],
    ];
    $html .= '<div style="margin-top:26px;padding-top:22px;border-top:2px solid ' . EMAIL_DARK . ';font-family:' . $hf . ';font-size:17px;font-weight:700;color:' . EMAIL_DARK . ';">What happens next</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;">';
    foreach ($steps as $n => [$t, $d]) {
        $html .= '<tr><td width="40" style="padding:8px 12px 8px 0;vertical-align:top;">
                <div style="width:28px;height:28px;line-height:28px;border-radius:14px;background:' . EMAIL_BLUE_200 . ';text-align:center;font-family:' . $bf . ';font-size:13px;font-weight:700;color:' . EMAIL_DARK . ';">' . ($n + 1) . '</div></td>
            <td style="padding:8px 0;vertical-align:top;"><div style="' . $td . 'font-weight:700;">' . $t . '</div><div style="' . $muted . 'font-size:14px;margin-top:2px;line-height:1.5;">' . $d . '</div></td></tr>';
    }
    $html .= '</table>';

    $html .= '<p style="margin:24px 0 0;' . $muted . 'font-size:14px;line-height:1.6;">Questions about your order? Just reply to this email and mention order #' . $orderNo . '.<br>Thanks for supporting a small studio. It genuinely means a lot.</p>
        <p style="margin:8px 0 0;font-family:' . $hf . ';font-size:17px;font-weight:700;color:' . EMAIL_RUST_DK . ';">Annie</p>';

    return email_wrap(($isPre ? 'Your ' . SITE_NAME . ' pre-order #' : 'Your ' . SITE_NAME . ' order #') . $orderNo, $html, EMAIL_RUST, EMAIL_BLUE_200);
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

/**
 * Pill-shaped CTA button. Table-based so Outlook renders the fill.
 */
function email_button($url, $label, $bg = EMAIL_RUST, $color = '#ffffff')
{
    // Same as the site's main button: mustard pill, a deeper mustard shadow,
    // dark caps label. ($bg/$color are kept for older callers and ignored.)
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
        <tr><td style="background:#D0C25F;border-radius:999px;box-shadow:3px 3px 0 #A99A3A;">
          <a href="' . htmlspecialchars($url) . '" style="display:inline-block;padding:15px 30px;font-family:' . EMAIL_FONT_BODY
            . ';font-size:14px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:' . EMAIL_DARK . ';text-decoration:none;">' . $label . '</a>
        </td></tr></table>';
}

/**
 * Money breakdown rows for custom orders. Each row is [label, amount, style]
 * where style is 'muted', 'accent', or 'total'.
 */
function email_money_rows(array $rows)
{
    $bf   = EMAIL_FONT_BODY;
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 4px;">';
    foreach ($rows as [$label, $amount, $style]) {
        if ($style === 'total') {
            $html .= '<tr>
                <td style="padding:12px 0 4px;border-top:2px solid ' . EMAIL_DARK . ';font-family:' . $bf . ';font-size:18px;font-weight:700;color:' . EMAIL_DARK . ';">' . $label . '</td>
                <td align="right" style="padding:12px 0 4px;border-top:2px solid ' . EMAIL_DARK . ';font-family:' . $bf . ';font-size:18px;font-weight:800;color:' . EMAIL_DARK . ';">$' . number_format($amount, 2) . '</td>
            </tr>';
            continue;
        }
        $color  = $style === 'accent' ? EMAIL_RUST_DK : EMAIL_DARK;
        $weight = $style === 'accent' ? '700' : '500';
        $html .= '<tr>
            <td style="padding:5px 0;font-family:' . $bf . ';font-size:15px;font-weight:' . $weight . ';color:' . ($style === 'muted' ? EMAIL_MUTED : $color) . ';">' . $label . '</td>
            <td align="right" style="padding:5px 0;font-family:' . $bf . ';font-size:15px;font-weight:600;color:' . ($style === 'muted' ? EMAIL_MUTED : $color) . ';">$' . number_format($amount, 2) . '</td>
        </tr>';
    }
    return $html . '</table>';
}

/** One piece, receipt style: photo, name and a small line under it. */
function email_item_row(string $name, string $sub, ?string $imagePath, string $amount = '')
{
    $bf  = EMAIL_FONT_BODY;
    $img = $imagePath
        ? '<img src="' . htmlspecialchars(SITE_URL . $imagePath) . '" width="52" height="52" alt="" style="display:block;width:52px;height:52px;object-fit:cover;border-radius:4px;border:0;">'
        : '<div style="width:52px;height:52px;border-radius:4px;background:#EFE8D8;"></div>';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:2px solid ' . EMAIL_DARK . ';border-bottom:1px solid ' . EMAIL_LINE . ';margin-top:18px;">
        <tr><td width="52" style="padding:14px 14px 14px 0;vertical-align:middle;">' . $img . '</td>
        <td style="padding:14px 0;vertical-align:middle;"><div style="font-family:' . $bf . ';font-size:15px;font-weight:600;color:' . EMAIL_DARK . ';">' . htmlspecialchars($name) . '</div>
            <div style="font-family:' . $bf . ';font-size:13px;color:' . EMAIL_MUTED . ';margin-top:2px;">' . $sub . '</div></td>'
        . ($amount !== '' ? '<td align="right" style="padding:14px 0 14px 12px;font-family:' . $bf . ';font-size:15px;font-weight:600;color:' . EMAIL_DARK . ';white-space:nowrap;">' . $amount . '</td>' : '')
        . '</tr></table>';
}

/**
 * Quote ready for review, with the link to the private quote page.
 * Accent: mustard, shadow: blue-400.
 *
 * @param array $quote custom_quotes row
 */
function build_quote_email(array $quote)
{
    $name    = htmlspecialchars($quote['customer_name'] ?: 'there');
    $title   = htmlspecialchars($quote['title']);
    $total   = (float) $quote['total'];
    $deposit = (float) $quote['deposit_amount'];
    $bf      = EMAIL_FONT_BODY;
    $url     = SITE_URL . '/quote.php?t=' . urlencode($quote['token']);

    $leadBlock = '';
    if (!empty($quote['lead_time'])) {
        $leadBlock = '<div style="margin:0 0 20px;">'
            . '<span style="display:inline-block;font-family:' . $bf . ';font-size:13px;font-weight:700;color:' . EMAIL_DARK
            . ';background:' . EMAIL_MUSTARD . ';border-radius:30px;padding:6px 16px;">'
            . htmlspecialchars($quote['lead_time']) . '</span></div>';
    }

    $descBlock = '';
    if (!empty($quote['description'])) {
        $descBlock = '<p style="margin:0 0 20px;font-family:' . $bf . ';font-size:15px;line-height:1.65;white-space:pre-line;color:#4a4a4a;">'
            . htmlspecialchars($quote['description']) . '</p>';
    }

    $body = email_h1('Your quote is ready')
        . email_p('Hi ' . $name . ' &mdash; here are the details for your custom piece. Have a look and let me know if anything needs adjusting before we begin.')
        . '<div style="margin:24px 0 6px;">' . email_label('The piece') . '</div>'
        . '<p style="margin:0 0 10px;font-family:' . EMAIL_FONT_HEADING . ';font-size:20px;font-weight:800;color:' . EMAIL_DARK . ';">' . $title . '</p>'
        . $leadBlock
        . $descBlock
        . email_money_rows([
            ['Deposit due today', $deposit, 'accent'],
            ['Balance before shipping', $total - $deposit, 'muted'],
            ['Total', $total, 'total'],
        ])
        . email_button($url, 'View your quote &amp; pay the deposit', EMAIL_RUST)
        . email_p('The deposit is non-refundable once paid &mdash; it reserves your spot in the schedule and covers materials for your piece. Full terms are linked on the quote page.', '#7a756a', '13px');

    return email_wrap('Your quote from ' . SITE_NAME, $body, EMAIL_MUSTARD, EMAIL_BLUE_400);
}

/**
 * Deposit received — receipt + what happens next. Restates the non-refundable
 * terms the customer accepted at checkout. Accent: rust, shadow: mustard.
 *
 * @param array $quote custom_quotes row (order_id already set)
 */
function build_deposit_receipt_email(array $quote)
{
    $name    = htmlspecialchars($quote['customer_name'] ?: 'there');
    $title   = htmlspecialchars($quote['title']);
    $total   = (float) $quote['total'];
    $deposit = (float) $quote['deposit_amount'];
    $balance = $total - $deposit;

    $lead = !empty($quote['lead_time'])
        ? ' Your estimated timeline is <strong>' . htmlspecialchars($quote['lead_time']) . '</strong>.'
        : '';

    $body = email_h1('Deposit received &mdash; thank you!')
        . email_p('Thanks, ' . $name . '. Your deposit for <strong>' . $title . '</strong> has been received and your piece is now in the queue.' . $lead)
        . email_money_rows([
            ['Deposit paid', $deposit, 'muted'],
            ['Balance remaining', $balance, 'accent'],
            ['Total', $total, 'total'],
        ])
        . '<div style="margin:28px 0 6px;">' . email_label('What happens next') . '</div>'
        . email_p('I&rsquo;ll get to work and keep you posted as it comes together. When your piece is finished, you&rsquo;ll get an email with photos and a link to pay the remaining balance. It ships once that&rsquo;s settled.')
        . email_p('As noted when you paid, the deposit is non-refundable. Because this piece is made specifically for you, it can&rsquo;t be returned or refunded. If anything changes on your end, please reach out as early as you can.', '#7a756a', '13px');

    return email_wrap('Deposit received for your ' . SITE_NAME . ' commission', $body, EMAIL_RUST, EMAIL_MUSTARD);
}

/**
 * The piece is finished — balance due before it ships.
 * Accent: blue-400, shadow: rust.
 *
 * @param array  $quote custom_quotes row
 * @param float  $balance amount still owed
 * @param string $note  optional personal note from Annie
 */
function build_balance_due_email(array $quote, $balance, $note = '')
{
    $name  = htmlspecialchars($quote['customer_name'] ?: 'there');
    $title = htmlspecialchars($quote['title']);
    $url   = SITE_URL . '/quote.php?t=' . urlencode($quote['token']);

    $noteBlock = '';
    if (trim((string) $note) !== '') {
        $noteBlock = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;">
             <tr><td style="background:' . EMAIL_NEUTRAL . ';border-left:4px solid ' . EMAIL_MUSTARD . ';border-radius:8px;padding:18px 20px;">
               <p style="margin:0;font-family:' . EMAIL_FONT_BODY . ';font-size:15px;line-height:1.65;white-space:pre-line;color:' . EMAIL_DARK . ';">'
                . htmlspecialchars($note) . '</p>
             </td></tr></table>';
    }

    $body = email_h1('Your piece is ready')
        . email_p('Hi ' . $name . ' &mdash; <strong>' . $title . '</strong> is finished. Once the remaining balance is settled I&rsquo;ll get it packed up and on its way to you.')
        . $noteBlock
        . email_money_rows([
            ['Deposit already paid', (float) $quote['deposit_amount'], 'muted'],
            ['Balance due now', (float) $balance, 'accent'],
            ['Total', (float) $quote['total'], 'total'],
        ])
        . email_button($url, 'Pay the balance', EMAIL_RUST)
        . email_p('Once this is paid you&rsquo;ll get a shipping confirmation with tracking as soon as it&rsquo;s handed to the carrier.', '#7a756a', '13px');

    return email_wrap('Your ' . SITE_NAME . ' piece is ready', $body, EMAIL_BLUE_400, EMAIL_RUST);
}

/**
 * Balance settled — paid in full, shipping next. Accent: mustard, shadow: blue-400.
 */
function build_balance_receipt_email(array $quote)
{
    $name  = htmlspecialchars($quote['customer_name'] ?: 'there');
    $title = htmlspecialchars($quote['title']);

    $body = email_h1('Paid in full &mdash; thank you!')
        . email_p('Thanks, ' . $name . '. Your balance for <strong>' . $title . '</strong> has been received and it&rsquo;s all settled.')
        . email_money_rows([
            ['Total paid', (float) $quote['total'], 'muted'],
            ['Balance remaining', 0.0, 'muted'],
        ])
        . email_p('Your piece is being packed now. You&rsquo;ll get a separate email with tracking as soon as it ships.')
        . email_p('Thank you for commissioning something one of a kind &mdash; it means a great deal.', '#7a756a', '13px');

    return email_wrap('Payment received &mdash; your ' . SITE_NAME . ' piece ships soon', $body, EMAIL_MUSTARD, EMAIL_BLUE_400);
}

/**
 * Tells a pre-order buyer their batch will ship later than promised, and that
 * they can cancel for a full refund (US mail-order rule). Accent: mustard.
 */
function build_preorder_delay_email(array $order, string $productName, string $newDate, string $note = '')
{
    $name = htmlspecialchars($order['customer_name'] ?: 'there');
    $old  = !empty($order['preorder_ship_date']) ? date('F j', strtotime($order['preorder_ship_date'])) : null;
    $new  = date('F j, Y', strtotime($newDate));

    $body = email_h1('A new ship date for your pre-order')
        . email_p('Hi ' . $name . ', your ' . htmlspecialchars($productName) . ' (pre-order #' . (int) $order['id'] . ') is taking longer than I planned'
            . ($old ? '. It was due to ship by ' . $old . ', and' : ', and') . ' it will now ship by <strong>' . $new . '</strong>.')
        . ($note !== '' ? email_p(nl2br(htmlspecialchars(trim($note)))) : '')
        . email_p('If you&rsquo;d rather not wait, just reply to this email and I&rsquo;ll cancel your order and refund you in full. Otherwise there&rsquo;s nothing you need to do.')
        . email_p('Thanks for your patience. -Annie');

    return email_wrap('New ship date for your ' . SITE_NAME . ' pre-order', $body, EMAIL_MUSTARD, EMAIL_RUST);
}

/**
 * The batch is ready: pay the balance so the piece can ship.
 */
function build_preorder_balance_email(array $order, string $productName, string $payUrl, string $note = '', ?string $imagePath = null)
{
    $first   = trim(explode(' ', (string) ($order['customer_name'] ?? ''))[0]);
    $balance = (float) $order['balance_due'];
    $body = email_h1('It&rsquo;s ready' . ($first !== '' ? ', ' . htmlspecialchars($first) : '') . '.')
        . email_p('Your ' . htmlspecialchars($productName) . ' is made and ready to ship. Pay the rest below and it goes out within a few days.', EMAIL_DARK, '16px')
        . ($note !== '' ? email_p(nl2br(htmlspecialchars(trim($note)))) : '')
        . email_item_row($productName, 'Pre-order #' . (int) $order['id'], $imagePath)
        . email_money_rows([
            ['Total', (float) $order['total'], 'muted'],
            ['Deposit paid', (float) $order['amount_paid'], 'muted'],
            ['Left to pay', $balance, 'total'],
        ])
        . email_button($payUrl, 'Pay $' . number_format($balance, 2))
        . '<p style="margin:0;font-family:' . EMAIL_FONT_BODY . ';font-size:13.5px;line-height:1.6;color:' . EMAIL_MUTED . ';">Secure payment through Stripe. Questions? Just reply to this email.</p>'
        . '<p style="margin:14px 0 0;font-family:' . EMAIL_FONT_HEADING . ';font-size:17px;font-weight:700;color:' . EMAIL_RUST_DK . ';">Annie</p>';

    return email_wrap('Your ' . SITE_NAME . ' pre-order is ready', $body);
}

/** Balance received; the piece ships next. */
function build_preorder_balance_receipt_email(array $order, string $productName, float $paid, ?string $imagePath = null)
{
    $first = trim(explode(' ', (string) ($order['customer_name'] ?? ''))[0]);
    $body = email_h1('Paid in full. Thank you' . ($first !== '' ? ', ' . htmlspecialchars($first) : '') . '.')
        . email_p('Your ' . htmlspecialchars($productName) . ' is paid for. I&rsquo;ll pack it up and email you tracking as soon as it ships.', EMAIL_DARK, '16px')
        . email_item_row($productName, 'Pre-order #' . (int) $order['id'], $imagePath)
        . email_money_rows([
            ['Paid today', $paid, 'muted'],
            ['Paid in total', (float) $order['total'], 'total'],
        ])
        . '<p style="margin:18px 0 0;font-family:' . EMAIL_FONT_HEADING . ';font-size:17px;font-weight:700;color:' . EMAIL_RUST_DK . ';">Annie</p>';

    return email_wrap('Balance received for your ' . SITE_NAME . ' pre-order', $body);
}
