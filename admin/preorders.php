<?php
/**
 * Pre-orders: one card per pre-order batch, with how many spots are taken,
 * who's waiting, and a way to move the ship date that emails everyone still
 * waiting (they can reply to cancel for a refund).
 *
 * The orders themselves are ordinary orders (is_preorder = 1); they're
 * shipped from the order page like any other.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/product_status.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/emails/templates.php';
require_once __DIR__ . '/../includes/preorders.php';

$pdo    = getDB();
$errors = [];
$flash  = isset($_GET['msg']) ? (string) $_GET['msg'] : '';

// Orders still waiting on a batch: paid (in full or a deposit), not shipped,
// not cancelled.
$waitingSql = "SELECT DISTINCT o.* FROM orders o
    JOIN order_items i ON i.order_id = o.id
    WHERE o.is_preorder = 1 AND o.status IN ('paid', 'deposit_paid') AND i.product_id = ?";

// ── Request every balance still owed on a batch ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_balances') {
    $pid = (int) ($_POST['product_id'] ?? 0);
    if (!csrf_verify()) {
        $errors[] = 'Invalid form submission.';
    } else {
        $w = $pdo->prepare($waitingSql . " AND o.balance_due > 0");
        $w->execute([$pid]);
        $sent = 0; $failed = [];
        foreach ($w->fetchAll() as $order) {
            if (preorder_request_balance($pdo, $order, trim($_POST['note'] ?? ''))) $sent++;
            else $failed[] = '#' . $order['id'];
        }
        $msg = 'Balance requests emailed to ' . $sent . ' ' . ($sent === 1 ? 'buyer' : 'buyers') . '.';
        if ($failed) $msg .= ' Not sent to ' . implode(', ', $failed) . ": open each order to copy its pay link and send it yourself.";
        header('Location: /admin/preorders.php?msg=' . urlencode($msg));
        exit;
    }
}

// ── Move a batch's ship date ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move_date') {
    $pid     = (int) ($_POST['product_id'] ?? 0);
    $newDate = trim($_POST['new_date'] ?? '');
    $note    = trim($_POST['note'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$pid]);
    $product = $stmt->fetch();

    if (!csrf_verify()) {
        $errors[] = 'Invalid form submission.';
    } elseif (!$product) {
        $errors[] = 'That product no longer exists.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) || $newDate <= date('Y-m-d')) {
        $errors[] = 'Pick a new ship date in the future.';
    } elseif ($product['preorder_ship_date'] && $newDate <= $product['preorder_ship_date']) {
        $errors[] = 'The new date has to be later than the current one (' . date('M j', strtotime($product['preorder_ship_date'])) . ').';
    } else {
        $pdo->prepare("UPDATE products SET preorder_ship_date = ? WHERE id = ?")->execute([$newDate, $pid]);

        $w = $pdo->prepare($waitingSql);
        $w->execute([$pid]);
        $sent = 0; $failed = [];
        $mark = $pdo->prepare("UPDATE orders SET preorder_ship_date = ?, preorder_delay_email_sent_at = CURRENT_TIMESTAMP WHERE id = ?");
        foreach ($w->fetchAll() as $order) {
            $ok = $order['customer_email'] !== '' && send_mail(
                $order['customer_email'],
                $order['customer_name'] ?? '',
                'New ship date for your ' . SITE_NAME . ' pre-order',
                build_preorder_delay_email($order, $product['name'], $newDate, $note),
                '',
                null
            );
            // The order's promised date moves either way; the timestamp only
            // records that the buyer was actually told.
            $pdo->prepare("UPDATE orders SET preorder_ship_date = ? WHERE id = ?")->execute([$newDate, $order['id']]);
            if ($ok) { $mark->execute([$newDate, $order['id']]); $sent++; }
            else     { $failed[] = '#' . $order['id']; }
        }

        $msg = 'Ship date moved to ' . date('M j', strtotime($newDate)) . '. Emailed ' . $sent . ' ' . ($sent === 1 ? 'buyer' : 'buyers') . '.';
        if ($failed) $msg .= ' Not sent to ' . implode(', ', $failed) . ': email them yourself.';
        header('Location: /admin/preorders.php?msg=' . urlencode($msg));
        exit;
    }
}

// ── Batches ───────────────────────────────────────────────────────────
// Every pre-order product, plus any product that still has buyers waiting
// (e.g. Annie switched it back to Active once the batch was made).
$products = $pdo->query("
    SELECT p.* FROM products p
    WHERE p.status = 'preorder'
       OR p.id IN (SELECT i.product_id FROM order_items i JOIN orders o ON o.id = i.order_id
                   WHERE o.is_preorder = 1 AND o.status IN ('paid', 'deposit_paid'))
    ORDER BY p.preorder_ship_date IS NULL, p.preorder_ship_date, p.name
")->fetchAll();

$ordersStmt = $pdo->prepare("
    SELECT o.id, o.customer_name, o.customer_email, o.status, o.created_at, o.preorder_delay_email_sent_at,
           o.total, o.balance_due, o.preorder_balance_requested_at, SUM(i.quantity) AS qty
      FROM orders o JOIN order_items i ON i.order_id = o.id
     WHERE o.is_preorder = 1 AND i.product_id = ?
     GROUP BY o.id
     ORDER BY o.created_at
");

$pageTitle = 'Pre-orders';
$activeNav = 'preorders';
require __DIR__ . '/layout-top.php';
?>

<div class="container ui-page">
    <div class="ui-head">
        <div>
            <h1>Pre-orders</h1>
            <p class="ui-head__sub">Each batch you're taking orders for, who's in it, and what's still owed.</p>
        </div>
    </div>

    <?php foreach ($errors as $e): ?>
        <p class="error"><?php echo htmlspecialchars($e); ?></p>
    <?php endforeach; ?>
    <?php if ($flash): ?><p class="success"><?php echo htmlspecialchars($flash); ?></p><?php endif; ?>

    <?php if (!$products): ?>
        <div class="form-card">
            <p style="margin:0">No pre-orders yet. To open one, edit a product, set its status to <strong>Pre-order</strong>, pick a ship-by date, and set the stock quantity to the number of spots in the batch.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($products as $p):
        $ordersStmt->execute([$p['id']]);
        $orders  = $ordersStmt->fetchAll();
        $live    = array_filter($orders, fn($o) => $o['status'] !== 'cancelled');
        $taken   = array_sum(array_column($live, 'qty'));
        $isWaiting = fn($o) => in_array($o['status'], ['paid', 'deposit_paid'], true);
        $waiting = array_sum(array_map(fn($o) => $isWaiting($o) ? (int) $o['qty'] : 0, $orders));
        $owed    = array_sum(array_map(fn($o) => $isWaiting($o) ? (float) $o['balance_due'] : 0, $orders));
        $owedN   = count(array_filter($orders, fn($o) => $isWaiting($o) && (float) $o['balance_due'] > 0));
        $paidIn  = array_sum(array_map(fn($o) => $o['status'] === 'cancelled' ? 0 : (float) $o['total'] - (float) $o['balance_due'], $orders));
        $deposit = preorder_deposit_for($p);
        $shipped = array_sum(array_map(fn($o) => $o['status'] === 'fulfilled' ? (int) $o['qty'] : 0, $orders));
        $isOpen  = $p['status'] === 'preorder';
        $spots   = $isOpen ? $taken + (int) $p['stock_qty'] : null;
        $late    = $p['preorder_ship_date'] && $p['preorder_ship_date'] < date('Y-m-d') && $waiting;
    ?>
    <section class="form-card preorder-batch">
        <div class="preorder-batch__head">
            <div>
                <h2 class="preorder-batch__title">
                    <a href="/admin/edit-product.php?id=<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                </h2>
                <p class="preorder-batch__meta">
                    <?php if ($p['preorder_ship_date']): ?>
                        Ships by <strong<?php echo $late ? ' style="color:var(--danger)"' : ''; ?>><?php echo date('M j, Y', strtotime($p['preorder_ship_date'])); ?></strong><?php echo $late ? ' (past due)' : ''; ?>
                    <?php else: ?>
                        <strong style="color:var(--danger)">No ship date set</strong>
                    <?php endif; ?>
                    · $<?php echo number_format((float) $p['price'], 2); ?> each<?php if ($deposit): ?>, $<?php echo number_format($deposit, 2); ?> deposit<?php endif; ?>
                    <?php if (!$isOpen): ?> · <span class="badge-status badge-status--<?php echo $p['status']; ?>"><?php echo product_status_label($p['status']); ?></span><?php endif; ?>
                </p>
            </div>
            <a href="/pages/shop/product.php?id=<?php echo (int) $p['id']; ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">View page</a>
        </div>

        <div class="preorder-batch__stats">
            <div><span class="preorder-batch__num"><?php echo $taken; ?><?php if ($spots !== null): ?><small> / <?php echo $spots; ?></small><?php endif; ?></span><span>spots taken</span></div>
            <div><span class="preorder-batch__num"><?php echo $waiting; ?></span><span>waiting to ship</span></div>
            <div><span class="preorder-batch__num"><?php echo $shipped; ?></span><span>shipped</span></div>
            <div><span class="preorder-batch__num">$<?php echo number_format($paidIn, 0); ?></span><span>paid in<?php if ($owed > 0): ?>, <strong style="color:var(--rust-dark)">$<?php echo number_format($owed, 0); ?> still owed</strong><?php endif; ?></span></div>
        </div>
        <?php if ($spots): ?>
            <div class="preorder-batch__bar" role="img" aria-label="<?php echo $taken . ' of ' . $spots; ?> spots taken"><span style="width:<?php echo min(100, round($taken / $spots * 100)); ?>%"></span></div>
        <?php endif; ?>

        <?php if ($orders): ?>
        <table class="table--cards">
            <thead><tr><th>Order</th><th>Buyer</th><th>Qty</th><th>Ordered</th><th>Owed</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td data-label="Order"><a class="ui-order-link" href="/admin/order-detail.php?id=<?php echo (int) $o['id']; ?>" aria-label="Open order #<?php echo (int) $o['id']; ?>">#<?php echo (int) $o['id']; ?><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></a></td>
                    <td data-label="Buyer"><a class="ui-buyer-link" href="/admin/order-detail.php?id=<?php echo (int) $o['id']; ?>"><?php echo htmlspecialchars($o['customer_name']); ?></a>
                        <div style="font-size:12px;color:var(--muted)"><?php echo htmlspecialchars($o['customer_email']); ?></div></td>
                    <td data-label="Qty"><?php echo (int) $o['qty']; ?></td>
                    <td data-label="Ordered"><?php echo date('M j', strtotime($o['created_at'])); ?>
                        <?php if ($o['preorder_delay_email_sent_at']): ?><div style="font-size:12px;color:var(--muted)">Told about delay <?php echo date('M j', strtotime($o['preorder_delay_email_sent_at'])); ?></div><?php endif; ?></td>
                    <td data-label="Owed"><?php if ((float) $o['balance_due'] > 0): ?><strong style="color:var(--rust-dark)">$<?php echo number_format((float) $o['balance_due'], 2); ?></strong>
                        <div style="font-size:12px;color:var(--muted)"><?php echo $o['preorder_balance_requested_at'] ? 'Requested ' . date('M j', strtotime($o['preorder_balance_requested_at'])) : 'Not requested yet'; ?></div>
                        <?php else: ?>Paid in full<?php endif; ?></td>
                    <td data-label="Status">
                        <?php
                        $lbl = ['paid' => 'Paid in full', 'deposit_paid' => 'Deposit paid', 'fulfilled' => 'Shipped', 'cancelled' => 'Cancelled'][$o['status']] ?? ucfirst($o['status']);
                        ?>
                        <?php echo admin_status_badge($o['status'] === 'paid' ? 'paid' : $o['status'], $lbl); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="preorder-batch__empty">No pre-orders for this batch yet.</p>
        <?php endif; ?>

        <?php if ($owedN): ?>
        <details class="preorder-batch__delay">
            <summary>Batch ready? Request balances</summary>
            <form method="POST" onsubmit="return confirm('Email <?php echo $owedN; ?> <?php echo $owedN === 1 ? 'buyer' : 'buyers'; ?> a link to pay what they owe?')">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="request_balances">
                <input type="hidden" name="product_id" value="<?php echo (int) $p['id']; ?>">
                <label for="bn-<?php echo (int) $p['id']; ?>">A note to buyers <span style="font-weight:400;opacity:.6">(optional)</span></label>
                <textarea name="note" id="bn-<?php echo (int) $p['id']; ?>" rows="3" placeholder="They came out of the kiln this week and I'm so happy with the glazes."></textarea>
                <p class="field-hint" style="margin-top:0">Emails the <?php echo $owedN; ?> <?php echo $owedN === 1 ? 'buyer' : 'buyers'; ?> who still owe money ($<?php echo number_format($owed, 2); ?> in all) a link to pay. Sending again just re-sends the link. Each order can ship once it's paid.</p>
                <button type="submit" class="btn btn-primary">Email balance requests</button>
            </form>
        </details>
        <?php endif; ?>

        <?php if ($waiting): ?>
        <details class="preorder-batch__delay">
            <summary>Running late? Move the ship date</summary>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="move_date">
                <input type="hidden" name="product_id" value="<?php echo (int) $p['id']; ?>">
                <label for="nd-<?php echo (int) $p['id']; ?>">New ship-by date</label>
                <input type="date" name="new_date" id="nd-<?php echo (int) $p['id']; ?>" required
                       min="<?php echo date('Y-m-d', max(strtotime('+1 day'), $p['preorder_ship_date'] ? strtotime($p['preorder_ship_date'] . ' +1 day') : 0)); ?>">
                <label for="note-<?php echo (int) $p['id']; ?>">A note to buyers <span style="font-weight:400;opacity:.6">(optional: what happened)</span></label>
                <textarea name="note" id="note-<?php echo (int) $p['id']; ?>" rows="3" placeholder="The kiln needed a repair, so this firing is a couple of weeks behind."></textarea>
                <p class="field-hint" style="margin-top:0">Emails the <?php echo count(array_filter($orders, $isWaiting)); ?> buyers still waiting with the new date, and tells them they can reply to cancel for a full refund.</p>
                <button type="submit" class="btn btn-primary">Move date and email buyers</button>
            </form>
        </details>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
</div>

</body>
</html>
