/* =====================================================================
   QMO KAMPANYA BANNER SLIDER — FRONTEND SCRIPT
   document.currentScript kapsamı · IntersectionObserver autoplay ·
   ok navigasyonu · kaydırma/solma geçişi · peek · swipe ·
   prefers-reduced-motion

   Ürün vitrini slider'ının betiğinden (frontend-slider.js) bağımsızdır;
   yalnızca kendi kökünü (data-qmo-banner-slider) sürer.
===================================================================== */
(function () {
    'use strict';

    /* currentScript, betik değerlendirilirken okunmalı; DOMContentLoaded
       beklenirse o anda zaten null'dır. defer/birleştirilmiş kopyada da
       null olabilir — o zaman henüz bağlanmamış kökü DOM'dan buluruz. */
    var script = document.currentScript;

    function boot() {
        var preferred = script && script.previousElementSibling &&
            script.previousElementSibling.matches &&
            script.previousElementSibling.matches('[data-qmo-banner-slider]')
            ? [script.previousElementSibling]
            : document.querySelectorAll('[data-qmo-banner-slider]:not([data-qmo-banner-ready])');

        for (var i = 0; i < preferred.length; i++) {
            if (preferred[i].getAttribute('data-qmo-banner-ready')) continue;
            preferred[i].setAttribute('data-qmo-banner-ready', '1');
            init(preferred[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    function init(root) {
    var track = root.querySelector('[data-qmo-banner-track]');
    var slides = root.querySelectorAll('.qmo-banner-slide');
    if (!track || slides.length < 1) return;

    var dots = root.querySelectorAll('[data-qmo-banner-dot]');
    var prevBtn = root.querySelector('[data-qmo-banner-prev]');
    var nextBtn = root.querySelector('[data-qmo-banner-next]');

    /* Geçiş biçimi yönetimden gelir (bkz. QMO_Banner_Slider_Settings).
       'fade' ise track hiç kaydırılmaz: slaytlar üst üste durur ve
       görünürlük .is-active sınıfıyla değişir (bkz. .is-fade kuralları
       frontend-banner-slider.css içinde). */
    var fade = root.getAttribute('data-gecis') === 'fade';

    var current = 0;
    var timer = null;
    var visible = false;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // 0 = otomatik geçiş kapalı (kısa kod attribute'u; bkz. shortcode-banner-slider.php).
    var intervalMs = parseInt(root.getAttribute('data-autoplay'), 10);
    if (isNaN(intervalMs)) intervalMs = 4500;

    /**
     * Bir slayttan diğerine geçerken kaydırılacak piksel mesafesi:
     * slayt genişliği + aralarındaki boşluk.
     *
     * Neden ölçüm, neden sabit yüzde değil: peek açıkken slayt artık
     * viewport'un %100'ü değil, track padding'i düşülmüş hâli (%88) ve
     * araya --qmo-banner-gap kadar bir boşluk giriyor. Bu boşluk cqi
     * tabanlı bir clamp(), yani ekran genişliğine göre 8-12px arasında
     * değişiyor — tek bir yüzdeyle ifade edilemez. İki komşu slaytın sol
     * kenarları arasındaki fark ikisini birden tek okumada verir ve CSS
     * değerleri değişse bile doğru kalır.
     *
     * Track'in o an uygulanmış transform'u iki slaytı da aynı miktarda
     * kaydırdığı için farkı etkilemez; geçiş animasyonu sürerken çağrılsa
     * bile sonuç doğrudur.
     *
     * @return {number} Piksel cinsinden adım; tek slaytta 0.
     */
    function slideStep() {
        if (slides.length < 2) return 0;

        var fromRect = Math.abs(
            slides[1].getBoundingClientRect().left -
            slides[0].getBoundingClientRect().left
        );
        if (fromRect > 0) return fromRect;

        /* Layout henüz yoksa (CSS asenkron, display:none ebeveyn) rect
           0 döner. offsetLeft transform'dan bağımsızdır ve aynı adımı
           verir; o da 0'sa görsel genişlik + gap yedeği. */
        var fromOffset = slides[1].offsetLeft - slides[0].offsetLeft;
        if (fromOffset > 0) return fromOffset;

        var gap = 0;
        if (window.getComputedStyle) {
            gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap) || 0;
        }

        return (slides[0].offsetWidth || 0) + gap;
    }

    function setActive(index) {
        if (index < 0) index = slides.length - 1;
        if (index >= slides.length) index = 0;
        current = index;

        if (!fade) {
            track.style.transform = 'translateX(' + (-slideStep() * current) + 'px)';
        }

        for (var s = 0; s < slides.length; s++) {
            slides[s].classList.toggle('is-active', s === current);
        }

        for (var i = 0; i < dots.length; i++) {
            var isActive = i === current;
            dots[i].classList.toggle('is-active', isActive);
            dots[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
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
        if (reducedMotion || intervalMs <= 0 || slides.length < 2 || !visible) return;
        timer = setInterval(next, intervalMs);
    }

    /* Oklar: yönetimden kapatılabildiği için DOM'da olmayabilir. */
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            prev();
            startAutoplay();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            next();
            startAutoplay();
        });
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

    /* Transform px cinsinden olduğu için slayt genişliği değişince
       (pencere yeniden boyutlandırma, mobil döndürme) yeniden
       hesaplanmalı; aksi hâlde slaytlar viewport'a göre kayar. Geçiş
       animasyonu bu tek karede kapatılır: yeniden ölçüm bir "geçiş"
       değil, aynı slaydın yeni konumudur. */
    var resizeTimer = null;

    window.addEventListener('resize', function () {
        if (fade) return;

        if (resizeTimer) clearTimeout(resizeTimer);

        resizeTimer = setTimeout(function () {
            resizeTimer = null;
            track.style.transition = 'none';
            setActive(current);
            void track.offsetWidth; // reflow: transition'ı geri açmadan önce
            track.style.transition = '';
        }, 120);
    });

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                visible = entry.isIntersecting;
                if (visible) {
                    /* İlk ölçüm gizli/0 genişlikteydiysa görünür olunca
                       peek adımı yeniden hesaplanır. */
                    if (!fade) setActive(current);
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

    /* defer betik parse bitince çalışır ama stiller henüz uygulanmamış
       olabilir (asenkron CSS). İlk karede 0 okunan adım bir kare sonra
       ve window.load'da tekrar ölçülür. */
    if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(function () {
            if (!fade) setActive(current);
            requestAnimationFrame(function () {
                if (!fade) setActive(current);
            });
        });
    }

    window.addEventListener('load', function () {
        if (!fade) setActive(current);
    });
    } // init
})();
