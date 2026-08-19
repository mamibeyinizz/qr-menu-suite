<?php
if (!defined('ABSPATH')) exit;

// 6b. ADMİN: İLETİŞİM FORMU (Ayrı shortcode - yorum listesi ve ortalama olmadan)
function qrm_pro_admin_contact() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['qrm_save_contact'])) {
        check_admin_referer('qrm_save_contact_settings');
        $settings = qrm_pro_get_settings();
        $settings['contact_form_title'] = sanitize_text_field($_POST['contact_form_title']);
        update_option('qrm_settings', $settings);
        echo '<div class="notice notice-success is-dismissible"><p>İletişim formu ayarları kaydedildi.</p></div>';
    }
    $settings = qrm_pro_get_settings();
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1>İletişim Formu</h1>
        <p class="description">Bu form, restoranın iletişim/İletişim sayfasına özeldir. Yorumlar listesi ve puan ortalaması <strong>gösterilmez</strong>. Müşteri 3,5 ve üzeri (eşik ayarınıza göre) bir değerlendirme bırakırsa, işletmeyi Google'da değerlendirmesi için yönlendirme ekranı açılır. Eşiğin altındaki gönderimler yalnızca size ulaşır ve "Tüm Yorumlar" listesinde "İletişim" etiketiyle görünür.</p>
        <div class="qrm-card" style="max-width:600px;">
            <form method="POST">
                <?php wp_nonce_field('qrm_save_contact_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Form Başlığı</label></th>
                        <td><input type="text" name="contact_form_title" class="regular-text" value="<?php echo esc_attr($settings['contact_form_title']); ?>"></td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="qrm_save_contact" class="button button-primary" value="Kaydet"></p>
            </form>
        </div>
        <div class="qrm-card" style="max-width:600px;">
            <h3>Shortcode</h3>
            <p class="description">Aşağıdaki kısa kodu iletişim sayfanıza ekleyin:</p>
            <input type="text" readonly onclick="this.select();" value="[qr_menu_contact]" style="width:100%; font-size:16px; font-weight:600; padding:10px; background:#f3f4f6; border:1px solid #d1d5db; border-radius:6px;">
        </div>
    </div>
    <?php
}
