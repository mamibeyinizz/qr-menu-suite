<?php
if (!defined('ABSPATH')) exit;

// GOOGLE & ÖDÜL SİSTEMİ ADMİN SAYFASI
//
// v4.2.0: Google yönlendirme ayarları da bu sayfaya taşındı ve tüm ayar sekmeleri
// TEK BİR FORM + TEK KAYDET işlemiyle gönderiliyor. Böylece "bir sayfada kaydetmek
// diğer sayfanın ayarını eziyor" hata sınıfı yapısal olarak imkânsız hâle geliyor.

function qrm_reward_admin_page() {
    if (!current_user_can('manage_options')) return;

    $view = (isset($_GET['tab']) && sanitize_key($_GET['tab']) === 'codes') ? 'codes' : 'settings';
    $notices = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qrm_reward_save_all'])) {
        check_admin_referer('qrm_reward_save_all');
        $notices = qrm_reward_admin_save_all();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['qrm_reward_code_action']) || isset($_POST['qrm_reward_manual_create']))) {
        check_admin_referer('qrm_reward_codes');
        qrm_reward_admin_handle_code_actions();
    }

    $settings = qrm_pro_get_settings();
    $sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : 'kurulum';
    if (!in_array($sub, ['kurulum', 'popup', 'sablonlar'], true)) $sub = 'kurulum';
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1>Google &amp; Ödül Sistemi</h1>

        <?php foreach ($notices as $n): ?>
            <div class="notice notice-<?php echo esc_attr($n['type']); ?> is-dismissible"><p><?php echo wp_kses_post($n['text']); ?></p></div>
        <?php endforeach; ?>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-odul')); ?>" class="nav-tab <?php echo $view === 'settings' ? 'nav-tab-active' : ''; ?>">Ayarlar</a>
            <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-odul', ['tab' => 'codes'])); ?>" class="nav-tab <?php echo $view === 'codes' ? 'nav-tab-active' : ''; ?>">Ödül Kodları</a>
        </h2>

        <?php
        if ($view === 'codes') {
            qrm_reward_admin_codes_tab($settings);
        } else {
            qrm_reward_admin_setup_banner($settings);
            qrm_reward_admin_settings_view($settings, $sub);
        }
        ?>
    </div>
    <?php
}

// --- TEK KAYDET: tüm ayar sekmeleri + şablonlar aynı POST'ta ---

