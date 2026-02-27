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
});
