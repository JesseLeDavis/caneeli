<?php
$pageTitle = "Contact";
include __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$contactStatus = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $message  = trim($_POST['message'] ?? '');
    $honeypot = trim($_POST['website'] ?? ''); // hidden field — only bots fill it

    if ($honeypot !== '') {
        // Silently accept bot submissions: show success, store nothing.
        $contactStatus = ['type' => 'success', 'text' => "Thanks — I'll be in touch soon."];
    } elseif ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactStatus = ['type' => 'error', 'text' => 'Please fill out every field with a valid email.'];
    } else {
        // Store the message so Annie reads it in the admin (/admin/messages.php)
        // rather than relying on email delivery.
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                "INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)"
            );
            $stmt->execute([$name, $email, $message]);
            $contactStatus = ['type' => 'success', 'text' => "Thanks — I'll be in touch soon."];
        } catch (PDOException $e) {
            error_log('contact form: ' . $e->getMessage());
            $contactStatus = ['type' => 'error', 'text' => 'Something went wrong saving your message. Please try again.'];
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
        <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <input type="text" name="name" placeholder="Name" required value="<?= htmlspecialchars($contactStatus['type'] ?? '') === 'success' ? '' : htmlspecialchars($_POST['name'] ?? '') ?>">
        <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($contactStatus['type'] ?? '') === 'success' ? '' : htmlspecialchars($_POST['email'] ?? '') ?>">
        <textarea name="message" placeholder="What are you thinking?" required><?= htmlspecialchars($contactStatus['type'] ?? '') === 'success' ? '' : htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        <button type="submit" class="btn blue-button">SEND IT</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