function qrm_reward_admin_save_all() {
    $settings = qrm_pro_get_settings();
    $notices = [];

    // 1) Google yönlendirme ayarları
    if (array_key_exists('google_review_url', $_POST)) {
        $normalized = qrm_reward_normalize_google_url(wp_unslash($_POST['google_review_url']));
        $settings['google_review_url'] = $normalized['url'];

        if ($normalized['status'] === 'placeid') {
            $notices[] = ['type' => 'info', 'text' => 'Girdiğiniz Place ID tam değerlendirme linkine çevrildi: <code>' . esc_html($normalized['url']) . '</code>'];
        } elseif ($normalized['status'] === 'maps') {
            $notices[] = ['type' => 'warning', 'text' => 'Bu bir Google Haritalar bağlantısına benziyor. Çalışabilir, ancak değerlendirme penceresini doğrudan açmayabilir. En güvenlisi Place ID Finder ile bulduğunuz Place ID\'yi yapıştırmaktır.'];
        } elseif ($normalized['status'] === 'unknown') {
            $notices[] = ['type' => 'warning', 'text' => 'Bu geçerli bir Google değerlendirme linkine benzemiyor, kontrol eder misiniz? Kaydedildi, ancak müşterileriniz yanlış bir sayfaya yönlendirilebilir.'];
        }
    }
    $settings['google_review_enabled'] = isset($_POST['google_review_enabled']) ? 1 : 0;

    if (array_key_exists('google_review_threshold', $_POST)) {
        $threshold = floatval(str_replace(',', '.', $_POST['google_review_threshold']));
        $settings['google_review_threshold'] = min(5, max(1, $threshold));
    }

    foreach (['google_review_headline', 'google_review_btn_text', 'google_review_skip_text'] as $key) {
        if (array_key_exists($key, $_POST)) $settings[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
    }
    if (array_key_exists('google_review_subtext', $_POST)) {
        $settings['google_review_subtext'] = sanitize_textarea_field(wp_unslash($_POST['google_review_subtext']));
    }

    // 2) Ödül modülü + popup ayarları
    $settings['qrm_reward_enabled'] = isset($_POST['qrm_reward_enabled']) ? 1 : 0;

    $text_fields = [
        'qrm_reward_popup_title', 'qrm_reward_popup_button_text', 'qrm_reward_popup_claim_text',
        'qrm_reward_popup_waiting_text', 'qrm_reward_popup_skip_text', 'qrm_reward_popup_email_step_title',
        'qrm_reward_popup_email_placeholder', 'qrm_reward_popup_email_button_text',
        'qrm_reward_popup_success_text', 'qrm_reward_popup_copy_text', 'qrm_reward_popup_copied_text',
        'qrm_reward_email_subject',
    ];
    foreach ($text_fields as $key) {
        if (array_key_exists($key, $_POST)) $settings[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
    }

    $textarea_fields = [
        'qrm_reward_popup_text', 'qrm_reward_popup_email_step_text',
        'qrm_reward_popup_already_used_text', 'qrm_reward_popup_error_text', 'qrm_reward_email_intro',
    ];
    foreach ($textarea_fields as $key) {
        if (array_key_exists($key, $_POST)) $settings[$key] = sanitize_textarea_field(wp_unslash($_POST[$key]));
    }

    if (array_key_exists('qrm_reward_popup_theme', $_POST)) {
        $theme = sanitize_text_field($_POST['qrm_reward_popup_theme']);
        $settings['qrm_reward_popup_theme'] = in_array($theme, ['light', 'dark', 'custom'], true) ? $theme : 'light';
    }
    foreach (['qrm_reward_popup_bg_color', 'qrm_reward_popup_text_color', 'qrm_reward_popup_btn_color', 'qrm_reward_popup_btn_text_color'] as $key) {
        if (array_key_exists($key, $_POST)) {
            $settings[$key] = sanitize_hex_color($_POST[$key]) ?: $settings[$key];
        }
    }
    if (array_key_exists('qrm_reward_popup_border_radius', $_POST)) {
        $settings['qrm_reward_popup_border_radius'] = max(0, min(40, intval($_POST['qrm_reward_popup_border_radius'])));
    }
    if (array_key_exists('qrm_reward_wait_seconds', $_POST)) {
        $settings['qrm_reward_wait_seconds'] = max(5, min(120, intval($_POST['qrm_reward_wait_seconds'])));
    }
    if (array_key_exists('qrm_reward_auto_trigger_seconds', $_POST)) {
        $settings['qrm_reward_auto_trigger_seconds'] = max(15, min(30, intval($_POST['qrm_reward_auto_trigger_seconds'])));
    }

    update_option('qrm_settings', $settings);

    // 3) İndirim şablonları (aynı POST içinde)
    if (isset($_POST['tpl']) && is_array($_POST['tpl'])) {
        qrm_reward_admin_save_templates_post();
    }

    array_unshift($notices, ['type' => 'success', 'text' => 'Tüm ayarlar kaydedildi.']);
    return $notices;
}

function qrm_reward_admin_save_templates_post() {
    $rows = isset($_POST['tpl']) && is_array($_POST['tpl']) ? wp_unslash($_POST['tpl']) : [];
    $default_id = isset($_POST['tpl_default']) ? sanitize_key($_POST['tpl_default']) : '';

    $prepared = [];
    foreach ($rows as $row) {
        if (!empty($row['delete'])) continue;
        if (!isset($row['name']) || trim($row['name']) === '') continue;
        $id = !empty($row['id']) ? sanitize_key($row['id']) : qrm_reward_new_template_id();
        $prepared[] = [
            'id'         => $id,
            'name'       => $row['name'],
            'percent'    => isset($row['percent']) ? $row['percent'] : 0,
            'active'     => !empty($row['active']) ? 1 : 0,
            'is_default' => ($default_id !== '' && $default_id === $id) ? 1 : 0,
        ];
    }

    // Hiçbiri varsayılan işaretlenmemişse ilk aktif şablon varsayılan olur.
    $has_default = false;
    foreach ($prepared as $p) { if ($p['is_default']) { $has_default = true; break; } }
    if (!$has_default) {
        foreach ($prepared as $i => $p) {
            if ($p['active']) { $prepared[$i]['is_default'] = 1; break; }
        }
    }

    qrm_reward_save_templates($prepared);
}

// --- KURULUM SİHİRBAZI (checklist) ---

function qrm_reward_admin_setup_banner($settings) {
    $status = qrm_reward_setup_status($settings);
    ?>
    <div class="qrm-card" style="margin-top:16px; border-left:4px solid <?php echo $status['all_ok'] ? '#10b981' : '#f59e0b'; ?>;">
        <h3 style="margin-top:0;">🚀 Hızlı Kurulum</h3>

        <?php if ($status['all_ok']): ?>
            <div style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:16px 18px; border-radius:8px; font-size:15px; font-weight:600; margin-bottom:14px;">
                ✅ Popup çalışıyor — eşiği geçen müşterilere ödül popup'ı gösteriliyor.
            </div>
        <?php else: $first = $status['missing'][0]; ?>
            <div style="background:#fef3c7; border:1px solid #fcd34d; color:#92400e; padding:16px 18px; border-radius:8px; font-size:15px; margin-bottom:14px;">
                <strong>Popup henüz çalışmıyor.</strong> Eksik adım: <?php echo esc_html($first['label']); ?> — <?php echo esc_html($first['hint']); ?>
                <a href="#" class="button button-primary qrm-rw-goto" data-sub="<?php echo esc_attr($first['sub']); ?>" style="margin-left:10px;">Bu ayara git</a>
            </div>
        <?php endif; ?>

        <ul style="margin:0; font-size:14px; line-height:2;">
            <?php foreach ($status['steps'] as $step): ?>
                <li style="list-style:none;">
                    <span style="font-size:16px;"><?php echo $step['ok'] ? '✅' : '❌'; ?></span>
                    <span style="<?php echo $step['ok'] ? '' : 'font-weight:600;'; ?>"><?php echo esc_html($step['label']); ?></span>
                    <?php if (!$step['ok']): ?>
                        <span style="opacity:.75;">— <?php echo esc_html($step['hint']); ?></span>
                        <a href="#" class="qrm-rw-goto" data-sub="<?php echo esc_attr($step['sub']); ?>">düzelt</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <p style="margin-bottom:0;">
            <button type="button" class="button" id="qrm-rw-selftest">🔍 Test Et</button>
            <button type="button" class="button" id="qrm-rw-preview-btn">👁 Popup'ı Önizle</button>
            <span id="qrm-rw-selftest-result" style="margin-left:10px; font-size:13px;"></span>
        </p>
        <p class="description" style="margin-bottom:0;">"Test Et", 5 yıldızlık bir gönderimi <strong>kayıt oluşturmadan</strong> simüle eder ve popup'ın açılıp açılmayacağını söyler.</p>
    </div>
    <?php
}

// --- AYARLAR GÖRÜNÜMÜ: tek form, JS sekmeleri ---

function qrm_reward_admin_settings_view($settings, $sub = 'kurulum') {
    $templates = qrm_reward_get_templates();
    $default_template = qrm_reward_get_default_template();
    ?>
    <h2 class="nav-tab-wrapper" style="margin-top:18px;">
        <a href="#" class="nav-tab qrm-rw-subtab" data-sub="kurulum">1. Kurulum</a>
        <a href="#" class="nav-tab qrm-rw-subtab" data-sub="popup">2. Popup Görünümü</a>
        <a href="#" class="nav-tab qrm-rw-subtab" data-sub="sablonlar">3. İndirim Şablonları</a>
    </h2>

    <?php
    if (function_exists('rma_ceviri_option_alan_dil_sayisi')) {
        echo rma_ceviri_bayat_uyari_html(rma_ceviri_bayat_uyari_ekran_metni(rma_ceviri_option_alan_dil_sayisi(array(
            'qrm_settings.google_review_headline',
            'qrm_settings.google_review_subtext',
            'qrm_settings.google_review_btn_text',
            'qrm_settings.google_review_skip_text',
            'qrm_settings.qrm_reward_popup_title',
            'qrm_settings.qrm_reward_popup_text',
            'qrm_settings.qrm_reward_popup_button_text',
            'qrm_settings.qrm_reward_popup_claim_text',
            'qrm_settings.qrm_reward_popup_waiting_text',
            'qrm_settings.qrm_reward_popup_skip_text',
            'qrm_settings.qrm_reward_popup_email_step_title',
            'qrm_settings.qrm_reward_popup_email_step_text',
            'qrm_settings.qrm_reward_popup_email_placeholder',
            'qrm_settings.qrm_reward_popup_email_button_text',
            'qrm_settings.qrm_reward_popup_success_text',
            'qrm_settings.qrm_reward_popup_already_used_text',
            'qrm_settings.qrm_reward_popup_error_text',
            'qrm_settings.qrm_reward_popup_copy_text',
            'qrm_settings.qrm_reward_popup_copied_text',
            'qrm_settings.qrm_reward_email_subject',
            'qrm_settings.qrm_reward_email_intro',
        ))));
    }
    ?>
    <form method="POST" id="qrm-reward-settings-form">
        <?php wp_nonce_field('qrm_reward_save_all'); ?>
        <input type="hidden" name="qrm_reward_save_all" value="1">

        <!-- ================= SEKME 1: KURULUM ================= -->
        <div class="qrm-rw-pane" data-pane="kurulum">
            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                <div style="flex:1 1 560px; min-width:420px;">
                    <div class="qrm-card">
                        <h3>1️⃣ Google Değerlendirme Linki <span style="color:#b91c1c;">(zorunlu)</span></h3>
                        <p class="description">İşletmenizin Google değerlendirme sayfası. Aşağıdakilerden <strong>herhangi birini</strong> yapıştırabilirsiniz, biz doğru biçime çeviririz:</p>
                        <ul style="font-size:13px; line-height:1.8; margin-left:18px; list-style:disc;">
                            <li>Place ID (örn. <code>ChIJN1t_tDeuEmsRUsoyG83frY4</code>) — <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">Place ID Finder</a></li>
                            <li>Hazır değerlendirme linki (<code>search.google.com/local/writereview?placeid=...</code>)</li>
                            <li>Google Haritalar / <code>g.page</code> bağlantısı</li>
                        </ul>
                        <table class="form-table">
                            <tr>
                                <th><label>İşletme Linki veya Place ID</label></th>
                                <td>
                                    <input type="text" name="google_review_url" class="large-text" style="font-family:monospace;"
                                           placeholder="ChIJ... veya https://search.google.com/local/writereview?placeid=..."
                                           value="<?php echo esc_attr($settings['google_review_url']); ?>">
                                    <?php if (!empty($settings['google_review_url'])): ?>
                                        <p class="description">Kayıtlı link: <a href="<?php echo esc_url($settings['google_review_url']); ?>" target="_blank" rel="noopener">test etmek için tıklayın ↗</a></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="qrm-card">
                        <h3>2️⃣ Açma / Kapama</h3>
                        <table class="form-table">
                            <tr>
                                <th><label>Google Yönlendirme</label></th>
                                <td><label><input type="checkbox" name="google_review_enabled" value="1" <?php checked($settings['google_review_enabled'], 1); ?>> Google yönlendirmesini etkinleştir</label>
                                    <p class="description">Kapalıyken ne satır içi CTA ne de ödül popup'ı gösterilir.</p></td>
                            </tr>
                            <tr>
                                <th><label>Ödül Popup Modülü</label></th>
                                <td><label><input type="checkbox" name="qrm_reward_enabled" value="1" <?php checked($settings['qrm_reward_enabled'], 1); ?>> Ödül sistemini etkinleştir (indirim kodu popup'ı)</label>
                                    <p class="description">Açıkken eşiği geçen müşteriye popup gösterilir; kapalıyken eski satır içi Google CTA'sı çalışır.</p></td>
                            </tr>
                            <tr>
                                <th><label>Yönlendirme Eşiği</label></th>
                                <td>
                                    <select name="google_review_threshold">
                                        <?php foreach ([3, 3.5, 4, 4.5, 5] as $opt): ?>
                                            <option value="<?php echo $opt; ?>" <?php selected((float)$settings['google_review_threshold'], (float)$opt); ?>><?php echo number_format($opt, 1); ?> ve üzeri</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Bu puanın altındaki değerlendirmeler Google'a yönlendirilmez, yalnızca size ulaşır.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="qrm-card">
                        <h3>Satır İçi CTA Metinleri <span style="font-weight:400; font-size:13px; opacity:.7;">(ödül popup'ı kapalıyken kullanılır)</span></h3>
                        <table class="form-table">
                            <tr><th><label>Ekran Başlığı</label></th>
                                <td><input type="text" name="google_review_headline" class="regular-text" value="<?php echo esc_attr($settings['google_review_headline']); ?>"></td></tr>
                            <tr><th><label>Ekran Açıklaması</label></th>
                                <td><textarea name="google_review_subtext" class="large-text" rows="2"><?php echo esc_textarea($settings['google_review_subtext']); ?></textarea></td></tr>
                            <tr><th><label>Buton Metni</label></th>
                                <td><input type="text" name="google_review_btn_text" class="regular-text" value="<?php echo esc_attr($settings['google_review_btn_text']); ?>"></td></tr>
                            <tr><th><label>Geç Butonu Metni</label></th>
                                <td><input type="text" name="google_review_skip_text" class="regular-text" value="<?php echo esc_attr($settings['google_review_skip_text']); ?>"></td></tr>
                        </table>
                    </div>
                </div>
                <?php qrm_reward_admin_preview_box($default_template); ?>
            </div>
        </div>

        <!-- ================= SEKME 2: POPUP ================= -->
        <div class="qrm-rw-pane" data-pane="popup" style="display:none;">
            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                <div style="flex:1 1 560px; min-width:420px;">
                    <div class="qrm-card">
                        <h3>⏱ Süreler</h3>
                        <table class="form-table">
                            <tr>
                                <th><label>Bekleme Süresi</label></th>
                                <td><input type="number" name="qrm_reward_wait_seconds" min="5" max="120" value="<?php echo esc_attr($settings['qrm_reward_wait_seconds']); ?>" class="small-text"> saniye
                                    <p class="description">Müşteri "Google'da Değerlendir"e bastıktan sonra buton bu süre boyunca dolum göstergesiyle kilitli kalır (önerilen: 30).</p></td>
                            </tr>
                            <tr>
                                <th><label>Otomatik Geçiş</label></th>
                                <td><input type="number" name="qrm_reward_auto_trigger_seconds" min="15" max="30" value="<?php echo esc_attr($settings['qrm_reward_auto_trigger_seconds']); ?>" class="small-text"> saniye
                                    <p class="description">Müşteri hiçbir şey yapmazsa popup bu süre sonunda kendiliğinden e-posta adımına geçer (15-30 sn).</p></td>
                            </tr>
                        </table>
                    </div>

                    <div class="qrm-card">
                        <h3>📝 Popup Metinleri</h3>
                        <table class="form-table">
                            <tr><th><label>Başlık</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_title" value="<?php echo esc_attr($settings['qrm_reward_popup_title']); ?>"></td></tr>
                            <tr><th><label>Açıklama</label></th>
                                <td><textarea class="large-text" name="qrm_reward_popup_text" rows="2"><?php echo esc_textarea($settings['qrm_reward_popup_text']); ?></textarea></td></tr>
                            <tr><th><label>Buton Metni</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_button_text" value="<?php echo esc_attr($settings['qrm_reward_popup_button_text']); ?>"></td></tr>
                            <tr><th><label>Bekleme Metni</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_waiting_text" value="<?php echo esc_attr($settings['qrm_reward_popup_waiting_text']); ?>">
                                    <p class="description">Geri sayım sırasında butonda görünür, sonuna kalan saniye eklenir.</p></td></tr>
                            <tr><th><label>Kod Alma Buton Metni</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_claim_text" value="<?php echo esc_attr($settings['qrm_reward_popup_claim_text']); ?>"></td></tr>
                            <tr><th><label>Vazgeç Linki</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_skip_text" value="<?php echo esc_attr($settings['qrm_reward_popup_skip_text']); ?>"></td></tr>
                        </table>

                        <h4 style="margin-bottom:0;">E-posta Adımı</h4>
                        <table class="form-table">
                            <tr><th><label>Başlık</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_email_step_title" value="<?php echo esc_attr($settings['qrm_reward_popup_email_step_title']); ?>"></td></tr>
                            <tr><th><label>Açıklama</label></th>
                                <td><textarea class="large-text" name="qrm_reward_popup_email_step_text" rows="2"><?php echo esc_textarea($settings['qrm_reward_popup_email_step_text']); ?></textarea></td></tr>
                            <tr><th><label>Input Placeholder</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_email_placeholder" value="<?php echo esc_attr($settings['qrm_reward_popup_email_placeholder']); ?>"></td></tr>
                            <tr><th><label>Gönder Buton Metni</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_email_button_text" value="<?php echo esc_attr($settings['qrm_reward_popup_email_button_text']); ?>"></td></tr>
                        </table>

                        <h4 style="margin-bottom:0;">Sonuç Ekranları</h4>
                        <table class="form-table">
                            <tr><th><label>Kod Gösterim Mesajı</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_popup_success_text" value="<?php echo esc_attr($settings['qrm_reward_popup_success_text']); ?>"></td></tr>
                            <tr><th><label>"Zaten Kod Alınmış" Mesajı</label></th>
                                <td><textarea class="large-text" name="qrm_reward_popup_already_used_text" rows="2"><?php echo esc_textarea($settings['qrm_reward_popup_already_used_text']); ?></textarea></td></tr>
                            <tr><th><label>Hata Mesajı</label></th>
                                <td><textarea class="large-text" name="qrm_reward_popup_error_text" rows="2"><?php echo esc_textarea($settings['qrm_reward_popup_error_text']); ?></textarea></td></tr>
                            <tr><th><label>Kopyala / Kopyalandı</label></th>
                                <td>
                                    <input type="text" class="regular-text" style="width:150px;" name="qrm_reward_popup_copy_text" value="<?php echo esc_attr($settings['qrm_reward_popup_copy_text']); ?>">
                                    <input type="text" class="regular-text" style="width:150px;" name="qrm_reward_popup_copied_text" value="<?php echo esc_attr($settings['qrm_reward_popup_copied_text']); ?>">
                                </td></tr>
                        </table>
                    </div>

                    <div class="qrm-card">
                        <h3>🎨 Görünüm</h3>
                        <table class="form-table">
                            <tr>
                                <th><label>Tema</label></th>
                                <td>
                                    <select name="qrm_reward_popup_theme" id="qrm-rw-theme">
                                        <option value="light" <?php selected($settings['qrm_reward_popup_theme'], 'light'); ?>>Aydınlık</option>
                                        <option value="dark" <?php selected($settings['qrm_reward_popup_theme'], 'dark'); ?>>Karanlık</option>
                                        <option value="custom" <?php selected($settings['qrm_reward_popup_theme'], 'custom'); ?>>Özel (aşağıdaki renkler)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr><th><label>Arkaplan Rengi</label></th>
                                <td><input type="text" class="qrm-color-picker" name="qrm_reward_popup_bg_color" value="<?php echo esc_attr($settings['qrm_reward_popup_bg_color']); ?>"></td></tr>
                            <tr><th><label>Yazı Rengi</label></th>
                                <td><input type="text" class="qrm-color-picker" name="qrm_reward_popup_text_color" value="<?php echo esc_attr($settings['qrm_reward_popup_text_color']); ?>"></td></tr>
                            <tr><th><label>Buton Rengi</label></th>
                                <td><input type="text" class="qrm-color-picker" name="qrm_reward_popup_btn_color" value="<?php echo esc_attr($settings['qrm_reward_popup_btn_color']); ?>"></td></tr>
                            <tr><th><label>Buton Yazı Rengi</label></th>
                                <td><input type="text" class="qrm-color-picker" name="qrm_reward_popup_btn_text_color" value="<?php echo esc_attr($settings['qrm_reward_popup_btn_text_color']); ?>"></td></tr>
                            <tr><th><label>Köşe Yuvarlaklığı</label></th>
                                <td><input type="number" class="small-text" name="qrm_reward_popup_border_radius" min="0" max="40" value="<?php echo esc_attr($settings['qrm_reward_popup_border_radius']); ?>"> px</td></tr>
                        </table>
                    </div>

                    <div class="qrm-card">
                        <h3>✉️ E-posta</h3>
                        <table class="form-table">
                            <tr><th><label>Konu</label></th>
                                <td><input type="text" class="regular-text" name="qrm_reward_email_subject" value="<?php echo esc_attr($settings['qrm_reward_email_subject']); ?>"></td></tr>
                            <tr><th><label>Giriş Metni</label></th>
                                <td><textarea class="large-text" name="qrm_reward_email_intro" rows="3"><?php echo esc_textarea($settings['qrm_reward_email_intro']); ?></textarea>
                                    <p class="description">Kod ve indirim etiketi bu metnin altına otomatik eklenir.</p></td></tr>
                        </table>
                    </div>
                </div>
                <?php qrm_reward_admin_preview_box($default_template); ?>
            </div>
        </div>

        <!-- ================= SEKME 3: ŞABLONLAR ================= -->
        <div class="qrm-rw-pane" data-pane="sablonlar" style="display:none;">
            <div class="qrm-card">
                <h3>İndirim Şablonları</h3>
                <p class="description">"Otomatik" işaretli şablon, popup üzerinden e-posta girerek kod alan müşteriler için kullanılır. Manuel kod üretirken şablonu Ödül Kodları sekmesinde tek tek seçebilirsiniz.</p>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th style="width:35%;">Şablon Adı</th>
                            <th style="width:15%;">İndirim (%)</th>
                            <th style="width:12%;">Aktif</th>
                            <th style="width:20%;">Otomatik kodlar için varsayılan</th>
                            <th style="width:10%;">Sil</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 0; foreach ($templates as $t): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="tpl[<?php echo $i; ?>][id]" value="<?php echo esc_attr($t['id']); ?>">
                                <input type="text" class="regular-text" name="tpl[<?php echo $i; ?>][name]" value="<?php echo esc_attr($t['name']); ?>">
                            </td>
                            <td><input type="number" step="0.5" min="0" max="100" class="small-text" name="tpl[<?php echo $i; ?>][percent]" value="<?php echo esc_attr($t['percent']); ?>"></td>
                            <td><label><input type="checkbox" name="tpl[<?php echo $i; ?>][active]" value="1" <?php checked($t['active'], 1); ?>> Aktif</label></td>
                            <td><label><input type="radio" name="tpl_default" value="<?php echo esc_attr($t['id']); ?>" <?php checked($t['is_default'], 1); ?>> Varsayılan</label></td>
                            <td><label style="color:#b91c1c;"><input type="checkbox" name="tpl[<?php echo $i; ?>][delete]" value="1"> Sil</label></td>
                        </tr>
                    <?php $i++; endforeach; ?>
                        <tr style="background:#f0fdf4;">
                            <td>
                                <input type="hidden" name="tpl[<?php echo $i; ?>][id]" value="">
                                <input type="text" class="regular-text" name="tpl[<?php echo $i; ?>][name]" placeholder="Yeni şablon adı (örn. VIP %20)">
                            </td>
                            <td><input type="number" step="0.5" min="0" max="100" class="small-text" name="tpl[<?php echo $i; ?>][percent]" value=""></td>
                            <td><label><input type="checkbox" name="tpl[<?php echo $i; ?>][active]" value="1" checked> Aktif</label></td>
                            <td><span class="description">Kaydettikten sonra seçilebilir</span></td>
                            <td>—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="submit" style="position:sticky; bottom:0; background:#f0f0f1; padding:12px 0; margin:0; border-top:1px solid #dcdcde;">
            <input type="submit" class="button button-primary button-large" value="💾 Tüm Ayarları Kaydet">
            <span class="description" style="margin-left:10px;">Üç sekmenin tamamı tek seferde kaydedilir — bir sekmedeki değişiklik diğerini asla etkilemez.</span>
        </p>
    </form>

    <!-- Popup önizleme katmanı -->
    <div id="qrm-rw-preview-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:100000; align-items:center; justify-content:center;">
        <div id="qrm-rw-preview-overlay-inner" style="max-width:400px; width:92%;"></div>
    </div>

    <script>
    jQuery(document).ready(function($){
        var form = $('#qrm-reward-settings-form');
        var startSub = <?php echo wp_json_encode($sub); ?>;
        // Sayfa slug'ı JS içine gömülmez; adres PHP tarafından üretilir.
        var subUrlBase = <?php echo wp_json_encode(qrm_pro_admin_url('qrms-yf-odul', ['sub' => ''])); ?>;

        // --- Sekmeler: sayfa yenilenmez, POST tek seferde tüm alanları gönderir ---
        function showSub(name) {
            $('.qrm-rw-pane').hide();
            $('.qrm-rw-pane[data-pane="' + name + '"]').show();
            $('.qrm-rw-subtab').removeClass('nav-tab-active');
            $('.qrm-rw-subtab[data-sub="' + name + '"]').addClass('nav-tab-active');
            if (history.replaceState) {
                history.replaceState(null, '', subUrlBase + encodeURIComponent(name));
            }
            paint();
        }
        $('.qrm-rw-subtab, .qrm-rw-goto').on('click', function(e){
            e.preventDefault();
            showSub($(this).data('sub'));
            $('html, body').animate({ scrollTop: 0 }, 150);
        });
        showSub(startSub);

        // --- Canlı önizleme ---
        function val(name) { return form.find('[name="' + name + '"]').val() || ''; }
        function paint() {
            var theme = $('#qrm-rw-theme').val() || 'light';
            var bg = val('qrm_reward_popup_bg_color'), fg = val('qrm_reward_popup_text_color'),
                btnBg = val('qrm_reward_popup_btn_color'), btnFg = val('qrm_reward_popup_btn_text_color');
            if (theme === 'light') { bg = '#ffffff'; fg = '#1e293b'; btnBg = '#10b981'; btnFg = '#ffffff'; }
            if (theme === 'dark')  { bg = '#111827'; fg = '#f9fafb'; btnBg = '#10b981'; btnFg = '#ffffff'; }
            var radius = parseInt(val('qrm_reward_popup_border_radius'), 10);
            if (isNaN(radius)) radius = 18;

            $('.qrm-rw-preview-modal').css({ background: bg, color: fg, borderRadius: radius + 'px' });
            $('.qrm-rw-preview-btn').css({ background: btnBg, color: btnFg, borderRadius: Math.max(6, Math.min(18, radius)) + 'px' });
            $('.qrm-rw-preview-title').text(val('qrm_reward_popup_title'));
            $('.qrm-rw-preview-text').text(val('qrm_reward_popup_text'));
            $('.qrm-rw-preview-btn').text(val('qrm_reward_popup_button_text'));
            $('.qrm-rw-preview-skip').text(val('qrm_reward_popup_skip_text'));
        }
        form.on('input change keyup', 'input, textarea, select', paint);
        form.find('.qrm-color-picker').each(function(){
            var el = this;
            setTimeout(function(){
                $(el).closest('.wp-picker-container').on('click keyup', function(){ setTimeout(paint, 60); });
            }, 300);
        });
        paint();

        // --- Popup'ı büyük önizle ---
        $('#qrm-rw-preview-btn').on('click', function(){
            var clone = $('.qrm-rw-pane:visible .qrm-rw-preview-modal').first().clone();
            if (!clone.length) clone = $('.qrm-rw-preview-modal').first().clone();
            $('#qrm-rw-preview-overlay-inner').empty().append(clone);
            $('#qrm-rw-preview-overlay').css('display', 'flex');
        });
        $('#qrm-rw-preview-overlay').on('click', function(){ $(this).hide(); });

        // --- Test Et (kayıt oluşturmadan simülasyon) ---
        $('#qrm-rw-selftest').on('click', function(){
            var btn = $(this), out = $('#qrm-rw-selftest-result');
            btn.prop('disabled', true);
            out.css('color', '').text('Kontrol ediliyor...');
            $.post(ajaxurl, {
                action: 'qrm_reward_admin_selftest',
                nonce: <?php echo wp_json_encode(wp_create_nonce('qrm_reward_admin')); ?>
            }, function(res){
                btn.prop('disabled', false);
                if (res && res.show_reward) {
                    out.css('color', '#166534').html('✅ ' + res.message);
                } else {
                    out.css('color', '#b91c1c').html('❌ ' + ((res && res.message) ? res.message : 'Bilinmeyen hata'));
                }
            }, 'json').fail(function(){
                btn.prop('disabled', false);
                out.css('color', '#b91c1c').text('Bağlantı hatası.');
            });
        });
    });
    </script>
    <?php
}

/** Sağ sütundaki canlı önizleme kutusu (her sekmede aynı işaretleme kullanılır). */
function qrm_reward_admin_preview_box($default_template) {
    ?>
    <div style="flex:0 0 340px;">
        <div class="qrm-card" style="position:sticky; top:40px;">
            <h3>👁 Canlı Önizleme</h3>
            <p class="description">Metin ve renkleri değiştirdikçe anında güncellenir.</p>
            <div style="background:#0f172a1a; padding:22px; border-radius:12px;">
                <div class="qrm-rw-preview-modal" style="background:#fff; color:#1e293b; border-radius:18px; padding:24px 20px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,.15);">
                    <div style="width:46px;height:46px;border-radius:50%;background:rgba(0,0,0,.06);display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 12px;">🎁</div>
                    <div class="qrm-rw-preview-title" style="font-size:17px;font-weight:700;margin-bottom:8px;"></div>
                    <div class="qrm-rw-preview-text" style="font-size:13px;line-height:1.5;opacity:.82;margin-bottom:18px;"></div>
                    <div class="qrm-rw-preview-btn" style="background:#10b981;color:#fff;font-size:14px;font-weight:600;padding:13px 16px;border-radius:10px;"></div>
                    <div class="qrm-rw-preview-skip" style="font-size:12px;opacity:.5;text-decoration:underline;margin-top:12px;"></div>
                </div>
            </div>
            <p class="description" style="margin-top:12px; margin-bottom:0;">
                Otomatik kodlarda kullanılacak şablon:
                <strong><?php echo $default_template ? esc_html(qrm_reward_template_label($default_template)) : 'tanımlı değil'; ?></strong>
            </p>
        </div>
    </div>
    <?php
}
