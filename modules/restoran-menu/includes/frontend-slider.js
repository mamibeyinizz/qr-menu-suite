/* =====================================================================
   QMO ÖNE ÇIKAN SLIDER — FRONTEND SCRIPT
   document.currentScript kapsamı · IntersectionObserver autoplay
===================================================================== */
(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) return;

    var root = script.previousElementSibling;
    if (!root || !root.matches('[data-qmo-slider]')) return;

    var slides = root.querySelectorAll('.qmo-slider-slide');
    if (!slides.length) return;

    var prevBtn = root.querySelector('.qmo-slider-nav-prev');
    var nextBtn = root.querySelector('.qmo-slider-nav-next');

    /* Ürün detayı: kart tıklanınca/Enter-Space ile paylaşımlı modal açılır
       (bkz. rma-detail-modal.js, vitrin.js ile aynı desen). */
    var products = root.querySelectorAll('.qmo-slider-product');
    for (var p = 0; p < products.length; p++) {
        (function (card) {
            var id = card.getAttribute('data-id');
            if (!id) return;

            card.addEventListener('click', function () {
                if (window.QRMSDetailModal) window.QRMSDetailModal.open(id);
            });

            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (window.QRMSDetailModal) window.QRMSDetailModal.open(id);
                }
            });
        })(products[p]);
    }
    var current = 0;
    var timer = null;
    var visible = false;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var intervalMs = 5000;

    function setActive(index) {
        if (index < 0) index = slides.length - 1;
        if (index >= slides.length) index = 0;
        current = index;

        for (var i = 0; i < slides.length; i++) {
            slides[i].classList.toggle('is-active', i === current);
        }
    }

    function next() {
        setActive(current + 1);
    }

    function prev() {
        setActive(current - 1);
    }

    function stopAutoplay() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function startAutoplay() {
        stopAutoplay();
        if (reducedMotion || slides.length < 2 || !visible) return;
        timer = setInterval(next, intervalMs);
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            next();
            startAutoplay();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            prev();
            startAutoplay();
        });
    }

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

    setActive(0);
})();
