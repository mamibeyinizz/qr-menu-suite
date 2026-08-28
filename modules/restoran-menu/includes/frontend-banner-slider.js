/* =====================================================================
   QMO KAMPANYA BANNER SLIDER — FRONTEND SCRIPT
   document.currentScript kapsamı · IntersectionObserver autoplay ·
   swipe · prefers-reduced-motion

   Ürün vitrini slider'ının betiğinden (frontend-slider.js) bağımsızdır;
   yalnızca kendi kökünü (data-qmo-banner-slider) sürer.
===================================================================== */
(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) return;

    var root = script.previousElementSibling;
    if (!root || !root.matches('[data-qmo-banner-slider]')) return;

    var track = root.querySelector('[data-qmo-banner-track]');
    var slides = root.querySelectorAll('.qmo-banner-slide');
    if (!track || slides.length < 1) return;

    var dots = root.querySelectorAll('[data-qmo-banner-dot]');

    var current = 0;
    var timer = null;
    var visible = false;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // 0 = otomatik geçiş kapalı (kısa kod attribute'u; bkz. shortcode-banner-slider.php).
    var intervalMs = parseInt(root.getAttribute('data-autoplay'), 10);
    if (isNaN(intervalMs)) intervalMs = 4500;

    function setActive(index) {
        if (index < 0) index = slides.length - 1;
        if (index >= slides.length) index = 0;
        current = index;

        track.style.transform = 'translateX(' + (-100 * current) + '%)';

        for (var i = 0; i < dots.length; i++) {
            var isActive = i === current;
            dots[i].classList.toggle('is-active', isActive);
            dots[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
        }
    }

    function next() {
        setActive(current + 1);
    }

    function stopAutoplay() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function startAutoplay() {
        stopAutoplay();
        if (reducedMotion || intervalMs <= 0 || slides.length < 2 || !visible) return;
        timer = setInterval(next, intervalMs);
    }

    for (var d = 0; d < dots.length; d++) {
        (function (dot, index) {
            dot.addEventListener('click', function () {
                setActive(index);
                startAutoplay();
            });
        })(dots[d], d);
    }

    /* Swipe: yatay hareket dikeyden baskınsa slayt değiştirir; dikey
       kaydırma sayfanın kendi işidir, engellenmez. */
    var startX = 0;
    var startY = 0;
    var tracking = false;
    var moved = false;

    root.addEventListener('touchstart', function (e) {
        if (e.touches.length !== 1) return;
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        tracking = true;
        moved = false;
        stopAutoplay();
    }, { passive: true });

    root.addEventListener('touchmove', function (e) {
        if (!tracking) return;
        var dx = e.touches[0].clientX - startX;
        var dy = e.touches[0].clientY - startY;

        if (!moved && Math.abs(dy) > Math.abs(dx)) {
            tracking = false; // dikey kaydırma: karuseli bırak
            return;
        }
        if (Math.abs(dx) > 8) moved = true;
    }, { passive: true });

    root.addEventListener('touchend', function (e) {
        if (!tracking) {
            startAutoplay();
            return;
        }
        tracking = false;

        var endX = e.changedTouches && e.changedTouches.length
            ? e.changedTouches[0].clientX
            : startX;
        var dx = endX - startX;

        if (Math.abs(dx) > 40) {
            setActive(current + (dx < 0 ? 1 : -1));
        }
        startAutoplay();
    });

    /* Bağlantılı banner'da swipe yanlışlıkla tıklama sayılmasın. */
    root.addEventListener('click', function (e) {
        if (!moved) return;
        moved = false;
        e.preventDefault();
    }, true);

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                visible = entry.isIntersecting;
                if (visible) {
                    startAutoplay();
                } else {
                    stopAutoplay();
                }
            });
        }, { threshold: 0.25 });

        observer.observe(root);
    } else {
        visible = true;
        startAutoplay();
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopAutoplay();
        } else if (visible) {
            startAutoplay();
        }
    });

    root.addEventListener('mouseenter', stopAutoplay);
    root.addEventListener('mouseleave', function () {
        if (visible) startAutoplay();
    });

    setActive(0);
})();
