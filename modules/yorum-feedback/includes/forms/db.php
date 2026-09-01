<?php
if (!defined('ABSPATH')) exit;

// ÖZEL FORM BUILDER MODÜLÜ (v4.2.0): TABLO ADLARI VE KURULUM
//
// Bu modül, koda gömülü yorum/iletişim formlarından tamamen bağımsızdır.
// Yorum formu kendi qrm_reviews / qrm_form_fields tablolarını kullanmaya devam eder;
// buradaki üç tablo yalnızca admin panelinden oluşturulan özel formlara aittir.

function qrm_cf_forms_table() {
    global $wpdb;
    return $wpdb->prefix . 'qrm_custom_forms';
}

function qrm_cf_fields_table() {
    global $wpdb;
    return $wpdb->prefix . 'qrm_custom_form_fields';
}

function qrm_cf_submissions_table() {
    global $wpdb;
    return $wpdb->prefix . 'qrm_custom_form_submissions';
}

/**
 * Özel form tablolarını kurar. qrm_pro_install() (register_activation_hook ve
 * sürüm değişiminde qrm_pro_maybe_upgrade) içinden çağrılır.
 */
function qrm_cf_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $forms       = qrm_cf_forms_table();
    $fields      = qrm_cf_fields_table();
    $submissions = qrm_cf_submissions_table();

    // form_key shortcode'da kullanılır: [qr_menu_form key="sikayet-formu"]
    $sql_forms = "CREATE TABLE $forms (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        form_key varchar(50) NOT NULL,
        title varchar(255) NOT NULL,
        description text,
        status varchar(20) DEFAULT 'active' NOT NULL,
        settings longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY form_key (form_key)
    ) $charset_collate;";

    // options: select/radio/checkbox seçenekleri JSON dizisi olarak tutulur.
    $sql_fields = "CREATE TABLE $fields (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        form_id mediumint(9) NOT NULL,
        field_key varchar(50) NOT NULL,
        label varchar(255) NOT NULL,
        field_type varchar(30) NOT NULL,
        options text,
        is_required tinyint(1) DEFAULT 0,
        sort_order int(11) DEFAULT 0,
        column_width varchar(10) DEFAULT 'full' NOT NULL,
        PRIMARY KEY  (id),
        KEY form_id (form_id)
    ) $charset_collate;";

    // data: {field_key: value, ...} JSON. Alan seti zamanla değişebileceği için
    // gönderimler sabit sütunlara değil, JSON'a yazılır.
    $sql_submissions = "CREATE TABLE $submissions (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        form_id mediumint(9) NOT NULL,
        data longtext NOT NULL,
        status varchar(20) DEFAULT 'new' NOT NULL,
        ip_address varchar(100) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY form_id (form_id),
        KEY status (status),
        KEY idx_form_status_created (form_id, status, created_at),
        KEY idx_form_created (form_id, created_at)
    ) $charset_collate;";
    //
    // İNDEKSLER (v4.2.3): tek sütunluk form_id / status indeksleri gönderim
    // listesinin gerçek sorgusunu karşılamıyordu — filtre iki sütunu birden
    // kullanıp üçüncüsüne göre sıralıyor, yani her sayfada filesort çıkıyordu:
    //   idx_form_status_created -> WHERE form_id = %d AND status = %s ORDER BY created_at DESC
    //   idx_form_created        -> WHERE form_id = %d ORDER BY created_at DESC (durum filtresi yokken)
    //
    // Eski form_id indeksi artık idx_form_created'ın önekiyle aynı işi görüyor
    // ama BİLEREK bırakıldı: dbDelta indeks düşürmez, elle DROP etmek de eski
    // kurulumlarda geri dönüşü olmayan bir şema müdahalesi olurdu.

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_forms);
    dbDelta($sql_fields);
    dbDelta($sql_submissions);

    // Modül sürümü ayrı tutulur: eklenti sürümü değişmeden bu modül eklendiği için
    // qrm_pro_maybe_upgrade() tabloların oluşup oluşmadığını bu option'dan anlar.
    update_option('qrm_cf_db_version', QRM_PRO_VERSION, false);
}
