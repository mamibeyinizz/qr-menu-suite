/* Açılış Ekranı - yönetim ekranı davranışları.
   Yalnızca modülün kendi sayfalarında yüklenir.

   Sekme kodu suite'e taşınırken kaldırıldı: dört sekme artık dört ayrı sayfa.
   Yerine canlı önizleme geldi. */
(function ($) {
    'use strict';

    /* ---------------- Canlı önizleme ----------------

       Önizleme, frontend'in GERÇEK markup'ı ve GERÇEK stylesheet'idir; ayrı
       bir taklit yoktur. Overlay data-preview="1" taşır ve bu bayrak iki işi
       birden yapar:

         - splash.js (ön yüz betiği) bayrağı görürse hiç çalışmaz: çerez
           okunmaz/yazılmaz, yönlendirme zamanlayıcısı kurulmaz. Yönetici
           önizlemeye bakarken kendi tarayıcısında splash'ı "kapatmış" duruma
           düşmez.
         - Buradaki kod da yalnızca bu bayrağı taşıyan overlay'e dokunur.

       Neyin canlı güncellendiği: renk, opaklık, ölçü gibi CSS değişkenine
       inen her ayar ve metin alanları. Yapıyı değiştiren ayarlar (görsel
       seçimi, ödeme yöntemi, sosyal hesap, yüklenme göstergesi tipi) yeni
       markup gerektirdiği için kaydedilince yansır; o durumda önizlemenin
       üstünde "kaydedilince güncellenecek" rozeti belirir. */

    function initPreview() {
        var overlay = document.querySelector('#custom-splash-overlay[data-preview="1"]');
        if (!overlay) return;

        var stack = overlay.querySelector('.splash-stack');

        function setVar(name, value) {
            overlay.style.setProperty(name, value);
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

        function val(name) {
            var el = document.querySelector('[name="' + name + '"]');
            return el ? el.value : '';
        }

        /* CTA dolgusu iki kaynaktan beslenir; hangisinin geçerli olduğunu
           "CTA da buton yüzeyini kullansın" kutusu belirler - PHP tarafındaki
           build_css_vars() ile aynı kural. */
        function syncCtaBg() {
            var useSurface = document.querySelector('[name="btn_surface_apply_cta"]');
            var accent = hexToRgb(val('button_bg_color')) || '0,115,170';
            var surface = hexToRgb(val('btn_surface_color')) || '255,255,255';
            var btnAlpha = (parseInt(val('button_opacity'), 10) || 0) / 100;
            var surfaceAlpha = (parseInt(val('btn_surface_opacity'), 10) || 0) / 100;

            setVar('--sp-cta-bg', useSurface && useSurface.checked
                ? 'rgba(' + surface + ',' + surfaceAlpha + ')'
                : 'rgba(' + accent + ',' + btnAlpha + ')');
        }

        function markStale() {
            overlay.closest('.qrms-ae-preview').classList.add('is-stale');
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
            button_text_color: function (v) { setVar('--sp-btn-text', v); },
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

        /* Metin alanları. */

        /* --- İki dilli metinler ---

           Önizlemedeki metin düğümleri iki dili de data niteliğinde taşır
           (data-sp-tr / data-sp-en); hangisinin görüneceğine splash.js karar
           verir. Yönetici yazarken NİTELİĞİ güncelliyoruz, görünen metni
           değil: önizleme o an İngilizceye alınmışsa Türkçe alanına yazmak
           ekrandaki İngilizce metni bozmamalı. */

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
         * Dil düğmesi AÇIKKEN düğüm iki dili de data niteliğinde taşır: o
         * zaman yazılan değer ilgili niteliğe gider ve görünen metin ancak
         * o an gösterilen dil buysa değişir. Aksi hâlde önizleme İngilizceye
         * alınmışken Türkçe alana yazmak ekrandaki çeviriyi silerdi.
         *
         * Dil düğmesi KAPALIYKEN data niteliği hiç basılmaz; o durumda
         * yedek seçici kullanılır ve metin doğrudan yazılır (eski davranış).
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

        // Bir rozetin görünür etiketi ile erişilebilirlik etiketi aynı metni
        // paylaşır; ikisi de aynı data-sp-key'i taşıdığı için tek seferde
        // bulunur.
        // Yalnızca CTA ve ayracın görünür bir metni vardır; dil düğmesi
        // kapalıyken canlı güncelleme onlarda yedek seçiciyle sürer.
        var yedek = { btn1: '.splash-cta', divider: '.splash-divider-label' };

        [1, 2, 3, 4, 5].forEach(function (i) {
            bindLangText('button_text_' + i, 'btn' + i, 'tr', yedek[ 'btn' + i ]);
        });

        bindLangText('divider_text', 'divider', 'tr', yedek.divider);

        $('[name="wifi_password"]').on('input change', function () {
            var el = document.querySelector('#wifi-modal p');
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
        // Dil düğmesinin açılması/kapanması markup'ı değiştirir (düğme ve
        // data nitelikleri), ayrıca ilk İngilizce metin girildiğinde düğme
        // görünür hâle gelir — ikisi de kaydetmeyi gerektirir.
        $('[name="loader_type"], [name="bg_scheme"], [name="payment_display_mode"], [name="payment_methods[]"], [name="social_media_active[]"], [name="lang_toggle"], [name="ceviri_selector"], [name="ceviri_selector_langs[]"], .upload-image-btn, .splash-remove-image')
            .on('change click', markStale);

        if (!overlay.querySelector('.splash-lang')) {
            $('[name="lang_toggle"]').on('change', markStale);
        }

        // Sayfa açılışında animasyon bir kez oynasın.
        replayAnimation();
    }

    /* ---------------- Renk seçiciler + temalar ---------------- */

    function initColors() {
        var $pickers = $('.color-picker');
        if (!$pickers.length || typeof $.fn.wpColorPicker !== 'function') return;

        $pickers.wpColorPicker();

        $('.theme-preset').on('click', function (e) {
            e.preventDefault();
            var $btn = $(this);
            $('input[name="bg_color"]').wpColorPicker('color', $btn.data('bg'));
            $('input[name="button_bg_color"]').wpColorPicker('color', $btn.data('btn'));
            $('input[name="button_text_color"]').wpColorPicker('color', $btn.data('text'));
        });
    }

    /* ---------------- Aralık alanlarının canlı değeri ---------------- */

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
            $field.find('.image-preview').empty();
            $field.find('.splash-media-warnings').empty();
            $btn.addClass('hidden');
        });
    }

    /* ---------------- Sosyal medya (checkbox + URL, en fazla 6) ---------------- */
    /* Sıralama JS ile takip edilir: her checkbox değişiminde işaretli
       anahtarların "işaretlenme sırası" bir gizli alanda (social_media_order)
       tutulur ve kaydedilirken PHP tarafında render/kayıt sırası olarak
       kullanılır. JS çalışmazsa form yine de gönderilir, sıralama o zaman
       checkbox'ların DOM sırasına düşer (progressive enhancement). */

    function initSocialMedia() {
        var $grid = $('.splash-social-grid');
        if (!$grid.length) return;

        var MAX_ACTIVE = 6;
        var $order = $('#social_media_order');
        var $note = $('.splash-social-limit-note');
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

        function updateLimitState() {
            var atMax = checkedKeys().length >= MAX_ACTIVE;
            $grid.find('.splash-social-check').each(function () {
                var $cb = $(this);
                $cb.prop('disabled', atMax && !$cb.is(':checked'));
            });
            $note.toggleClass('is-visible', atMax);
        }

        $grid.on('change', '.splash-social-check', function () {
            syncOrder();
            updateLimitState();
        });

        syncOrder();
        updateLimitState();
    }

    /* Dil seçici açık/kapalı — dil listesini görsel olarak soldurur.
       Checkbox'lar disable EDİLMEZ: POST'tan düşerlerse kayıtlı seçim
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
        initMedia();
        initSocialMedia();
        initCeviriSelector();
    });
})(jQuery);
