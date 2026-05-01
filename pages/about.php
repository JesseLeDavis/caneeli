<?php
$pageTitle = "About";
include __DIR__ . '/../includes/header.php';
?>

<section class="about-editorial">
    <div class="container">
        <div class="about-masthead">
            <span class="about-masthead__mark">Vol. 01</span>
            <span class="about-masthead__rule" aria-hidden="true"></span>
            <span class="about-masthead__label">Maker's Notes</span>
            <span class="about-masthead__rule" aria-hidden="true"></span>
            <span class="about-masthead__date">Spring 2026</span>
        </div>

        <h1 class="about-title">
            <span class="about-title__line">Hi,</span>
            <span class="about-title__line about-title__line--accent">I'm Annie.</span>
        </h1>

        <p class="about-deck">I run Caneeli Designs out of my shop — just me, my tools, and whatever I'm currently obsessing over building.</p>

        <p class="about-byline">A note from the workbench &mdash; in her own words</p>
    </div>

    <div class="container">
        <article class="about-chapters">
            <div class="about-chapter">
                <span class="about-chapter__num" aria-hidden="true">01</span>
                <h2 class="about-chapter__title">The Shop</h2>
                <div class="about-chapter__body">
                    <p>I make furniture and home decor by hand. Shelves, tables, chairs, wall pieces — if it lives in your home, I've probably made a version of it. Every piece is one of a kind, which means no two are exactly alike.</p>
                </div>
            </div>

            <blockquote class="about-pullquote">
                <span class="about-pullquote__open" aria-hidden="true">&ldquo;</span>
                <p>That's a feature, not a bug.</p>
            </blockquote>

            <div class="about-chapter">
                <span class="about-chapter__num" aria-hidden="true">02</span>
                <h2 class="about-chapter__title">The Origin</h2>
                <div class="about-chapter__body">
                    <p>I started this because I couldn't find the things I actually wanted to live with, so I learned to make them myself. Turns out other people felt the same way.</p>
                </div>
            </div>

            <div class="about-chapter">
                <span class="about-chapter__num" aria-hidden="true">03</span>
                <h2 class="about-chapter__title">Commissions</h2>
                <div class="about-chapter__body">
                    <p>If you see something in the shop, it's ready to go. If you have something specific in mind, commissions are open — reach out and tell me what you're thinking. I'm easier to work with than IKEA and the end result lasts longer.</p>
                </div>
            </div>
        </article>
    </div>

    <div class="about-stats">
        <div class="container">
            <ul class="about-stats__list">
                <li class="about-stats__item">
                    <span class="about-stats__num">01</span>
                    <span class="about-stats__label">Maker</span>
                </li>
                <li class="about-stats__item">
                    <span class="about-stats__num">&infin;</span>
                    <span class="about-stats__label">Variations</span>
                </li>
                <li class="about-stats__item">
                    <span class="about-stats__num">00</span>
                    <span class="about-stats__label">Two Alike</span>
                </li>
                <li class="about-stats__item">
                    <span class="about-stats__num">100%</span>
                    <span class="about-stats__label">By Hand</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="container">
        <div class="about-cta">
            <p class="about-cta__eyebrow">Two ways in</p>
            <h2 class="about-cta__title">Have a look, or tell me what you have in mind.</h2>
            <div class="about-cta__buttons">
                <a class="btn red-button" href="<?php echo SITE_URL; ?>/pages/shop/">Browse the Shop</a>
                <a class="btn blue-button" href="<?php echo SITE_URL; ?>/pages/contact.php">Start a Commission</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
