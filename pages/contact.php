<?php
$pageTitle = "Contact";
include __DIR__ . '/../includes/header.php';
?>

<div class="contact container">
    <div class="content">
        <h1 class="large_title">Would you like to commission a piece or get in touch?</h1>
        <h2 class="large_title">Let's <span>Talk</span>!</h2>
        <p>Give me some details on what you'd like done, your time frame, and your budget. I normally respond within 1-2 days.</p>
        <p>I'm excited to hear from you!</p>
        <p>-Annie</p>
    </div>
    <form class="contact-form" action="" method="POST">
        <input type="text" name="name" placeholder="Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <textarea name="message" placeholder="Type here...." required></textarea>
        <button type="submit" class="btn blue-button">SUBMIT</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
