<?php
if (!defined('ABSPATH')) exit;

// 5. ADMİN: AYARLAR & PUANLAMA
//
// Suite'e taşınırken sadeleşti:
//   - "Müşteri Bilgileri Formu" sekmesi kendi sayfasına ayrıldı (form-builder.php),
//     bu sayfadaki JS sekme kabuğu tamamen kalktı.
//   - Google/ödül durumunu tekrarlayan bilgi kartı kaldırıldı: aynı checklist zaten
//     "Google & Ödül Sistemi" sayfasının başında duruyor. Geriye tek satırlık bir
//     bağlantı kaldı.
//
// google_review_* alanları bu formda YOK; kaydederken de dokunulmuyor (aşağıdaki
// array_key_exists koruması). O ayarların tek sahibi "Google & Ödül Sistemi"dir.

function qrm_pro_admin_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $notice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qrm_save_settings'])) {
        check_admin_referer('qrm_save_review_form_settings');
        qrm_pro_admin_save_settings();
        $notice = 'Ayarlar ve puanlama kriterleri kaydedildi.';
    }

    $settings = qrm_pro_get_settings();
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1>Ayarlar &amp; Puanlama</h1>
        <?php
        if (function_exists('rma_ceviri_option_alan_dil_sayisi')) {
            $ceviri_n = rma_ceviri_option_alan_dil_sayisi(array(
                'qrm_settings.form_title',
                'qrm_settings.contact_form_title',
                'qrm_settings.qrm_consent_text',
                'qrm_settings.crit_1_name',
                'qrm_settings.crit_2_name',
                'qrm_settings.crit_3_name',
                'qrm_settings.crit_4_name',
                'qrm_settings.crit_5_name',
            ));
            echo rma_ceviri_bayat_uyari_html(rma_ceviri_bayat_uyari_ekran_metni($ceviri_n));
        }
        ?>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <form method="POST">
            <?php wp_nonce_field('qrm_save_review_form_settings'); ?>

            <div class="qrm-card-row">
                <div class="qrm-card">
                    <h3>Puanlama Kriterleri</h3>
                    <p class="description">Müşteriden 5 yıldız üzerinden istenecek puanlama başlıkları. İstemediğinizi kapatabilirsiniz.</p>
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

                <div class="qrm-card">
                    <h3>Form Görünümü</h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="qrm_form_title">Form Başlığı</label></th>
                            <td><input type="text" id="qrm_form_title" name="form_title" class="regular-text" value="<?php echo esc_attr($settings['form_title']); ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Buton Rengi</label></th>
                            <td><input type="text" name="btn_color" class="qrm-color-picker" value="<?php echo esc_attr($settings['btn_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Buton Yazı Rengi</label></th>
                            <td><input type="text" name="btn_text_color" class="qrm-color-picker" value="<?php echo esc_attr($settings['btn_text_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="qrm_theme_style">Tema Stili</label></th>
                            <td>
                                <select id="qrm_theme_style" name="theme_style">
                                    <option value="light" <?php selected($settings['theme_style'], 'light'); ?>>Aydınlık Kutu</option>
                                    <option value="dark" <?php selected($settings['theme_style'], 'dark'); ?>>Karanlık Kutu</option>
                                    <option value="transparent" <?php selected($settings['theme_style'], 'transparent'); ?>>Saydam</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="qrm_reviews_per_page">Sayfada Gösterilecek Yorum</label></th>
                            <td>
                                <select id="qrm_reviews_per_page" name="reviews_per_page">
                                    <option value="3" <?php selected($settings['reviews_per_page'], '3'); ?>>3 Yorum</option>
                                    <option value="5" <?php selected($settings['reviews_per_page'], '5'); ?>>5 Yorum</option>
                                    <option value="all" <?php selected($settings['reviews_per_page'], 'all'); ?>>Tümü</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="qrm_reviews_pagination_mode"><?php esc_html_e('Yorum listesi sayfalama', 'qrms'); ?></label></th>
                            <td>
                                <select id="qrm_reviews_pagination_mode" name="qrm_reviews_pagination_mode">
                                    <option value="loadmore" <?php selected($settings['qrm_reviews_pagination_mode'], 'loadmore'); ?>><?php esc_html_e('Daha Fazla Yükle (varsayılan)', 'qrms'); ?></option>
                                    <option value="pages" <?php selected($settings['qrm_reviews_pagination_mode'], 'pages'); ?>><?php esc_html_e('Sayfa numaraları', 'qrms'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Ön yüzdeki yorum listesinin nasıl sayfalanacağını belirler.', 'qrms'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Puan Özeti</label></th>
                            <td>
                                <label><input type="checkbox" name="show_overall_stats" value="1" <?php checked(isset($settings['show_overall_stats']) ? $settings['show_overall_stats'] : 1, 1); ?>> Sayfa başında genel puan durumunu göster</label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="qrm_auto_approve">Otomatik Onay</label></th>
                            <td>
                                <select id="qrm_auto_approve" name="auto_approve_rating">
                                    <option value="0" <?php selected($settings['auto_approve_rating'], 0); ?>>Kapalı — tümünü ben onaylayayım</option>
                                    <option value="4" <?php selected($settings['auto_approve_rating'], 4); ?>>Ortalaması 4 ve üzeri olanları anında yayınla</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Çok Adımlı Form</th>
                            <td>
                                <label><input type="checkbox" name="qrm_steps_summary_enabled" value="1" <?php checked(!empty($settings['qrm_steps_summary_enabled'])); ?>> Gönderimden önce özet adımı göster (opsiyonel 4. adım)</label>
                                <p class="description">Kapalıyken form en fazla 3 adımda tamamlanır (puanlama → yorum → bilgiler).</p>
                            </td>
                        </tr>
                        <?php
                        $step_label_keys = ['rating' => 'Puanlama', 'comment' => 'Yorumunuz', 'info' => 'Bilgileriniz', 'summary' => 'Gönder'];
                        $saved_labels = isset($settings['qrm_step_labels']) && is_array($settings['qrm_step_labels']) ? $settings['qrm_step_labels'] : [];
                        foreach ($step_label_keys as $lk => $default_lbl): ?>
                        <tr>
                            <th><label for="qrm_step_label_<?php echo esc_attr($lk); ?>">Adım: <?php echo esc_html($default_lbl); ?></label></th>
                            <td>
                                <input type="text" id="qrm_step_label_<?php echo esc_attr($lk); ?>" name="qrm_step_labels[<?php echo esc_attr($lk); ?>]" class="regular-text"
                                       value="<?php echo esc_attr(isset($saved_labels[$lk]) ? $saved_labels[$lk] : ''); ?>"
                                       placeholder="<?php echo esc_attr($default_lbl); ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <div class="qrm-card">
                <h3>Otomatik Renklendirme</h3>
                <p class="description">Açıldığında formdaki tüm alanlar (puanlama kriterleri ve bilgi alanları) aşağıdaki üç rengi sırayla kullanır.</p>
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
                <h3>Spam Koruması</h3>
                <p class="description">
                    Aynı ziyaretçi (IP ve girdiği e-posta/telefon üzerinden tanınır) bu süre dolmadan
                    ikinci bir form gönderemez. Yorum, iletişim ve özel formların hepsinde geçerlidir.
                    Yönetici ve editörler bu kısıttan muaftır.
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="qrm_spam_cooldown_minutes">Gönderimler arası bekleme</label></th>
                        <td>
                            <input type="number" id="qrm_spam_cooldown_minutes" name="qrm_spam_cooldown_minutes" min="0" max="1440" step="1"
                                   class="small-text" value="<?php echo intval($settings['qrm_spam_cooldown_minutes']); ?>"> dakika
                            <p class="description">Varsayılan 10 dakika. <code>0</code> yazarsanız kapanır (honeypot ve güvenlik sorusu çalışmaya devam eder).</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="qrm-card">
                <h3><?php esc_html_e('KVKK / Pazarlama İzni', 'qrms'); ?></h3>
                <p class="description">
                    <?php esc_html_e('İsteğe bağlı onay kutusu tüm yorum, iletişim ve özel formlarda gösterilir. Metin boş bırakılırsa kutu hiç render edilmez; işaretlenmezse form normal gönderilir.', 'qrms'); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="qrm_consent_text"><?php esc_html_e('Onay metni', 'qrms'); ?></label></th>
                        <td>
                            <input type="text" id="qrm_consent_text" name="qrm_consent_text" class="large-text"
                                   value="<?php echo esc_attr($settings['qrm_consent_text']); ?>"
                                   placeholder="<?php esc_attr_e('Kampanya ve duyurulardan haberdar olmak istiyorum', 'qrms'); ?>">
                            <p class="description"><?php esc_html_e('Checkbox yanında görünen metin. Boşsa özellik kapalıdır.', 'qrms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qrm_consent_page_url"><?php esc_html_e('Aydınlatma Metni linki', 'qrms'); ?></label></th>
                        <td>
                            <input type="url" id="qrm_consent_page_url" name="qrm_consent_page_url" class="large-text"
                                   value="<?php echo esc_attr($settings['qrm_consent_page_url']); ?>"
                                   placeholder="https://">
                            <p class="description"><?php esc_html_e('Onay metninin yanında "Aydınlatma Metni" bağlantısı olarak açılır.', 'qrms'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="qrm-card">
                <h3><?php esc_html_e('Yorum Görseli Yükleme', 'qrms'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Müşteriler yorum formundan fotoğraf ekleyebilir. Kapalıyken form ve akış eskisiyle aynıdır.', 'qrms'); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Görsel yükleme', 'qrms'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="qrm_media_enabled" value="1" <?php checked(!empty($settings['qrm_media_enabled'])); ?>>
                                <?php esc_html_e('Yorum formunda fotoğraf yüklemeye izin ver', 'qrms'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qrm_media_max_files"><?php esc_html_e('Azami dosya sayısı', 'qrms'); ?></label></th>
                        <td>
                            <input type="number" id="qrm_media_max_files" name="qrm_media_max_files" min="1" max="5" step="1"
                                   class="small-text" value="<?php echo esc_attr((string) max(1, min(5, (int) $settings['qrm_media_max_files']))); ?>">
                            <p class="description"><?php esc_html_e('Varsayılan 2, üst sınır 5.', 'qrms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qrm_media_max_mb"><?php esc_html_e('Dosya başına azami boyut (MB)', 'qrms'); ?></label></th>
                        <td>
                            <input type="number" id="qrm_media_max_mb" name="qrm_media_max_mb" min="1" max="8" step="1"
                                   class="small-text" value="<?php echo esc_attr((string) max(1, min(8, (int) $settings['qrm_media_max_mb']))); ?>">
                            <p class="description"><?php esc_html_e('Varsayılan 3 MB, üst sınır 8 MB. Yalnızca JPEG, PNG ve WebP.', 'qrms'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="qrm-card">
                <h3><?php esc_html_e('Rapor Vardiyaları', 'qrms'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Tüm Yorumlar → Rapor ekranındaki vardiya kırılımı bu saatlere göre hesaplanır. Bitiş saati hariçtir; gece yarısını geçen aralıklar (ör. 23–06) desteklenir.', 'qrms'); ?>
                </p>
                <table class="widefat striped qrm-shifts-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Ad', 'qrms'); ?></th>
                            <th><?php esc_html_e('Başlangıç (saat)', 'qrms'); ?></th>
                            <th><?php esc_html_e('Bitiş (saat)', 'qrms'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $shifts = qrm_pro_get_shifts($settings);
                        foreach ($shifts as $idx => $shift):
                        ?>
                        <tr>
                            <td>
                                <input type="text" name="qrm_shifts[<?php echo (int) $idx; ?>][name]" class="regular-text"
                                       value="<?php echo esc_attr($shift['name']); ?>">
                            </td>
                            <td>
                                <input type="number" name="qrm_shifts[<?php echo (int) $idx; ?>][start]" min="0" max="23" step="1" class="small-text"
                                       value="<?php echo esc_attr((string) (int) $shift['start']); ?>">
                            </td>
                            <td>
                                <input type="number" name="qrm_shifts[<?php echo (int) $idx; ?>][end]" min="0" max="23" step="1" class="small-text"
                                       value="<?php echo esc_attr((string) (int) $shift['end']); ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="qrm-card">
                <h3><?php esc_html_e('Trend & Düşüş Uyarısı', 'qrms'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Rapor ekranındaki kriter trendleri ve son 7 günlük düşüş uyarısı bu eşiğe göre çalışır.', 'qrms'); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="qrm_trend_drop_threshold"><?php esc_html_e('Düşüş uyarı eşiği', 'qrms'); ?></label></th>
                        <td>
                            <input type="number" id="qrm_trend_drop_threshold" name="qrm_trend_drop_threshold"
                                   min="0.1" max="5" step="0.1" class="small-text"
                                   value="<?php echo esc_attr(number_format((float) $settings['qrm_trend_drop_threshold'], 1, '.', '')); ?>">
                            <?php esc_html_e('puan', 'qrms'); ?>
                            <p class="description"><?php esc_html_e('Bir kriterin son 7 günlük ortalaması, önceki 7 güne göre bu kadar veya daha fazla düşerse uyarı verilir (varsayılan 0,5).', 'qrms'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <input type="submit" name="qrm_save_settings" class="button button-primary button-large" value="Kaydet">
                <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-odul')); ?>" class="button" style="margin-left:8px;">Google &amp; Ödül Ayarları</a>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Ayarlar formunun kaydı.
 *
 * Yalnızca bu sayfanın sahip olduğu anahtarlara dokunur. google_review_* ve ödül
 * ayarları burada hiç okunmaz — o alanlar formda yok, koruma olmadan her kayıt
 * onları sessizce sıfırlardı.
 *
 * @return void
 */
function qrm_pro_admin_save_settings() {
    $settings = qrm_pro_get_settings();

    $settings['form_title']          = sanitize_text_field(wp_unslash($_POST['form_title']));
    $settings['btn_color']           = sanitize_hex_color($_POST['btn_color']) ?: $settings['btn_color'];
    $settings['btn_text_color']      = sanitize_hex_color($_POST['btn_text_color']) ?: $settings['btn_text_color'];
    $settings['theme_style']         = sanitize_text_field(wp_unslash($_POST['theme_style']));
    $settings['auto_approve_rating'] = intval($_POST['auto_approve_rating']);
    $settings['reviews_per_page']    = sanitize_text_field(wp_unslash($_POST['reviews_per_page']));
    $pagination_mode = isset($_POST['qrm_reviews_pagination_mode'])
        ? sanitize_key(wp_unslash($_POST['qrm_reviews_pagination_mode']))
        : 'loadmore';
    $settings['qrm_reviews_pagination_mode'] = in_array($pagination_mode, ['loadmore', 'pages'], true)
        ? $pagination_mode
        : 'loadmore';
    $settings['show_overall_stats']  = isset($_POST['show_overall_stats']) ? 1 : 0;

    for ($i = 1; $i <= 5; $i++) {
        $settings['crit_'.$i.'_name']   = sanitize_text_field(wp_unslash($_POST['crit_'.$i.'_name']));
        $settings['crit_'.$i.'_active'] = isset($_POST['crit_'.$i.'_active']) ? 1 : 0;
    }

    $settings['auto_color_enabled'] = isset($_POST['auto_color_enabled']) ? 1 : 0;
    $settings['auto_color_1'] = sanitize_hex_color($_POST['auto_color_1']) ?: $settings['auto_color_1'];
    $settings['auto_color_2'] = sanitize_hex_color($_POST['auto_color_2']) ?: $settings['auto_color_2'];
    $settings['auto_color_3'] = sanitize_hex_color($_POST['auto_color_3']) ?: $settings['auto_color_3'];

    // 0 = kapalı, üst sınır 1 gün.
    $settings['qrm_spam_cooldown_minutes'] = max(0, min(1440, intval($_POST['qrm_spam_cooldown_minutes'])));

    if (function_exists('qrm_pro_sanitize_shifts_from_post')) {
        $settings['qrm_shifts'] = qrm_pro_sanitize_shifts_from_post(
            isset($_POST['qrm_shifts']) ? wp_unslash($_POST['qrm_shifts']) : []
        );
    }

    $settings['qrm_trend_drop_threshold'] = qrm_pro_trend_drop_threshold([
        'qrm_trend_drop_threshold' => isset($_POST['qrm_trend_drop_threshold'])
            ? floatval(wp_unslash($_POST['qrm_trend_drop_threshold']))
            : 0.5,
    ]);

    if (array_key_exists('qrm_consent_text', $_POST)) {
        $settings['qrm_consent_text'] = sanitize_text_field(wp_unslash($_POST['qrm_consent_text']));
    }
    if (array_key_exists('qrm_consent_page_url', $_POST)) {
        $settings['qrm_consent_page_url'] = esc_url_raw(trim(wp_unslash($_POST['qrm_consent_page_url'])));
    }

    $settings['qrm_media_enabled']   = isset($_POST['qrm_media_enabled']) ? 1 : 0;
    $settings['qrm_media_max_files'] = max(1, min(5, intval($_POST['qrm_media_max_files'] ?? 2)));
    $settings['qrm_media_max_mb']    = max(1, min(8, intval($_POST['qrm_media_max_mb'] ?? 3)));

    $settings['qrm_steps_summary_enabled'] = isset($_POST['qrm_steps_summary_enabled']) ? 1 : 0;
    if (isset($_POST['qrm_step_labels']) && is_array($_POST['qrm_step_labels'])) {
        $labels = [];
        foreach ($_POST['qrm_step_labels'] as $key => $val) {
            $key = sanitize_key($key);
            if ($key !== '') {
                $labels[$key] = sanitize_text_field(wp_unslash($val));
            }
        }
        $settings['qrm_step_labels'] = $labels;
    }

    update_option('qrm_settings', $settings);
    qrm_pro_flush_trend_drop_cache();
}
