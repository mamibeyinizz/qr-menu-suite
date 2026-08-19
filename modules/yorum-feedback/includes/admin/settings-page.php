<?php
if (!defined('ABSPATH')) exit;

// 5. ADMİN: YORUM FORMU AYARLARI
//
// v4.2.1: "Müşteri Bilgileri Formu" ayrı bir menü maddesi olmaktan çıkıp bu sayfanın
// ikinci sekmesi oldu. İki sekme TEK form + TEK POST ile kaydedilir; böylece bir
// sekmedeki kayıt diğerinin verisini asla sıfırlayamaz (v4.2.0'da Google/ödül
// ayarlarında uygulanan prensibin aynısı).
function qrm_pro_admin_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['qrm_save_settings'])) {
        check_admin_referer('qrm_save_review_form_settings');
        $settings = qrm_pro_get_settings();
        $settings['form_title'] = sanitize_text_field($_POST['form_title']);
        $settings['btn_color'] = sanitize_hex_color($_POST['btn_color']);
        $settings['btn_text_color'] = sanitize_hex_color($_POST['btn_text_color']);
        $settings['theme_style'] = sanitize_text_field($_POST['theme_style']);
        $settings['auto_approve_rating'] = intval($_POST['auto_approve_rating']);
        $settings['reviews_per_page'] = sanitize_text_field($_POST['reviews_per_page']);
        $settings['show_overall_stats'] = isset($_POST['show_overall_stats']) ? 1 : 0;

        for($i=1; $i<=5; $i++) {
            $settings['crit_'.$i.'_name'] = sanitize_text_field($_POST['crit_'.$i.'_name']);
            $settings['crit_'.$i.'_active'] = isset($_POST['crit_'.$i.'_active']) ? 1 : 0;
        }

        // Google Yorum Yönlendirme ayarları v4.2.0'da "Google & Ödül Sistemi" sayfasına
        // taşındı; bu form artık o alanları göndermiyor.
        // array_key_exists koruması ZORUNLU: alanlar formda olmadığı için koruma olmadan
        // bu sayfanın her kaydı google_review_* değerlerini sessizce sıfırlardı.
        if (array_key_exists('google_review_url', $_POST)) {
            $settings['google_review_enabled'] = isset($_POST['google_review_enabled']) ? 1 : 0;
            $settings['google_review_url'] = esc_url_raw(trim($_POST['google_review_url']));
            $threshold = floatval(str_replace(',', '.', $_POST['google_review_threshold']));
            $settings['google_review_threshold'] = min(5, max(1, $threshold));
            $settings['google_review_headline'] = sanitize_text_field($_POST['google_review_headline']);
            $settings['google_review_subtext'] = sanitize_textarea_field($_POST['google_review_subtext']);
            $settings['google_review_btn_text'] = sanitize_text_field($_POST['google_review_btn_text']);
            $settings['google_review_skip_text'] = sanitize_text_field($_POST['google_review_skip_text']);
        }

        $settings['auto_color_enabled'] = isset($_POST['auto_color_enabled']) ? 1 : 0;
        $settings['auto_color_1'] = sanitize_hex_color($_POST['auto_color_1']) ?: $settings['auto_color_1'];
        $settings['auto_color_2'] = sanitize_hex_color($_POST['auto_color_2']) ?: $settings['auto_color_2'];
        $settings['auto_color_3'] = sanitize_hex_color($_POST['auto_color_3']) ?: $settings['auto_color_3'];

        // Spam koruması: aynı kişi kaç dakikada bir gönderebilir (0 = kapalı, üst sınır 1 gün)
        if (array_key_exists('qrm_spam_cooldown_minutes', $_POST)) {
            $settings['qrm_spam_cooldown_minutes'] = max(0, min(1440, intval($_POST['qrm_spam_cooldown_minutes'])));
        }

        update_option('qrm_settings', $settings);

        // "Müşteri Bilgileri Formu" sekmesi aynı POST içinde kaydedilir.
        // array_key_exists koruması: alanlar gönderilmediyse tabloya dokunulmaz.
        $field_msg = '';
        if (array_key_exists('fields', $_POST) && is_array($_POST['fields'])) {
            $saved = qrm_pro_save_review_form_fields($_POST['fields']);
            $field_msg = sprintf(' %d form alanı güncellendi.', $saved);
        }

        echo '<div class="notice notice-success is-dismissible"><p>Ayarlar ve puanlama kriterleri kaydedildi.' . esc_html($field_msg) . '</p></div>';
    }

    $settings = qrm_pro_get_settings();
    $sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : 'genel';
    if (!in_array($sub, ['genel', 'alanlar'], true)) $sub = 'genel';
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1>Yorum Formu Ayarları</h1>

        <h2 class="nav-tab-wrapper">
            <a href="#" class="nav-tab qrm-set-subtab" data-sub="genel">Puanlama &amp; Ayarlar</a>
            <a href="#" class="nav-tab qrm-set-subtab" data-sub="alanlar">Müşteri Bilgileri Formu</a>
        </h2>

        <form method="POST" id="qrm-review-settings-form">
            <?php wp_nonce_field('qrm_save_review_form_settings'); ?>

            <div class="qrm-set-pane" data-pane="genel">
            <div style="display:flex; gap:20px; margin-top:18px;">
                <div class="qrm-card" style="flex:1;">
                    <h3>Puanlama Kriterleri (5 Yıldız Sistemi)</h3>
                    <p class="description">Ön yüzde müşteriden istenecek puanlama türlerini belirleyin. İsimleri değiştirebilir veya istemediklerinizi kapatabilirsiniz.</p>
                    <table class="form-table">
                        <?php for($i=1; $i<=5; $i++): ?>
                        <tr>
                            <th>Kriter <?php echo $i; ?></th>
                            <td>
                                <input type="text" name="crit_<?php echo $i; ?>_name" value="<?php echo esc_attr($settings['crit_'.$i.'_name']); ?>" class="regular-text">
                                <label style="margin-left:10px;">
                                    <input type="checkbox" name="crit_<?php echo $i; ?>_active" value="1" <?php checked($settings['crit_'.$i.'_active'], 1); ?>> Aktif
                                </label>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </table>
                </div>

                <div class="qrm-card" style="flex:1;">
                    <h3>Genel Ayarlar</h3>
                    <table class="form-table">
                        <tr>
                            <th><label>Form Başlığı</label></th>
                            <td><input type="text" name="form_title" class="regular-text" value="<?php echo esc_attr($settings['form_title']); ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Buton Rengi</label></th>
                            <td><input type="text" name="btn_color" class="qrm-color-picker" value="<?php echo esc_attr($settings['btn_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Buton Yazısı</label></th>
                            <td><input type="text" name="btn_text_color" class="qrm-color-picker" value="<?php echo esc_attr($settings['btn_text_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Tema Stili</label></th>
                            <td>
                                <select name="theme_style">
                                    <option value="light" <?php selected($settings['theme_style'], 'light'); ?>>Aydınlık Kutu</option>
                                    <option value="dark" <?php selected($settings['theme_style'], 'dark'); ?>>Karanlık Kutu</option>
                                    <option value="transparent" <?php selected($settings['theme_style'], 'transparent'); ?>>Saydam</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Ön Yüz Görüntüleme</label></th>
                            <td>
                                <select name="reviews_per_page">
                                    <option value="3" <?php selected($settings['reviews_per_page'], '3'); ?>>3 Yorum</option>
                                    <option value="5" <?php selected($settings['reviews_per_page'], '5'); ?>>5 Yorum</option>
                                    <option value="all" <?php selected($settings['reviews_per_page'], 'all'); ?>>Tümü</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Otomatik Onay</label></th>
                            <td>
                                <select name="auto_approve_rating">
                                    <option value="0" <?php selected($settings['auto_approve_rating'], 0); ?>>Kapalı (Tümünü manuel onayla)</option>
                                    <option value="4" <?php selected($settings['auto_approve_rating'], 4); ?>>Ortalaması 4 ve 5 olanları anında onayla</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Restoran Puan Özeti</label></th>
                            <td>
                                <label><input type="checkbox" name="show_overall_stats" value="1" <?php checked(isset($settings['show_overall_stats']) ? $settings['show_overall_stats'] : 1, 1); ?>> Sayfa başında genel puan durumunu grafiksel göster.</label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="qrm-card">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 48 48" style="vertical-align:-3px;margin-right:6px;"><path fill="#FFC107" d="M43.6 20.5H42V20.4H24v7.2h11.3C33.7 32 29.3 35 24 35c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.1-5.1C33.6 5.5 29 3.6 24 3.6 12.9 3.6 3.9 12.6 3.9 23.7S12.9 43.8 24 43.8c11.1 0 20-9 20-20 0-1.3-.1-2.2-.4-3.3z"/><path fill="#FF3D00" d="M6.3 14.7l6 4.4C13.9 15.5 18.6 12.6 24 12.6c3 0 5.8 1.1 7.9 3l5.1-5.1C33.6 6.5 29 4.6 24 4.6c-7.7 0-14.3 4.3-17.7 10.1z"/><path fill="#4CAF50" d="M24 43.8c5 0 9.6-1.9 13-5l-6-4.9c-2 1.4-4.5 2.2-7 2.2-5.3 0-9.7-3.6-11.3-8.4l-6.1 4.7C9.7 39.6 16.3 43.8 24 43.8z"/><path fill="#1976D2" d="M43.6 20.5H42V20.4H24v7.2h11.3c-.8 2.3-2.3 4.3-4.3 5.7l6 4.9c-.4.4 6.4-4.7 6.4-14.5 0-1.3-.1-2.2-.4-3.3z"/></svg>
                    Google Yorum Yönlendirme &amp; Ödül Popup
                </h3>
                <p class="description">
                    Bu ayarlar v4.2.0 ile <strong>tek bir sayfada</strong> toplandı: Google değerlendirme linki, yönlendirme eşiği,
                    ödül popup'ı ve indirim şablonları artık <a href="?page=qrm-pro-rewards">Google &amp; Ödül Sistemi</a> sayfasında,
                    tek bir "Kaydet" ile yönetiliyor. Böylece bir sayfada yaptığınız kayıt diğerinin ayarını asla etkilemiyor.
                </p>
                <?php $qrm_setup = qrm_reward_setup_status($settings); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Mevcut Durum</label></th>
                        <td>
                            <?php if ($qrm_setup['all_ok']): ?>
                                <span style="color:#166534;font-weight:600;">✅ Popup çalışıyor</span>
                            <?php elseif (!empty($settings['google_review_enabled']) && !empty($settings['google_review_url'])): ?>
                                <span style="color:#92400e;font-weight:600;">⚠️ Google yönlendirmesi çalışıyor, ödül popup'ı kapalı/eksik</span>
                            <?php else: ?>
                                <span style="color:#b91c1c;font-weight:600;">❌ Yönlendirme kapalı veya link girilmemiş</span>
                            <?php endif; ?>
                            <ul style="margin:8px 0 0; font-size:13px; line-height:1.9;">
                                <?php foreach ($qrm_setup['steps'] as $qrm_step): ?>
                                    <li style="list-style:none;"><?php echo $qrm_step['ok'] ? '✅' : '❌'; ?> <?php echo esc_html($qrm_step['label']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Google Linki</label></th>
                        <td>
                            <?php if (!empty($settings['google_review_url'])): ?>
                                <code style="word-break:break-all;"><?php echo esc_html($settings['google_review_url']); ?></code>
                            <?php else: ?>
                                <em>Henüz girilmedi.</em>
                            <?php endif; ?>
                            <p class="description">Eşik: <?php echo number_format((float)$settings['google_review_threshold'], 1); ?> ve üzeri</p>
                        </td>
                    </tr>
                </table>
                <p><a href="?page=qrm-pro-rewards" class="button button-primary">Google &amp; Ödül Ayarlarını Aç</a></p>
            </div>

            <div class="qrm-card">
                <h3>✨ Form Alanı Otomatik Renklendirme (Premium)</h3>
                <p class="description">Etkinleştirildiğinde, formdaki tüm alanlar (puanlama kriterleri + müşteri bilgi alanları) belirlediğiniz 3 rengi sırayla kullanarak otomatik renklendirilir. Modern, premium bir görünüm sağlar.</p>
                <table class="form-table">
                    <tr>
                        <th><label>Aktif Et</label></th>
                        <td><label><input type="checkbox" name="auto_color_enabled" value="1" <?php checked($settings['auto_color_enabled'], 1); ?>> Otomatik renklendirmeyi etkinleştir</label></td>
                    </tr>
                    <tr>
                        <th><label>Renk 1</label></th>
                        <td><input type="text" name="auto_color_1" class="qrm-color-picker" value="<?php echo esc_attr($settings['auto_color_1']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label>Renk 2</label></th>
                        <td><input type="text" name="auto_color_2" class="qrm-color-picker" value="<?php echo esc_attr($settings['auto_color_2']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label>Renk 3</label></th>
                        <td><input type="text" name="auto_color_3" class="qrm-color-picker" value="<?php echo esc_attr($settings['auto_color_3']); ?>"></td>
                    </tr>
                </table>
            </div>

            <div class="qrm-card">
                <h3>🛡️ Spam Koruması</h3>
                <p class="description">
                    Aşağıdaki süre <strong>tüm formlar</strong> için geçerlidir: yorum formu, iletişim formu ve
                    Formlar sayfasından oluşturduğunuz özel formlar. Aynı ziyaretçi (IP adresi ve girdiği
                    e-posta/telefon üzerinden tanınır) bu süre dolmadan ikinci bir form gönderemez.
                    Yönetici ve editör yetkisiyle giriş yapmış kullanıcılar bu kısıttan <strong>muaftır</strong>,
                    böylece test amaçlı art arda gönderim yapabilirsiniz.
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="qrm_spam_cooldown_minutes">Aynı kişi kaç dakikada bir form gönderebilir</label></th>
                        <td>
                            <input type="number" id="qrm_spam_cooldown_minutes" name="qrm_spam_cooldown_minutes" min="0" max="1440" step="1"
                                   class="small-text" value="<?php echo intval($settings['qrm_spam_cooldown_minutes']); ?>"> dakika
                            <p class="description">Varsayılan: 10 dakika. <code>0</code> yazarsanız bu kontrol tamamen kapanır (honeypot ve güvenlik sorusu çalışmaya devam eder).</p>
                        </td>
                    </tr>
                </table>
            </div>
            </div><!-- /pane genel -->

            <div class="qrm-set-pane" data-pane="alanlar">
                <?php qrm_pro_admin_fields_pane(); ?>
            </div>

            <p class="submit">
                <input type="submit" name="qrm_save_settings" class="button button-primary button-large" value="Tüm Ayarları Kaydet">
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($){
        // Sekmeler sayfa yenilemeden değişir; iki sekme de TEK formun parçası
        // olduğu için Kaydet her iki sekmenin verisini birlikte gönderir.
        function showSub(name) {
            $('.qrm-set-pane').hide();
            $('.qrm-set-pane[data-pane="' + name + '"]').show();
            $('.qrm-set-subtab').removeClass('nav-tab-active');
            $('.qrm-set-subtab[data-sub="' + name + '"]').addClass('nav-tab-active');
            if (history.replaceState) {
                history.replaceState(null, '', '?page=qrm-pro-settings' + (name === 'alanlar' ? '&sub=alanlar' : ''));
            }
        }
        $('.qrm-set-subtab').on('click', function(e){
            e.preventDefault();
            showSub($(this).data('sub'));
            $('html, body').animate({ scrollTop: 0 }, 150);
        });
        showSub(<?php echo wp_json_encode($sub); ?>);
    });
    </script>
    <?php
}
