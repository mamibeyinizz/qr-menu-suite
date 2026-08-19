<?php
if (!defined('ABSPATH')) exit;

// ÖZEL FORM BUILDER: TEK GENEL SHORTCODE (v4.2.0)
//
//   [qr_menu_form key="sikayet-formu"]
//
// Yorum ([qr_menu_reviews]) ve İletişim ([qr_menu_contact]) shortcode'ları
// kendi özel mantıklarıyla çalışmaya devam eder; bu shortcode onlardan bağımsızdır.

add_shortcode('qr_menu_form', 'qrm_pro_shortcode_custom_form');

function qrm_pro_shortcode_custom_form($atts) {
    $atts = shortcode_atts(['key' => ''], $atts, 'qr_menu_form');
    $key  = sanitize_key($atts['key']);

    if ($key === '') {
        return qrm_cf_shortcode_notice('Kısa koda bir form anahtarı verilmemiş. Örnek: [qr_menu_form key="sikayet-formu"]');
    }

    $form = qrm_cf_get_form_by_key($key);
    if (!$form) {
        return qrm_cf_shortcode_notice(sprintf('Form bulunamadı: "%s". Form Oluşturucu sayfasından anahtarı kontrol edin.', $key));
    }
    if ($form->status !== 'active') {
        return qrm_cf_shortcode_notice(sprintf(
            'Form bulunamadı veya yayında değil: "%s" (durum: %s). Yayına almak için formu Aktif yapın.',
            $key,
            qrm_cf_status_label($form->status)
        ));
    }

    $fields = qrm_cf_get_fields($form->id);
    if (!$fields) {
        return qrm_cf_shortcode_notice(sprintf('"%s" formunda hiç alan tanımlı değil.', $form->title));
    }

    $settings = qrm_cf_get_form_settings($form);

    ob_start();
    echo qrm_cf_form_style_block($form->id, $settings);
    echo qrm_cf_render_form($form, $fields, $settings);
    echo qrm_cf_render_form_script($form, $settings);
    return ob_get_clean();
}

/**
 * Sorun mesajını yalnızca yetkili kullanıcıya gösterir; ziyaretçiye boş çıktı verir.
 * Böylece taslak/hatalı bir shortcode müşteriye asla teknik metin göstermez.
 */
function qrm_cf_shortcode_notice($message) {
    if (!current_user_can('edit_posts')) {
        return '';
    }
    return '<div style="padding:14px 16px;border:1px dashed #f59e0b;background:#fffbeb;color:#92400e;border-radius:8px;font-size:14px;margin:12px 0;">'
        . '<strong>QR Menü Form:</strong> ' . esc_html($message)
        . ' <em style="opacity:.75;">(Bu uyarı yalnızca yönetici/editör yetkisi olan kullanıcılara görünür.)</em></div>';
}
