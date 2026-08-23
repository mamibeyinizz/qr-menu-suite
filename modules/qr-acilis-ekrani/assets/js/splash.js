(function () {
    'use strict';

    var DISMISS_EVENTS = ['click', 'touchstart', 'keydown'];

    function hasDismissCookie() {
        return document.cookie.indexOf('splash_dismissed=1') !== -1;
    }

    function removeLoadingState() {
        document.documentElement.classList.remove('splash-loading');
    }

    function getOverlay() {
        return document.getElementById('custom-splash-overlay');
    }

    function clearHeadFailsafe() {
        if (window.__splashFailsafe) {
            clearTimeout(window.__splashFailsafe);
            window.__splashFailsafe = null;
        }
    }

    /* ---------------- TR / EN düğmesi ----------------

       Dil sunucuda seçilmez: ana sayfanın HTML'i her ziyaretçide birebir
       aynıdır (tam sayfa cache güvenliği). Sunucu Türkçeyi basar, İngilizcesi
       her metnin yanında data-sp-en olarak durur; hangisinin görüneceğine
       çerezi okuyan istemci karar verir — splash'ın "gösterilsin mi"
       kararındaki desenin aynısı.

       Önizlemede (isPreview) çerez OKUNMAZ ve YAZILMAZ: yönetici önizlemede
       EN'e bakınca kendi ziyaretçi tercihini değiştirmiş olmaz. */

    var LANG_COOKIE = 'qrms_splash_lang';

    function readLangCookie() {
        var m = document.cookie.match(/(?:^|;\s*)qrms_splash_lang=(tr|en)/);
        return m ? m[1] : '';
    }

    function applyLang(root, lang) {
        var nodes = root.querySelectorAll('[data-sp-tr]');
        var i, el, text, attrs, j;

        for (i = 0; i < nodes.length; i++) {
            el = nodes[i];
            text = el.getAttribute('data-sp-' + lang);

            if (text === null) continue;

            attrs = el.getAttribute('data-sp-attr');

            if (attrs) {
                // Rozetin görünür metni yoktur; dil değişimi erişilebilirlik
                // etiketine ve dokunma ipucuna yazılır.
                attrs = attrs.split(/\s+/);
                for (j = 0; j < attrs.length; j++) {
                    if (attrs[j]) el.setAttribute(attrs[j], text);
                }
            } else {
                el.textContent = text;
            }
        }

        var buttons = root.querySelectorAll('.splash-lang-btn');
        for (i = 0; i < buttons.length; i++) {
            var on = buttons[i].getAttribute('data-sp-lang') === lang;
            buttons[i].classList.toggle('is-active', on);
            buttons[i].setAttribute('aria-pressed', on ? 'true' : 'false');
        }

        root.setAttribute('lang', lang);
    }

    function initLang(overlay, isPreview) {
        var toggle = overlay.querySelector('.splash-lang');
        if (!toggle) return;

        // Modal overlay'in DIŞINDA durur; iki dilli metinleri o da taşır.
        var roots = [ overlay ];
        var modal = document.getElementById('wifi-modal');
        if (modal) roots.push(modal);

        function setLang(lang, persist) {
            for (var i = 0; i < roots.length; i++) {
                applyLang(roots[i], lang);
            }

            if (!persist || isPreview) return;

            var date = new Date();
            date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000));
            document.cookie = LANG_COOKIE + '=' + lang + '; expires=' + date.toUTCString() + '; path=/; SameSite=Lax';
        }

        toggle.addEventListener('click', function (event) {
            var btn = event.target.closest('.splash-lang-btn');
            if (!btn) return;

            event.preventDefault();
            event.stopPropagation();   // dil seçmek splash'ı kapatmaz
            setLang(btn.getAttribute('data-sp-lang') === 'en' ? 'en' : 'tr', true);
        });

        var stored = isPreview ? '' : readLangCookie();
        if (stored === 'en') {
            setLang('en', false);
        }
    }

    /* ---------------- QR Çeviri bayrak seçici ----------------

       Kendi çeviri motoru yok. Element DOM'da yoksa (admin kapalı) hemen
       çıkar — dinleyici, zamanlayıcı, sürekli çalışan kod yok.

       Tıklama splash'ı kapatmaz ve sayfayı o anda yenilemez (splash iki
       kez görünmesin). Dil QR Çeviri'nin rma_lang çerezine + sessionStorage
       rma_dil'e yazılır; splash kapandıktan sonra menü URL'sine ?lang=
       eklenir. qrmenuTranslate() varsa aynı sayfada kalındığında o çağrılır. */

    function noopCeviri() {
        return {
            applyToUrl: function (url) { return url; },
            shouldReload: function () { return false; },
            navigate: function () {}
        };
    }

        function initCeviri(overlay, isPreview, onActivity) {
        var root = overlay.querySelector('.splash-ceviri');
        if (!root) return noopCeviri();

        var cookieName = root.getAttribute('data-cookie') || 'rma_lang';
        var btn = root.querySelector('.splash-ceviri-btn');
        var flagEl = root.querySelector('.splash-ceviri-flag');
        var selectedLang = root.getAttribute('data-default') || 'tr';
        var selectedFlag = flagEl ? flagEl.textContent : '';
        var selectedName = '';

        function updateQueryParam(url, ad, deger) {
            if (typeof window.rmaCeviriUpdateQueryParam === 'function') {
                return window.rmaCeviriUpdateQueryParam(url, ad, deger);
            }

            var parca = String(url).split('#');
            var temel = parca[0];
            var hash = parca[1] ? '#' + parca[1] : '';
            var bolum = temel.split('?');
            var yol = bolum[0];
            var sorgu = bolum[1] || '';
            var parcalar = sorgu ? sorgu.split('&') : [];
            var yeni = [];
            var bulundu = false;
            var i;

            for (i = 0; i < parcalar.length; i++) {
                if (!parcalar[i]) continue;
                if (parcalar[i].split('=')[0] === ad) {
                    if (!bulundu) {
                        yeni.push(ad + '=' + encodeURIComponent(deger));
                        bulundu = true;
                    }
                    continue;
                }
                yeni.push(parcalar[i]);
            }
            if (!bulundu) {
                yeni.push(ad + '=' + encodeURIComponent(deger));
            }

            return yol + (yeni.length ? '?' + yeni.join('&') : '') + hash;
        }

        function removeQueryParam(url, ad) {
            var parca = String(url).split('#');
            var temel = parca[0];
            var hash = parca[1] ? '#' + parca[1] : '';
            var bolum = temel.split('?');
            var yol = bolum[0];
            var sorgu = bolum[1] || '';
            if (!sorgu) return url;

            var parcalar = sorgu.split('&');
            var yeni = [];
            var i;

            for (i = 0; i < parcalar.length; i++) {
                if (!parcalar[i]) continue;
                if (parcalar[i].split('=')[0] === ad) continue;
                yeni.push(parcalar[i]);
            }

            return yol + (yeni.length ? '?' + yeni.join('&') : '') + hash;
        }

        function applyToUrl(url, lang) {
            if (!url || !lang) return url;
            return lang === 'tr' ? removeQueryParam(url, 'lang') : updateQueryParam(url, 'lang', lang);
        }

        function urlLang() {
            var m = window.location.search.match(/[?&]lang=([^&]+)/);
            return m ? decodeURIComponent(m[1]) : 'tr';
        }

        function closePanel() {
            root.classList.remove('is-open');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }

        function paint(lang) {
            var opts = root.querySelectorAll('.splash-ceviri-opt');
            var i, on, optLang;

            for (i = 0; i < opts.length; i++) {
                optLang = opts[i].getAttribute('data-lang');
                on = optLang === lang;
                opts[i].setAttribute('aria-selected', on ? 'true' : 'false');
                if (!on) continue;

                selectedLang = optLang;
                selectedFlag = opts[i].getAttribute('data-flag') || '';
                selectedName = opts[i].getAttribute('data-name') || optLang;
                if (flagEl) flagEl.textContent = selectedFlag;
                if (btn) btn.setAttribute('aria-label', 'Dil seç (' + selectedName + ')');
            }
        }

        function persist(lang) {
            paint(lang);

            window.rmaCeviriDil = lang;
            try { sessionStorage.setItem('rma_dil', lang); } catch (e) {}

            if (!isPreview) {
                try {
                    document.cookie = cookieName + '=' + encodeURIComponent(lang) +
                        ';path=/;max-age=31536000;samesite=lax';
                } catch (e) {}

                overlay.querySelectorAll('a[data-splash-dismiss]').forEach(function (a) {
                    var href = a.getAttribute('href');
                    if (href) a.setAttribute('href', applyToUrl(href, lang));
                });

                var redirect = overlay.getAttribute('data-redirect-url') || '';
                if (redirect) {
                    overlay.setAttribute('data-redirect-url', applyToUrl(redirect, lang));
                }
            }
        }

        function detectLang() {
            var m = window.location.search.match(/[?&]lang=([^&]+)/);
            if (m) return decodeURIComponent(m[1]);
            if (window.rmaCeviriDil) return window.rmaCeviriDil;

            var escaped = cookieName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            m = document.cookie.match(new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)'));
            if (m) return decodeURIComponent(m[1]);

            return root.getAttribute('data-default') || 'tr';
        }

        root.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof onActivity === 'function') onActivity();

            var opt = event.target.closest('.splash-ceviri-opt');
            if (opt) {
                persist(opt.getAttribute('data-lang'));
                closePanel();
                return;
            }

            if (event.target.closest('.splash-ceviri-btn')) {
                var open = root.classList.toggle('is-open');
                if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        });

        document.addEventListener('click', closePanel);

        var initial = detectLang();
        var hasInitial = false;
        root.querySelectorAll('.splash-ceviri-opt').forEach(function (opt) {
            if (opt.getAttribute('data-lang') === initial) hasInitial = true;
        });
        if (hasInitial) {
            persist(initial);
        }

        return {
            applyToUrl: function (url) { return applyToUrl(url, selectedLang); },
            shouldReload: function () {
                return !isPreview && selectedLang && selectedLang !== urlLang();
            },
            navigate: function () {
                if (typeof window.qrmenuTranslate === 'function') {
                    window.qrmenuTranslate(selectedLang, selectedFlag, selectedName);
                    return;
                }
                window.location.href = applyToUrl(window.location.href, selectedLang);
            }
        };
    }

    function initSplash() {
        var overlay = getOverlay();

        // Admin canlı önizlemesi. Aynı markup yönetim ekranında da basılıyor;
        // orada bu betik hiç kuyruğa alınmasa bile bayrak kontrolü kalıcı bir
        // güvencedir: önizlemeye bakmak çerez yazmamalı, yönlendirme
        // tetiklememeli ve yönetim sayfasını kilitlememeli.
        if (overlay && overlay.getAttribute('data-preview') === '1') {
            clearHeadFailsafe();
            removeLoadingState();
            // Dil düğmesi önizlemede de çalışır (çerezsiz): yönetici
            // İngilizce hâli nasıl görünüyor diye bakabilmeli.
            initLang(overlay, true);
            initCeviri(overlay, true);
            return;
        }

        // Failsafe: overlay DOM'da yoksa (ör. bayat/eski cache HTML'i) sınıfı
        // kaldırıp çık - sayfa asla kilitli kalmasın. :has() desteklemeyen eski
        // tarayıcılar için bu kontrol şart.
        if (!overlay) {
            clearHeadFailsafe();
            removeLoadingState();
            return;
        }

        var redirectUrl = overlay.getAttribute('data-redirect-url') || '';
        var redirectMs = parseInt(overlay.getAttribute('data-redirect-ms'), 10) || 0;
        var dismissMinutes = parseInt(overlay.getAttribute('data-dismiss-minutes'), 10);
        if (isNaN(dismissMinutes) || dismissMinutes < 0) {
            dismissMinutes = 0;
        }

        // 0 = her ziyarette göster: çerez kontrolü yok. Eski oturum
        // çerezi (önceki sürüm 0'da expires'siz yazıyordu) silinir.
        if (dismissMinutes === 0) {
            document.cookie = 'splash_dismissed=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
        } else if (hasDismissCookie()) {
            // Tam sayfa cache eski/bayat HTML servis ediyor olabilir.
            // Cookie varsa hiçbir zamanlayıcı/dinleyici kurmadan temizleyip çık.
            clearHeadFailsafe();
            removeLoadingState();
            overlay.remove();
            document.body.style.overflow = '';
            return;
        }

        // NOT: .splash-loading sınıfı BURADA kaldırılmaz - overlay'i display:flex
        // yapan CSS kuralı bu sınıfa bağlı. Sınıf sadece dismissSplash()'te kaldırılır.
        clearHeadFailsafe();
        document.body.style.overflow = 'hidden';
        var ceviri = noopCeviri();

        var idleTimer = null;
        var listenersAttached = false;

        function attachListeners() {
            if (listenersAttached) return;
            DISMISS_EVENTS.forEach(function (evt) {
                document.addEventListener(evt, resetIdle, { passive: true });
            });
            listenersAttached = true;
        }

        function detachListeners() {
            if (!listenersAttached) return;
            DISMISS_EVENTS.forEach(function (evt) {
                document.removeEventListener(evt, resetIdle, { passive: true });
            });
            listenersAttached = false;
        }

        function dismissSplash(fromLink) {
            // 0 = süresiz gizleme yok; çerez yazılmaz ki yenilemede tekrar görünsün.
            if (dismissMinutes > 0) {
                var date = new Date();
                date.setTime(date.getTime() + (dismissMinutes * 60 * 1000));
                document.cookie = 'splash_dismissed=1; expires=' + date.toUTCString() + '; path=/; SameSite=Lax';
            }

            clearTimeout(idleTimer);
            clearHeadFailsafe();
            detachListeners();
            removeLoadingState();

            // Aynı sayfada kalınıyorsa menünün yeni dilde basılması için
            // QR Çeviri'nin kendi yenilemesini çağır (dil çerezi yazıldı;
            // dismiss_duration > 0 ise splash_dismissed de yazıldı).
            if (!fromLink && ceviri.shouldReload()) {
                ceviri.navigate();
                return;
            }

            var overlayDom = getOverlay();
            if (overlayDom) {
                overlayDom.style.transition = 'opacity 0.3s ease';
                overlayDom.style.opacity = '0';
                setTimeout(function () {
                    var el = getOverlay();
                    if (el) el.remove();
                }, 300);
            }
            document.body.style.overflow = '';
        }

        // Geri sayım halkası (sağ üstteki gösterge "ring" tipindeyken basılır).
        // Burada JS ile animasyon YOK: sadece CSS animasyonu sıfırlanır,
        // süre ve eğri CSS'te (--sp-seconds) kalır.
        function restartLoaderRing() {
            var ring = document.getElementById('splash-loader-ring');
            if (!ring) return;
            ring.style.animation = 'none';
            void ring.getBoundingClientRect(); // reflow ile animasyonu resetle
            ring.style.animation = '';
        }

        function processAutoAction() {
            var checkOverlay = getOverlay();
            if (!checkOverlay) return;

            var dest = ceviri.applyToUrl(checkOverlay.getAttribute('data-redirect-url') || redirectUrl);
            var currentPath = window.location.href.split('#')[0].replace(/\/$/, '');
            var targetPath = dest.split('#')[0].replace(/\/$/, '');

            if (targetPath && targetPath !== currentPath) {
                window.location.href = dest;
            } else {
                dismissSplash();
            }
        }

        function resetIdle() {
            clearTimeout(idleTimer);
            if (redirectMs > 0) {
                idleTimer = setTimeout(processAutoAction, redirectMs);
                restartLoaderRing();
            }
        }

        function openModal(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'flex';
        }

        function closeModal(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        }

        // Giriş animasyonu tek seferliktir: bitince sınıf kaldırılır, böylece
        // geride sürekli compositing yapan bir katman kalmaz. Eleman temel
        // durumuna (opacity:1) düşer.
        var stack = overlay.querySelector('.splash-stack');
        if (stack) {
            stack.addEventListener('animationend', function onEnd(e) {
                if (e.target !== stack) return;
                stack.classList.remove('is-animating');
                stack.removeEventListener('animationend', onEnd);
            });
        }

        // Menü/İletişim/Rezervasyon/Yorum: tıklayınca splash kapanır (navigasyon devam eder).
        // Sosyal medya rozetleri yeni sekmede açıldığı için dismiss etmez.
        overlay.querySelectorAll('[data-splash-dismiss]').forEach(function (el) {
            el.addEventListener('click', function () {
                var href = el.tagName === 'A' ? el.getAttribute('href') : '';
                dismissSplash(!!href);
            });
        });

        // Wifi: SADECE modal açar, splash'ı kapatmaz.
        var wifiBtn = document.getElementById('wifi-btn');
        if (wifiBtn) {
            wifiBtn.addEventListener('click', function () { openModal('wifi-modal'); });
        }

        document.querySelectorAll('.splash-modal-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = btn.closest('.splash-modal');
                if (modal) closeModal(modal.id);
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target.classList && event.target.classList.contains('splash-modal')) {
                event.target.style.display = 'none';
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.splash-modal').forEach(function (modal) {
                if (modal.style.display === 'flex') modal.style.display = 'none';
            });
            var openSel = overlay.querySelector('.splash-ceviri.is-open');
            if (openSel) {
                openSel.classList.remove('is-open');
                var openBtn = openSel.querySelector('.splash-ceviri-btn');
                if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
            }
        });

        initLang(overlay, false);
        ceviri = initCeviri(overlay, false, resetIdle);

        attachListeners();
        resetIdle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSplash);
    } else {
        initSplash();
    }
})();
