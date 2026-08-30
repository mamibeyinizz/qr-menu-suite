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

    var timer = null;
    var visible = false;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var wrapping = false;

    /* Sonsuz kaydırma: son slaydın klonu başa, ilk slaydın klonu sona.
       Peek bozulmaz (uçta gerçek komşu görünür); sınırda transition
       kapatılıp gerçek slayta atlanır. Solma ve reduced-motion'da
       klon yok — orada "yön" zaten anlamsız / istenmez. */
    var realCount = slides.length;
    var looping = !fade && !reducedMotion && realCount > 1;

    function cloneSlide(el) {
        var copy = el.cloneNode(true);
        copy.classList.remove('is-active');
        copy.removeAttribute('data-qmo-banner-slide');
        copy.setAttribute('data-qmo-banner-clone', '1');
        copy.setAttribute('aria-hidden', 'true');
        copy.removeAttribute('aria-roledescription');
        copy.removeAttribute('aria-label');
        copy.setAttribute('tabindex', '-1');
        var img = copy.querySelector('img');
        if (img) img.setAttribute('loading', 'eager');
        return copy;
    }

    if (looping) {
        track.insertBefore(cloneSlide(slides[realCount - 1]), slides[0]);
        track.appendChild(cloneSlide(slides[0]));
        slides = track.querySelectorAll('.qmo-banner-slide');
    }

    /* looping: [klonSon, gerçek0..gerçekN-1, klonİlk] — ilk gerçek index 1. */
    var current = looping ? 1 : 0;

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

    function realIndex(trackIndex) {
        if (!looping) return trackIndex;
        if (trackIndex <= 0) return realCount - 1;
        if (trackIndex >= realCount + 1) return 0;
        return trackIndex - 1;
    }

    function applyTransform(trackIndex, instant) {
        if (fade) return;
        if (instant) track.style.transition = 'none';
        track.style.transform = 'translateX(' + (-slideStep() * trackIndex) + 'px)';
        if (instant) {
            void track.offsetWidth;
            track.style.transition = '';
        }
    }

    function syncUi(trackIndex) {
        var real = realIndex(trackIndex);
        for (var s = 0; s < slides.length; s++) {
            slides[s].classList.toggle('is-active', s === trackIndex);
        }
        for (var i = 0; i < dots.length; i++) {
            var isActive = i === real;
            dots[i].classList.toggle('is-active', isActive);
            dots[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
        }
    }

    function snapIfNeeded() {
        if (!wrapping) return;
        wrapping = false;
        if (current === 0) {
            current = realCount;
            applyTransform(current, true);
            syncUi(current);
        } else if (current === realCount + 1) {
            current = 1;
            applyTransform(current, true);
            syncUi(current);
        }
    }

    function setActive(index, instant) {
        if (fade || !looping) {
            if (index < 0) index = realCount - 1;
            if (index >= realCount) index = 0;
            current = index;
            applyTransform(current, instant);
            syncUi(current);
            return;
        }

        current = index;
        applyTransform(current, instant);
        syncUi(current);

        if (!instant && (current === 0 || current === realCount + 1)) {
            wrapping = true;
            /* transitionend kaçarsa (eski motor, reduced-motion geçişi)
               0.5s CSS süresinin ardından yine de sıçra. */
            window.setTimeout(snapIfNeeded, 600);
        }
    }

    function goToReal(realIdx) {
        if (!looping) {
            setActive(realIdx);
            return;
        }
        var hedef = realIdx + 1;
        var simdi = realIndex(current);
        /* Son → ilk ve ilk → son nokta tıklamasında klon üzerinden
           doğru yönde kay (geriye tüm slaytları tarama). */
        if (simdi === realCount - 1 && realIdx === 0) {
            hedef = realCount + 1;
        } else if (simdi === 0 && realIdx === realCount - 1) {
            hedef = 0;
        }
        setActive(hedef);
    }

    function next() {
        if (wrapping) return;
        setActive(current + 1);
    }

    function prev() {
        if (wrapping) return;
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
        if (reducedMotion || intervalMs <= 0 || realCount < 2 || !visible) return;
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
                if (wrapping) return;
                goToReal(index);
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
            if (dx < 0) next();
            else prev();
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
            if (looping && (current === 0 || current === realCount + 1)) {
                current = current === 0 ? realCount : 1;
            }
            setActive(current, true);
        }, 120);
    });

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                visible = entry.isIntersecting;
                if (visible) {
                    /* İlk ölçüm gizli/0 genişlikteydiysa görünür olunca
                       peek adımı yeniden hesaplanır. */
                    if (!fade) setActive(current, true);
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

    if (looping) {
        track.addEventListener('transitionend', function (e) {
            if (e.target !== track) return;
            if (e.propertyName && e.propertyName.indexOf('transform') === -1) return;
            snapIfNeeded();
        });
    }

    setActive(current, true);

    /* defer betik parse bitince çalışır ama stiller henüz uygulanmamış
       olabilir (asenkron CSS). İlk karede 0 okunan adım bir kare sonra
       ve window.load'da tekrar ölçülür. */
    if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(function () {
            if (!fade) setActive(current, true);
            requestAnimationFrame(function () {
                if (!fade) setActive(current, true);
            });
        });
    }

    window.addEventListener('load', function () {
        if (!fade) setActive(current, true);
    });
    } // init
})();
