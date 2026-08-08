<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/quotes.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/emails/templates.php';

$pdo = getDB();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header('Location: /admin/custom-orders.php');
    exit;
}

$errors = [];
$flash  = isset($_GET['msg']) ? (string) $_GET['msg'] : '';

// --- Actions ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM custom_quotes WHERE id = ?");
    $stmt->execute([$id]);
    $q = $stmt->fetch();

    if (!$q) {
        header('Location: /admin/custom-orders.php');
        exit;
    }

    if ($action === 'send') {
        // Sending is re-sendable on purpose: emails get lost, and Annie should
        // be able to nudge without any state juggling.
        $sent = send_mail(
            $q['customer_email'],
            $q['customer_name'],
            'Your quote from ' . SITE_NAME,
            build_quote_email($q),
            '',
            OWNER_EMAIL !== '' ? OWNER_EMAIL : null
        );

        // Advance the state whether or not the email got out — otherwise a
        // failed send leaves the quote in draft, where the customer link shows
        // "not found", so Annie can't even share it manually as a fallback.
        $pdo->prepare("
            UPDATE custom_quotes
               SET status = CASE WHEN status = 'draft' THEN 'sent' ELSE status END,
                   quote_email_sent_at = CASE WHEN ? THEN CURRENT_TIMESTAMP ELSE quote_email_sent_at END
             WHERE id = ?
        ")->execute([$sent ? 1 : 0, $id]);

        if ($sent) {
            header('Location: /admin/quote-detail.php?id=' . $id . '&msg=' . urlencode('Quote sent to ' . $q['customer_email']));
            exit;
        }

        header('Location: /admin/quote-detail.php?id=' . $id . '&err=' . urlencode("The quote is marked as sent, but the email couldn't go out — check the SMTP settings. The customer link below works now, so you can send it over yourself in the meantime."));
        exit;
    }

    if ($action === 'cancel' && quote_is_editable($q)) {
        $pdo->prepare("UPDATE custom_quotes SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        header('Location: /admin/quote-detail.php?id=' . $id . '&msg=' . urlencode('Quote cancelled.'));
        exit;
    }

    if ($action === 'reopen' && $q['status'] === 'cancelled') {
        $pdo->prepare("UPDATE custom_quotes SET status = 'draft' WHERE id = ?")->execute([$id]);
        header('Location: /admin/quote-detail.php?id=' . $id . '&msg=' . urlencode('Quote reopened as a draft.'));
        exit;
    }

    header('Location: /admin/quote-detail.php?id=' . $id);
    exit;
}

if (isset($_GET['err'])) {
    $errors[] = (string) $_GET['err'];
}

// --- Load ---------------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM custom_quotes WHERE id = ?");
$stmt->execute([$id]);
$quote = $stmt->fetch();

if (!$quote) {
    http_response_code(404);
    $pageTitle = 'Quote not found';
    $activeNav = 'custom';
    require __DIR__ . '/layout-top.php';
    echo '<div class="container"><div class="page-header"><h1>Quote not found</h1></div>'
       . '<p><a href="/admin/custom-orders.php">&larr; All custom orders</a></p></div></body></html>';
    exit;
}

$order = null;
if (!empty($quote['order_id'])) {
    $ostmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $ostmt->execute([(int) $quote['order_id']]);
    $order = $ostmt->fetch() ?: null;
}

[$statusLabel, $statusMod] = quote_status_meta($quote['status']);
$link    = quote_url($quote);
$total   = (float) $quote['total'];
$deposit = (float) $quote['deposit_amount'];
$balance = $order !== null && $order['balance_due'] !== null
    ? (float) $order['balance_due']
    : $total - $deposit;

$pageTitle = $quote['title'];
$activeNav = 'custom';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <p style="margin:0 0 6px"><a href="/admin/custom-orders.php" style="font-size:13px">&larr; All custom orders</a></p>

    <div class="page-header">
        <h1><?php echo htmlspecialchars($quote['title']); ?></h1>
        <span class="badge-status badge-status--<?php echo $statusMod; ?>"><?php echo $statusLabel; ?></span>
    </div>

    <?php foreach ($errors as $e): ?>
        <p class="error"><?php echo htmlspecialchars($e); ?></p>
    <?php endforeach; ?>
    <?php if ($flash): ?><p class="success"><?php echo htmlspecialchars($flash); ?></p><?php endif; ?>

    <div class="order-grid" style="display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:22px;align-items:start">

        <div class="order-card">
            <h3 class="order-card__title">Quote</h3>

            <div style="margin-bottom:16px">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:1.2px;color:rgba(45,45,45,0.5);margin-bottom:4px">Customer</div>
                <?php echo htmlspecialchars($quote['customer_name']); ?><br>
                <a href="mailto:<?php echo htmlspecialchars($quote['customer_email']); ?>"><?php echo htmlspecialchars($quote['customer_email']); ?></a>
            </div>

            <?php if (!empty($quote['lead_time'])): ?>
                <div style="margin-bottom:16px">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:1.2px;color:rgba(45,45,45,0.5);margin-bottom:4px">Lead time</div>
                    <?php echo htmlspecialchars($quote['lead_time']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($quote['description'])): ?>
                <div style="margin-bottom:16px">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:1.2px;color:rgba(45,45,45,0.5);margin-bottom:4px">Description</div>
                    <div style="white-space:pre-line;line-height:1.6"><?php echo htmlspecialchars($quote['description']); ?></div>
                </div>
            <?php endif; ?>

            <?php // Flex rows, not a <table> — a bare table here inherits the global
                  // admin table styles (white card + shadow, rust row dividers, hover
                  // bar, and an overflow:hidden that clipped the Total row). Same
                  // markup the orders page uses for its money rows. ?>
            <div class="order-totals">
                <div class="order-totals__row">
                    <span>Deposit</span>
                    <span>$<?php echo number_format($deposit, 2); ?></span>
                </div>
                <div class="order-totals__row">
                    <span>Balance</span>
                    <span>$<?php echo number_format($balance, 2); ?></span>
                </div>
                <div class="order-totals__row order-totals__row--grand">
                    <span>Total</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>

        <div>
            <div class="order-card" style="margin-bottom:18px">
                <h3 class="order-card__title">Customer link</h3>
                <p style="margin:0 0 10px;font-size:12px;color:rgba(45,45,45,0.55)">
                    Anyone with this link can pay the deposit — it isn't password protected.
                </p>
                <input type="text" readonly id="quote-link" value="<?php echo htmlspecialchars($link); ?>"
                       onclick="this.select()"
                       style="width:100%;margin-bottom:10px;font-size:12px">
                <button type="button" class="btn btn-secondary" style="width:100%" onclick="
                    navigator.clipboard.writeText(document.getElementById('quote-link').value);
                    this.textContent='Copied!';
                    setTimeout(()=>this.textContent='Copy link',1500);
                ">Copy link</button>
                <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" rel="noopener"
                   class="btn btn-secondary" style="width:100%;text-align:center;margin-top:8px">Preview as customer &rarr;</a>
            </div>

            <div class="order-card">
                <h3 class="order-card__title">Actions</h3>

                <?php if (in_array($quote['status'], ['draft', 'sent'], true)): ?>
                    <form method="POST" style="margin-bottom:10px">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="send">
                        <button type="submit" class="btn btn-primary" style="width:100%">
                            <?php echo $quote['status'] === 'draft' ? 'Send quote to customer' : 'Resend quote'; ?>
                        </button>
                    </form>
                    <?php if (!empty($quote['quote_email_sent_at'])): ?>
                        <p style="margin:0 0 12px;font-size:12px;color:rgba(45,45,45,0.55)">
                            Last sent <?php echo date('M j, Y g:ia', strtotime($quote['quote_email_sent_at'])); ?>
                        </p>
                    <?php endif; ?>

                    <a href="/admin/custom-orders.php?edit=<?php echo (int) $quote['id']; ?>"
                       class="btn btn-secondary" style="width:100%;text-align:center;margin-bottom:10px">Edit quote</a>

                    <form method="POST" onsubmit="return confirm('Cancel this quote?')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-secondary" style="width:100%">Cancel quote</button>
                    </form>

                <?php elseif ($quote['status'] === 'cancelled'): ?>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reopen">
                        <button type="submit" class="btn btn-secondary" style="width:100%">Reopen as draft</button>
                    </form>

                <?php else: ?>
                    <p style="margin:0 0 12px;font-size:13px;color:rgba(45,45,45,0.6)">
                        The deposit has been paid, so this quote is locked. Manage the rest from the order.
                    </p>
                    <?php if ($order): ?>
                        <a href="/admin/order-detail.php?id=<?php echo (int) $order['id']; ?>"
                           class="btn btn-primary" style="width:100%;text-align:center">Open order #<?php echo (int) $order['id']; ?></a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
