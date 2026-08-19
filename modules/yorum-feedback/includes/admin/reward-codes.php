<?php
if (!defined('ABSPATH')) exit;

// ÖDÜL MODÜLÜ: KOD YÖNETİMİ SEKMESİ (liste + durum değiştirme + manuel üretim + kod sorgulama)

/**
 * Kod listesi aksiyonları. Mevcut admin sayfalarındaki senkron POST desenini izler
 * (nonce doğrulaması qrm_reward_admin_page() içinde yapılır).
 */
function qrm_reward_admin_handle_code_actions() {
    // Tekil satır aksiyonları
    if (isset($_POST['qrm_reward_code_action'])) {
        $action = sanitize_key($_POST['qrm_reward_code_action']);
        $id     = isset($_POST['code_id']) ? intval($_POST['code_id']) : 0;

        if ($id > 0) {
            if ($action === 'mark_used' && qrm_reward_set_status($id, 'used')) {
                echo '<div class="notice notice-success is-dismissible"><p>Kod "kullanıldı" olarak işaretlendi.</p></div>';
            } elseif ($action === 'revoke' && qrm_reward_set_status($id, 'revoked')) {
                echo '<div class="notice notice-success is-dismissible"><p>Kod iptal edildi.</p></div>';
            } elseif ($action === 'reactivate' && qrm_reward_set_status($id, 'active')) {
                echo '<div class="notice notice-success is-dismissible"><p>Kod yeniden geçerli hale getirildi.</p></div>';
            } elseif ($action === 'delete' && qrm_reward_delete($id)) {
                echo '<div class="notice notice-success is-dismissible"><p>Kod silindi.</p></div>';
            }
        }
    }

    // Manuel kod üretimi
    if (isset($_POST['qrm_reward_manual_create'])) {
        $email       = isset($_POST['manual_email']) ? wp_unslash($_POST['manual_email']) : '';
        $template_id = isset($_POST['manual_template']) ? sanitize_key($_POST['manual_template']) : '';

        if (trim($email) !== '' && qrm_reward_normalize_email($email) === '') {
            echo '<div class="notice notice-error is-dismissible"><p>Girilen e-posta adresi geçerli değil.</p></div>';
            return;
        }

        $result = qrm_reward_create_code([
            'email'       => trim($email),
            'template_id' => $template_id,
            'is_manual'   => 1,
            'ip_address'  => qrm_pro_client_ip(),
        ]);

        if (is_wp_error($result)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            return;
        }

        $mail_note = '';
        if ($result['email'] !== '') {
            $sent = qrm_reward_send_code_email($result['email'], $result['code'], $result['discount_label']);
            $mail_note = $sent ? ' Kod e-posta ile gönderildi.' : ' (E-posta gönderilemedi, kodu elle iletmeniz gerekebilir.)';
        }

        echo '<div class="notice notice-success is-dismissible"><p>Kod oluşturuldu: <strong>' . esc_html($result['code']) . '</strong> — ' . esc_html($result['discount_label']) . '.' . esc_html($mail_note) . '</p></div>';
    }
}

