/* =====================================================================
   ÜRÜN DETAY MODALI — Vitrin ve Slider için paylaşılan hafif modal.

   Ana menünün (`rma-frontend.js`) kart-arası kaydırmalı modalından
   BAĞIMSIZDIR: Vitrin/Slider ana menü şortkodu hiç sayfada olmadan da
   çalışabildiği için kendi minimal aç/kapat mantığını taşır. İçerik,
   ana menüyle AYNI uç noktadan (`rma_get_product_details`) gelir —
   görsel, açıklama, alerjen, rozet… hepsi tek yerden (trait-ajax.php)
   üretilir.

   Sayfada birden çok Vitrin/Slider olsa da bu dosya bir kez yüklenir
   (wp_enqueue_script tekilleştirir) ve tek paylaşımlı modal DOM'u
   ilk açılışta oluşturulur.

   Beklenen global: window.RMA_MODAL_CFG = { ajaxUrl, nonce, lang }
   (shortcode-vitrin.php / shortcode-slider.php tarafından basılır).
===================================================================== */
(function (global) {
    'use strict';

    if (global.QRMSDetailModal) return;

    var overlay, closeBtn, inner;
    var cache = {};
    var lastFocused = null;
    var currentId = null;

    function ensure() {
        if (overlay) return;

        overlay = document.createElement('div');
        overlay.id = 'qrms-detail-modal';
        overlay.className = 'qrms-detail-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Ürün detayı');
        overlay.innerHTML =
            '<div class="qrms-detail-box">' +
                '<button type="button" class="qrms-detail-close" aria-label="Kapat">&#x2715;</button>' +
                '<div class="qrms-detail-inner"></div>' +
            '</div>';
        document.body.appendChild(overlay);

        closeBtn = overlay.querySelector('.qrms-detail-close');
        inner    = overlay.querySelector('.qrms-detail-inner');

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close();
        });
        closeBtn.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) close();
        });
    }

    function lock() {
        document.documentElement.classList.add('qrms-detail-lock');
        document.body.classList.add('qrms-detail-lock');
    }

    function unlock() {
        document.documentElement.classList.remove('qrms-detail-lock');
        document.body.classList.remove('qrms-detail-lock');
    }

    function render(html) {
        if (inner) inner.innerHTML = html;
    }

    function fetchDetails(id) {
        var cfg = global.RMA_MODAL_CFG;
        if (!cfg || !cfg.ajaxUrl) {
            render('<p class="qrms-detail-error">Ürün yüklenemedi.</p>');
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onload = function () {
            // Kullanıcı yükleme bitmeden modalı kapattıysa ya da başka bir
            // ürüne geçtiyse gelen cevap artık geçersizdir — yok say.
            if (currentId !== id || !overlay || !overlay.classList.contains('open')) return;

            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }

            if (data && data.success && typeof data.data === 'string') {
                cache[id] = data.data;
                render(data.data);
            } else {
                render('<p class="qrms-detail-error">Ürün yüklenemedi.</p>');
            }
        };
        xhr.onerror = function () {
            if (currentId === id) render('<p class="qrms-detail-error">Ürün yüklenemedi.</p>');
        };

        var params = 'action=rma_get_product_details'
            + '&id=' + encodeURIComponent(id)
            + '&security=' + encodeURIComponent(cfg.nonce || '')
            + '&lang=' + encodeURIComponent(cfg.lang || 'tr');

        xhr.send(params);
    }

    function open(id) {
        id = String(id || '');
        if (!id) return;

        ensure();
        currentId = id;
        lastFocused = document.activeElement;

        render(cache[id] ? cache[id] : '<div class="qrms-detail-loading" aria-hidden="true"></div>');

        overlay.classList.add('open');
        lock();
        if (closeBtn) closeBtn.focus();

        if (!cache[id]) fetchDetails(id);
    }

    function close() {
        if (!overlay || !overlay.classList.contains('open')) return;

        overlay.classList.remove('open');
        currentId = null;
        unlock();
        render('');

        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        lastFocused = null;
    }

    global.QRMSDetailModal = { open: open, close: close };
})(window);
