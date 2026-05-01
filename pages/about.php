<?php
$pageTitle = "About";
include __DIR__ . '/../includes/header.php';
?>

<section class="about-editorial">
    <div class="container">
        <h1 class="about-title">
            <span class="about-title__line">Hi,</span>
            <span class="about-title__line about-title__line--accent">I'm Annie.</span>
        </h1>

        <p class="about-lede">Raised in South Florida, I always knew I was an artist.</p>
    </div>

    <div class="container">
        <article class="about-prose">
            <p>That instinct was nurtured by my free-spirited mother, who raised my sister and me in a home where creativity was encouraged at every turn. I began attending a specialized art school in sixth grade and continued there until my junior year, when my family moved to Colorado. I finished high school there before enrolling in the University of Colorado's 3D animation program.</p>
            <p>I quickly fell in love with the medium and truly believed it would shape my future. I thrived in the program, and many opportunities began to open up. But during my final semester, my mother passed away. Instead of moving to pursue a career in animation, I returned to her home in South Florida.</p>
            <p>Not long after, I landed a job at a creative agency, where I spent three years working as a 3D generalist, focusing mostly on product renderings, animated ads, and social media campaigns. While the work was creative, I began to feel stuck and knew I needed a change.</p>
            <p>So my partner, our 3 dogs and I packed up and moved across the country to Oregon, without much of a plan.</p>
            <p>I joined a creative coworking space, hoping to reconnect with hands-on work. With access to woodworking, metalworking, ceramics, and more, I found myself unexpectedly drawn to the stained glass studio. Before long, I was spending nearly all my free time there. Working with glass came naturally.</p>
            <p>Soon after, I transformed my garage into a studio for stained glass and woodworking. I realized that sitting behind a computer, creating work dictated by others, was no longer the future I wanted. Instead, I found a renewed sense of purpose in building playful, functional pieces and sharing them with others.</p>
            <p>I love working with my hands, creating pieces that spark curiosity, and connecting with people through shared creativity. It's my hope to spend the rest of my life doing exactly that.</p>
        </article>
    </div>

    <div class="container">
        <div class="about-cta">
            <h2 class="about-cta__title">Take a look around, or get in touch.</h2>
            <div class="about-cta__buttons">
                <a class="btn red-button" href="<?php echo SITE_URL; ?>/pages/shop/">Browse the Shop</a>
                <a class="btn blue-button" href="<?php echo SITE_URL; ?>/pages/contact.php">Get in Touch</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
