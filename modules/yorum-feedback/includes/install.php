<?php
if (!defined('ABSPATH')) exit;

// 1. VERİTABANI VE KURULUM
function qrm_pro_install() {
    global $wpdb;
    $table_reviews = $wpdb->prefix . 'qrm_reviews';
    $table_fields = $wpdb->prefix . 'qrm_form_fields';
    $charset_collate = $wpdb->get_charset_collate();

    // Veritabanı tablosuna rating_1, rating_2 vb. eklendi. rating sütunu ortalamayı tutar.
    $sql_reviews = "CREATE TABLE $table_reviews (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        customer_name varchar(255) DEFAULT '',
        customer_phone varchar(50) DEFAULT '',
        table_no varchar(50) DEFAULT '',
        is_anonymous tinyint(1) DEFAULT 0,
        rating float NOT NULL,
        rating_1 tinyint(1) DEFAULT 0,
        rating_2 tinyint(1) DEFAULT 0,
        rating_3 tinyint(1) DEFAULT 0,
        rating_4 tinyint(1) DEFAULT 0,
        rating_5 tinyint(1) DEFAULT 0,
        comment text NOT NULL,
        status tinyint(1) DEFAULT 0 NOT NULL,
        sentiment varchar(20) DEFAULT 'neutral',
        is_manual tinyint(1) DEFAULT 0,
        form_source varchar(20) DEFAULT 'review' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql_fields = "CREATE TABLE $table_fields (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        field_key varchar(50) NOT NULL,
        field_label varchar(255) NOT NULL,
        field_type varchar(50) NOT NULL,
        is_required tinyint(1) DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        sort_order int(11) DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY field_key (field_key)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_reviews);
    dbDelta($sql_fields);

    // Varsayılan form alanları (Eğer boşsa)
    $field_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_fields");
    if ($field_count == 0) {
        $default_fields = [
            ['customer_name', 'Adınız Soyadınız', 'text', 0, 1, 1],
            ['customer_phone', 'Telefon Numaranız', 'text', 0, 1, 2],
            ['table_no', 'Masa No', 'text', 1, 1, 3],
            ['comment', 'Yorumunuz', 'textarea', 1, 1, 4],
            ['is_anonymous', 'İsmimi gizle (Anonim kal)', 'checkbox', 0, 1, 5]
        ];
        foreach ($default_fields as $f) {
            $wpdb->insert($table_fields, [
                'field_key' => $f[0], 'field_label' => $f[1], 'field_type' => $f[2],
                'is_required' => $f[3], 'is_active' => $f[4], 'sort_order' => $f[5]
            ]);
        }
    }

    // Ayarlar (yeni kurulumda varsayılanlar yazılır)
    add_option('qrm_settings', qrm_pro_default_settings());

    // Ödül modülü tabloları / varsayılan şablonlar (v4.1.0)
    qrm_reward_install();

    // Özel form builder tabloları (v4.2.0)
    qrm_cf_install();

    update_option('qrm_db_version', QRM_PRO_VERSION, false);
}

/**
 * Yorum tablosu veritabanında gerçekten var mı?
 *
 * Boş bir liste iki farklı sebepten gelebilir: hiç yorum yoktur ya da tablo hiç
 * oluşmamıştır (ör. dbDelta CREATE yetkisi bulamamıştır). İkisi ekranda birebir
 * aynı görünüyordu — "sorgu çalışmıyor" şüphesinin kaynağı buydu. Ekranlar artık
 * bu kontrolle ikisini ayırıp farklı mesaj basar.
 *
 * Sonuç istek başına önbelleklenir; her ekran birden çok kez sorabilir.
 *
 * @return bool
 */
function qrm_pro_reviews_table_exists() {
    static $exists = null;
    if ($exists !== null) return $exists;

    global $wpdb;
    $table = $wpdb->prefix . 'qrm_reviews';

    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    $exists = ($found === $table);

    return $exists;
}

/**
 * Yorum sayaçları — başlangıç ekranı ve İçgörüler aynı kaynaktan okur.
 *
 * @return array{table_ok:bool,total:int,approved:int,pending:int,avg:float}
 */
function qrm_pro_review_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'qrm_reviews';

    if (!qrm_pro_reviews_table_exists()) {
        return ['table_ok' => false, 'total' => 0, 'approved' => 0, 'pending' => 0, 'avg' => 0.0];
    }

    $total    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $approved = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 1");

    return [
        'table_ok' => true,
        'total'    => $total,
        'approved' => $approved,
        'pending'  => $total - $approved,
        'avg'      => (float) $wpdb->get_var("SELECT AVG(rating) FROM $table WHERE status = 1"),
    ];
}
