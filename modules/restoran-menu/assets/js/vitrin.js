/* =====================================================================
   ÜRÜN VİTRİNİ — FRONTEND

   Kaydırmanın kendisi CSS'in işi (overflow-x + scroll-snap); dokunmatik
   swipe ve momentum tarayıcıdan gelir. Bu dosya yalnızca ÜSTÜNE ekler:
   ok butonları, sayfa noktaları, otomatik kayma ve fare sürüklemesi.
   Dosya hiç yüklenmese de vitrin kaydırılabilir kalır — denetimler
   `hidden` başlar ve yalnızca burada açılır.

   Sayfa sayısı ekran genişliğine bağlıdır (dar ekranda daha az sütun
   görünür), bu yüzden noktalar sunucuda değil burada üretilir ve
   yeniden boyutlandırmada tazelenir.

   jQuery bağımlılığı yoktur.
===================================================================== */
(function () {
    'use strict';

    var REDUCED_MOTION = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Bir vitrini hazırlar.
     *
     * @param {HTMLElement} root .qrms-vitrin kökü
     */
    function initVitrin(root) {
        if (root.dataset.qrmsReady === '1') return;
        root.dataset.qrmsReady = '1';

        var viewport = root.querySelector('[data-qrms-viewport]');
        if (!viewport) return;

        var cards = Array.prototype.slice.call(root.querySelectorAll('.qrms-vitrin-card'));
        if (!cards.length) return;

        var dotsWrap = root.querySelector('[data-qrms-dots]');
        var prev     = root.querySelector('[data-qrms-prev]');
        var next     = root.querySelector('[data-qrms-next]');

        /* -------------------------------------------------------------
           ÖLÇÜLER
           Sütun genişliği ve boşluk CSS'ten gelir (dar ekranda değişir),
           bu yüzden sabit varsayılmaz — her seferinde DOM'dan okunur.
        ------------------------------------------------------------- */
        function gap() {
            var g = parseFloat(getComputedStyle(viewport).columnGap || '0');
            return isNaN(g) ? 0 : g;
        }

        /** Bir kartın kapladığı yatay adım (kart genişliği + boşluk). */
        function step() {
            return cards[0].getBoundingClientRect().width + gap();
        }

        /** Bir ekranda yan yana kaç kart görünüyor. */
        function perView() {
            var s = step();
            if (s <= 0) return 1;
            return Math.max(1, Math.round((viewport.clientWidth + gap()) / s));
        }

        /** Kartlar kaç "satır"a bölündü (grid-template-rows). */
        function rowCount() {
            var r = parseInt(getComputedStyle(root).getPropertyValue('--qrms-vitrin-rows'), 10);
            return r > 0 ? r : 1;
        }

        /** Toplam sayfa sayısı — grid sütun sütun dolduğu için satır sayısına da bağlı. */
        function pageCount() {
            var sutunSayisi = Math.ceil(cards.length / rowCount());
            return Math.max(1, Math.ceil(sutunSayisi / perView()));
        }

        function pageWidth() {
            return perView() * step();
        }

        function currentPage() {
            var w = pageWidth();
            if (w <= 0) return 0;
            return Math.min(pageCount() - 1, Math.round(viewport.scrollLeft / w));
        }

        function goTo(index) {
            var last = pageCount() - 1;
            // Sondan sonra başa dön: otomatik kayma sonda takılıp kalmasın.
            if (index > last) index = 0;
            if (index < 0) index = last;

            viewport.scrollTo({ left: index * pageWidth(), behavior: REDUCED_MOTION ? 'auto' : 'smooth' });
        }

        /* -------------------------------------------------------------
           NOKTALAR — sayfa sayısı değiştikçe yeniden kurulur
        ------------------------------------------------------------- */
        var dots       = [];
        var dotsForCnt = -1;

        function buildDots() {
            if (!dotsWrap) return;

            var cnt = pageCount();
            if (cnt === dotsForCnt) return;

            dotsForCnt = cnt;
            dotsWrap.textContent = '';
            dots = [];

            if (cnt < 2) {
                dotsWrap.hidden = true;
                return;
            }

            for (var i = 0; i < cnt; i++) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'qrms-vitrin-dot';
                dot.setAttribute('aria-label', (i + 1) + '. sayfa');
                dot.addEventListener('click', (function (n) {
                    return function () { goTo(n); };
                })(i));
                dotsWrap.appendChild(dot);
                dots.push(dot);
            }

            dotsWrap.hidden = false;
        }

        /* -------------------------------------------------------------
           GÖSTERGELER
        ------------------------------------------------------------- */
        function syncUi() {
            buildDots();

            var i       = currentPage();
            var tekSayfa = pageCount() < 2;

            dots.forEach(function (dot, n) {
                dot.classList.toggle('is-active', n === i);
                dot.setAttribute('aria-current', n === i ? 'true' : 'false');
            });

            // Sondaki 1px'lik yuvarlama farkları "devam ediyor" gibi
            // görünmesin diye küçük bir tolerans bırakılıyor.
            var maxScroll = viewport.scrollWidth - viewport.clientWidth - 2;

            if (prev) {
                prev.hidden   = tekSayfa;
                prev.disabled = viewport.scrollLeft <= 2;
            }
            if (next) {
                next.hidden   = tekSayfa;
                next.disabled = viewport.scrollLeft >= maxScroll;
            }
        }

        var syncQueued = false;
        function queueSync() {
            if (syncQueued) return;
            syncQueued = true;
            window.requestAnimationFrame(function () {
                syncQueued = false;
                syncUi();
            });
        }

        viewport.addEventListener('scroll', queueSync, { passive: true });
        window.addEventListener('resize', queueSync);

        if (prev) prev.addEventListener('click', function () { goTo(currentPage() - 1); });
        if (next) next.addEventListener('click', function () { goTo(currentPage() + 1); });

        // Klavye: viewport odaklıyken sol/sağ ok tuşları sayfa değiştirir.
        viewport.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); goTo(currentPage() + 1); }
            if (e.key === 'ArrowLeft')  { e.preventDefault(); goTo(currentPage() - 1); }
        });

        /* -------------------------------------------------------------
           OTOMATİK KAYMA
           Fare üzerindeyken, odak içerideyken, dokunma sırasında ve
           sekme arka plandayken durur.
        ------------------------------------------------------------- */
        var autoplay = root.getAttribute('data-autoplay') === '1' && !REDUCED_MOTION;
        var speed    = parseInt(root.getAttribute('data-speed'), 10);
        var timer    = null;

        if (!speed || speed < 1000) speed = 4000;

        function start() {
            if (!autoplay || timer || pageCount() < 2) return;
            timer = window.setInterval(function () { goTo(currentPage() + 1); }, speed);
        }

        function stop() {
            if (!timer) return;
            window.clearInterval(timer);
            timer = null;
        }

        if (autoplay) {
            ['mouseenter', 'focusin', 'touchstart', 'pointerdown'].forEach(function (evt) {
                root.addEventListener(evt, stop, { passive: true });
            });

            ['mouseleave', 'focusout', 'touchend'].forEach(function (evt) {
                root.addEventListener(evt, start, { passive: true });
            });

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) { stop(); } else { start(); }
            });

            start();
        }

        /* -------------------------------------------------------------
           FARE İLE SÜRÜKLEME
           Yalnızca ince işaretçilerde (fare) bağlanır: dokunmatikte
           tarayıcının kendi kaydırması zaten daha iyi çalışıyor ve
           ikisi birbirine karışırdı.
        ------------------------------------------------------------- */
        var dragEnabled = root.getAttribute('data-drag') === '1';
        var finePointer = !window.matchMedia || window.matchMedia('(pointer: fine)').matches;

        if (dragEnabled && finePointer && window.PointerEvent) {
            root.classList.add('is-draggable');

            var dragging  = false;
            var startX    = 0;
            var startLeft = 0;
            var moved     = 0;

            viewport.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                dragging  = true;
                moved     = 0;
                startX    = e.clientX;
                startLeft = viewport.scrollLeft;
                root.classList.add('is-dragging');
                viewport.setPointerCapture(e.pointerId);
            });

            viewport.addEventListener('pointermove', function (e) {
                if (!dragging) return;
                var delta = e.clientX - startX;
                moved = Math.abs(delta);
                viewport.scrollLeft = startLeft - delta;
            });

            var endDrag = function (e) {
                if (!dragging) return;
                dragging = false;
                root.classList.remove('is-dragging');

                if (e.pointerId !== undefined && viewport.hasPointerCapture && viewport.hasPointerCapture(e.pointerId)) {
                    viewport.releasePointerCapture(e.pointerId);
                }

                // snap kapalıyken bırakıldı: en yakın sayfaya oturt.
                goTo(currentPage());
            };

            viewport.addEventListener('pointerup', endDrag);
            viewport.addEventListener('pointercancel', endDrag);
            viewport.addEventListener('pointerleave', endDrag);

            // Sürüklemenin sonundaki tıklama karta gitmesin.
            viewport.addEventListener('click', function (e) {
                if (moved > 5) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);
        }

        syncUi();
    }

    function initAll() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-qrms-vitrin]'), initVitrin);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Elementor editöründe widget yeniden çizildiğinde tekrar bağla.
    window.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/rma_vitrin_widget.default', initAll);
        }
    });
})();
