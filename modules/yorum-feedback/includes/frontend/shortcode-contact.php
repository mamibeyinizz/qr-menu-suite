<?php
if (!defined('ABSPATH')) exit;

// 10. FRONT-END: İLETİŞİM SAYFASI KISA KODU (Sadece form; yorum listesi yok, puan ortalaması yok)
add_shortcode('qr_menu_contact', 'qrm_pro_shortcode_contact');
function qrm_pro_shortcode_contact() {
    global $wpdb;
    $table_fields = $wpdb->prefix . 'qrm_form_fields';
    $settings = qrm_pro_get_settings();
    $message = '';
    $show_google_cta = false;
    $cta_avg = 0;
    $auto_open_reward = false;

    // FORM GÖNDERİMİ İŞLEME (JS kapalıysa devreye giren klasik yol)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['qrm_review_submit']) && isset($_POST['qrm_form_source']) && $_POST['qrm_form_source'] === 'contact' && wp_verify_nonce($_POST['qrm_review_nonce'], 'qrm_submit_review')) {
        $result = qrm_pro_handle_review_submission($settings);
        if ($result['success']) {
            if (!empty($result['show_google'])) {
                $show_google_cta = true;
                $cta_avg = $result['avg'];
            } else {
                $message = '<div class="qrm-alert qrm-success">' . esc_html($result['message']) . '</div>';
                // v4.1.0: Ödül modülü açıksa popup sayfa yüklenir yüklenmez gösterilir.
                // Popup JS gerektirdiği için, JS tamamen kapalı ziyaretçilere eski
                // satır içi Google CTA'sı <noscript> içinde yedek olarak sunulur.
                if (!empty($result['show_reward'])) {
                    $auto_open_reward = true;
                    $message .= '<noscript>' . qrm_pro_render_google_cta($settings, $result['avg']) . '</noscript>';
                }
            }
        } else {
            $message = '<div class="qrm-alert qrm-error">' . esc_html($result['message']) . '</div>';
        }
    }

    $active_fields = $wpdb->get_results("SELECT * FROM $table_fields WHERE is_active = 1 ORDER BY sort_order ASC");

    ob_start();
    echo qrm_pro_render_style_block($settings);
    ?>
    <div class="qrm-wrap-full">
        <?php echo qrm_pro_render_review_form($settings, $active_fields, $message, $show_google_cta, $cta_avg, 'contact', $auto_open_reward); ?>
    </div>
    <?php
    echo qrm_pro_render_form_script($settings, 0, false);
    return ob_get_clean();
}
