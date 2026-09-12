/* Açılış Ekranı — yönetim ekranı davranışları.
   Yalnızca modülün kendi sayfalarında yüklenir.

   Buradaki her şey ilerlemeli (progressive): JS hiç çalışmazsa form yine
   gönderilir ve hiçbir ayar kaybolmaz. Koşullu alanlar CSS ile GİZLENİR,
   asla disable edilmez — disable edilen alan POST'a girmez ve sahibi sayfa
   kaydedilince kayıtlı değer sessizce silinirdi. */
(function ($) {
    'use strict';

    /* ---------------- Ortak yardımcılar ---------------- */

    function field(name) {
        return document.querySelector('[name="' + name + '"]');
    }

    function fieldValue(name) {
        // Radyo grubunda seçili olan, onay kutusunda "1"/"0", diğerlerinde
        // alanın değeri. Koşul motoru ve önizleme aynı okumayı kullanır.
        var radios = document.querySelectorAll('input[type="radio"][name="' + name + '"]');
        if (radios.length) {
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].checked) return radios[i].value;
            }
            return '';
        }

        var el = field(name);
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        return el.value;
    }

    function hexToRgb(hex) {
        hex = String(hex || '').replace('#', '');
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        if (!/^[0-9a-f]{6}$/i.test(hex)) return null;
        return [
            parseInt(hex.slice(0, 2), 16),
            parseInt(hex.slice(2, 4), 16),
            parseInt(hex.slice(4, 6), 16)
        ].join(',');
    }

    function luminance(hex) {
        var rgb = hexToRgb(hex);
        if (!rgb) return 255;
        var p = rgb.split(',');
        return (p[0] * 0.299) + (p[1] * 0.587) + (p[2] * 0.114);
    }

    /* ---------------- Canlı önizleme ----------------

       Önizleme, ön yüzün GERÇEK markup'ı ve GERÇEK stylesheet'idir; ayrı
       bir taklit yoktur. Overlay data-preview="1" taşır ve bu bayrak iki işi
       birden yapar:

         - splash.js (ön yüz betiği) bayrağı görürse hiç çalışmaz: çerez
           okunmaz/yazılmaz, yönlendirme zamanlayıcısı kurulmaz.
         - Buradaki kod da yalnızca bu bayrağı taşıyan overlay'e dokunur.

       Neyin canlı güncellendiği: renk, opaklık, ölçü gibi CSS değişkenine
       inen her ayar ve metin alanları. Yapıyı değiştiren ayarlar (görsel
       seçimi, ödeme yöntemi, sosyal hesap, gösterge tipi) yeni markup
       gerektirdiği için kaydedilince yansır; o durumda önizlemenin üstünde
       "kaydedilince güncellenecek" rozeti belirir. */

    function initPreview() {
        var overlay = document.querySelector('#custom-splash-overlay[data-preview="1"]');
        if (!overlay) return;

        var stack = overlay.querySelector('.splash-stack');

        function setVar(name, value) {
            overlay.style.setProperty(name, value);
        }

        function val(name) {
            var el = field(name);
            return el ? el.value : '';
        }

        /* CTA dolgusu iki kaynaktan beslenir; hangisinin geçerli olduğunu
           "Ana buton da bu yüzeyi kullansın" anahtarı belirler — PHP
           tarafındaki build_css_vars() ile aynı kural. */
        function syncCtaBg() {
            var useSurface = field('btn_surface_apply_cta');
            var accent = hexToRgb(val('button_bg_color')) || '0,115,170';
            var surface = hexToRgb(val('btn_surface_color')) || '255,255,255';
            var btnAlpha = (parseInt(val('button_opacity'), 10) || 0) / 100;
            var surfaceAlpha = (parseInt(val('btn_surface_opacity'), 10) || 0) / 100;

            setVar('--sp-cta-bg', useSurface && useSurface.checked
                ? 'rgba(' + surface + ',' + surfaceAlpha + ')'
                : 'rgba(' + accent + ',' + btnAlpha + ')');
        }

        /* CTA yazı rengi: açık şemada beyaz kalmışsa okunabilir koyuya
           düşürülür — PHP'deki cta_text_color() ile aynı kural. */
        function syncCtaText() {
            var color = val('button_text_color') || '#ffffff';
            var isLight = overlay.classList.contains('splash-scheme-light');

            setVar('--sp-btn-text', color);
            setVar('--sp-cta-text', (isLight && luminance(color) > 200) ? '#1c1c1e' : color);
        }

        function markStale() {
            var box = overlay.closest('.qrms-ae-preview');
            if (box) box.classList.add('is-stale');
        }

        function replayAnimation() {
            if (!stack) return;
            stack.classList.remove('is-animating');
            void stack.getBoundingClientRect(); // reflow ile animasyonu resetle
            stack.classList.add('is-animating');
        }

        /* Renk alanları: wpColorPicker kendi olayını tetikler. */
        var colorVars = {
            bg_color: function (v) { setVar('--sp-bg', v); },
            button_bg_color: function (v) {
                setVar('--sp-accent', v);
                setVar('--sp-accent-rgb', hexToRgb(v) || '0,115,170');
                syncCtaBg();
            },
            button_text_color: syncCtaText,
            btn_surface_color: function (v) {
                setVar('--splash-btn-bg', hexToRgb(v) || '255,255,255');
                syncCtaBg();
            },
            logo_bar_color: function (v) { setVar('--sp-logo-bar-rgb', hexToRgb(v) || '0,0,0'); },
            loader_color: function (v) {
                setVar('--sp-loader', v);
                setVar('--sp-loader-rgb', hexToRgb(v) || '255,255,255');
            }
        };

        Object.keys(colorVars).forEach(function (name) {
            var $input = $('[name="' + name + '"]');
            if (!$input.length) return;
            $input.on('input change', function () { colorVars[name](this.value); });
            // wpColorPicker paletten seçimde ayrı bir olay yayar.
            $input.on('irisChange', function (event, ui) { colorVars[name](ui.color.toString()); });
        });

        /* Sayısal/aralık alanları. */
        var numberVars = {
            bg_overlay_strength: function (v) { setVar('--sp-overlay', (parseInt(v, 10) || 0) / 100); },
            button_opacity: function (v) { setVar('--sp-btn-alpha', (parseInt(v, 10) || 0) / 100); syncCtaBg(); },
            btn_surface_opacity: function (v) { setVar('--splash-btn-opacity', (parseInt(v, 10) || 0) / 100); syncCtaBg(); },
            button_font_size_px: function (v) { setVar('--sp-cta-font', (parseInt(v, 10) || 16) + 'px'); },
            logo_bar_height: function (v) { setVar('--sp-logo-bar-h', (parseInt(v, 10) || 0) + 'px'); },
            logo_bar_opacity: function (v) { setVar('--sp-logo-bar-alpha', (parseInt(v, 10) || 0) / 100); },
            loader_size: function (v) { setVar('--sp-loader-size', (parseInt(v, 10) || 0) + 'px'); },
            ceviri_flag_size: function (v) { setVar('--sp-flag-size', (parseInt(v, 10) || 0) + 'px'); },
            redirect_seconds: function (v) { setVar('--sp-seconds', (parseInt(v, 10) || 0) + 's'); }
        };

        Object.keys(numberVars).forEach(function (name) {
            $('[name="' + name + '"]').on('input change', function () { numberVars[name](this.value); });
        });

        $('[name="btn_surface_apply_cta"]').on('change', syncCtaBg);

        /* --- İki dilli metinler ---

           Önizlemedeki metin düğümleri tüm dilleri data niteliğinde taşır
           (data-sp-tr / data-sp-en ...); hangisinin görüneceğine splash.js
           karar verir. Yönetici yazarken NİTELİĞİ güncelliyoruz, görünen
           metni değil: önizleme o an İngilizceye alınmışsa Türkçe alanına
           yazmak ekrandaki İngilizce metni bozmamalı. */

        function currentLang() {
            return overlay.getAttribute('lang') === 'en' ? 'en' : 'tr';
        }

        function writeText(el, value) {
            var attrs = el.getAttribute('data-sp-attr');

            if (attrs) {
                // Rozetin görünür yazısı yoktur; metin erişilebilirlik
                // etiketine ve dokunma ipucuna yazılır.
                attrs.split(/\s+/).forEach(function (attr) {
                    if (attr) el.setAttribute(attr, value);
                });
            } else {
                el.textContent = value;
            }
        }

        /**
         * Bir ayar alanını önizlemedeki metin düğümüne bağlar.
         *
         * Dil seçici AÇIKKEN düğüm dilleri data niteliğinde taşır: o zaman
         * yazılan değer ilgili niteliğe gider ve görünen metin ancak o an
         * gösterilen dil buysa değişir. Kapalıyken data niteliği hiç
         * basılmaz; o durumda yedek seçici kullanılır.
         */
        function bindLangText(name, key, lang, fallbackSelector) {
            $('[name="' + name + '"]').on('input change', function () {
                var value = this.value;
                var nodes = overlay.querySelectorAll('[data-sp-key="' + key + '"]');

                if (!nodes.length) {
                    if ('tr' === lang && fallbackSelector) {
                        overlay.querySelectorAll(fallbackSelector).forEach(function (el) {
                            writeText(el, value);
                        });
                    }
                    return;
                }

                nodes.forEach(function (el) {
                    el.setAttribute('data-sp-' + lang, value);

                    if (currentLang() === lang) {
                        writeText(el, value);
                    }
                });
            });
        }

        // Yalnızca CTA ve ayracın görünür bir metni vardır; dil seçici
        // kapalıyken canlı güncelleme onlarda yedek seçiciyle sürer.
        var yedek = { btn1: '.splash-cta', divider: '.splash-divider-label' };

        [1, 2, 3, 4, 5].forEach(function (i) {
            bindLangText('button_text_' + i, 'btn' + i, 'tr', yedek['btn' + i]);
        });

        bindLangText('divider_text', 'divider', 'tr', yedek.divider);

        $('[name="wifi_password"]').on('input change', function () {
            var el = document.querySelector('#wifi-modal .splash-modal-value');
            if (el) el.textContent = this.value || 'Henüz bir şifre girilmedi.';
        });

        /* Giriş animasyonu: sınıfı değiştir ve hemen oynat. */
        $('[name="animation_type"]').on('change', function () {
            if (!stack) return;
            stack.classList.remove('anim-blur-up', 'anim-elastic', 'anim-zoom-out');
            stack.classList.add(this.value);
            replayAnimation();
        });

        $('.qrms-ae-preview-replay').on('click', replayAnimation);

        /* Yapıyı değiştiren ayarlar yeni markup ister; önizleme bayatlar. */
        $('[name="loader_type"], [name="bg_scheme"], [name="payment_display_mode"], [name="payment_methods[]"], [name="social_media_active[]"], [name="lang_toggle"], [name="ceviri_selector"], [name="ceviri_selector_langs[]"], .upload-image-btn, .splash-remove-image')
            .on('change click', markStale);

        // Tema paleti şema da değiştirebildiği için aynı uyarıyı doğurur.
        $('.theme-preset').on('click', function () {
            if ($(this).data('scheme')) markStale();
        });

        // Sayfa açılışında animasyon bir kez oynasın.
        replayAnimation();
    }

    /* ---------------- Renk seçiciler + hızlı temalar ---------------- */

    function initColors() {
        var $pickers = $('.color-picker');
        if (!$pickers.length || typeof $.fn.wpColorPicker !== 'function') return;

        $pickers.wpColorPicker();

        /* Palet, üç ana rengin yanında yüzey/şerit/gösterge değerlerini de
           kurar: tek tıkla tutarlı bir görünüm çıkar. Yalnızca FORM doldurulur;
           kaydedilene kadar ön yüzde hiçbir şey değişmez. */
        $('.theme-preset').on('click', function (e) {
            e.preventDefault();

            var $btn = $(this);

            function setColor(name, value) {
                if (!value) return;
                var $input = $('input[name="' + name + '"]');
                if (!$input.length) return;
                $input.wpColorPicker('color', value);
            }

            function setNumber(name, value) {
                if (value === undefined || value === '') return;
                var el = field(name);
                if (!el) return;
                el.value = value;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            setColor('bg_color', $btn.data('bg'));
            setColor('button_bg_color', $btn.data('btn'));
            setColor('button_text_color', $btn.data('text'));
            setColor('btn_surface_color', $btn.data('surface'));
            setColor('logo_bar_color', $btn.data('bar'));
            setColor('loader_color', $btn.data('loader'));

            setNumber('btn_surface_opacity', $btn.data('surface-op'));
            setNumber('logo_bar_opacity', $btn.data('bar-op'));

            var scheme = $btn.data('scheme');
            if (scheme) setNumber('bg_scheme', scheme);
        });
    }

    /* ---------------- Aralık alanları ve hazır değerler ---------------- */

    function initRanges() {
        $('.splash-range').each(function () {
            var $input = $(this);
            var $out = $('#' + $input.data('output'));
            var suffix = $input.data('suffix') || '';
            if (!$out.length) return;

            $input.on('input change', function () {
                $out.text($input.val() + suffix);
            });
        });

        /* Hazır değer düğmeleri: hedef alana yazar ve olayları tetikler ki
           önizleme, koşullu alanlar ve "kaydedilmemiş değişiklik" uyarısı
           kullanıcı elle yazmış gibi çalışsın. */
        $('.qrae-preset').on('click', function (e) {
            e.preventDefault();

            var target = document.getElementById($(this).data('preset-target'));
            if (!target) return;

            target.value = $(this).data('preset-value');
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Seçili hazır değer vurgulanır; kullanıcı kaydırıcıyı elle
        // oynatınca vurgu kendiliğinden kalkar.
        function syncPresets() {
            $('.qrae-preset').each(function () {
                var $btn = $(this);
                var target = document.getElementById($btn.data('preset-target'));
                if (!target) return;
                $btn.toggleClass('is-active', String($btn.data('preset-value')) === String(target.value));
            });
        }

        $(document).on('input change', '.splash-range, .qrae-input-number', syncPresets);
        syncPresets();
    }

    /* ---------------- Koşullu alanlar ----------------

       data-when-field: izlenecek alan adı
       data-when-value: "a|b" biçiminde kabul edilen değerler
       data-when-not:   koşulu tersine çevirir
       data-when-mode:  "mute" ise alan gizlenmez, soluklaşır (etkisiz
                        olduğu belli olur ama erişilebilir kalır) */

    function initConditionals() {
        var nodes = document.querySelectorAll('[data-when-field]');
        if (!nodes.length) return;

        function evaluate() {
            nodes.forEach(function (node) {
                var name = node.getAttribute('data-when-field');
                var accepted = (node.getAttribute('data-when-value') || '1').split('|');
                var negate = node.getAttribute('data-when-not') === '1';
                var mode = node.getAttribute('data-when-mode') || 'hide';
                var current = fieldValue(name);
                var match = accepted.indexOf(String(current)) !== -1;

                if (negate) match = !match;

                if (mode === 'mute') {
                    node.classList.toggle('is-muted', !match);
                } else {
                    node.classList.toggle('is-hidden', !match);
                }
            });
        }

        $(document).on('input change', 'input, select, textarea', evaluate);
        evaluate();
    }

    /* ---------------- Görsel ölçü uyarıları ---------------- */

    function buildWarnings(mode, attachment) {
        var warnings = [];
        var width = parseInt(attachment.width, 10) || 0;
        var height = parseInt(attachment.height, 10) || 0;
        var size = parseInt(attachment.filesizeInBytes, 10) || 0;

        if (mode === 'portrait') {
            if (width && height) {
                if (width > height) {
                    warnings.push('Yatay görsel seçtiniz. Açılış ekranı dikey çalışır; sol/sağ kenarlar ciddi şekilde kırpılır.');
                } else {
                    var ratio = width / height;
                    if (ratio < 0.40 || ratio > 0.62) {
                        warnings.push('Oran önerilen 9:20\'den uzak; kırpma beklenenden fazla olacak.');
                    }
                }
            }
            if (width && width < 800) {
                warnings.push('Çözünürlük düşük; yüksek çözünürlüklü telefonlarda bulanık görünecek.');
            }
        }

        if (mode === 'wide' && width && height && height > width) {
            warnings.push('Dikey görsel seçtiniz. Bu alan tablet ve bilgisayar için yatay görsel bekler.');
        }

        if (size > 600000) {
            warnings.push('Dosya 600 KB üzerinde; açılış hızı düşer. WebP\'ye çevirip tekrar yükleyin.');
        }

        return warnings;
    }

    function renderWarnings($container, warnings) {
        $container.empty();
        warnings.forEach(function (text) {
            $('<div/>', { 'class': 'splash-media-warning', text: text }).appendTo($container);
        });
    }

    /* ---------------- Medya seçici ---------------- */

    function initMedia() {
        $('.upload-image-btn').on('click', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var target = $btn.data('target');
            var mode = $btn.data('warn') || '';
            var $field = $btn.closest('.splash-media-field');

            var frame = wp.media({
                title: 'Görsel Seç',
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();

                $('#' + target).val(attachment.id);

                var previewUrl = attachment.url;
                if (attachment.sizes && attachment.sizes.medium) {
                    previewUrl = attachment.sizes.medium.url;
                }
                $field.find('.image-preview').html($('<img/>', { src: previewUrl, alt: '' }));
                $field.find('.splash-remove-image').removeClass('hidden');
                $btn.text('Görseli değiştir');

                renderWarnings(
                    $field.find('.splash-media-warnings'),
                    buildWarnings(mode, attachment)
                );
            });

            frame.open();
        });

        $('.splash-remove-image').on('click', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $field = $btn.closest('.splash-media-field');

            $('#' + $btn.data('target')).val('');
            // Boş kutu yerine "görsel seçilmedi" yer tutucusu geri gelir.
            $field.find('.image-preview').html($('<span/>', { 'class': 'qrae-media-empty', text: 'Görsel seçilmedi' }));
            $field.find('.splash-media-warnings').empty();
            $field.find('.upload-image-btn').text('Görsel seç');
            $btn.addClass('hidden');
        });
    }

    /* ---------------- Sosyal medya (en fazla 6 hesap) ----------------

       Sıralama JS ile takip edilir: her değişimde işaretli anahtarların
       "işaretlenme sırası" gizli bir alanda (social_media_order) tutulur ve
       kaydedilirken PHP tarafında render sırası olarak kullanılır. JS
       çalışmazsa form yine gönderilir, sıralama o zaman DOM sırasına
       düşer (progressive enhancement). */

    function initSocialMedia() {
        var $grid = $('.splash-social-grid');
        if (!$grid.length) return;

        var MAX_ACTIVE = 6;
        var $order = $('#social_media_order');
        var $note = $('.splash-social-limit-note');
        var $count = $('#qrae-social-count');
        var order = ($order.val() || '').split(',').filter(Boolean);

        function checkedKeys() {
            return $grid.find('.splash-social-check:checked').map(function () {
                return $(this).val();
            }).get();
        }

        function syncOrder() {
            var checked = checkedKeys();
            order = order.filter(function (key) { return checked.indexOf(key) !== -1; });
            checked.forEach(function (key) {
                if (order.indexOf(key) === -1) order.push(key);
            });
            $order.val(order.join(','));
        }

        function updateState() {
            var count = checkedKeys().length;
            var atMax = count >= MAX_ACTIVE;

            $grid.find('.splash-social-check').each(function () {
                var $cb = $(this);
                // Yedinci hesap seçilemez; kapatılabilir olanlar açık kalır.
                $cb.prop('disabled', atMax && !$cb.is(':checked'));
                $cb.closest('.splash-social-row').toggleClass('is-on', $cb.is(':checked'));
            });

            if ($count.length) $count.text(count);
            $note.toggleClass('is-visible', atMax);
        }

        $grid.on('change', '.splash-social-check', function () {
            syncOrder();
            updateState();
        });

        syncOrder();
        updateState();
    }

    /* ---------------- Ödeme yöntemi kartları ---------------- */

    function initPayments() {
        var $chips = $('.qrae-chip-input');
        if (!$chips.length) return;

        var $count = $('#qrae-pay-count');

        function update() {
            $chips.each(function () {
                $(this).closest('.qrae-chip').toggleClass('is-on', this.checked);
            });
            if ($count.length) $count.text($chips.filter(':checked').length);
        }

        $chips.on('change', update);
        update();
    }

    /* ---------------- Adres doğrulama ----------------

       Sunucu tarafı sanitize'ın (esc_url_raw) YERİNE geçmez, ona ek olarak
       çalışır: kullanıcı yanlış bir adres yazdığında kaydedip boş sonuçla
       karşılaşmak yerine hatayı anında görür.

       tel: ve mailto: kabul edilir — iletişim rozeti için geçerli
       adreslerdir. javascript:/data: gibi şemalar reddedilir. */

    var UNSAFE_SCHEME = /^\s*(javascript|data|vbscript|file)\s*:/i;
    var SAFE_SCHEME = /^\s*(https?|tel|mailto|sms|whatsapp):/i;

    function urlError(value) {
        var raw = String(value || '').trim();
        if (raw === '') return '';

        if (UNSAFE_SCHEME.test(raw)) {
            return 'Bu adres güvenli değil ve kaydedilmez. https:// ile başlayan bir adres yazın.';
        }
        if (SAFE_SCHEME.test(raw)) return '';
        if (/\s/.test(raw)) {
            return 'Adres boşluk içeremez. Örnek: https://ornek.com/menu';
        }
        // Şemasız yazım (ornek.com/menu) kabul edilir; kaydederken
        // WordPress başına https:// ekler. En azından bir nokta olmalı.
        if (/^[^\s/]+\.[^\s/]{2,}/.test(raw)) return '';

        return 'Geçerli bir adres yazın. Örnek: https://ornek.com/menu';
    }

    function initUrlValidation() {
        var $inputs = $('[data-qrae-url]');
        if (!$inputs.length) return;

        function validate(input) {
            var message = urlError(input.value);
            var $error = $('#' + input.id + '-error');

            input.setAttribute('aria-invalid', message ? 'true' : 'false');

            if ($error.length) {
                $error.text(message);
                $error.prop('hidden', !message);
            }

            return !message;
        }

        $inputs.on('blur change', function () { validate(this); });
        $inputs.on('input', function () {
            // Yazarken uyarı yalnızca KALKAR; her karakterde kırmızı
            // uyarı basmak yazmayı zorlaştırır.
            if (this.getAttribute('aria-invalid') === 'true') validate(this);
        });

        $('#qrae-form').on('submit', function (e) {
            var invalid = null;

            $inputs.each(function () {
                if (!validate(this) && !invalid) invalid = this;
            });

            if (invalid) {
                e.preventDefault();
                invalid.focus();
                invalid.scrollIntoView({ block: 'center' });
            }
        });
    }

    /* ---------------- "Süre dolunca" seçimi ----------------

       Kaydedilen bir ayar DEĞİLDİR: davranış, yönlendirme adresinin dolu
       olup olmamasından okunur (redirect_url boş = ekranı kapat). Seçim
       değişince adres alanı görünür/gizlenir ve değeri de buna göre
       ayarlanır — kullanıcı ne görüyorsa o kaydedilir, gizli sürpriz yok.
       Kapat'a geçerken adres hatırlanır; aynı oturumda geri dönülürse
       tekrar yazılır. */

    function initAutoAction() {
        var select = document.getElementById('qrae_auto_action');
        var url = document.getElementById('redirect_url');
        if (!select || !url) return;

        var remembered = url.value;

        select.addEventListener('change', function () {
            if (select.value === 'close') {
                remembered = url.value || remembered;
                url.value = '';
            } else if (!url.value && remembered) {
                url.value = remembered;
            }

            url.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    /* ---------------- Buton durum rozetleri ---------------- */

    function initButtonState() {
        var badges = document.querySelectorAll('[data-qrae-state]');
        if (!badges.length) return;

        badges.forEach(function (badge) {
            var input = field(badge.getAttribute('data-qrae-state'));
            if (!input) return;

            function sync() {
                var on = input.value.trim() !== '';
                badge.classList.toggle('is-on', on);
                badge.textContent = on ? 'Ekranda görünüyor' : 'Gizli';
            }

            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            sync();
        });
    }

    /* ---------------- Kaydedilmemiş değişiklik uyarısı ---------------- */

    function initDirtyState() {
        var form = document.getElementById('qrae-form');
        var flag = document.getElementById('qrae-dirty');
        if (!form) return;

        var dirty = false;

        function warn(e) {
            if (!dirty) return;
            e.preventDefault();
            e.returnValue = '';
        }

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        function markDirty() {
            if (dirty) return;
            dirty = true;
            if (flag) flag.hidden = false;
            window.addEventListener('beforeunload', warn);
        }

        form.addEventListener('submit', function () {
            dirty = false;
            window.removeEventListener('beforeunload', warn);
        });
    }

    /* ---------------- Önizleme katlama (dar ekran) ---------------- */

    function initPreviewToggle() {
        var btn = document.querySelector('.qrae-preview-collapse');
        var col = document.querySelector('.qrms-ae-preview-col');
        if (!btn || !col) return;

        var text = btn.querySelector('.qrae-preview-collapse-text');
        var narrow = window.matchMedia('(max-width: 960px)');

        function apply(collapsed) {
            col.classList.toggle('is-collapsed', collapsed);
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (text) text.textContent = collapsed ? 'Göster' : 'Gizle';
        }

        btn.addEventListener('click', function () {
            apply(!col.classList.contains('is-collapsed'));
        });

        // Dar ekranda kapalı başlar: form gereksiz yere uzamasın.
        apply(narrow.matches);

        if (typeof narrow.addEventListener === 'function') {
            narrow.addEventListener('change', function (e) { apply(e.matches); });
        }
    }

    /* Dil seçici açık/kapalı — dil listesini görsel olarak soldurur.
       Onay kutuları disable EDİLMEZ: POST'tan düşerlerse kayıtlı seçim
       silinirdi. */

    function initCeviriSelector() {
        var $toggle = $('[name="ceviri_selector"]');
        var $list = $('.splash-ceviri-langs');
        if (!$toggle.length || !$list.length) return;

        function sync() {
            $list.toggleClass('is-disabled', !$toggle.is(':checked'));
        }

        $toggle.on('change', sync);
        sync();
    }

    $(function () {
        initPreview();
        initColors();
        initRanges();
        initConditionals();
        initMedia();
        initSocialMedia();
        initPayments();
        initUrlValidation();
        initAutoAction();
        initButtonState();
        initDirtyState();
        initPreviewToggle();
        initCeviriSelector();
    });
})(jQuery);
