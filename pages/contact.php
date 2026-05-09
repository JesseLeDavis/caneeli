<?php
$pageTitle = "Contact";
include __DIR__ . '/../includes/header.php';

$contactStatus = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactStatus = ['type' => 'error', 'text' => 'Please fill out every field with a valid email.'];
    } else {
        $to      = 'annie@caneelidesigns.com';
        $subject = 'New message from ' . $name . ' via caneelidesigns.com';
        $body    = "Name: {$name}\nEmail: {$email}\n\n{$message}\n";
        $headers = [
            'From: website@caneelidesigns.com',
            'Reply-To: ' . $email,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        if (mail($to, $subject, $body, implode("\r\n", $headers))) {
            $contactStatus = ['type' => 'success', 'text' => "Thanks — I'll be in touch soon."];
        } else {
            $contactStatus = ['type' => 'error', 'text' => 'Something went wrong sending your message. Please try again.'];
        }
    }
}
?>

<div class="contact container">
    <div class="content">
        <h1 class="large_title">Have something in mind?</h1>
        <h2 class="large_title">Let's <span>Talk</span>.</h2>
        <p>Tell me what you're looking for — what it is, your timeline, and your budget. I get back to people within a day or two.</p>
        <p>-Annie</p>
    </div>
    <form class="contact-form" action="" method="POST">
        <?php if ($contactStatus): ?>
            <p class="contact-status contact-status--<?= htmlspecialchars($contactStatus['type']) ?>">
                <?= htmlspecialchars($contactStatus['text']) ?>
            </p>
        <?php endif; ?>
        <input type="text" name="name" placeholder="Name" required value="<?= htmlspecialchars($contactStatus['type'] ?? '') === 'success' ? '' : htmlspecialchars($_POST['name'] ?? '') ?>">
        <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($contactStatus['type'] ?? '') === 'success' ? '' : htmlspecialchars($_POST['email'] ?? '') ?>">
        <textarea name="message" placeholder="What are you thinking?" required><?= htmlspecialchars($contactStatus['type'] ?? '') === 'success' ? '' : htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        <button type="submit" class="btn blue-button">SEND IT</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
