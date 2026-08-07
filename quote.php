<?php
/**
 * Public custom-order quote page.
 *
 * Reached via a private token link (no login). Shows the piece and the money
 * split, and gates the deposit payment behind explicit acceptance of the
 * non-refundable terms — ticking the box is what records terms_accepted_at.
 */
$pageTitle = "Your Quote";
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/quotes.php';

$token = trim($_GET['t'] ?? '');
$quote = null;

if ($token !== '') {
    $stmt = getDB()->prepare("SELECT * FROM custom_quotes WHERE token = ?");
    $stmt->execute([$token]);
    $quote = $stmt->fetch() ?: null;
}

// A draft hasn't been sent yet — treat it as not-found publicly so a guessed
// or prematurely shared link can't expose pricing Annie is still working on.
if ($quote && $quote['status'] === 'draft') {
    $quote = null;
}
?>

<div class="container">
<?php if (!$quote): ?>

    <div class="quote">
        <div class="quote__eyebrow">Caneeli Designs</div>
        <h1 class="quote__title">We couldn't find that quote</h1>
        <p class="quote__desc">This link may have expired, or it might have a typo in it. If you were expecting a quote, just reply to Annie's email and she'll send a fresh link.</p>
        <a href="<?php echo SITE_URL; ?>/pages/contact.php" class="btn btn-primary">Get in touch</a>
    </div>

<?php else:
    $total    = (float) $quote['total'];
    $deposit  = (float) $quote['deposit_amount'];
    $balance  = $total - $deposit;
    $isOpen    = $quote['status'] === 'sent';
    $isBalance = $quote['status'] === 'balance_requested';
    $isPaidUp  = in_array($quote['status'], ['deposit_paid', 'paid'], true);

    // The order is the record of what's actually owed — a balance can be
    // settled outside Stripe, which the quote row wouldn't know about.
    if ($isBalance && !empty($quote['order_id'])) {
        $ost = getDB()->prepare("SELECT balance_due FROM orders WHERE id = ?");
        $ost->execute([(int) $quote['order_id']]);
        $due = $ost->fetchColumn();
        if ($due !== false) {
            $balance = (float) $due;
        }
        if ($balance <= 0) {
            $isBalance = false;
            $isPaidUp  = true;
        }
    }
?>

    <div class="quote">
        <div class="quote__eyebrow">Custom order quote</div>
        <h1 class="quote__title"><?php echo htmlspecialchars($quote['title']); ?></h1>

        <?php if (!empty($quote['lead_time'])): ?>
            <div class="quote__lead"><?php echo htmlspecialchars($quote['lead_time']); ?></div>
        <?php endif; ?>

        <?php if (!empty($quote['description'])): ?>
            <p class="quote__desc"><?php echo htmlspecialchars($quote['description']); ?></p>
        <?php endif; ?>

        <div class="quote__card">
            <div class="quote__row quote__row--due">
                <span><?php echo $isPaidUp ? 'Deposit paid' : 'Deposit due today'; ?></span>
                <span>$<?php echo number_format($deposit, 2); ?></span>
            </div>
            <div class="quote__row">
                <span>Balance before shipping</span>
                <span>$<?php echo number_format($balance, 2); ?></span>
            </div>
            <div class="quote__row quote__row--total">
                <span>Total</span>
                <span>$<?php echo number_format($total, 2); ?></span>
            </div>
        </div>

        <?php if ($isBalance): ?>

            <div class="quote__state" style="margin-bottom:26px">
                <h2>Your piece is ready</h2>
                <p>
                    <?php echo htmlspecialchars($quote['title']); ?> is finished. Once the remaining
                    balance is settled, Annie will get it packed up and on its way to you.
                </p>
            </div>

            <form method="POST" action="<?php echo SITE_URL; ?>/quote-checkout.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($quote['token']); ?>">
                <input type="hidden" name="mode" value="balance">
                <button type="submit" class="btn btn-primary quote__pay">
                    Pay $<?php echo number_format($balance, 2); ?> balance
                </button>
                <p class="quote__reassurance">
                    Secure payment through Stripe. Your piece ships once this clears.
                </p>
            </form>

        <?php elseif ($isPaidUp && $quote['status'] === 'paid'): ?>

            <div class="quote__state">
                <h2>Paid in full — thank you!</h2>
                <p>
                    Everything's settled on <?php echo htmlspecialchars($quote['title']); ?>. Annie is
                    getting it packed up, and you'll get a separate email with tracking as soon as it ships.
                </p>
            </div>

        <?php elseif ($isPaidUp): ?>

            <div class="quote__state">
                <h2>Your deposit is in — thank you!</h2>
                <p>
                    Annie has started on your piece<?php echo !empty($quote['lead_time'])
                        ? ', with an estimated timeline of ' . htmlspecialchars($quote['lead_time'])
                        : ''; ?>.
                    You'll get an email when it's finished, along with a link to pay the remaining
                    balance of $<?php echo number_format($balance, 2); ?>.
                </p>
            </div>

        <?php elseif ($quote['status'] === 'cancelled'): ?>

            <div class="quote__state">
                <h2>This quote is no longer active</h2>
                <p>If you'd still like to go ahead with this piece, reach out and Annie will put together a fresh quote.</p>
            </div>

        <?php elseif ($isOpen): ?>

            <form method="POST" action="<?php echo SITE_URL; ?>/quote-checkout.php" id="quote-form">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($quote['token']); ?>">

                <label class="quote__terms">
                    <input type="checkbox" name="accept_terms" value="1" id="accept-terms" required>
                    <span>
                        I understand the <strong>$<?php echo number_format($deposit, 2); ?> deposit is non-refundable
                        as soon as it's paid</strong> — it reserves my place in the schedule and covers materials for
                        my piece. Because this piece is made specifically for me, it can't be returned or refunded.
                        I've read the <a href="<?php echo SITE_URL; ?>/pages/terms.php" target="_blank" rel="noopener">full terms</a>.
                    </span>
                </label>

                <button type="submit" class="btn btn-primary quote__pay" id="pay-btn" disabled>
                    Pay $<?php echo number_format($deposit, 2); ?> deposit
                </button>

                <p class="quote__reassurance">
                    Secure payment through Stripe. Questions about any of this?
                    <a href="<?php echo SITE_URL; ?>/pages/contact.php">Just ask</a> before you pay.
                </p>
            </form>

            <script>
                // Keep the pay button disabled until the terms box is ticked, so
                // nobody can pay without having seen what they're agreeing to.
                (function () {
                    var box = document.getElementById('accept-terms');
                    var btn = document.getElementById('pay-btn');
                    function sync() { btn.disabled = !box.checked; }
                    box.addEventListener('change', sync);
                    sync();
                })();
            </script>

        <?php endif; ?>
    </div>

<?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
