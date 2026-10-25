document.addEventListener('DOMContentLoaded', function() {

    // Spotlight carousel
    (function() {
        const stage = document.querySelector('.spotlight__stage');
        if (!stage) return;

        const cards = Array.from(stage.querySelectorAll('.product-card'));
        const dots  = Array.from(document.querySelectorAll('.spotlight__dot'));
        const total = cards.length;
        if (total < 2) {
            // Single card: just make it active, no controls needed
            if (total === 1) cards[0].classList.add('is-active');
            return;
        }

        let current = 0;
        let timer;

        function update() {
            const prev = (current - 1 + total) % total;
            const next = (current + 1) % total;

            cards.forEach(function(card, i) {
                card.classList.remove('is-active', 'is-prev', 'is-next');
                if      (i === current) card.classList.add('is-active');
                else if (i === prev)    card.classList.add('is-prev');
                else if (i === next)    card.classList.add('is-next');
            });

            dots.forEach(function(dot, i) {
                dot.classList.toggle('is-active', i === current);
            });
        }

        function goTo(index) {
            current = ((index % total) + total) % total;
            update();
            restart();
        }

        function advance() {
            current = (current + 1) % total;
            update();
        }

        function restart() {
            clearInterval(timer);
            timer = setInterval(advance, 4000);
        }

        document.querySelector('.spotlight__arrow--prev')
            ?.addEventListener('click', function() { goTo(current - 1); });
        document.querySelector('.spotlight__arrow--next')
            ?.addEventListener('click', function() { goTo(current + 1); });

        dots.forEach(function(dot, i) {
            dot.addEventListener('click', function() { goTo(i); });
        });

        // Pause autoplay on hover
        stage.addEventListener('mouseenter', function() { clearInterval(timer); });
        stage.addEventListener('mouseleave', restart);


        // Init without transition so cards snap to position instantly
        cards.forEach(function(c) { c.style.transition = 'none'; });
        update();
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                cards.forEach(function(c) { c.style.transition = ''; });
                restart();
            });
        });
    })();
    // Homepage hero: lamps in a tub of water (markup: includes/hero_lamps.php)
    (function() {
        const tub = document.querySelector('.lamp-tub');
        if (!tub) return;

        const splash = tub.querySelector('.lamp-tub__splash');
        const lamps  = tub.querySelectorAll('.lamp-tub__lamp');
        const calm   = matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (calm) tub.querySelectorAll('svg').forEach(function(svg) { svg.pauseAnimations(); });

        // Each lamp gets a random site colour per visit, never the same as its neighbour.
        const inks = ['#C25B32', '#6497D6', '#D4C14B', '#2D2D2D'];
        let prev = null;
        lamps.forEach(function(lamp) {
            const pick = inks.filter(function(c) { return c !== prev; });
            prev = pick[Math.floor(Math.random() * pick.length)];
            lamp.style.setProperty('--ink', prev);
        });

        // Throw a few droplets from where the lamp crosses the water surface.
        // The surface sits at y=440 of the 600×630 drawing; lamps pivot 10% up.
        const SURFACE = 440 / 630;
        function splashAt(lamp) {
            if (calm) return;
            const W = tub.clientWidth, H = tub.clientHeight;
            const tilt = parseFloat(lamp.style.getPropertyValue('--tilt')) * Math.PI / 180;
            const pivotX = parseFloat(lamp.style.getPropertyValue('--x')) / 100 * W;
            const x = pivotX + (0.90 - SURFACE) * H * Math.tan(tilt);
            for (let i = 0; i < 5; i++) {
                const d = document.createElement('span');
                d.className = 'lamp-tub__drop';
                d.style.left = (x / W * 100) + '%';
                d.style.top  = (SURFACE * 100) + '%';
                d.style.setProperty('--dx', ((Math.random() - .5) * 80 + Math.sin(tilt) * 30) + 'px');
                d.style.setProperty('--dy', (-25 - Math.random() * 45) + 'px');
                d.style.animationDelay = (i * 25) + 'ms';
                splash.appendChild(d);
                d.addEventListener('animationend', function() { d.remove(); });
            }
        }

        // Touch has no hover, so a tap toggles the lamp up; the wiggle replays on every pop.
        lamps.forEach(function(lamp) {
            function pop() {
                lamp.classList.remove('is-pop');
                void lamp.offsetWidth;
                lamp.classList.add('is-pop');
                splashAt(lamp);
            }
            lamp.addEventListener('mouseenter', pop);
            lamp.addEventListener('click', function() { lamp.classList.toggle('is-up'); pop(); });
        });
    })();

    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelectorAll('.nav a');

    // Toggle menu
    menuToggle.addEventListener('click', function() {
        document.body.classList.toggle('menu-open');
    });

    // Close menu when a link is clicked
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            document.body.classList.remove('menu-open');
        });
    });

    // Close menu on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.body.classList.remove('menu-open');
        }
    });

    // Quantity steppers
    document.querySelectorAll('.qty-stepper').forEach(function(stepper) {
        const input = stepper.querySelector('.qty-stepper__input');
        const btns  = stepper.querySelectorAll('.qty-stepper__btn');

        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const dir  = parseInt(btn.dataset.dir);
                const min  = parseInt(input.min) || 0;
                const max  = parseInt(input.max) || 99;
                const next = parseInt(input.value) + dir;

                if (next < min || next > max) return;
                input.value = next;

                // Auto-submit cart update forms
                const form = stepper.closest('form');
                if (form && form.classList.contains('cart__qty-form')) {
                    form.submit();
                }
            });
        });
    });
});
