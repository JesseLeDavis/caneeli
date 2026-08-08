<?php
/**
 * Balance actions for a custom order: request it from the customer, or record
 * that it was paid outside Stripe.
 *
 * Both routes end in the same place — balance_due 0 and status 'paid' — which
 * is what releases the fulfillment gate in update-order-status.php.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/quotes.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/emails/templates.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(403);
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$id) {
    header('Location: /admin/orders.php');
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order || empty($order['quote_id'])) {
    header('Location: /admin/order-detail.php?id=' . $id);
    exit;
}

$qstmt = $pdo->prepare("SELECT * FROM custom_quotes WHERE id = ?");
$qstmt->execute([(int) $order['quote_id']]);
$quote = $qstmt->fetch();

if (!$quote) {
    header('Location: /admin/order-detail.php?id=' . $id);
    exit;
}

$balance = (float) $order['balance_due'];

// --- Request the balance from the customer ------------------------------
if ($action === 'request') {
    if ($balance <= 0) {
        header('Location: /admin/order-detail.php?id=' . $id);
        exit;
    }

    $note = trim($_POST['note'] ?? '');

    $sent = send_mail(
        $quote['customer_email'],
        $quote['customer_name'],
        'Your ' . SITE_NAME . ' piece is ready',
        build_balance_due_email($quote, $balance, $note),
        '',
        OWNER_EMAIL !== '' ? OWNER_EMAIL : null
    );

    // Advance the state whether or not the email got out. "The balance has been
    // requested" is a business fact; the email is just how we told them. Tying
    // the two together would leave Annie stuck if SMTP hiccups — she couldn't
    // even share the link herself, because the pay page requires this status.
    // Re-requestable on purpose, so she can nudge without any state juggling.
    $pdo->prepare("
        UPDATE custom_quotes
           SET status = CASE WHEN status = 'deposit_paid' THEN 'balance_requested' ELSE status END,
               balance_email_sent_at = CASE WHEN ? THEN CURRENT_TIMESTAMP ELSE balance_email_sent_at END
         WHERE id = ?
    ")->execute([$sent ? 1 : 0, (int) $quote['id']]);

    if ($sent) {
        header('Location: /admin/order-detail.php?id=' . $id . '&balance=' . urlencode('Balance request sent to ' . $quote['customer_email']));
        exit;
    }

    header('Location: /admin/order-detail.php?id=' . $id . '&balance_err=' . urlencode("The balance is marked as requested, but the email couldn't be sent — check the SMTP settings. Copy the customer's quote link and send it over yourself in the meantime."));
    exit;
}

// --- Record a balance paid outside Stripe -------------------------------
if ($action === 'manual') {
    if ($balance <= 0) {
        header('Location: /admin/order-detail.php?id=' . $id);
        exit;
    }

    $how = trim($_POST['how'] ?? '');

    try {
        $pdo->beginTransaction();

        // Stamp balance_paid_manually_at so this is never mistaken later for a
        // balance Stripe actually settled.
        $pdo->prepare("
            UPDATE orders
               SET amount_paid = total,
                   balance_due = 0,
                   status = 'paid',
                   balance_paid_manually_at = CURRENT_TIMESTAMP
             WHERE id = ?
        ")->execute([$id]);

        // Leave a human-readable trail in the notes Annie already reads.
        $stamp = date('M j, Y');
        $line  = 'Balance of $' . number_format($balance, 2) . ' marked paid manually on ' . $stamp
               . ($how !== '' ? ' (' . $how . ')' : '') . '.';
        $notes = trim((string) ($order['notes'] ?? ''));
        $pdo->prepare("UPDATE orders SET notes = ? WHERE id = ?")
            ->execute([$notes === '' ? $line : $notes . "\n" . $line, $id]);

        $pdo->prepare("UPDATE custom_quotes SET status = 'paid' WHERE id = ?")
            ->execute([(int) $quote['id']]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('order-balance: manual settle failed for order ' . $id . ': ' . $e->getMessage());
        header('Location: /admin/order-detail.php?id=' . $id . '&balance_err=' . urlencode("Couldn't record that payment — nothing was changed."));
        exit;
    }

    header('Location: /admin/order-detail.php?id=' . $id . '&balance=' . urlencode('Balance recorded as paid. This order can be fulfilled now.'));
    exit;
}

header('Location: /admin/order-detail.php?id=' . $id);
exit;
