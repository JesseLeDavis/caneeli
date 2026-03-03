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
        let dragStartX = null;
        let didDrag    = false;
        const DRAG_THRESHOLD = 50;

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

        // Drag / swipe support
        stage.addEventListener('pointerdown', function(e) {
            if (e.button !== 0) return;
            dragStartX = e.clientX;
            didDrag    = false;
            stage.setPointerCapture(e.pointerId);
        });

        stage.addEventListener('pointermove', function(e) {
            if (dragStartX === null) return;
            if (Math.abs(e.clientX - dragStartX) > 8) didDrag = true;
        });

        stage.addEventListener('pointerup', function(e) {
            if (dragStartX === null) return;
            const dx = e.clientX - dragStartX;
            dragStartX = null;
            if (Math.abs(dx) >= DRAG_THRESHOLD) {
                goTo(dx < 0 ? current + 1 : current - 1);
            }
        });

        stage.addEventListener('pointercancel', function() { dragStartX = null; });

        // Suppress card link clicks after a drag
        stage.addEventListener('click', function(e) {
            if (didDrag) { e.preventDefault(); didDrag = false; }
        }, true);

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
