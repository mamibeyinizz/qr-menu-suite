<?php
if (!defined('ABSPATH')) exit;

// 10. FRONT-END: İLETİŞİM SAYFASI KISA KODU (Sadece form; yorum listesi yok, puan ortalaması yok)
add_shortcode('qr_menu_contact', 'qrm_pro_shortcode_contact');
function qrm_pro_shortcode_contact() {
    global $wpdb;
    $table_fields = $wpdb->prefix . 'qrm_form_fields';
    $settings = qrm_pro_get_settings();
    $gonderim = qrm_pro_process_classic_form_submission($settings, 'contact');
    $message = $gonderim['message'];
    $show_google_cta = $gonderim['show_google_cta'];
    $cta_avg = $gonderim['cta_avg'];
    $auto_open_reward = $gonderim['auto_open_reward'];

    $active_fields = $wpdb->get_results("SELECT * FROM $table_fields WHERE is_active = 1 ORDER BY sort_order ASC");

    ob_start();
    echo qrm_pro_render_style_block($settings);
    ?>
    <div class="qrm-wrap-full qrm-form-fullbleed">
        <?php echo qrm_pro_render_review_form($settings, $active_fields, $message, $show_google_cta, $cta_avg, 'contact', $auto_open_reward); ?>
    </div>
    <?php
    echo qrm_pro_render_form_script($settings, 0, false);
    return ob_get_clean();
}
