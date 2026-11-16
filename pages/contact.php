<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/contact_spam.php';

// Handled before header.php prints anything, so a successful send can
// redirect (post/redirect/get): refreshing the thanks page won't resend.
$contactStatus = null;
$thanks = ['type' => 'success', 'text' => "Thanks — I'll be in touch soon."];

// Optional "What's this about?" choice. Saved as the first line of the
// message, so the admin inbox shows it without a new column.
const CONTACT_TOPICS = [
    'commission' => 'A commission',
    'question'   => 'A question about a piece',
    'other'      => 'Something else',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    // Browsers send line breaks as \r\n but count them as one character for maxlength.
    $message = trim(str_replace("\r\n", "\n", $_POST['message'] ?? ''));
    $topic   = $_POST['topic'] ?? '';

    if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactStatus = ['type' => 'error', 'text' => 'Please fill out every field with a valid email.'];
    } elseif (mb_strlen($name) > CONTACT_MAX_NAME || mb_strlen($message) > CONTACT_MAX_MESSAGE) {
        $contactStatus = ['type' => 'error', 'text' => 'That message is a bit long. Please keep it under ' . number_format(CONTACT_MAX_MESSAGE) . ' characters.'];
    } else {
        // Stored for Annie to read in the admin (/admin/messages.php) rather
        // than relying on email delivery. Spam is saved too, just flagged.
        try {
            $pdo = getDB();
            $ip  = login_client_ip();

            if (contact_recent_count($pdo, $ip) >= CONTACT_MAX_PER_HOUR) {
                $contactStatus = ['type' => 'error', 'text' => "You've sent a few messages in the last hour. Please try again a bit later."];
            } else {
                $saved = isset(CONTACT_TOPICS[$topic])
                    ? 'About: ' . CONTACT_TOPICS[$topic] . "\n\n" . $message
                    : $message;
                contact_save($pdo, $name, $email, $saved, contact_spam_reasons($_POST, $name, $message), $ip);
                header('Location: /pages/contact.php?sent=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log('contact form: ' . $e->getMessage());
            $contactStatus = ['type' => 'error', 'text' => 'Something went wrong saving your message. Please try again.'];
        }
    }
} elseif (isset($_GET['sent'])) {
    $contactStatus = $thanks;
}

$pageTitle = "Contact";
$pageDescription = "Commission a stained glass lamp, ceramic light or salmon piece from Annie, or ask a question about one. Tell her what you have in mind, your timeline and budget.";
include __DIR__ . '/../includes/header.php';

// Keep what they typed when there's an error; blank after a send.
$keep = function (string $field): string {
    return $_SERVER['REQUEST_METHOD'] === 'POST' ? htmlspecialchars($_POST[$field] ?? '') : '';
};

// Arriving from a sold-out piece (?about=<name>): start the message for them
// and preselect "A commission". Plain text, escaped on output, length-capped.
$about = $_SERVER['REQUEST_METHOD'] === 'POST' ? '' : trim(strip_tags((string) ($_GET['about'] ?? '')));
$about = mb_substr(preg_replace('/\s+/', ' ', $about), 0, 120);
$messageValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $keep('message')
    : ($about !== '' ? htmlspecialchars("I'm interested in something like the {$about}. ") : '');
$topicValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['topic'] ?? '')
    : ($about !== '' ? 'commission' : '');
?>

<div class="contact container">
    <div class="content">
        <h1 class="large_title">Have something in mind? Let's talk.</h1>
        <p>Tell me what you're looking for: what it is, your timeline, and your budget. Commissions, questions about a piece, or just saying hi. I get back to people within a day or two.</p>
        <p class="contact-sign">Annie</p>

        <section class="contact-steps" aria-labelledby="contact-steps-title">
            <h2 class="contact-steps__title" id="contact-steps-title">How commissions work</h2>
            <ol class="contact-steps__list">
                <li><strong>Tell me your idea.</strong> What it is, colors, size, timeline and budget.</li>
                <li><strong>I reply with a quote,</strong> usually within a day or two.</li>
                <li><strong>A deposit holds your spot.</strong> It's non-refundable once paid (<a href="/pages/terms.php">see terms</a>).</li>
                <li><strong>I make it,</strong> and you pay the balance before it ships.</li>
            </ol>
        </section>
    </div>
    <form class="contact-form" action="" method="POST">
        <?php if ($contactStatus): ?>
            <p class="contact-status contact-status--<?= htmlspecialchars($contactStatus['type']) ?>">
                <?= htmlspecialchars($contactStatus['text']) ?>
            </p>
        <?php endif; ?>
        <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
            <label>Leave this empty<input type="text" name="<?= CONTACT_HONEYPOT_FIELD ?>" tabindex="-1" autocomplete="off"></label>
        </div>
        <input type="hidden" name="<?= CONTACT_TIME_FIELD ?>" value="<?= time() ?>">
        <label class="contact-field">
            <span>Your name</span>
            <input type="text" name="name" required maxlength="<?= CONTACT_MAX_NAME ?>" autocomplete="name" value="<?= $keep('name') ?>">
        </label>
        <label class="contact-field">
            <span>Email</span>
            <input type="email" name="email" required maxlength="255" autocomplete="email" value="<?= $keep('email') ?>">
        </label>
        <label class="contact-field">
            <span>What's this about? <em class="contact-field__optional">(optional)</em></span>
            <select name="topic">
                <option value="">Choose one</option>
                <?php foreach (CONTACT_TOPICS as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $topicValue === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="contact-field">
            <span>What are you thinking?</span>
            <textarea name="message" required maxlength="<?= CONTACT_MAX_MESSAGE ?>" placeholder="A lamp for my reading corner, blues and greens…"><?= $messageValue ?></textarea>
        </label>
        <button type="submit" class="btn red-button">Send message</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
