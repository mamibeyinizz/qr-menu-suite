/* =====================================================================
   MÜŞTERİ BİLGİLERİ FORMU — CANLI ÖNİZLEME

   Restoran sahibi etiketi değiştirdiğinde, bir alanı kapattığında ya da
   sırayı değiştirdiğinde formun müşteri tarafındaki hâlini KAYDETMEDEN
   görür.

   Önizlemenin ilk hâli PHP tarafından basılır (qrm_pro_render_form_preview);
   burası yalnızca onu tazeler. Yeni bir AJAX ucu yoktur — her şey ayar
   formundaki DOM değerlerinden okunur.

   Sıralama iki ayrı yoldan değişebiliyor (jQuery UI sortable ve yukarı/aşağı
   butonları, bkz. module.php). İkisine de ayrı ayrı bağlanmak yerine liste
   bir MutationObserver ile izlenir: hangi yolla taşınırsa taşınsın önizleme
   güncellenir, sıralama kodu ile bu dosya birbirine bağlanmaz.

   jQuery bağımlılığı yoktur.
===================================================================== */
(function () {
    'use strict';

    /** Yarım genişlik, satırdaki Sütun seçiminden okunur (frontend ile aynı). */

    function el(html) {
        var d = document.createElement('div');
        d.innerHTML = html.trim();
        return d.firstChild;
    }

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    /**
     * Tek alanın önizleme HTML'i.
     * Sınıf adları ve yapı qrm_pro_form_preview_field() ile birebir aynıdır.
     */
    function fieldHtml(key, type, label, required, columnWidth) {
        var half = columnWidth === 'half' ? ' half' : '';
        var star = required ? ' <span class="qrm-fp-req">*</span>' : '';

        if (type === 'checkbox') {
            return '<div class="qrm-fp-group qrm-fp-check">' +
                   '<label><input type="checkbox" disabled> <span>' + esc(label) + '</span></label>' +
                   '</div>';
        }

        var control = type === 'textarea'
            ? '<textarea rows="3" disabled></textarea>'
            : '<input type="text" disabled' +
              (key === 'customer_phone' ? ' placeholder="0 (5__) ___ __ __"' : '') + '>';

        return '<div class="qrm-fp-group' + half + '">' +
               '<label>' + esc(label) + star + '</label>' + control + '</div>';
    }

    function init() {
        var list    = document.getElementById('qrm-sortable-fields');
        var preview = document.getElementById('qrm-form-preview');

        if (!list || !preview) return;

        var target = preview.querySelector('.qrm-fp-fields');
        if (!target) return;

        function refresh() {
            var rows  = list.querySelectorAll('.qrm-field-row');
            var frag  = document.createDocumentFragment();
            var shown = 0;

            Array.prototype.forEach.call(rows, function (row) {
                var activeInput = row.querySelector('input[name*="[active]"]');

                // Çekirdek alan (yorum metni) gizli input taşır: her zaman aktiftir.
                var isActive = activeInput
                    ? (activeInput.type === 'checkbox' ? activeInput.checked : activeInput.value === '1')
                    : true;

                if (!isActive) return;

                var reqInput = row.querySelector('input[name*="[required]"]');
                var required = reqInput
                    ? (reqInput.type === 'checkbox' ? reqInput.checked : reqInput.value === '1')
                    : false;

                var labelInput = row.querySelector('input[name*="[label]"]');
                var label = labelInput ? labelInput.value : '';

                var widthSelect = row.querySelector('select[name*="[column_width]"]');
                var columnWidth = widthSelect ? widthSelect.value : 'full';

                frag.appendChild(el(fieldHtml(
                    row.getAttribute('data-key'),
                    row.getAttribute('data-type'),
                    label,
                    required,
                    columnWidth
                )));
                shown++;
            });

            target.textContent = '';

            if (shown === 0) {
                target.appendChild(el('<p class="qrm-fp-empty">Aktif alan yok — müşteriye yalnızca güvenlik sorusu gösterilir.</p>'));
                return;
            }

            target.appendChild(frag);
        }

        // Etiket yazımı ve zorunlu/aktif kutuları
        list.addEventListener('input', refresh);
        list.addEventListener('change', refresh);

        // Sıra değişimi — sortable da, yukarı/aşağı butonları da DOM'u taşır.
        if (window.MutationObserver) {
            new MutationObserver(refresh).observe(list, { childList: true });
        } else {
            // Çok eski tarayıcı: butonlar yine de yakalanır, sürükle-bırak kaçar.
            list.addEventListener('click', function (e) {
                if (e.target.closest && e.target.closest('.qrm-field-up, .qrm-field-down')) {
                    window.setTimeout(refresh, 0);
                }
            });
        }

        refresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
