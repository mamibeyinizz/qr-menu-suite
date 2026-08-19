/* =====================================================================
   QR MENÜ — ORTAK ADMIN ARAYÜZ SCRIPT'İ

   Daha önce dört ayrı yere dağılmıştı:
     - admin_scripts() içindeki inline jQuery bloğu
     - Ayarlar sayfasının kendi <script> bloğu (iç sekmeler + paletler)
     - Kayar Başlık sayfasının <script> bloğu (preset uygulama)
     - Öneriler sayfasının <script> bloğu (mod + kaydetme)

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
       GENEL SEKME MOTORU
       Hem ana sekmeleri hem de Ayarlar sayfasının iç sekmelerini aynı
       kod sürer; fark yalnızca sınıf/veri adlarında.
    ----------------------------------------------------------------- */
    function initTabs(options) {
        var $buttons = $(options.button);
        if (!$buttons.length) return;

        function activate(id, updateUrl) {
            $buttons.each(function () {
                var $btn = $(this);
                var isActive = String($btn.data(options.dataKey)) === String(id);
                $btn.toggleClass('is-active', isActive);
                $btn.attr('aria-selected', isActive ? 'true' : 'false');
            });

            $(options.panel).each(function () {
                var $panel = $(this);
                var isActive = String($panel.data(options.panelKey)) === String(id);
                $panel.prop('hidden', !isActive);
            });

            if (updateUrl && options.urlParam && window.history && window.history.replaceState) {
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set(options.urlParam, id);
                    window.history.replaceState({}, '', url);
                } catch (e) {
                    /* URL API yoksa sessizce geç — sekme yine çalışır */
                }
            }
        }

        $buttons.on('click', function (e) {
            e.preventDefault();
            activate($(this).data(options.dataKey), true);
        });

        // Klavye ile sol/sağ ok gezinmesi
        $buttons.on('keydown', function (e) {
            if (e.which !== 37 && e.which !== 39) return;
            var idx = $buttons.index(this);
            var next = e.which === 39 ? idx + 1 : idx - 1;
            if (next < 0) next = $buttons.length - 1;
            if (next >= $buttons.length) next = 0;
            $buttons.eq(next).trigger('click').trigger('focus');
        });

        var initial = $buttons.filter('.is-active').first().data(options.dataKey);
        if (typeof initial === 'undefined') initial = $buttons.first().data(options.dataKey);
        activate(initial, false);
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
        $('.rma-toggle-status').on('change', function () {
            var isChecked = $(this).is(':checked') ? '1' : '0';
            var postId = $(this).data('id');
            var tog = $(this);
            $.post(AJAX_URL, {
                action: 'rma_toggle_status',
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
       AYARLAR — HAZIR PALETLER
    ----------------------------------------------------------------- */
    function initPalettes() {
        $('.rma-palette-btn').on('click', function () {
            var p = $(this).data('palette');
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
       BAŞLAT
    ----------------------------------------------------------------- */
    $(function () {
        initTabs({
            button: '.rma-tab',
            panel: '.rma-tabpanel',
            dataKey: 'rmaTab',
            panelKey: 'rmaPanel',
            urlParam: 'tab'
        });

        initTabs({
            button: '.rma-subtab',
            panel: '.rma-subtabpanel',
            dataKey: 'rmaSubtab',
            panelKey: 'rmaSubpanel'
        });

        stripNoticeArgs();
        initColorPickers();
        initStatusToggle();
        initCategorySorter();
        initPalettes();
        initNavPreview();
        initNavDesignPresets();
        initSuggestions();
        initCsvSample();
    });
})(jQuery);
