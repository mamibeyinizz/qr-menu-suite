<?php
if (!defined('ABSPATH')) exit;

// 2. ADMİN MENÜSÜ
//
// v4.2.1: Menü sadeleştirildi. Alt sayfa sayısı 8'den 5'e indi; kaldırılan sayfalar
// yok olmadı, ilgili sayfaların sekmesi hâline geldi:
//   Müşteri Bilgileri Formu -> Yorum Formu Ayarları > "Müşteri Bilgileri Formu" sekmesi
//   Detaylı İçgörüler       -> Tüm Yorumlar > "İçgörüler" sekmesi
//   Form Gönderileri        -> Formlar > "Gönderiler" sekmesi
//   Form Düzenle            -> Formlar sayfasının bir görünümü (?page=qrm-forms&view=edit)
//
// ÖNEMLİ (v4.2.0'daki 403 hatasının dersi): Bir admin sayfası ASLA add_submenu_page()
// ile kaydedilip sonra remove_submenu_page() ile menüden çıkarılmamalı. WordPress,
// admin.php?page=X isteklerinde üst menüyü $submenu dizisini tarayarak bulur
// (get_admin_page_parent()); satır kaldırılınca parent boş kalır, hook adı
// "qr-yorumlar_page_X" yerine "admin_page_X" hesaplanır, $_registered_pages'te
// karşılığı bulunamaz ve WordPress "bu sayfaya erişmenize izin verilmiyor" der.
// Bu yüzden gizli sayfa yerine, kayıtlı bir sayfanın görünümü (view parametresi) kullanılır.

add_action('admin_menu', 'qrm_pro_menu');
function qrm_pro_menu() {
    // v4.1.0: Ödül modülü açık ama yapılandırması eksikse (Google linki boş ya da
    // yönlendirme kapalı) popup hiç gösterilmez. Bunun sessizce fark edilmemesini
    // önlemek için menüde standart WordPress uyarı rozeti gösterilir.
    $qrm_settings = qrm_pro_get_settings();
    $reward_badge = '';
    if (!empty($qrm_settings['qrm_reward_enabled']) && !qrm_reward_is_active($qrm_settings)) {
        $reward_badge = ' <span class="update-plugins count-1" title="Ödül popup modülü açık ama Google Yorum Linki/yönlendirme eksik"><span class="update-count">!</span></span>';
    }

    // v4.2.0: okunmamış özel form gönderimi varsa, admin panele her girişte
    // fark edilsin diye standart WP sayı rozeti gösterilir.
    $unread = qrm_cf_unread_total();
    $forms_badge = $unread > 0
        ? ' <span class="update-plugins count-' . intval($unread) . '" title="Okunmamış form gönderimi"><span class="update-count">' . intval($unread) . '</span></span>'
        : '';

    add_menu_page('QR Yorumlar', 'QR Yorumlar' . $reward_badge . $forms_badge, 'manage_options', 'qrm-pro-main', 'qrm_pro_admin_dashboard', 'dashicons-star-filled', 25);
    add_submenu_page('qrm-pro-main', 'Tüm Yorumlar', 'Tüm Yorumlar', 'manage_options', 'qrm-pro-main', 'qrm_pro_admin_dashboard');
    add_submenu_page('qrm-pro-main', 'Yorum Formu Ayarları', 'Yorum Formu Ayarları', 'manage_options', 'qrm-pro-settings', 'qrm_pro_admin_settings');
    add_submenu_page('qrm-pro-main', 'İletişim Formu', 'İletişim', 'manage_options', 'qrm-pro-contact', 'qrm_pro_admin_contact');
    add_submenu_page('qrm-pro-main', 'Google & Ödül Sistemi', 'Google & Ödül Sistemi' . $reward_badge, 'manage_options', 'qrm-pro-rewards', 'qrm_reward_admin_page');
    add_submenu_page('qrm-pro-main', 'Formlar', 'Formlar' . $forms_badge, 'manage_options', 'qrm-forms', 'qrm_cf_admin_forms_page');
}