function qrm_reward_admin_codes_tab($settings) {
    global $wpdb;
    $table = qrm_reward_table();

    $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
    $search        = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $paged         = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page      = 25;
    $offset        = ($paged - 1) * $per_page;

    $where  = 'WHERE 1=1';
    $params = [];

    if (in_array($status_filter, ['active', 'used', 'expired', 'revoked'], true)) {
        $where .= ' AND status = %s';
        $params[] = $status_filter;
    }
    if ($search !== '') {
        $where .= ' AND (email LIKE %s OR code LIKE %s)';
        $like = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $count_sql = "SELECT COUNT(*) FROM $table $where";
    $total = $params ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

    $list_sql    = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $list_params = array_merge($params, [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_params));

    $stats = $wpdb->get_results("SELECT status, COUNT(*) AS c FROM $table GROUP BY status");
    $counts = ['active' => 0, 'used' => 0, 'revoked' => 0, 'expired' => 0];
    foreach ($stats as $s) {
        if (isset($counts[$s->status])) $counts[$s->status] = (int) $s->c;
    }

    $templates = qrm_reward_get_templates();
    $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;
    ?>
    <div class="qrm-insight-grid" style="margin-top:20px;">
        <div class="qrm-stat-box" style="border-left-color:#10b981;"><h3>Geçerli Kod</h3><div class="value"><?php echo (int) $counts['active']; ?></div></div>
        <div class="qrm-stat-box" style="border-left-color:#6b7280;"><h3>Kullanılmış</h3><div class="value"><?php echo (int) $counts['used']; ?></div></div>
        <div class="qrm-stat-box" style="border-left-color:#ef4444;"><h3>İptal Edilmiş</h3><div class="value"><?php echo (int) $counts['revoked']; ?></div></div>
        <div class="qrm-stat-box" style="border-left-color:#3b82f6;"><h3>Toplam</h3><div class="value"><?php echo (int) array_sum($counts); ?></div></div>
    </div>

    <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
        <div class="qrm-card" style="flex:1 1 380px;">
            <h3>🔍 Kod Sorgula</h3>
            <p class="description">Kasada hızlı kontrol için: kodu yazın, geçerli mi kullanılmış mı anında görün.</p>
            <p>
                <input type="text" id="qrm-rw-lookup-input" class="regular-text" placeholder="QRM-XXXXXX" style="text-transform:uppercase;">
                <button type="button" class="button button-primary" id="qrm-rw-lookup-btn">Sorgula</button>
            </p>
            <div id="qrm-rw-lookup-result" style="display:none; padding:14px; border-radius:8px; font-size:14px;"></div>
        </div>

        <div class="qrm-card" style="flex:1 1 380px;">
            <h3>➕ Manuel Kod Oluştur</h3>
            <form method="POST">
                <?php wp_nonce_field('qrm_reward_codes'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>E-posta (opsiyonel)</label></th>
                        <td>
                            <input type="email" name="manual_email" class="regular-text" placeholder="bos birakilabilir">
                            <p class="description">Boş bırakılırsa e-postasız kampanya kodu üretilir; doldurulursa kod aynı zamanda o adrese gönderilir ve "1 e-posta = 1 kod" kuralına dahil olur.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>İndirim Şablonu</label></th>
                        <td>
                            <select name="manual_template">
                                <option value="">Varsayılan (otomatik şablon)</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?php echo esc_attr($t['id']); ?>"><?php echo esc_html(qrm_reward_template_label($t)); ?><?php echo $t['active'] ? '' : ' — pasif'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="qrm_reward_manual_create" class="button button-primary" value="Kod Oluştur"></p>
            </form>
        </div>
    </div>

    <div class="qrm-card">
        <h3>Ödül Kodları</h3>
        <form method="GET" style="margin-bottom:14px;">
            <input type="hidden" name="page" value="qrm-pro-rewards">
            <input type="hidden" name="tab" value="codes">
            <select name="status">
                <option value="">Tüm durumlar</option>
                <?php foreach (['active', 'used', 'revoked', 'expired'] as $st): ?>
                    <option value="<?php echo esc_attr($st); ?>" <?php selected($status_filter, $st); ?>><?php echo esc_html(qrm_reward_status_label($st)); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="E-posta veya kod ara">
            <button type="submit" class="button">Filtrele</button>
            <?php if ($status_filter !== '' || $search !== ''): ?>
                <a href="?page=qrm-pro-rewards&tab=codes" class="button">Temizle</a>
            <?php endif; ?>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:130px;">Oluşturulma</th>
                    <th>E-posta</th>
                    <th style="width:140px;">Kod</th>
                    <th>İndirim</th>
                    <th style="width:110px;">Durum</th>
                    <th style="width:130px;">Kullanım</th>
                    <th style="width:250px;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7">Kayıt bulunamadı.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo esc_html(date('d.m.Y H:i', strtotime($r->created_at))); ?></td>
                    <td>
                        <?php echo $r->email ? esc_html($r->email) : '<em>—</em>'; ?>
                        <?php if ($r->is_manual): ?><span class="qrm-google-pill" style="background:#fef3c7;color:#92400e;">Manuel</span><?php endif; ?>
                    </td>
                    <td><span class="qrm-code-pill"><?php echo esc_html($r->code); ?></span></td>
                    <td><?php echo esc_html($r->discount_label); ?></td>
                    <td><span class="qrm-status-<?php echo esc_attr($r->status); ?>"><?php echo esc_html(qrm_reward_status_label($r->status)); ?></span></td>
                    <td><?php echo $r->used_at ? esc_html(date('d.m.Y H:i', strtotime($r->used_at))) : '—'; ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <?php wp_nonce_field('qrm_reward_codes'); ?>
                            <input type="hidden" name="code_id" value="<?php echo (int) $r->id; ?>">
                            <?php if ($r->status !== 'used'): ?>
                                <button type="submit" name="qrm_reward_code_action" value="mark_used" class="button button-small">Kullanıldı</button>
                            <?php endif; ?>
                            <?php if ($r->status !== 'revoked'): ?>
                                <button type="submit" name="qrm_reward_code_action" value="revoke" class="button button-small">İptal</button>
                            <?php else: ?>
                                <button type="submit" name="qrm_reward_code_action" value="reactivate" class="button button-small">Geçerli Yap</button>
                            <?php endif; ?>
                            <button type="submit" name="qrm_reward_code_action" value="delete" class="button button-small" style="color:#b91c1c;border-color:#b91c1c;" onclick="return confirm('Bu kod silinsin mi?');">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">
            <?php
            echo paginate_links([
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => $total_pages,
                'current'   => $paged,
            ]);
            ?>
        </div></div>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        var btn = document.getElementById('qrm-rw-lookup-btn');
        if (!btn) return;
        var input  = document.getElementById('qrm-rw-lookup-input');
        var result = document.getElementById('qrm-rw-lookup-result');
        var nonce  = <?php echo wp_json_encode(wp_create_nonce('qrm_reward_admin')); ?>;
        var ajax   = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

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
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'qrm_reward_admin_lookup');
            fd.append('nonce', nonce);
            fd.append('code', code);

            fetch(ajax, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.disabled = false;
                    if (!res || !res.success) { paint((res && res.message) ? res.message : 'Kod bulunamadı.', false); return; }
                    var lines = '<strong>' + res.code + '</strong> — ' + res.status_label +
                        '<br>E-posta: ' + res.email +
                        '<br>İndirim: ' + (res.discount_label || '—') +
                        '<br>Oluşturulma: ' + res.created_at +
                        (res.used_at ? '<br>Kullanım: ' + res.used_at : '');
                    paint(lines, res.valid);
                })
                .catch(function(){
                    btn.disabled = false;
                    paint('Bağlantı hatası, tekrar deneyin.', false);
                });
        }

        btn.addEventListener('click', lookup);
        input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); lookup(); } });
    })();
    </script>
    <?php
}
