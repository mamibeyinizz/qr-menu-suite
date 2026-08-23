(function () {
    'use strict';

    var DISMISS_EVENTS = ['click', 'touchstart', 'keydown'];

    var selectedCeviriLang = 'tr';
    var splashAppendLang = null;

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

       qrmenuTranslate() sayfayı yeniden yükler; splash sırasında bunu
       istemiyoruz. Aynı çerez (rma_lang), sessionStorage (rma_dil) ve
       window.rmaCeviriDil kullanılır; sayfa yenilenmez. Splash kapandığında
       veya menü bağlantısına tıklandığında URL'ye ?lang= eklenir. */

    function updateQueryParam(url, ad, deger) {
        if (window.rmaCeviriUpdateQueryParam) {
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

    function appendLangToUrl(url, lang) {
        if (!url || !lang || lang === 'tr') return url;
        return updateQueryParam(url, 'lang', lang);
    }

    function initLangSelector(overlay, isPreview) {
        var picker = overlay.querySelector('.splash-lang-picker');
        if (!picker) {
            splashAppendLang = null;
            return;
        }

        var cookieName = picker.getAttribute('data-lang-cookie') ||
            (window.rmaCeviri && window.rmaCeviri.cookie) || 'rma_lang';
        var btn = picker.querySelector('.splash-lang-picker-btn');
        var panel = picker.querySelector('.splash-lang-picker-panel');
        var flagEl = picker.querySelector('.splash-lang-picker-flag');
        var opts = picker.querySelectorAll('.splash-lang-picker-opt');
        var panelOpen = false;

        splashAppendLang = appendLangToUrl;

        function validLang(code) {
            var i;
            for (i = 0; i < opts.length; i++) {
                if (opts[i].getAttribute('data-lang') === code) return true;
            }
            return false;
        }

        function readUrlLang() {
            var m = location.search.match(/[?&]lang=([^&]+)/);
            return m ? decodeURIComponent(m[1]) : '';
        }

        function readCeviriCookie() {
            var re = new RegExp('(?:^|;\\s*)' + cookieName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)');
            var m = document.cookie.match(re);
            return m ? decodeURIComponent(m[1]) : '';
        }

        function closePanel() {
            panelOpen = false;
            if (panel) panel.hidden = true;
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }

        function openPanel() {
            panelOpen = true;
            if (panel) panel.hidden = false;
            if (btn) btn.setAttribute('aria-expanded', 'true');
        }

        function applyPickerUi(lang) {
            var i, opt, flag;
            selectedCeviriLang = lang;

            for (i = 0; i < opts.length; i++) {
                opt = opts[i];
                var on = opt.getAttribute('data-lang') === lang;
                opt.setAttribute('aria-selected', on ? 'true' : 'false');
                if (on) {
                    flag = opt.querySelector('.splash-lang-picker-opt-flag');
                    if (flag && flagEl) flagEl.textContent = flag.textContent;
                }
            }
        }

        function persistCeviriLang(lang) {
            try {
                document.cookie = cookieName + '=' + encodeURIComponent(lang) +
                    ';path=/;max-age=31536000;samesite=lax';
            } catch (e) {}

            window.rmaCeviriDil = lang;
            try {
                sessionStorage.setItem('rma_dil', lang);
            } catch (e) {}
        }

        function setCeviriLang(lang, persist) {
            if (!validLang(lang)) lang = 'tr';
            applyPickerUi(lang);
            if (persist && !isPreview) persistCeviriLang(lang);
        }

        if (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (panelOpen) closePanel();
                else openPanel();
            });
        }

        picker.addEventListener('click', function (event) {
            var opt = event.target.closest('.splash-lang-picker-opt');
            if (!opt) return;

            event.preventDefault();
            event.stopPropagation();
            setCeviriLang(opt.getAttribute('data-lang') || 'tr', true);
            closePanel();
        });

        document.addEventListener('click', function (event) {
            if (!panelOpen || picker.contains(event.target)) return;
            closePanel();
        });

        var stored = isPreview ? '' : (readUrlLang() || readCeviriCookie() ||
            (window.rmaCeviriDil || '') || (function () {
                try { return sessionStorage.getItem('rma_dil') || ''; } catch (e) { return ''; }
            })());

        if (stored && validLang(stored)) {
            setCeviriLang(stored, !isPreview);
        } else {
            setCeviriLang('tr', false);
        }
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
            initLangSelector(overlay, true);
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

        // İkinci kontrol: tam sayfa cache eski/bayat HTML servis ediyor olabilir.
        // Cookie varsa hiçbir zamanlayıcı/dinleyici kurmadan temizleyip çık.
        if (hasDismissCookie()) {
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

        var redirectUrl = overlay.getAttribute('data-redirect-url') || '';
        var redirectMs = parseInt(overlay.getAttribute('data-redirect-ms'), 10) || 0;
        var dismissMinutes = parseInt(overlay.getAttribute('data-dismiss-minutes'), 10) || 0;

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

        function syncPageLangUrl() {
            if (!splashAppendLang || selectedCeviriLang === 'tr') return;

            try {
                var next = splashAppendLang(location.href, selectedCeviriLang);
                if (next !== location.href) {
                    history.replaceState(null, '', next);
                }
            } catch (e) {}
        }

        function dismissSplash() {
            var expires = '';
            if (dismissMinutes > 0) {
                var date = new Date();
                date.setTime(date.getTime() + (dismissMinutes * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = 'splash_dismissed=1' + expires + '; path=/; SameSite=Lax';

            syncPageLangUrl();

            clearTimeout(idleTimer);
            clearHeadFailsafe();
            detachListeners();
            removeLoadingState();

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

            var currentPath = window.location.href.split('#')[0].replace(/\/$/, '');
            var targetPath = redirectUrl.split('#')[0].replace(/\/$/, '');

            if (targetPath && targetPath !== currentPath) {
                window.location.href = splashAppendLang
                    ? splashAppendLang(redirectUrl, selectedCeviriLang)
                    : redirectUrl;
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
                if (splashAppendLang && el.href) {
                    el.href = splashAppendLang(el.href, selectedCeviriLang);
                }
                dismissSplash();
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
        });

        initLang(overlay, false);
        initLangSelector(overlay, false);

        attachListeners();
        resetIdle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSplash);
    } else {
        initSplash();
    }
})();
