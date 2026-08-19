<?php
if (!defined('ABSPATH')) exit;

// ÖDÜL MODÜLÜ: TABLO ADI VE KURULUM

function qrm_reward_table() {
    global $wpdb;
    return $wpdb->prefix . 'qrm_reward_codes';
}

/**
 * Ödül kodları tablosunu ve varsayılan indirim şablonlarını kurar.
 * qrm_pro_install() içinden (register_activation_hook) çağrılır.
 *
 * Not: email sütunu NULL kabul eder. MySQL'de NULL değerler UNIQUE KEY
 * kısıtına takılmaz; böylece "1 e-posta = 1 kod" kuralı korunurken
 * e-postasız manuel kampanya kodları da üretilebilir.
 */
function qrm_reward_install() {
    global $wpdb;
    $table = qrm_reward_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        email varchar(191) DEFAULT NULL,
        code varchar(50) NOT NULL,
        template_id varchar(50) DEFAULT '',
        discount_label varchar(255) DEFAULT '',
        status varchar(20) DEFAULT 'active' NOT NULL,
        is_manual tinyint(1) DEFAULT 0,
        used_at datetime DEFAULT NULL,
        source_review_id mediumint(9) DEFAULT NULL,
        ip_address varchar(100) DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY email (email),
        UNIQUE KEY code (code)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Fabrika ayarı: aktif + varsayılan bir "Standart %10" şablonu her zaman hazır gelsin,
    // böylece kullanıcı yalnızca Google linkini girerek kurulumu tamamlayabilir.
    // (Yalnızca option hiç yokken ya da bozuk/dizi olmayan bir değer taşırken yazılır;
    // adminin bilerek boşalttığı liste geri getirilmez.)
    $templates = get_option('qrm_reward_templates', null);
    if (!is_array($templates)) {
        update_option('qrm_reward_templates', qrm_reward_default_templates(), false);
    }
}

function qrm_reward_default_templates() {
    return [
        [
            'id'         => 'std10',
            'name'       => 'Standart %10',
            'percent'    => 10,
            'active'     => 1,
            'is_default' => 1,
        ],
        [
            'id'         => 'vip15',
            'name'       => 'VIP %15',
            'percent'    => 15,
            'active'     => 0,
            'is_default' => 0,
        ],
    ];
}
