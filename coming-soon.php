<?php
// ── Guard: if we've passed launch, redirect to the live site ─────────────────
$launch = new DateTime('2026-05-01 18:00:00', new DateTimeZone('America/Los_Angeles'));
$now    = new DateTime();
if ($now >= $launch) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Coming Soon — Caneeli Designs</title>
    <meta name="description" content="Handmade furniture and décor, crafted with care. Caneeli Designs launches May 1, 2026. Sign up to be first through the door.">
    <meta name="robots" content="noindex, nofollow">

    <!-- Open Graph -->
    <meta property="og:title"       content="Coming Soon — Caneeli Designs">
    <meta property="og:description" content="Something beautiful is almost here. Handmade furniture and décor, launching May 1.">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="<?php echo SITE_URL; ?>/coming-soon.php">

    <!-- Fonts (same as main site) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400..800&family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon-96x96.png" type="image/png" sizes="96x96">
    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/images/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/site.webmanifest">
    <meta name="theme-color" content="#C25B32">

    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body class="cs-body">
<div class="cs-page">

    <!-- ── Wordmark ───────────────────────────────────────────────────── -->
    <header class="cs-wordmark">
        <img class="cs-wordmark__logo"
             src="/assets/images/logowi.svg"
             alt="Caneeli Designs">
    </header>

    <!-- ── Hero ──────────────────────────────────────────────────────── -->
    <section class="cs-hero">
        <div class="cs-hero__inner">
            <p class="cs-hero__eyebrow">Something beautiful is almost here.</p>
            <h1 class="cs-hero__headline">Made by hand.<br>Arriving May&nbsp;1.</h1>
            <div class="cs-hero__rule"></div>
            <p class="cs-hero__sub">
                Every piece in the Caneeli collection is shaped, sanded, and finished by hand —
                no assembly lines, no shortcuts. We're putting the final touches on our shop
                and can't wait to share it with you.
            </p>
        </div>
    </section>

    <!-- ── Countdown ─────────────────────────────────────────────────── -->
    <section class="cs-countdown" aria-label="Countdown to launch">
        <div class="cs-countdown__grid">

            <div class="cs-countdown__block">
                <span class="cs-countdown__number" id="cs-d">00</span>
                <span class="cs-countdown__label">Days</span>
            </div>

            <span class="cs-countdown__sep" aria-hidden="true">:</span>

            <div class="cs-countdown__block">
                <span class="cs-countdown__number" id="cs-h">00</span>
                <span class="cs-countdown__label">Hours</span>
            </div>

            <span class="cs-countdown__sep" aria-hidden="true">:</span>

            <div class="cs-countdown__block">
                <span class="cs-countdown__number" id="cs-m">00</span>
                <span class="cs-countdown__label">Minutes</span>
            </div>

            <span class="cs-countdown__sep" aria-hidden="true">:</span>

            <div class="cs-countdown__block">
                <span class="cs-countdown__number" id="cs-s">00</span>
                <span class="cs-countdown__label">Seconds</span>
            </div>

        </div>
    </section>

    <!-- ── Email signup ───────────────────────────────────────────────── -->
    <section class="cs-signup">
        <div class="cs-signup__inner">
            <p class="cs-signup__eyebrow">Be first through the door.</p>
            <h2 class="cs-signup__heading">Get notified at launch.</h2>
            <p class="cs-signup__sub">
                Drop your email below and we'll send you a note the moment the shop opens —
                no spam, just one beautiful announcement.
            </p>

            <form class="cs-signup__field" id="cs-form" novalidate>
                <input
                    class="cs-signup__input"
                    id="cs-email"
                    type="email"
                    name="email"
                    placeholder="your@email.com"
                    autocomplete="email"
                    required
                    aria-label="Email address">
                <button class="btn red-button cs-signup__btn" type="submit" id="cs-submit">
                    Notify Me
                </button>
            </form>

            <p class="cs-signup__error" id="cs-error" role="alert" aria-live="polite"></p>

            <div class="cs-signup__success" id="cs-success" aria-live="polite">
                <span class="cs-signup__success-icon">✓</span>
                <span>You're on the list — see you May&nbsp;1!</span>
            </div>
        </div>
    </section>

    <!-- ── Footer ─────────────────────────────────────────────────────── -->
    <footer class="cs-footer">
        <div class="cs-footer__socials">
            <a href="https://www.tiktok.com/@caneeli.designs"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="TikTok">
                <img class="cs-footer__icon"
                     src="/assets/images/tiktok.svg"
                     alt="TikTok">
            </a>
            <a href="#"
               aria-label="Instagram">
                <img class="cs-footer__icon"
                     src="/assets/images/instagram.svg"
                     alt="Instagram">
            </a>
            <a href="#"
               aria-label="Facebook">
                <img class="cs-footer__icon"
                     src="/assets/images/facebook.svg"
                     alt="Facebook">
            </a>
        </div>
        <p class="cs-footer__copy">© 2026 Caneeli Designs — Handmade with care.</p>
    </footer>