/**
 * v4.2.1: Sadeleştirmeden önceki sayfa adreslerine yer imi/eski link ile gelenler
 * kayıtlı olmayan bir slug'a düşüp 403 almasın diye yeni adreslerine yönlendirilir.
 *
 * Kanca seçimi önemli: wp-admin/admin.php, menüyü kuran wp-admin/menu.php'yi
 * do_action('admin_init')'ten ÖNCE yükler ve erişim kontrolünü orada yapar. Bu yüzden
 * admin_init çok geç kalır; WordPress'in 403'ten hemen önce tetiklediği
 * admin_page_access_denied kancası kullanılır (henüz çıktı basılmadığı için
 * yönlendirme güvenle yapılabilir).
 */
add_action('admin_page_access_denied', 'qrm_pro_redirect_legacy_pages');
function qrm_pro_redirect_legacy_pages() {
    if (empty($_GET['page'])) return;

    $target = qrm_pro_legacy_page_target(
        sanitize_key($_GET['page']),
        isset($_GET['form_id']) ? intval($_GET['form_id']) : 0
    );
    if ($target === '') return;

    wp_safe_redirect(admin_url($target));
    exit;
}

/**
 * Eski sayfa slug'ının yeni adresi. Bilinmeyen/geçerli slug için boş string döner.
 * (Saf fonksiyon: yönlendirme ve exit çağıranın işi, böylece test edilebilir.)
 */
function qrm_pro_legacy_page_target($page, $form_id = 0) {
    $map = [
        'qrm-pro-form'         => 'admin.php?page=qrm-pro-settings&sub=alanlar',
        'qrm-pro-insights'     => 'admin.php?page=qrm-pro-main&tab=insights',
        'qrm-form-submissions' => 'admin.php?page=qrm-forms&tab=submissions',
        'qrm-form-edit'        => 'admin.php?page=qrm-forms&view=edit',
    ];

    if (!isset($map[$page])) return '';

    $target = $map[$page];
    if ($form_id > 0 && in_array($page, ['qrm-form-edit', 'qrm-form-submissions'], true)) {
        $target .= '&form_id=' . $form_id;
    }
    return $target;
}

add_action('admin_enqueue_scripts', 'qrm_pro_admin_scripts');
function qrm_pro_admin_scripts($hook) {
    // v4.2.0: form builder sayfalarının slug'ı qrm-forms olduğu için koşul
    // "qrm-pro" yerine ortak "qrm-" ön ekine bakar.
    if (strpos($hook, 'qrm-') !== false) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('jquery-ui-sortable');
        wp_add_inline_script('jquery-ui-sortable', "
            jQuery(document).ready(function($){
                $('.qrm-color-picker').wpColorPicker();
                // v4.2.1: Sıralama artık formu otomatik göndermiyor; alan ayarları
                // 'Yorum Formu Ayarları' sayfasındaki tek Kaydet butonuyla kaydedilir.
                $('#qrm-sortable-fields').sortable({
                    handle: '.qrm-field-handle',
                    update: function(event, ui) {
                        $('#qrm-sort-hint').slideDown(120);
                    }
                });
            });
        ");
        wp_add_inline_style('wp-admin', "
            .qrm-pro-wrap { max-width: 1200px; margin-top: 20px; }
            .qrm-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
            .qrm-field-row { background: #f9fafb; border: 1px solid #e5e7eb; padding: 15px; margin-bottom: 10px; border-radius: 6px; display: flex; align-items: center; gap: 15px; }
            .qrm-field-row input[type=text] { flex-grow: 1; }
            .qrm-field-handle { cursor: move; color:#9ca3af; }
            .qrm-breakdown { font-size: 11px; color: #6b7280; display: block; margin-top: 4px; }
            .qrm-insight-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;}
            .qrm-stat-box { background: #fff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .qrm-stat-box h3 { margin:0 0 10px; font-size:14px; color:#6b7280; }
            .qrm-stat-box .value { font-size: 32px; font-weight: bold; color: #111827; }
            .qrm-google-pill { display:inline-block; margin-left:6px; background:#e8f0fe; color:#1a73e8; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; vertical-align:middle; }
            .qrm-code-pill { display:inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight:700; letter-spacing:1px; background:#f3f4f6; border:1px dashed #9ca3af; border-radius:6px; padding:3px 9px; }
            .qrm-status-active { color:#166534; font-weight:700; }
            .qrm-status-used { color:#6b7280; font-weight:700; }
            .qrm-status-revoked { color:#b91c1c; font-weight:700; }
            .qrm-status-expired { color:#b45309; font-weight:700; }
        ");
    }
}
