document.addEventListener('DOMContentLoaded', function() {
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
