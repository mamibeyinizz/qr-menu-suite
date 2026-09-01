<?php
if (!defined('ABSPATH')) exit;

// 6b. ADMİN: İLETİŞİM FORMU (Ayrı shortcode - yorum listesi ve ortalama olmadan)
function qrm_pro_admin_contact() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $notice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qrm_save_contact'])) {
        check_admin_referer('qrm_save_contact_settings');
        $settings = qrm_pro_get_settings();
        $settings['contact_form_title'] = sanitize_text_field($_POST['contact_form_title']);
        update_option('qrm_settings', $settings);
        $notice = 'İletişim formu ayarları kaydedildi.';
    }

    $settings = qrm_pro_get_settings();
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1><?php esc_html_e('İletişim Formu', 'qrms'); ?></h1>
        <p class="qrm-lead"><?php esc_html_e('İletişim sayfanıza özel form. Yorum listesi ve puan ortalaması burada gösterilmez.', 'qrms'); ?></p>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <div class="qrm-card" style="max-width:640px;">
            <form method="POST">
                <?php wp_nonce_field('qrm_save_contact_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="qrm_contact_form_title">Form Başlığı</label></th>
                        <td>
                            <input type="text" id="qrm_contact_form_title" name="contact_form_title" class="regular-text" value="<?php echo esc_attr($settings['contact_form_title']); ?>">
                            <?php
                            if (function_exists('rma_ceviri_bayat_uyari_html')) {
                                echo rma_ceviri_bayat_uyari_html(rma_ceviri_bayat_uyari_metni(rma_ceviri_veri_dil_sayisi('option', 0, 'qrm_settings.contact_form_title')));
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="qrm_save_contact" class="button button-primary" value="Kaydet"></p>
            </form>
        </div>

        <div class="qrm-card" style="max-width:640px;">
            <h3>Kısa Kod</h3>
            <p class="description">Bu kodu iletişim sayfanıza yapıştırın:</p>
            <input type="text" readonly onclick="this.select();" value="[qr_menu_contact]"
                   style="width:100%; font-size:16px; font-weight:600; padding:10px; background:#f3f4f6; border:1px solid #d1d5db; border-radius:6px;">
            <ul style="margin:16px 0 0; color:#646970; font-size:13px; line-height:1.7;">
                <li>Eşiğin (<?php echo esc_html(number_format((float) $settings['google_review_threshold'], 1)); ?>) üzerinde puan veren müşteri Google'a yönlendirilir.</li>
                <li>Eşiğin altındaki gönderimler yalnızca size ulaşır; <strong>Tüm Yorumlar</strong> listesinde <em>İletişim</em> etiketiyle görünür.</li>
            </ul>
        </div>
    </div>
    <?php
}
