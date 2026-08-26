/* =====================================================================
   QR MENÜ — ORTAK ADMIN ARAYÜZ SCRIPT'İ

   Modülün yönetim ekranlarının tamamına hizmet eder: hazır temalar,
   renk seçiciler, kategori çubuğu önizlemesi, kategori sıralaması,
   öne çıkan ürün seçimi ve örnek CSV indirme.

   Sayfalar artık gerçek, ayrı WordPress sayfalarıdır — JS ile gizlenip
   gösterilen sekme motoru kaldırıldı.

   Nonce ve ajax adresi PHP tarafından `RMA_ADMIN` nesnesiyle verilir
   (bkz. admin_scripts() içindeki wp_add_inline_script(..., 'before')).
===================================================================== */
(function ($) {
    'use strict';

    var CFG = window.RMA_ADMIN || {};
    var AJAX_URL = CFG.ajaxUrl || window.ajaxurl;
    var NONCE = CFG.nonce || '';

    /* -----------------------------------------------------------------
       BİLDİRİM QUERY ARG'LARINI TEMİZLE
       Import/export redirect'leri sonuç bilgisini URL'de taşıyor. Mesaj
       basıldıktan sonra bu arg'lar URL'den düşürülür; böylece sayfa
       yenilendiğinde eski bildirim tekrar görünmez.
    ----------------------------------------------------------------- */
    var NOTICE_ARGS = [ 'imported', 'csv_error', 'rma_updated', 'rma_created', 'rma_backup_error' ];

    function stripNoticeArgs() {
        if ( ! window.history || ! window.history.replaceState ) return;
        try {
            var url = new URL(window.location.href);
            var touched = false;
            NOTICE_ARGS.forEach(function (key) {
                if (url.searchParams.has(key)) {
                    url.searchParams.delete(key);
                    touched = true;
                }
            });
            if (touched) window.history.replaceState({}, '', url);
        } catch (e) {
            /* URL API yoksa sessizce geç — bildirim yine de basılmış olur */
        }
    }

    /* -----------------------------------------------------------------
       KAYAR BAŞLIK — CANLI ÖNİZLEME

       Önizleme, frontend'in gerçek nav markup'ını ve gerçek stylesheet'ini
       (assets/css/rma-nav.css) kullanır; görünüm tamamen --rma-nav-*
       custom property'lerinden sürülür. Burada yaptığımız tek şey, form
       değerlerini bu değişkenlere yazmak.
    ----------------------------------------------------------------- */
    var NAV_VARS = {
        bg:           { prop: '--rma-nav-bg' },
        text:         { prop: '--rma-nav-text' },
        active:       { prop: '--rma-nav-active' },
        border_color: { prop: '--rma-nav-border' },
        padding_top:    { prop: '--rma-nav-pt',        unit: 'px' },
        padding_bottom: { prop: '--rma-nav-pb',        unit: 'px' },
        btn_padding_h:  { prop: '--rma-nav-btn-ph',    unit: 'px' },
        btn_padding_v:  { prop: '--rma-nav-btn-pv',    unit: 'px' },
        btn_spacing:    { prop: '--rma-nav-btn-gap',   unit: 'px' },
        font_size:      { prop: '--rma-nav-font-size', unit: 'rem' },
        font_weight:    { prop: '--rma-nav-font-weight' }
    };

    function fieldValue(key) {
        var $f = $('[name="rma_nav_design_settings[' + key + ']"]');
        if (!$f.length) return null;
        if ($f.first().is(':radio')) return $f.filter(':checked').val();
        return $f.val();
    }

    /**
     * Formdaki güncel değerleri önizlemeye uygular.
     * overrides: renk seçici sürüklenirken input henüz güncellenmemiş
     * olabildiği için { anahtar: değer } biçiminde taze değer geçilebilir.
     */
    function syncPreview(overrides) {
        var el = document.querySelector('.rma-nav-preview');
        if (!el) return;

        Object.keys(NAV_VARS).forEach(function (key) {
            var spec = NAV_VARS[key];
            var val  = (overrides && overrides[key] !== undefined) ? overrides[key] : fieldValue(key);
            if (val === null || val === undefined || val === '') return;
            el.style.setProperty(spec.prop, String(val) + (spec.unit || ''));
        });

        var ind = (overrides && overrides.active_indicator) || fieldValue('active_indicator');
        if (ind) el.setAttribute('data-rma-ind', ind);
    }

    function initNavPreview() {
        if (!document.querySelector('.rma-nav-preview')) return;

        // Range / select / metin alanları ve gösterge radyoları
        $(document).on(
            'input change',
            '[name^="rma_nav_design_settings"]',
            function () { syncPreview(); }
        );

        syncPreview();
    }

    /* -----------------------------------------------------------------
       RENK SEÇİCİ
    ----------------------------------------------------------------- */
    function initColorPickers() {
        var $pickers = $('.rma-color-picker');
        if (!$pickers.length || typeof $.fn.wpColorPicker !== 'function') return;

        $pickers.wpColorPicker({
            // Iris sürükleme sırasında input.val() henüz güncellenmediği için
            // taze değeri ui.color'dan alıp önizlemeye doğrudan geçiriyoruz.
            change: function (event, ui) {
                var key = navKeyOf(event.target);
                if (!key) return;
                syncPreview(keyed(key, ui.color.toString()));
            },
            clear: function (event) {
                var key = navKeyOf(event.target);
                if (key) syncPreview();
            }
        });
    }

    // input[name="rma_nav_design_settings[bg]"] -> "bg"
    function navKeyOf(el) {
        var name = el && el.getAttribute ? el.getAttribute('name') : null;
        var m = name && name.match(/^rma_nav_design_settings\[([a-z_]+)\]$/);
        return m ? m[1] : null;
    }

    function keyed(key, val) {
        var o = {};
        o[key] = val;
        return o;
    }

    function setPickerValue($input, value) {
        if (!$input.length) return;
        try {
            $input.wpColorPicker('color', value);
        } catch (e) {
            $input.val(value);
        }
    }

    /* -----------------------------------------------------------------
       ÜRÜN LİSTESİ — Göster/Gizle anahtarı
    ----------------------------------------------------------------- */
    function initStatusToggle() {
        bindListToggle('.rma-toggle-status', 'rma_toggle_status');
    }

    /* -----------------------------------------------------------------
       ÜRÜN LİSTESİ — Tükendi anahtarı (Göster/Gizle'den bağımsız)
    ----------------------------------------------------------------- */
    function initTukendiToggle() {
        bindListToggle('.rma-toggle-tukendi', 'rma_toggle_tukendi');
    }

    function bindListToggle(selector, action) {
        $(selector).on('change', function () {
            var isChecked = $(this).is(':checked') ? '1' : '0';
            var postId = $(this).data('id');
            var tog = $(this);
            $.post(AJAX_URL, {
                action: action,
                id: postId,
                status: isChecked,
                security: NONCE
            }, function (r) {
                if (!r.success) {
                    alert('Hata!');
                    tog.prop('checked', !tog.prop('checked'));
                }
            });
        });
    }

    /* -----------------------------------------------------------------
       KATEGORİ SIRALAMA
    ----------------------------------------------------------------- */
    function initCategorySorter() {
        var $list = $('#rma-category-list');
        if (!$list.length || typeof $.fn.sortable !== 'function') return;

        $list.sortable({
            update: function () {
                var order = [];
                $('#rma-category-list li').each(function () {
                    order.push($(this).data('id'));
                });
                $.post(AJAX_URL, {
                    action: 'rma_save_category_order',
                    order: order,
                    security: NONCE
                }, function (r) {
                    if (r.success) {
                        $('#rma-category-sorter-msg').fadeIn().delay(2000).fadeOut();
                    } else {
                        alert('Kaydedilemedi.');
                    }
                });
            }
        });
    }

    /* -----------------------------------------------------------------
       GÖRÜNÜM — HAZIR TEMALAR
       Tema kartına dokununca renk seçicilere değerler yazılır; kullanıcı
       "Görünümü Kaydet" ile onaylar. Renklerin bir kısmı "Diğer renk
       ayarları" bölümünün içinde olabildiği için seçilen kart işaretlenir —
       aksi hâlde tıklamanın işe yarayıp yaramadığı belli olmazdı.
    ----------------------------------------------------------------- */
    function initPalettes() {
        var $cards = $('.rma-theme-card');
        if (!$cards.length) return;

        $cards.on('click', function () {
            var p = $(this).data('palette');
            if (!p) return;

            $cards.removeClass('is-selected');
            $(this).addClass('is-selected');

            $.each(p, function (key, val) {
                setPickerValue($('#rma_c_' + key), val);
            });
        });
    }

    /* -----------------------------------------------------------------
       KAYAR BAŞLIK — HAZIR TASARIMLAR
    ----------------------------------------------------------------- */
    function syncIndicatorSelection() {
        $('input[name="rma_nav_design_settings[active_indicator]"]').each(function () {
            $(this).closest('.rma-choice').toggleClass('is-selected', $(this).is(':checked'));
        });
    }

    function initNavDesignPresets() {
        var $presets = $('.rma-nd-preset');
        if (!$presets.length) return;

        $presets.on('click', function () {
            var vals = $(this).data('values');
            var pid = $(this).data('preset');

            $presets.removeClass('is-selected');
            $presets.find('.rma-active-badge').remove();
            $(this).addClass('is-selected');
            $('<div class="rma-badge rma-active-badge">Aktif</div>').appendTo($(this));
            $('#rma_nd_preset').val(pid);

            $.each(['bg', 'text', 'active', 'border_color'], function (i, key) {
                if (vals[key] !== undefined) {
                    setPickerValue($('#rma_nd_' + key), vals[key]);
                }
            });

            $.each(['padding_top', 'padding_bottom', 'btn_padding_h', 'btn_padding_v', 'btn_spacing', 'font_size'], function (i, key) {
                if (vals[key] !== undefined) {
                    $('input[name="rma_nav_design_settings[' + key + ']"]').val(vals[key]).trigger('input');
                }
            });

            if (vals.font_weight !== undefined) {
                $('select[name="rma_nav_design_settings[font_weight]"]').val(vals.font_weight);
            }

            if (vals.active_indicator !== undefined) {
                $('input[name="rma_nav_design_settings[active_indicator]"][value="' + vals.active_indicator + '"]').prop('checked', true);
                syncIndicatorSelection();
            }

            if (vals.sticky !== undefined) {
                $('input[name="rma_nav_design_settings[sticky]"]').prop('checked', vals.sticky == '1');
            }
            if (vals.blur !== undefined) {
                $('input[name="rma_nav_design_settings[blur]"]').prop('checked', vals.blur == '1');
            }

            // Önizlemeyi doğrudan preset değerleriyle tazele. DOM'u yeniden
            // okumuyoruz: wpColorPicker('color', …) input.val()'i ne zaman
            // güncellediğine bağlı kalmamak için taze değerleri geçiriyoruz.
            syncPreview(vals);
        });

        // Kart bir <div> olduğu için klavye erişimi elle verilir
        // (role="button" + tabindex="0" işaretlemesinin karşılığı).
        $presets.on('keydown', function (e) {
            if (e.which !== 13 && e.which !== 32) return;
            e.preventDefault();
            $(this).trigger('click');
        });

        $(document).on('change', 'input[name="rma_nav_design_settings[active_indicator]"]', syncIndicatorSelection);
    }

    /* -----------------------------------------------------------------
       ÖNERİLER
    ----------------------------------------------------------------- */
    function initSuggestions() {
        var $modeInputs = $('input[name="rma_suggestion_mode"]');
        if (!$modeInputs.length) return;

        $modeInputs.on('change', function () {
            var v = $(this).val();
            $('#rma-manual-items-wrap').toggle(v === 'manual');
            $('#rma-mode-system-wrap').toggleClass('is-selected', v === 'system');
            $('#rma-mode-manual-wrap').toggleClass('is-selected', v === 'manual');
        });

        $(document).on('change', '.rma-manual-item-cb', function () {
            $(this).closest('label').find('.rma-selected-badge').toggle($(this).is(':checked'));
        });

        $('#rma-save-suggestions').on('click', function () {
            var mode = $('input[name="rma_suggestion_mode"]:checked').val();
            var ids = [];
            $('.rma-manual-item-cb:checked').each(function () {
                ids.push($(this).val());
            });
            $('#rma-save-spinner').show();
            $.post(AJAX_URL, {
                action: 'rma_save_suggestions',
                security: NONCE,
                mode: mode,
                manual_ids: ids
            }, function (r) {
                $('#rma-save-spinner').hide();
                if (r.success) {
                    $('#rma-suggestions-saved').fadeIn().delay(2500).fadeOut();
                } else {
                    alert('Kaydetme hatası!');
                }
            });
        });
    }

    /* -----------------------------------------------------------------
       ÖRNEK CSV İNDİRME
       Sütun sırası PHP'den (get_csv_columns) data-csv ile gelir; burada
       yalnızca dosyaya çevirip indiriyoruz — ayrı bir backend uç noktası
       ve nonce gerekmiyor.
    ----------------------------------------------------------------- */
    function csvEscape(v) {
        v = String(v == null ? '' : v);
        return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    }

    function initCsvSample() {
        var $btn = $('#rma-csv-sample');
        if (!$btn.length) return;

        $btn.on('click', function () {
            var rows;
            try {
                rows = JSON.parse($btn.attr('data-csv'));
            } catch (e) {
                return;
            }

            var csv = rows.map(function (row) {
                return row.map(csvEscape).join(',');
            }).join('\r\n');

            // BOM — Excel'in UTF-8 Türkçe karakterleri doğru açması için
            var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href = url;
            a.download = 'qr-menu-ornek.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }

    /* -----------------------------------------------------------------
       ÜRÜN VİTRİNİ — SEÇİM, SIRALAMA VE KISA KOD

       Sıra ayrı bir AJAX ucuna yazılmaz: sortable her değişimde gizli
       #rma-vitrin-order alanını tazeler, form gönderilirken sıra da
       birlikte gider (bkz. trait-vitrin-admin.php).
    ----------------------------------------------------------------- */
    function vitrinSyncOrder() {
        var ids = [];
        $('#rma-vitrin-sortable .rma-vitrin-chip').each(function () {
            ids.push($(this).data('id'));
        });
        $('#rma-vitrin-order').val(ids.join(','));
        $('.rma-vitrin-empty').toggle(ids.length === 0);
        vitrinRenderPreviewCards();
    }

    /**
     * Ürün havuzundaki her satırın ad/görsel/fiyat verisini id'ye göre
     * haritalar (bkz. trait-vitrin-admin.php data-title/data-img/data-price).
     * Satırlar sayfa yüklendiğinde sabitlendiği için tek seferde çıkarılıp
     * önbelleğe alınır.
     *
     * @return Object<string,{title:string,img:string,price:string}>
     */
    var vitrinProductMapCache = null;

    function vitrinBuildProductMap() {
        if (vitrinProductMapCache) return vitrinProductMapCache;

        var map = {};
        $('.rma-vitrin-pool-row').each(function () {
            var $row = $(this);
            map[String($row.data('id'))] = {
                title: String($row.data('title') || ''),
                img: String($row.data('img') || ''),
                price: String($row.data('price') || '')
            };
        });

        vitrinProductMapCache = map;
        return map;
    }

    /**
     * Canlı önizleme kartlarını (#rma-vitrin-preview .qrms-vitrin-viewport)
     * o an "Vitrindeki Sıra" listesinde duran ürünlerin GERÇEK verisiyle
     * yeniden çizer — trait-vitrin-admin.php render_vitrin_preview_card()
     * ile BİREBİR aynı markup/sınıflar kullanılır ki vitrin.css'ten aynı
     * şekilde boyansın.
     *
     * Yer tutucu kartlar YALNIZCA hiç ürün seçilmemişken gösterilir; ürün
     * seçiliyse önizleme sadece o seçimi gösterir (6'ya tamamlanmaz).
     * "Vitrindeki Sıra" listesi DOM'da kaldığı sürece (adımlar arası
     * geçişte yalnızca display:none olur, kaldırılmaz) bu state hiç
     * kaybolmaz.
     */
    function vitrinRenderPreviewCards() {
        var $viewport = $('#rma-vitrin-preview .qrms-vitrin-viewport');
        if (!$viewport.length) return;

        var map = vitrinBuildProductMap();
        var azami = 8;

        var ids = [];
        $('#rma-vitrin-sortable .rma-vitrin-chip').each(function () {
            ids.push(String($(this).data('id')));
        });

        var kartlar = [];
        if (ids.length === 0) {
            for (var i = 0; i < 6; i++) kartlar.push(null);
        } else {
            ids.slice(0, azami).forEach(function (id) {
                kartlar.push(map[id] || null);
            });
        }

        $viewport.empty();

        kartlar.forEach(function (urun) {
            var $card = $('<article class="qrms-vitrin-card"></article>');
            var $media = $('<div class="qrms-vitrin-media"></div>');

            if (urun && urun.img) {
                $media.append($('<img class="qrms-vitrin-img" alt="">').attr('src', urun.img));
            } else {
                $media.append('<span class="qrms-vitrin-img qrms-vitrin-img-empty" aria-hidden="true">◆</span>');
            }

            var $body = $('<div class="qrms-vitrin-body"></div>');
            $body.append($('<h3 class="qrms-vitrin-title"></h3>').text(urun ? urun.title : 'Ürün Adı'));
            // Fiyat kampanya/kombin işaretlemesi (ör. üstü çizili eski fiyat)
            // barındırabileceği için .html() ile yazılır; kaynağı kendi
            // sunucu render'ımız (data-price), kullanıcı girdisi değil.
            $body.append($('<p class="qrms-vitrin-price"></p>').html(urun && urun.price ? urun.price : '₺0,00'));

            $card.append($media, $body);
            $viewport.append($card);
        });
    }

    function vitrinChip($row) {
        var id  = $row.data('id');
        var $li = $('<li class="rma-vitrin-chip"></li>').attr('data-id', id);

        $li.append('<span class="rma-vitrin-drag" aria-hidden="true">⋮⋮</span>');
        // Küçük görsel havuzdaki satırdan klonlanır — yeniden indirilmez.
        $li.append($row.find('.rma-vitrin-thumb').first().clone());
        $li.append($('<span class="rma-vitrin-chip-title"></span>').text($row.find('.rma-vitrin-pool-title').text()));
        $li.append('<button type="button" class="rma-vitrin-remove" aria-label="Vitrinden çıkar">&times;</button>');

        return $li;
    }

    function initVitrinPicker() {
        var $form = $('#rma-vitrin-form');
        if (!$form.length) return;

        var $sortable = $('#rma-vitrin-sortable');

        if (typeof $.fn.sortable === 'function') {
            $sortable.sortable({
                items: '.rma-vitrin-chip',
                handle: '.rma-vitrin-drag',
                placeholder: 'rma-vitrin-placeholder',
                forcePlaceholderSize: true,
                tolerance: 'pointer',
                update: vitrinSyncOrder
            });
        }

        // Havuzdaki işaret kutusu: seçilince sıraya eklenir, kaldırılınca çıkar.
        $form.on('change', '.rma-vitrin-cb', function () {
            var $row = $(this).closest('.rma-vitrin-pool-row');
            var id   = $row.data('id');

            $row.toggleClass('is-selected', this.checked);

            if (this.checked) {
                if (!$sortable.find('.rma-vitrin-chip[data-id="' + id + '"]').length) {
                    $sortable.append(vitrinChip($row));
                }
            } else {
                $sortable.find('.rma-vitrin-chip[data-id="' + id + '"]').remove();
            }

            vitrinSyncOrder();
        });

        // Sıradaki "×": kaydı çıkarır ve havuzdaki kutunun işaretini kaldırır.
        $form.on('click', '.rma-vitrin-remove', function () {
            var $chip = $(this).closest('.rma-vitrin-chip');
            var id    = $chip.data('id');

            $form.find('.rma-vitrin-pool-row[data-id="' + id + '"]')
                 .removeClass('is-selected')
                 .find('.rma-vitrin-cb').prop('checked', false);

            $chip.remove();
            vitrinSyncOrder();
        });

        // Arama — sunucuya gitmeden havuzu süzer.
        $form.on('input', '#rma-vitrin-search', function () {
            // $.trim değil: jQuery 4'te kaldırıldı, native karşılığı her sürümde var.
            var q = String(this.value).trim().toLowerCase();

            $form.find('.rma-vitrin-pool-row').each(function () {
                var title = String($(this).data('title') || '').toLowerCase();
                $(this).toggleClass('is-hidden', q !== '' && title.indexOf(q) === -1);
            });
        });

        vitrinSyncOrder();
    }

    /* -----------------------------------------------------------------
       ÜRÜN VİTRİNİ — CANLI ÖNİZLEME

       "3. Düzen" ve "4. Kart Boyutu" alanlarındaki her değişiklik, sayfa
       yenilenmeden #rma-vitrin-preview'e yansır. Önizleme gerçek
       vitrin.css'i kullandığı için burada yapılan tek şey, seçilen moda
       (masaüstü/mobil) göre doğru alan grubunu okuyup aynı CSS
       değişkenlerine ('--qrms-vitrin-*') yazmak — desen NAV_VARS/
       syncPreview() ile aynı (bkz. yukarısı).
    ----------------------------------------------------------------- */
    var VITRIN_PREVIEW_FIELDS = {
        desktop: {
            cols:    'rma-vitrin-cols',
            rows:    'rma-vitrin-rows',
            gap:     'rma-vitrin-desktop-gap',
            cardMin: 'rma-vitrin-desktop-card-min',
            ratio:   'rma-vitrin-desktop-ratio'
        },
        mobile: {
            cols:    'rma-vitrin-mobile-cols',
            rows:    'rma-vitrin-mobile-rows',
            gap:     'rma-vitrin-mobile-gap',
            cardMin: 'rma-vitrin-mobile-card-min',
            ratio:   'rma-vitrin-mobile-ratio'
        }
    };

    function initVitrinPreview() {
        var $form  = $('#rma-vitrin-form');
        var $stage = $('#rma-vitrin-preview-stage');
        var $preview = $('#rma-vitrin-preview');

        if (!$form.length || !$preview.length) return;

        var mode = 'desktop';
        var previewEl = $preview.get(0);

        function fieldVal(id, fallback) {
            var $el = $('#' + id);
            return $el.length ? $el.val() : fallback;
        }

        function applyPreview() {
            var f = VITRIN_PREVIEW_FIELDS[mode];
            var m = VITRIN_PREVIEW_FIELDS.mobile;

            previewEl.style.setProperty('--qrms-vitrin-cols', fieldVal(f.cols, 4));
            previewEl.style.setProperty('--qrms-vitrin-rows', fieldVal(f.rows, 1));
            previewEl.style.setProperty('--qrms-vitrin-gap', fieldVal(f.gap, 16) + 'px');
            previewEl.style.setProperty('--qrms-vitrin-card-min', fieldVal(f.cardMin, 200) + 'px');
            previewEl.style.setProperty('--qrms-vitrin-image-ratio', fieldVal(f.ratio, 100));

            // vitrin.css'in GERÇEK dar ekran medya sorgusu (<1023px) admin
            // penceresi kendisi o kadar darsa yine devreye girer; --mobile-*
            // değişkenleri burada da her zaman güncel tutulur ki o durumda
            // "Masaüstü Önizleme" seçiliyken bile boş/tanımsız değere düşmesin.
            previewEl.style.setProperty('--qrms-vitrin-mobile-cols', fieldVal(m.cols, 2));
            previewEl.style.setProperty('--qrms-vitrin-mobile-rows', fieldVal(m.rows, 1));
            previewEl.style.setProperty('--qrms-vitrin-mobile-gap', fieldVal(m.gap, 12) + 'px');
            previewEl.style.setProperty('--qrms-vitrin-mobile-card-min', fieldVal(m.cardMin, 132) + 'px');
            previewEl.style.setProperty('--qrms-vitrin-mobile-image-ratio', fieldVal(m.ratio, 100));

            var bg = fieldVal('rma-vitrin-bg-color', '');
            previewEl.style.setProperty('--qrms-vitrin-bg', bg ? bg : 'transparent');

            $stage.toggleClass('is-mobile-mode', mode === 'mobile');
            $stage.toggleClass('is-price-hidden', !$('#rma-vitrin-show-price').is(':checked'));
        }

        var allFieldIds = [];
        $.each(VITRIN_PREVIEW_FIELDS, function (_, f) {
            $.each(f, function (_, id) { allFieldIds.push('#' + id); });
        });

        $form.on('input change', allFieldIds.join(', ') + ', #rma-vitrin-show-price, #rma-vitrin-bg-color', applyPreview);

        // wpColorPicker Iris ile sürüklenirken text input'un value'su henüz
        // güncellenmemiş olabilir (bkz. initColorPickers'taki aynı not) —
        // taze değeri ui.color'dan alıp doğrudan CSS değişkenine yazıyoruz.
        var $bgColor = $('#rma-vitrin-bg-color');
        if ($bgColor.length && typeof $.fn.wpColorPicker === 'function') {
            $bgColor.wpColorPicker({
                change: function (event, ui) {
                    previewEl.style.setProperty('--qrms-vitrin-bg', ui.color.toString());
                },
                clear: function () {
                    previewEl.style.setProperty('--qrms-vitrin-bg', 'transparent');
                }
            });
        }

        $form.on('click', '.rma-vitrin-preview-btn', function () {
            mode = $(this).data('preview-mode');
            $('.rma-vitrin-preview-btn').removeClass('is-active');
            $(this).addClass('is-active');
            applyPreview();
        });

        applyPreview();
        initVitrinPreviewSticky();
    }

    /**
     * Canlı önizleme sütununun sticky `top` ofsetini WP admin bar
     * yüksekliğine göre yazar. Sabit olmayan (mobilde kaydırılan) bar
     * yok sayılır; dar ekranda sticky CSS zaten kapalıdır.
     */
    function initVitrinPreviewSticky() {
        var root = document.querySelector('.rma-admin');
        if (!root) return;

        function applyOffset() {
            var bar = document.getElementById('wpadminbar');
            var top = 32;

            if (bar) {
                var pos = window.getComputedStyle(bar).position;
                top = (pos === 'fixed' || pos === 'sticky') ? bar.offsetHeight : 0;
            } else {
                top = 0;
            }

            root.style.setProperty('--rma-vitrin-sticky-top', top + 'px');
        }

        applyOffset();
        $(window).on('resize', applyOffset);
    }

    /* -----------------------------------------------------------------
       ÜRÜN VİTRİNİ — ADIM ADIM (STEPPER)

       Saf UI bölmesi: beş .rma-vitrin-step kartı DOM'da hep birlikte
       kalır, yalnızca aktif olmayanlar gizlenir (display:none). Böylece
       hiçbir form alanı submit'ten düşmez, hiçbir ayar adımlar arası
       geçişte kaybolmaz — kaydetme her zaman TÜM adımların verisini
       gönderir (bkz. trait-vitrin-admin.php handle_vitrin_save()).
       Gerçek bir "wizard validation" yoktur: adımlar arasında serbestçe
       ileri/geri/atlama yapılabilir, hiçbir alan zorunlu kılınmaz.
    ----------------------------------------------------------------- */
    function initVitrinStepper() {
        var $form = $('#rma-vitrin-form');
        var $steps = $('.rma-vitrin-step');

        if (!$form.length || !$steps.length) return;

        var toplam = $steps.length;
        var mevcut = 1;

        var $stepBtns = $('#rma-vitrin-steps .rma-vitrin-step-btn');
        var $compact = $('#rma-vitrin-step-compact');
        var $prev = $('.rma-vitrin-step-prev');
        var $next = $('.rma-vitrin-step-next');
        var $submit = $('.rma-vitrin-step-submit');

        function baslik(adimNo) {
            return $steps.filter('[data-step="' + adimNo + '"]').data('step-title') || '';
        }

        function goster(adimNo) {
            mevcut = Math.min(Math.max(adimNo, 1), toplam);

            $steps.each(function () {
                var no = parseInt($(this).data('step'), 10);
                $(this).toggle(no === mevcut);
            });

            $stepBtns.each(function () {
                var no = parseInt($(this).data('step-target'), 10);
                $(this)
                    .toggleClass('is-active', no === mevcut)
                    .toggleClass('is-done', no < mevcut)
                    .attr('aria-selected', no === mevcut ? 'true' : 'false');
            });

            $compact.text('Adım ' + mevcut + '/' + toplam + ': ' + baslik(mevcut));

            $prev.prop('disabled', mevcut === 1);
            $next.toggle(mevcut < toplam);
            $submit.toggle(mevcut === toplam);
        }

        $stepBtns.on('click', function () {
            goster(parseInt($(this).data('step-target'), 10));
        });

        $prev.on('click', function () { goster(mevcut - 1); });
        $next.on('click', function () { goster(mevcut + 1); });

        goster(1);
    }

    /**
     * Kısa kodu panoya kopyalar. Pano API'si yoksa (http:// üzerinde
     * çalışan siteler) metin seçili bırakılır — kullanıcı elle kopyalar.
     */
    function initShortcodeCopy() {
        $(document).on('click', '.rma-copy-shortcode', function () {
            var $btn = $(this);
            var code = $btn.data('shortcode');
            var $input = $btn.closest('.rma-shortcode-row').find('.rma-shortcode-input');

            function done() {
                var eski = $btn.text();
                $btn.text('Kopyalandı ✓');
                window.setTimeout(function () { $btn.text(eski); }, 1800);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(done, function () { $input.trigger('select'); });
                return;
            }

            $input.trigger('select');
            try {
                if (document.execCommand('copy')) done();
            } catch (e) {
                /* Seçili metin kullanıcıya bırakılır */
            }
        });
    }

    /* -----------------------------------------------------------------
       TOPLU FİYAT KAMPANYASI
       Kapsam seçimi, ürün/kategori işaretleri ve canlı önizleme.

       Kritik davranış: "Kampanyayı Başlat" butonu, formun O ANKİ hâli için
       önizleme üretilene kadar KAPALIDIR. Ayarların herhangi biri değişince
       önizleme bayatlar ve buton tekrar kilitlenir — kaza ile tüm menüyü
       zamlamaya/indirmeye karşı asıl fren budur.
    ----------------------------------------------------------------- */
    function initKampanya() {
        var $form = $('#rma-kmp-form');
        if (!$form.length) return;

        var $uygulaBtn = $('#rma-kmp-uygula-btn');
        var $onizleme = $('#rma-kmp-onizleme');
        var $gizliIdler = $('#rma-kmp-scope-ids');

        function kapsam() {
            return $form.find('input[name="scope_type"]:checked').val() || 'all';
        }

        function secimiSenkronla() {
            var tur = kapsam();
            var idler = [];

            if (tur === 'category') {
                $('.rma-kmp-kat-cb:checked').each(function () { idler.push($(this).val()); });
            } else if (tur === 'manual') {
                $('.rma-kmp-urun-cb:checked').each(function () { idler.push($(this).val()); });
            }

            $gizliIdler.val(idler.join(','));
        }

        function kapsamKutulari() {
            var tur = kapsam();
            $('.rma-kmp-kapsam-kutu').each(function () {
                $(this).toggle($(this).data('kapsam') === tur);
            });
        }

        function secimVurgusu() {
            $('.rma-kmp-choice').each(function () {
                $(this).toggleClass('is-selected', $(this).find('input').is(':checked'));
            });
        }

        function birim() {
            var tur = $form.find('input[name="calc_type"]:checked').val();
            $('#rma-kmp-birim').text(tur === 'fixed' ? '₺' : '%');
        }

        function bayatla() {
            $uygulaBtn.prop('disabled', true);
            if ($onizleme.children().length) {
                $onizleme.html('<p class="rma-kmp-bayat">Ayarları değiştirdiniz. Kampanyayı başlatmadan önce önizlemeyi tekrar alın.</p>');
            }
        }

        $form.on('change', 'input[name="scope_type"]', function () {
            kapsamKutulari();
            secimiSenkronla();
        });

        // Seçili ürün satırı vitrin seçicisiyle aynı vurguyu alır.
        $form.on('change', '.rma-kmp-urun-cb', function () {
            $(this).closest('.rma-vitrin-pool-row').toggleClass('is-selected', $(this).is(':checked'));
        });

        $form.on('change', 'input, select', function () {
            secimVurgusu();
            birim();
            secimiSenkronla();
            bayatla();
        });

        $form.on('input', 'input[type="text"]', bayatla);

        // Ürün arama — küçük harfe çevirme tarayıcıda yapılır ki arama kutusu
        // ile satır başlıkları aynı Unicode kurallarını kullansın (Türkçe İ/I).
        $('#rma-kmp-search').on('input', function () {
            var q = ($(this).val() || '').toLocaleLowerCase();

            $('.rma-kmp-urun-cb').closest('.rma-vitrin-pool-row').each(function () {
                var ad = ($(this).data('title') || '').toString().toLocaleLowerCase();
                $(this).toggle(q === '' || ad.indexOf(q) !== -1);
            });
        });

        $('#rma-kmp-onizle').on('click', function () {
            var $spinner = $('.rma-kmp-spinner');

            secimiSenkronla();
            $spinner.addClass('is-active');
            $onizleme.html('');

            $.post(AJAX_URL, {
                action: 'rma_kampanya_onizleme',
                nonce: NONCE,
                title: $form.find('input[name="title"]').val(),
                calc_type: $form.find('input[name="calc_type"]:checked').val(),
                direction: $form.find('input[name="direction"]:checked').val(),
                amount: $('#rma-kmp-amount').val(),
                rounding: $('#rma-kmp-rounding').val(),
                scope_type: kapsam(),
                scope_ids: $gizliIdler.val(),
                show_old_price: $form.find('input[name="show_old_price"]').is(':checked') ? 1 : 0
            }, function (r) {
                $spinner.removeClass('is-active');

                if (!r || !r.success) {
                    $onizleme.html('<p class="rma-kmp-bayat">Önizleme alınamadı. Sayfayı yenileyip tekrar deneyin.</p>');
                    return;
                }

                $onizleme.html(r.data.html);
                $uygulaBtn.prop('disabled', r.data.etkilenen < 1);
            });
        });

        // Hangi butona basıldıysa "uygula" bayrağı ona göre gider.
        $('#rma-kmp-kaydet').on('click', function () { $('#rma-kmp-uygula').val('0'); });
        $uygulaBtn.on('click', function () { $('#rma-kmp-uygula').val('1'); });

        kapsamKutulari();
        secimVurgusu();
        birim();
        secimiSenkronla();
    }

    /* -----------------------------------------------------------------
       HIZLI DÜZENLE — görsel + alerjenler

       WordPress satırı açarken inlineEditPost.edit()'i çağırır; kaydetmede
       inlineEditPost.save() #edit-{id} içindeki tüm :input'ları serialize
       eder (inline-save AJAX). edit'i sarmalayarak #inline_{id} içindeki
       rma_thumb_* / rma_allergen verisini kopyalanmış satıra doldururuz.
       save sarmalaması çekirdeğin serialize'ına dokunmaz; yalnızca edit
       ile aynı koruma altında tanımlıdır (inlineEditPost yoksa no-op).
    ----------------------------------------------------------------- */
    function rmaQeFillImage($row, thumbId, thumbUrl) {
        var id = parseInt(thumbId, 10) || 0;
        $row.find('.rma-qe-thumb-id').val(id > 0 ? id : 0);

        var $img = $row.find('.rma-qe-thumb-preview');
        if (id > 0 && thumbUrl) {
            $img.attr('src', thumbUrl).prop('hidden', false);
            $row.find('.rma-qe-remove-image').prop('hidden', false);
        } else {
            $img.attr('src', '').prop('hidden', true);
            $row.find('.rma-qe-remove-image').prop('hidden', true);
        }
    }

    function rmaQeFillAllergens($row, idsCsv) {
        var ids = String(idsCsv || '')
            .split(',')
            .map(function (v) { return String(v).trim(); })
            .filter(function (v) { return v && v !== '0'; });

        var $boxes = $row.find('ul.rma_allergen-checklist :checkbox');
        $boxes.prop('checked', false);
        if (ids.length) {
            $boxes.val(ids);
        }
    }

    function initQuickEdit() {
        if (typeof window.inlineEditPost === 'undefined') return;

        var origEdit = inlineEditPost.edit;
        inlineEditPost.edit = function (id) {
            origEdit.apply(this, arguments);

            var postId = (typeof id === 'object') ? parseInt(this.getId(id), 10) : parseInt(id, 10);
            if (!postId) return;

            var $editRow = $('#edit-' + postId);
            var $data = $('#inline_' + postId);
            if (!$editRow.length || !$data.length) return;

            rmaQeFillImage(
                $editRow,
                $data.find('.rma_thumb_id').text(),
                $data.find('.rma_thumb_url').text()
            );
            rmaQeFillAllergens($editRow, $data.find('.rma_allergen').text());
        };

        var origSave = inlineEditPost.save;
        inlineEditPost.save = function (id) {
            return origSave.apply(this, arguments);
        };

        var mediaFrame = null;

        $(document).on('click', '.inline-edit-row .rma-qe-select-image', function (e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) return;

            var $row = $(this).closest('.inline-edit-row');

            if (mediaFrame) {
                mediaFrame.off('select');
            } else {
                mediaFrame = wp.media({
                    title: 'Görsel Seç',
                    button: { text: 'Görsel Seç' },
                    multiple: false
                });
            }

            mediaFrame.on('select', function () {
                var att = mediaFrame.state().get('selection').first().toJSON();
                var url = (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url)
                    ? att.sizes.thumbnail.url
                    : att.url;
                rmaQeFillImage($row, att.id, url);
            });

            mediaFrame.open();
        });

        $(document).on('click', '.inline-edit-row .rma-qe-remove-image', function (e) {
            e.preventDefault();
            rmaQeFillImage($(this).closest('.inline-edit-row'), 0, '');
        });
    }

    /* -----------------------------------------------------------------
       BAŞLAT
    ----------------------------------------------------------------- */
    /* -----------------------------------------------------------------
       BÖLÜM BAĞLANTILARI
       "Diğer Ayarlar" sayfasındaki kısayollar bir karta atlar; hedef kart
       kapalı bir açılır bölümün içindeyse önce o bölüm açılır.
    ----------------------------------------------------------------- */
    function openTargetDetails() {
        var hash = window.location.hash;
        if (!hash || hash.length < 2) return;

        var target;
        try {
            target = document.querySelector(hash);
        } catch (e) {
            return;
        }
        if (!target) return;

        var parent = target.closest ? target.closest('details') : null;
        while (parent) {
            parent.open = true;
            parent = parent.parentElement && parent.parentElement.closest
                ? parent.parentElement.closest('details')
                : null;
        }

        target.scrollIntoView();
    }

    $(function () {
        stripNoticeArgs();
        initColorPickers();
        initStatusToggle();
        initTukendiToggle();
        initCategorySorter();
        initPalettes();
        initNavPreview();
        initNavDesignPresets();
        initSuggestions();
        initCsvSample();
        initVitrinPicker();
        initVitrinPreview();
        initVitrinStepper();
        initShortcodeCopy();
        initKampanya();
        initQuickEdit();
        openTargetDetails();
        $(window).on('hashchange', openTargetDetails);
    });
})(jQuery);
