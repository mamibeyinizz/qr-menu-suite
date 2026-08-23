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

    /* ---------------- QR Çeviri dil seçicisi ----------------

       Markup yalnızca admin'den açıkken ve QR Çeviri yüklüyken basılır;
       bu fonksiyon seçici DOM'da yoksa hemen döner — dinleyici kurulmaz.

       Dil değişimi splash'ın kendi sözlüğüyle yapılmaz: QR Çeviri'nin
       rma_lang çerezi + sessionStorage['rma_dil'] + ?lang= sözleşmesi
       yazılır. Sayfa çevirisi splash kapandıktan sonraki yüklemede
       sunucuda uygulanır. */

    function updateLangQuery(url, lang) {
        if (typeof window.rmaCeviriUpdateQueryParam === 'function') {
            return window.rmaCeviriUpdateQueryParam(url, 'lang', lang);
        }
        try {
            var u = new URL(url, window.location.href);
            u.searchParams.set('lang', lang);
            return u.toString();
        } catch (e) {
            return url;
        }
    }

    function initLangPicker(overlay, isPreview) {
        var picker = overlay.querySelector('.splash-i18n');
        if (!picker) return null;

        var btn = picker.querySelector('.splash-i18n-btn');
        var flagEl = picker.querySelector('.splash-i18n-flag');
        var cookieName = picker.getAttribute('data-cookie') || 'rma_lang';
        var selected = picker.getAttribute('data-default') || 'tr';
        var options = picker.querySelectorAll('.splash-i18n-opt');

        function findOpt(code) {
            var i;
            for (i = 0; i < options.length; i++) {
                if (options[i].getAttribute('data-lang') === code) {
                    return options[i];
                }
            }
            return null;
        }

        function allowed(code) {
            return !!findOpt(code);
        }

        function readLang() {
            try {
                var params = window.location.search.match(/[?&]lang=([^&]+)/);
                if (params) {
                    var fromUrl = decodeURIComponent(params[1].replace(/\+/g, ' '));
                    if (allowed(fromUrl)) return fromUrl;
                }
            } catch (e) {}

            if (window.rmaCeviriDil && allowed(window.rmaCeviriDil)) {
                return window.rmaCeviriDil;
            }

            try {
                var escaped = cookieName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)'));
                if (match) {
                    var fromCookie = decodeURIComponent(match[1]);
                    if (allowed(fromCookie)) return fromCookie;
                }
            } catch (e2) {}

            return selected;
        }

        function persist(code) {
            if (isPreview) return;
            try {
                document.cookie = cookieName + '=' + encodeURIComponent(code) +
                    ';path=/;max-age=31536000;samesite=lax';
            } catch (e) {}
            window.rmaCeviriDil = code;
            try {
                sessionStorage.setItem('rma_dil', code);
            } catch (e2) {}
        }

        function paint(code) {
            var opt = findOpt(code);
            selected = code;
            if (opt && flagEl) {
                flagEl.textContent = opt.getAttribute('data-flag') || '';
            }
            var i, on;
            for (i = 0; i < options.length; i++) {
                on = options[i].getAttribute('data-lang') === code;
                options[i].classList.toggle('is-active', on);
                options[i].setAttribute('aria-selected', on ? 'true' : 'false');
            }
            picker.setAttribute('data-lang', code);
        }

        function closePanel() {
            picker.classList.remove('is-open');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }

        function togglePanel() {
            var open = picker.classList.toggle('is-open');
            if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        selected = readLang();
        paint(selected);

        picker.addEventListener('click', function (event) {
            var opt = event.target.closest('.splash-i18n-opt');
            if (opt) {
                event.preventDefault();
                var code = opt.getAttribute('data-lang');
                if (!code) return;
                paint(code);
                persist(code);
                closePanel();
                return;
            }
            if (event.target.closest('.splash-i18n-btn')) {
                event.preventDefault();
                togglePanel();
            }
        });

        overlay.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) closePanel();
        });

        return {
            getLang: function () { return selected; }
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
            initLangPicker(overlay, true);
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
        var pageLangAtLoad = window.rmaCeviriDil || 'tr';
        var langPicker = initLangPicker(overlay, false);

        var idleTimer = null;
        var listenersAttached = false;

        function langChanged() {
            return !!(langPicker && langPicker.getLang() && langPicker.getLang() !== pageLangAtLoad);
        }

        function canCarryLang(url) {
            var lower = String(url || '').toLowerCase();
            return lower.indexOf('tel:') !== 0 &&
                lower.indexOf('mailto:') !== 0 &&
                lower.indexOf('sms:') !== 0 &&
                lower.indexOf('whatsapp:') !== 0;
        }

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

        function dismissSplash(navUrl) {
            var expires = '';
            if (dismissMinutes > 0) {
                var date = new Date();
                date.setTime(date.getTime() + (dismissMinutes * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = 'splash_dismissed=1' + expires + '; path=/; SameSite=Lax';

            clearTimeout(idleTimer);
            clearHeadFailsafe();
            detachListeners();
            removeLoadingState();

            if (langChanged()) {
                var dest = navUrl || redirectUrl || window.location.href;
                window.location.href = updateLangQuery(dest, langPicker.getLang());
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

            var currentPath = window.location.href.split('#')[0].replace(/\/$/, '');
            var targetPath = redirectUrl.split('#')[0].replace(/\/$/, '');

            if (targetPath && targetPath !== currentPath) {
                window.location.href = langChanged()
                    ? updateLangQuery(redirectUrl, langPicker.getLang())
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
            el.addEventListener('click', function (event) {
                var href = el.getAttribute('href') || '';
                if (langChanged() && canCarryLang(href)) {
                    event.preventDefault();
                    dismissSplash(href || window.location.href);
                    return;
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
            var pickerOpen = overlay.querySelector('.splash-i18n.is-open');
            if (pickerOpen) {
                pickerOpen.classList.remove('is-open');
                var pickerBtn = pickerOpen.querySelector('.splash-i18n-btn');
                if (pickerBtn) pickerBtn.setAttribute('aria-expanded', 'false');
            }
        });

        initLang(overlay, false);

        attachListeners();
        resetIdle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSplash);
    } else {
        initSplash();
    }
})();
