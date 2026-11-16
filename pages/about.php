<?php
$pageTitle = "About Annie";
$pageDescription = "Meet Annie, the artist behind Caneeli Designs: a former 3D animator now making stained glass lamps, ceramic lighting and the salmon series in her Oregon studio.";
include __DIR__ . '/../includes/header.php';
?>

<?php $pelican = file_get_contents(__DIR__ . '/../assets/images/pelican.svg'); ?>
<section class="about-editorial">
    <div class="container">
        <h1 class="about-title">
            <span class="about-title__line">Hi,</span>
            <span class="about-title__line about-title__line--accent">I'm Annie.</span>
        </h1>

        <p class="about-lede">Raised in South Florida, I always knew I was an artist.</p>
    </div>

    <div class="container about-body">
        <aside class="about-facts" aria-label="Quick facts">
            <span class="about-facts__mark"><?php echo $pelican; ?></span>
            <dl>
                <div><dt>Studio</dt><dd>A converted garage in Oregon</dd></div>
                <div><dt>Makes</dt><dd>Stained glass lamps, ceramic lighting and salmon in every form</dd></div>
                <div><dt>Before this</dt><dd>3D animation and product rendering</dd></div>
                <div><dt>Studio crew</dt><dd>My partner and our three dogs</dd></div>
            </dl>
        </aside>

        <article class="about-prose">
            <p>That instinct was nurtured by my free-spirited mother, who raised my sister and me in a home where creativity was encouraged at every turn. I began attending a specialized art school in sixth grade and continued there until my junior year, when my family moved to Colorado. I finished high school there before enrolling in the University of Colorado's 3D animation program.</p>
            <p>I quickly fell in love with the medium and truly believed it would shape my future. I thrived in the program, and many opportunities began to open up. But during my final semester, my mother passed away. Instead of moving to pursue a career in animation, I returned to her home in South Florida.</p>
            <p>Not long after, I landed a job at a creative agency, where I spent three years working as a 3D generalist, focusing mostly on product renderings, animated ads, and social media campaigns. While the work was creative, I began to feel stuck and knew I needed a change.</p>
            <p>So my partner, our 3 dogs and I packed up and moved across the country to Oregon, without much of a plan.</p>
            <p>I joined a creative coworking space, hoping to reconnect with hands-on work. With access to woodworking, metalworking, ceramics, and more, I found myself unexpectedly drawn to the stained glass studio. Before long, I was spending nearly all my free time there. Working with glass came naturally.</p>
            <p>Soon after, I transformed my garage into a studio for stained glass and ceramics. I realized that sitting behind a computer, creating work dictated by others, was no longer the future I wanted. Instead, I found a renewed sense of purpose in making playful, functional pieces and sharing them with others.</p>
            <blockquote class="about-quote">Playful, functional pieces.</blockquote>
            <p>I love working with my hands, creating pieces that spark curiosity, and connecting with people through shared creativity. It's my hope to spend the rest of my life doing exactly that.</p>
        </article>
    </div>

    <div class="about-cta">
        <div class="container">
            <h2 class="about-cta__title">Take a look around, or get in touch.</h2>
            <div class="about-cta__buttons">
                <a class="btn btn--light" href="<?php echo SITE_URL; ?>/pages/shop/">Browse the shop</a>
                <a class="btn blue-button" href="<?php echo SITE_URL; ?>/pages/contact.php">Start a commission</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
