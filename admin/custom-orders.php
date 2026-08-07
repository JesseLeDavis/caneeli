<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/quotes.php';

$pdo    = getDB();
$errors = [];
$flash  = isset($_GET['msg']) ? (string) $_GET['msg'] : '';

// Deposit default. Annie can override per quote, but this is the house rate.
const DEFAULT_DEPOSIT = 300.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid form submission.';
    } else {
        $edit_id            = (int) ($_POST['id'] ?? 0);
        [$errors, $clean]   = quote_validate($_POST);

        // Re-check editability server-side: the form is hidden for locked
        // quotes, but a stale tab could still POST.
        if ($edit_id && !$errors) {
            $stmt = $pdo->prepare("SELECT status FROM custom_quotes WHERE id = ?");
            $stmt->execute([$edit_id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                $errors[] = 'That quote no longer exists.';
            } elseif (!quote_is_editable($existing)) {
                $errors[] = 'This quote has already been paid against and can no longer be edited.';
            }
        }

        if (!$errors) {
            if ($edit_id) {
                $pdo->prepare("
                    UPDATE custom_quotes
                       SET customer_name = ?, customer_email = ?, title = ?, description = ?,
                           lead_time = ?, total = ?, deposit_amount = ?
                     WHERE id = ?
                ")->execute([
                    $clean['customer_name'], $clean['customer_email'], $clean['title'],
                    $clean['description'], $clean['lead_time'], $clean['total'],
                    $clean['deposit_amount'], $edit_id,
                ]);
                header('Location: /admin/quote-detail.php?id=' . $edit_id . '&msg=' . urlencode('Quote updated.'));
                exit;
            }

            $pdo->prepare("
                INSERT INTO custom_quotes
                    (token, customer_name, customer_email, title, description, lead_time, total, deposit_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')
            ")->execute([
                quote_new_token(), $clean['customer_name'], $clean['customer_email'],
                $clean['title'], $clean['description'], $clean['lead_time'],
                $clean['total'], $clean['deposit_amount'],
            ]);
            $new_id = (int) $pdo->lastInsertId();
            header('Location: /admin/quote-detail.php?id=' . $new_id . '&msg=' . urlencode('Quote created — review it, then send.'));
            exit;
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM custom_quotes WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
    if ($editing && !quote_is_editable($editing)) {
        header('Location: /admin/quote-detail.php?id=' . (int) $editing['id']);
        exit;
    }
}

$quotes = $pdo->query("
    SELECT q.*, o.balance_due
      FROM custom_quotes q
      LEFT JOIN orders o ON o.id = q.order_id
     ORDER BY FIELD(q.status, 'balance_requested','deposit_paid','sent','draft','paid','cancelled'), q.created_at DESC
")->fetchAll();

$pageTitle = 'Custom Orders';
$activeNav = 'custom';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Custom Orders</h1>
    </div>

    <?php foreach ($errors as $e): ?>
        <p class="error"><?php echo htmlspecialchars($e); ?></p>
    <?php endforeach; ?>
    <?php if ($flash): ?><p class="success"><?php echo htmlspecialchars($flash); ?></p><?php endif; ?>

    <details class="form-card" style="margin-bottom:24px" <?php echo ($editing || $errors) ? 'open' : ''; ?>>
        <summary style="cursor:pointer;font-family:'Syne',sans-serif;font-weight:700;font-size:17px;list-style:none">
            <?php echo $editing ? 'Edit quote' : '+ New quote'; ?>
        </summary>

        <form method="POST" style="margin-top:18px">
            <?php echo csrf_field(); ?>
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
            <?php endif; ?>

            <div class="quote-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div>
                    <label>Customer name</label>
                    <input type="text" name="customer_name" required maxlength="255"
                           value="<?php echo htmlspecialchars($editing['customer_name'] ?? ($_POST['customer_name'] ?? '')); ?>"
                           style="margin:0">
                </div>
                <div>
                    <label>Customer email</label>
                    <input type="email" name="customer_email" required maxlength="255"
                           value="<?php echo htmlspecialchars($editing['customer_email'] ?? ($_POST['customer_email'] ?? '')); ?>"
                           style="margin:0">
                </div>
            </div>

            <div style="margin-bottom:14px">
                <label>The piece</label>
                <input type="text" name="title" required maxlength="255"
                       placeholder="Walnut dining table"
                       value="<?php echo htmlspecialchars($editing['title'] ?? ($_POST['title'] ?? '')); ?>"
                       style="margin:0">
            </div>

            <div style="margin-bottom:14px">
                <label>Description <span style="font-weight:400;font-size:11px;opacity:.6">(dimensions, wood, finish — shown to the customer)</span></label>
                <textarea name="description" rows="4" style="width:100%;margin:0;font-family:inherit"
                          placeholder="Solid black walnut, 72&quot; x 38&quot;, hand-rubbed oil finish, breadboard ends."><?php echo htmlspecialchars($editing['description'] ?? ($_POST['description'] ?? '')); ?></textarea>
            </div>

            <div class="quote-form-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px">
                <div>
                    <label>Total price</label>
                    <input type="number" name="total" step="0.01" min="0.01" required
                           value="<?php echo htmlspecialchars($editing['total'] ?? ($_POST['total'] ?? '')); ?>"
                           style="margin:0">
                </div>
                <div>
                    <label>Deposit</label>
                    <input type="number" name="deposit_amount" step="0.01" min="0.01" required
                           value="<?php echo htmlspecialchars($editing['deposit_amount'] ?? ($_POST['deposit_amount'] ?? number_format(DEFAULT_DEPOSIT, 2, '.', ''))); ?>"
                           style="margin:0">
                </div>
                <div>
                    <label>Lead time</label>
                    <input type="text" name="lead_time" maxlength="120" placeholder="8–10 weeks"
                           value="<?php echo htmlspecialchars($editing['lead_time'] ?? ($_POST['lead_time'] ?? '')); ?>"
                           style="margin:0">
                </div>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save quote' : 'Create quote'; ?></button>
                <?php if ($editing): ?>
                    <a href="/admin/custom-orders.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
            <p style="margin:12px 0 0;font-size:12px;color:rgba(45,45,45,0.55)">
                Creating a quote doesn't email anyone — you'll get a link to review and send on the next screen.
            </p>
        </form>
    </details>

    <?php if (!$quotes): ?>
        <p>No custom orders yet. Create a quote above to get started.</p>
    <?php else: ?>
    <table class="table--cards">
        <thead>
            <tr>
                <th>Piece</th>
                <th>Customer</th>
                <th>Money</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($quotes as $q): ?>
                <?php [$label, $mod] = quote_status_meta($q['status']); ?>
            <tr>
                <td data-label="Piece">
                    <a href="/admin/quote-detail.php?id=<?php echo (int) $q['id']; ?>"
                       style="font-family:'Syne',sans-serif;font-weight:700"><?php echo htmlspecialchars($q['title']); ?></a>
                    <?php if (!empty($q['lead_time'])): ?>
                        <div style="font-size:12px;color:rgba(45,45,45,0.55)"><?php echo htmlspecialchars($q['lead_time']); ?></div>
                    <?php endif; ?>
                </td>
                <td data-label="Customer">
                    <?php echo htmlspecialchars($q['customer_name']); ?>
                    <div style="font-size:12px;color:rgba(45,45,45,0.55)"><?php echo htmlspecialchars($q['customer_email']); ?></div>
                </td>
                <td data-label="Money">
                    $<?php echo number_format($q['total'], 2); ?>
                    <div style="font-size:12px;color:rgba(45,45,45,0.55)">
                        <?php if ($q['status'] === 'draft' || $q['status'] === 'sent'): ?>
                            $<?php echo number_format($q['deposit_amount'], 2); ?> deposit due
                        <?php elseif ($q['balance_due'] !== null && (float) $q['balance_due'] > 0): ?>
                            $<?php echo number_format($q['deposit_amount'], 2); ?> paid ·
                            <strong style="color:#C25B32">$<?php echo number_format($q['balance_due'], 2); ?> due</strong>
                        <?php else: ?>
                            paid in full
                        <?php endif; ?>
                    </div>
                </td>
                <td data-label="Status">
                    <span class="badge-status badge-status--<?php echo $mod; ?>"><?php echo $label; ?></span>
                </td>
                <td data-label="Actions" class="cell-actions">
                    <a href="/admin/quote-detail.php?id=<?php echo (int) $q['id']; ?>" class="btn btn-secondary" style="padding:6px 14px">Open</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</body>
</html>
