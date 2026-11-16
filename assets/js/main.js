document.addEventListener('DOMContentLoaded', function() {

    // Hot off the shelf: a swipeable row. Arrows scroll by one card and
    // switch off at either end; no autoplay.
    (function() {
        const row = document.querySelector('[data-shelf]');
        if (!row) return;
        const track = row.querySelector('[data-shelf-track]');
        const prev  = row.querySelector('[data-shelf-prev]');
        const next  = row.querySelector('[data-shelf-next]');

        function step() {
            const item = track.querySelector('.shelf-row__item');
            if (!item) return track.clientWidth;
            const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
            return item.getBoundingClientRect().width + gap;
        }
        function update() {
            const max = track.scrollWidth - track.clientWidth - 2;
            prev.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft >= max;
            row.classList.toggle('shelf-row--static', max <= 0);
        }
        const smooth = matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
        prev.addEventListener('click', function() { track.scrollBy({ left: -step(), behavior: smooth }); });
        next.addEventListener('click', function() { track.scrollBy({ left: step(), behavior: smooth }); });
        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    })();
    // Homepage hero video: hold on the first frame for reduced-motion visitors.
    (function() {
        const video = document.querySelector('.hero-fish');
        if (!video || !matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        video.removeAttribute('autoplay');
        video.pause();
    })();

    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelectorAll('.nav a');

    // Toggle menu
    function setMenu(open) {
        document.body.classList.toggle('menu-open', open);
        menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    menuToggle.addEventListener('click', function() {
        setMenu(!document.body.classList.contains('menu-open'));
    });

    // Close menu when a link is clicked
    navLinks.forEach(link => {
        link.addEventListener('click', function() { setMenu(false); });
    });

    // Close menu on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') setMenu(false);
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

// New series countdown in the home hero. The page renders
// the starting numbers server-side; this keeps them ticking and flips it to
// "out now" at the release time.
(function () {
    var nodes = document.querySelectorAll('[data-drop-release]');
    if (!nodes.length) return;
    var release = parseInt(nodes[0].getAttribute('data-drop-release'), 10);
    var section = document.getElementById('new-series');
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var timer;

    function tick() {
        var left = Math.max(0, Math.floor((release - Date.now()) / 1000));
        var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600),
            m = Math.floor(left % 3600 / 60), s = left % 60;
        if (section) {
            var set = function (k, v) { var el = section.querySelector('[data-drop-' + k + ']'); if (el) el.textContent = pad(v); };
            set('d', d); set('h', h); set('m', m); set('s', s);
        }
        if (left === 0) {
            clearInterval(timer);
            // Reload once so the bar and section show their "out now" state.
            if (!sessionStorage.getItem('dropReloaded')) {
                try { sessionStorage.setItem('dropReloaded', '1'); } catch (e) {}
                location.reload();
            }
        }
    }
    if (section && section.classList.contains('is-live')) return;
    tick();
    timer = setInterval(tick, 1000);
})();