</div><!-- /.cs-page -->

<!-- ── Countdown JS ───────────────────────────────────────────────────────── -->
<script>
(function () {
    var target = new Date('2026-05-02T01:00:00Z');

    var elD = document.getElementById('cs-d');
    var elH = document.getElementById('cs-h');
    var elM = document.getElementById('cs-m');
    var elS = document.getElementById('cs-s');

    function pad(n) {
        return String(Math.max(0, n)).padStart(2, '0');
    }

    function flip(el, newVal) {
        if (el.textContent === newVal) return;
        el.classList.add('is-flipping');
        setTimeout(function () {
            el.textContent = newVal;
            el.classList.remove('is-flipping');
        }, 200);
    }

    function tick() {
        var diff = target - Date.now();

        if (diff <= 0) {
            // Launch time — redirect to the shop
            window.location.href = '/';
            return;
        }

        var totalSecs = Math.floor(diff / 1000);
        var d = Math.floor(totalSecs / 86400);
        var h = Math.floor((totalSecs % 86400) / 3600);
        var m = Math.floor((totalSecs % 3600) / 60);
        var s = totalSecs % 60;

        flip(elD, pad(d));
        flip(elH, pad(h));
        flip(elM, pad(m));
        flip(elS, pad(s));
    }

    tick();
    setInterval(tick, 1000);
})();
</script>

<!-- ── Email form JS ──────────────────────────────────────────────────────── -->
<script>
(function () {
    var form    = document.getElementById('cs-form');
    var input   = document.getElementById('cs-email');
    var btn     = document.getElementById('cs-submit');
    var errorEl = document.getElementById('cs-error');
    var success = document.getElementById('cs-success');

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function showError(msg) {
        errorEl.textContent = msg;
        input.classList.add('is-invalid');
        // Re-trigger shake by forcing reflow
        void input.offsetWidth;
    }

    function clearError() {
        errorEl.textContent = '';
        input.classList.remove('is-invalid');
    }

    input.addEventListener('input', clearError);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearError();

        var email = input.value.trim();

        if (!EMAIL_RE.test(email)) {
            showError('Please enter a valid email address.');
            input.focus();
            return;
        }

        btn.classList.add('is-loading');
        btn.disabled = true;

        fetch('/coming-soon-signup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            btn.classList.remove('is-loading');
            btn.disabled = false;

            if (data.ok) {
                form.style.display = 'none';
                errorEl.style.display = 'none';
                success.classList.add('is-visible');
            } else {
                showError(data.error || 'Something went wrong. Please try again.');
            }
        })
        .catch(function () {
            btn.classList.remove('is-loading');
            btn.disabled = false;
            showError('Connection error. Please try again.');
        });
    });
})();
</script>

</body>
</html>
