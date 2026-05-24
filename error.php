<?php
$pageTitle = "Something Went Wrong";
include __DIR__ . '/includes/header.php';

$reason = $_GET['reason'] ?? '';

$messages = [
    'checkout' => "Checkout hit a snag before we could send you over to payment. Nothing was charged.",
];
$message = $messages[$reason] ?? "Something on my end didn't go as planned. Nothing was charged.";

$annie_email = 'annie@caneelidesigns.com';
?>

<section class="hero">
    <div class="container">
        <div class="checkout-status checkout-status--error">
            <div class="checkout-status__intro">
                <p class="checkout-status__eyebrow">Hiccup</p>
                <h1 class="large_title">Something went sideways.</h1>
                <p class="checkout-status__lede"><?php echo htmlspecialchars($message); ?></p>
                <p class="checkout-status__lede">Try again in a minute, or send me a quick note and I'll sort it out with you directly.</p>
                <p class="checkout-status__sign-off">-Annie</p>
            </div>

            <div class="checkout-status__card checkout-status__card--contact">
                <p class="checkout-status__label">Reach me</p>
                <a class="checkout-status__email" href="mailto:<?php echo htmlspecialchars($annie_email); ?>">
                    <?php echo htmlspecialchars($annie_email); ?>
                </a>
                <p class="checkout-status__contact-note">I usually reply within a day or two.</p>
            </div>

            <div class="checkout-status__actions">
                <a href="/cart.php" class="btn red-button checkout-status__cta">Back to Cart</a>
                <a href="/pages/shop/" class="checkout-status__secondary">Back to Shop</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
