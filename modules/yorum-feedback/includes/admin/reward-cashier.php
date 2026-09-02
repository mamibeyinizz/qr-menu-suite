<?php
if (!defined('ABSPATH')) exit;

// KASA GÖRÜNÜMÜ — ?view=kasa (edit_posts; ayarlar ve liste görünmez)

/**
 * Kasiyer doğrulama ekranı.
 *
 * @return void
 */
function qrm_reward_admin_cashier_view() {
    $kasa_url = qrm_pro_admin_url('qrms-yf-odul', ['view' => 'kasa']);
    ?>
    <div class="wrap qrm-pro-wrap qrm-reward-kasa-wrap">
        <h1><?php esc_html_e('Ödül Kodu Doğrulama', 'qrms'); ?></h1>
        <p class="qrm-lead"><?php esc_html_e('Müşterinin indirim kodunu girin; geçerliyse tek tıkla kullanıldı işaretleyin.', 'qrms'); ?></p>

        <div class="qrm-card qrm-reward-kasa-card">
            <p>
                <label class="screen-reader-text" for="qrm-rw-kasa-input"><?php esc_html_e('Kod', 'qrms'); ?></label>
                <input type="text" id="qrm-rw-kasa-input" class="regular-text qrm-reward-kasa-input" placeholder="QRM-XXXXXX" autocomplete="off">
                <button type="button" class="button button-primary button-large" id="qrm-rw-kasa-lookup"><?php esc_html_e('Sorgula', 'qrms'); ?></button>
            </p>
            <div id="qrm-rw-kasa-result" class="qrm-reward-kasa-result" hidden></div>
        </div>
    </div>

    <style>
        .qrm-reward-kasa-card { max-width: 520px; margin-top: 20px; }
        .qrm-reward-kasa-input { font-family: monospace; font-size: 18px; letter-spacing: 1px; text-transform: uppercase; width: min(100%, 280px); }
        .qrm-reward-kasa-result { margin-top: 16px; padding: 16px; border-radius: 8px; font-size: 15px; line-height: 1.6; }
        .qrm-reward-kasa-result.is-valid { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .qrm-reward-kasa-result.is-invalid { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
        .qrm-reward-kasa-result.is-neutral { background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; }
        .qrm-reward-kasa-mark { margin-top: 12px; }
        @media (pointer: coarse) {
            .qrm-reward-kasa-input, #qrm-rw-kasa-lookup { min-height: 44px; }
        }
    </style>

    <script>
    (function(){
        var input   = document.getElementById('qrm-rw-kasa-input');
        var btn     = document.getElementById('qrm-rw-kasa-lookup');
        var result  = document.getElementById('qrm-rw-kasa-result');
        if (!input || !btn || !result) return;

        var nonce = <?php echo wp_json_encode(wp_create_nonce('qrm_reward_cashier')); ?>;
        var ajax  = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        var lastCode = '';

        function paint(html, kind) {
            result.hidden = false;
            result.className = 'qrm-reward-kasa-result is-' + kind;
            result.innerHTML = html;
        }

        function lookup() {
            var code = (input.value || '').trim();
            if (!code) {
                paint(<?php echo wp_json_encode(esc_html__('Lütfen bir kod girin.', 'qrms')); ?>, 'invalid');
                return;
            }
            lastCode = code;
            btn.disabled = true;
            paint(<?php echo wp_json_encode(esc_html__('Kontrol ediliyor…', 'qrms')); ?>, 'neutral');

            var fd = new FormData();
            fd.append('action', 'qrm_reward_admin_lookup');
            fd.append('nonce', nonce);
            fd.append('code', code);

            fetch(ajax, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.disabled = false;
                    if (!res || !res.success) {
                        paint((res && res.message) ? res.message : <?php echo wp_json_encode(esc_html__('Kod bulunamadı.', 'qrms')); ?>, 'invalid');
                        return;
                    }
                    var lines = '<strong>' + res.code + '</strong> — ' + res.status_label +
                        '<br><?php echo esc_js(__('İndirim:', 'qrms')); ?> ' + (res.discount_label || '—') +
                        (res.expires_at ? '<br><?php echo esc_js(__('Son kullanma:', 'qrms')); ?> ' + res.expires_at : '');
                    if (res.can_mark_used) {
                        lines += '<div class="qrm-reward-kasa-mark"><button type="button" class="button button-primary button-large" id="qrm-rw-kasa-mark"><?php echo esc_js(__('Kullanıldı olarak işaretle', 'qrms')); ?></button></div>';
                    }
                    paint(lines, res.valid ? 'valid' : 'invalid');
                    var markBtn = document.getElementById('qrm-rw-kasa-mark');
                    if (markBtn) markBtn.addEventListener('click', markUsed);
                })
                .catch(function(){
                    btn.disabled = false;
                    paint(<?php echo wp_json_encode(esc_html__('Bağlantı hatası, tekrar deneyin.', 'qrms')); ?>, 'invalid');
                });
        }

        function markUsed() {
            if (!lastCode) return;
            var markBtn = document.getElementById('qrm-rw-kasa-mark');
            if (markBtn) markBtn.disabled = true;

            var fd = new FormData();
            fd.append('action', 'qrm_reward_cashier_mark_used');
            fd.append('nonce', nonce);
            fd.append('code', lastCode);

            fetch(ajax, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (!res || !res.success) {
                        paint((res && res.message) ? res.message : <?php echo wp_json_encode(esc_html__('İşlem başarısız.', 'qrms')); ?>, 'invalid');
                        return;
                    }
                    paint('<strong>' + res.code + '</strong> — ' + res.status_label +
                        '<br><?php echo esc_js(__('İndirim:', 'qrms')); ?> ' + (res.discount_label || '—') +
                        '<br><?php echo esc_js(__('Kullanım:', 'qrms')); ?> ' + res.used_at, 'valid');
                    input.value = '';
                    lastCode = '';
                    input.focus();
                })
                .catch(function(){
                    if (markBtn) markBtn.disabled = false;
                    paint(<?php echo wp_json_encode(esc_html__('Bağlantı hatası, tekrar deneyin.', 'qrms')); ?>, 'invalid');
                });
        }

        btn.addEventListener('click', lookup);
        input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); lookup(); } });
        input.focus();
    })();
    </script>
    <?php
}

