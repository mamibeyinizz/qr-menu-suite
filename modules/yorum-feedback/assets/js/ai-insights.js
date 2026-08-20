/* =====================================================================
   YAPAY ZEKÂ ÖZETİ — "Detaylı İçgörüler" ekranı

   Özet, sayfa açılışında değil butonla üretilir: her açılışta ücretli bir
   Gemini isteği atmamak için (bkz. includes/ai-insights.php). Sonuç sunucu
   tarafında saklanır, burada yalnızca gösterilir.

   jQuery bağımlılığı yoktur.
===================================================================== */
(function () {
    'use strict';

    function init() {
        var btn = document.getElementById('qrm-ai-run');
        if (!btn) return;

        var out    = document.getElementById('qrm-ai-output');
        var status = document.getElementById('qrm-ai-status');
        var cfg    = window.QRM_AI || {};
        var url    = cfg.ajaxUrl || window.ajaxurl;

        if (!url) return;

        btn.addEventListener('click', function () {
            if (btn.disabled) return;

            btn.disabled = true;
            status.textContent = 'Yorumlarınız özetleniyor…';

            var body = new URLSearchParams({
                action: 'qrm_ai_summary',
                _wpnonce: btn.getAttribute('data-nonce') || ''
            });

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        // Yanıt metin olarak basılır: Gemini'den gelen içerik
                        // hiçbir koşulda HTML olarak yorumlanmamalı.
                        out.textContent = res.data;
                        out.hidden = false;
                        status.textContent = '';
                        btn.textContent = 'Özeti yenile';
                    } else {
                        status.textContent = (res && res.data) ? String(res.data) : 'Özet oluşturulamadı.';
                    }
                })
                .catch(function () {
                    status.textContent = 'Sunucuya ulaşılamadı, lütfen tekrar deneyin.';
                })
                .then(function () {
                    btn.disabled = false;
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
