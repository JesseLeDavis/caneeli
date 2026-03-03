<?php
$pageTitle = "Contact";
include __DIR__ . '/../includes/header.php';
?>

<div class="contact container">
    <div class="content">
        <h1 class="large_title">Have something in mind?</h1>
        <h2 class="large_title">Let's <span>Talk</span>.</h2>
        <p>Tell me what you're looking for — what it is, your timeline, and your budget. I get back to people within a day or two.</p>
        <p>-Annie</p>
    </div>
    <form class="contact-form" action="" method="POST">
        <input type="text" name="name" placeholder="Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <textarea name="message" placeholder="What are you thinking?" required></textarea>
        <button type="submit" class="btn blue-button">SEND IT</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