/**
 * Kasiyer görünümü için ortak sorgulama + kullan JS'i (admin kod listesindeki kutu).
 *
 * @return void
 */
function qrm_reward_cashier_lookup_script() {
    ?>
    <script>
    (function(){
        function initLookup(inputId, btnId, resultId) {
            var input  = document.getElementById(inputId);
            var btn    = document.getElementById(btnId);
            var result = document.getElementById(resultId);
            if (!input || !btn || !result) return;

            var nonce = <?php echo wp_json_encode(wp_create_nonce('qrm_reward_cashier')); ?>;
            var ajax  = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var lastCode = '';

            function paint(html, ok) {
                result.style.display = '';
                result.style.background = ok ? '#dcfce7' : '#fee2e2';
                result.style.border = '1px solid ' + (ok ? '#bbf7d0' : '#fecaca');
                result.style.color = ok ? '#166534' : '#991b1b';
                result.innerHTML = html;
            }

            function lookup() {
                var code = (input.value || '').trim();
                if (!code) { paint('Lütfen bir kod girin.', false); return; }
                lastCode = code;
                btn.disabled = true;
                var fd = new FormData();
                fd.append('action', 'qrm_reward_admin_lookup');
                fd.append('nonce', nonce);
                fd.append('code', code);

                fetch(ajax, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        btn.disabled = false;
                        if (!res || !res.success) {
                            paint((res && res.message) ? res.message : 'Kod bulunamadı.', false);
                            return;
                        }
                        var lines = '<strong>' + res.code + '</strong> — ' + res.status_label +
                            '<br>E-posta: ' + res.email +
                            '<br>İndirim: ' + (res.discount_label || '—') +
                            '<br>Oluşturulma: ' + res.created_at +
                            (res.expires_at ? '<br>Son kullanma: ' + res.expires_at : '') +
                            (res.used_at ? '<br>Kullanım: ' + res.used_at : '');
                        if (res.can_mark_used) {
                            lines += '<p style="margin:12px 0 0;"><button type="button" class="button button-primary qrm-rw-mark-used-btn">Kullanıldı olarak işaretle</button></p>';
                        }
                        paint(lines, res.valid);
                        var markBtn = result.querySelector('.qrm-rw-mark-used-btn');
                        if (markBtn) markBtn.addEventListener('click', markUsed);
                    })
                    .catch(function(){
                        btn.disabled = false;
                        paint('Bağlantı hatası, tekrar deneyin.', false);
                    });
            }

            function markUsed() {
                if (!lastCode) return;
                var fd = new FormData();
                fd.append('action', 'qrm_reward_cashier_mark_used');
                fd.append('nonce', nonce);
                fd.append('code', lastCode);

                fetch(ajax, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if (!res || !res.success) {
                            paint((res && res.message) ? res.message : 'İşlem başarısız.', false);
                            return;
                        }
                        paint('<strong>' + res.code + '</strong> — ' + res.status_label +
                            '<br>İndirim: ' + (res.discount_label || '—') +
                            '<br>Kullanım: ' + res.used_at, true);
                        input.value = '';
                        lastCode = '';
                    })
                    .catch(function(){ paint('Bağlantı hatası, tekrar deneyin.', false); });
            }

            btn.addEventListener('click', lookup);
            input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); lookup(); } });
        }

        initLookup('qrm-rw-lookup-input', 'qrm-rw-lookup-btn', 'qrm-rw-lookup-result');
    })();
    </script>
    <?php
}
