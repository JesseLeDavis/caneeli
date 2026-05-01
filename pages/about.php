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

        <p class="about-deck">Raised in South Florida, I always knew I was an artist.</p>

        <p class="about-byline">In her own words</p>
    </div>

    <div class="container">
        <article class="about-chapters">
            <div class="about-chapter">
                <span class="about-chapter__num" aria-hidden="true">01</span>
                <h2 class="about-chapter__title">Beginnings</h2>
                <div class="about-chapter__body">
                    <p>That instinct was nurtured by my free-spirited mother, who raised my sister and me in a home where creativity was encouraged at every turn. I began attending a specialized art school in sixth grade and continued there until my junior year, when my family moved to Colorado.</p>
                    <p>I finished high school there before enrolling in the University of Colorado's 3D animation program.</p>
                </div>
            </div>

            <div class="about-chapter">
                <span class="about-chapter__num" aria-hidden="true">02</span>
                <h2 class="about-chapter__title">The Pivot</h2>
                <div class="about-chapter__body">
                    <p>I quickly fell in love with the medium and truly believed it would shape my future. I thrived in the program, and many opportunities began to open up. But during my final semester, my mother passed away. Instead of moving to pursue a career in animation, I returned to her home in South Florida.</p>
                    <p>Not long after, I landed a job at a creative agency, where I spent three years working as a 3D generalist, focusing mostly on product renderings, animated ads, and social media campaigns. While the work was creative, I began to feel stuck and knew I needed a change.</p>
                    <p>So my partner, our 3 dogs and I packed up and moved across the country to Oregon, without much of a plan.</p>
                </div>
            </div>

            <blockquote class="about-pullquote">
                <span class="about-pullquote__open" aria-hidden="true">&ldquo;</span>
                <p>I found a sense of flow I hadn't experienced in years.</p>
            </blockquote>

            <div class="about-chapter">
                <span class="about-chapter__num" aria-hidden="true">03</span>
                <h2 class="about-chapter__title">The Studio</h2>
                <div class="about-chapter__body">
                    <p>I joined a creative coworking space, hoping to reconnect with hands-on work. With access to woodworking, metalworking, ceramics, and more, I found myself unexpectedly drawn to the stained glass studio. Before long, I was spending nearly all my free time there. Working with glass came naturally.</p>
                    <p>Soon after, I transformed my garage into a studio for stained glass and woodworking. I realized that sitting behind a computer, creating work dictated by others, was no longer the future I wanted. Instead, I found a renewed sense of purpose in building playful, functional pieces and sharing them with others.</p>
                    <p>I love working with my hands, creating pieces that spark curiosity, and connecting with people through shared creativity. It's my hope to spend the rest of my life doing exactly that.</p>
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
