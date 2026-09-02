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
        table_id mediumint(9) DEFAULT NULL,
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
        workflow_status varchar(20) DEFAULT 'new' NOT NULL,
        assigned_user_id bigint(20) unsigned DEFAULT NULL,
        internal_note text NULL,
        resolved_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_status_created (status, created_at),
        KEY idx_created (created_at),
        KEY idx_workflow (workflow_status, created_at),
        KEY idx_table_created (table_id, created_at)
    ) $charset_collate;";
    //
    // İNDEKSLER (v4.2.3) — tabloda uzun süre PRIMARY KEY dışında hiçbir indeks
    // yoktu; üzerindeki her sorgu tam tablo taraması + filesort yapıyordu:
    //
    //   idx_status_created  ->  WHERE status = X ORDER BY created_at DESC LIMIT ...
    //                           (ön yüz yorum listesi: HER ziyaretçide çalışır),
    //                           WHERE status = 1 sayaçları ve yapay zekâ özetinin
    //                           "son N yayındaki yorum" sorgusu.
    //   idx_created         ->  filtresiz yönetim listesinin ORDER BY created_at'i.
    //
    // InnoDB ikincil indekslere birincil anahtarı örtük olarak eklediği için
    // "ORDER BY created_at DESC, id DESC" da bu indekslerden karşılanır; ayrıca
    // bir (…, id) sütunu yazmaya gerek yoktur.

    $sql_fields = "CREATE TABLE $table_fields (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        field_key varchar(50) NOT NULL,
        field_label varchar(255) NOT NULL,
        field_type varchar(50) NOT NULL,
        is_required tinyint(1) DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        sort_order int(11) DEFAULT 0,
        column_width varchar(10) DEFAULT 'full' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY field_key (field_key),
        KEY idx_active_order (is_active, sort_order)
    ) $charset_collate;";
    // idx_active_order: kısa kodun her render'ında çalışan
    // "WHERE is_active = 1 ORDER BY sort_order ASC" sorgusu içindir.

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_reviews);
    dbDelta($sql_fields);

    // Varsayılan form alanları (Eğer boşsa)
    //
    // P1: field_label DB verisidir; CSV'ye item_type=form_field,
    // item_id=satır id, field=label olarak çıkar.
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
            $half_keys = ['customer_name', 'customer_phone', 'table_no'];
            $wpdb->insert($table_fields, [
                'field_key'     => $f[0],
                'field_label'   => $f[1],
                'field_type'    => $f[2],
                'is_required'   => $f[3],
                'is_active'     => $f[4],
                'sort_order'    => $f[5],
                'column_width'  => in_array($f[0], $half_keys, true) ? 'half' : 'full',
            ]);
        }
    }

    // Ayarlar (yeni kurulumda varsayılanlar yazılır)
    add_option('qrm_settings', qrm_pro_default_settings());

    // Ödül modülü tabloları / varsayılan şablonlar (v4.1.0)
    qrm_reward_install();

    // Özel form builder tabloları (v4.2.0)
    qrm_cf_install();

    qrm_pro_migrate_column_widths();

    update_option('qrm_db_version', QRM_PRO_VERSION, false);
}

/**
 * Mevcut alanlara sütun genişliği yazar (bir kez).
 *
 * Eski davranış otomatikti: yorum formunda ad/telefon/masa no, özel formlarda
 * text/email/tel/number/date yarım genişlikti. Yeni alanlar tam genişlik
 * başlar; restoran sahibi her alanı ayrı ayrı seçer.
 *
 * @return void
 */
function qrm_pro_migrate_column_widths() {
    if (get_option('qrm_column_width_migrated') === '1') {
        return;
    }

    global $wpdb;
    $review_fields = $wpdb->prefix . 'qrm_form_fields';
    $custom_fields = qrm_cf_fields_table();

    if (!qrm_pro_table_has_column($review_fields, 'column_width')
        || !qrm_pro_table_has_column($custom_fields, 'column_width')) {
        return;
    }

    $wpdb->query(
        "UPDATE {$review_fields} SET column_width = 'half'
         WHERE field_key IN ('customer_name','customer_phone','table_no')
           AND column_width IN ('', 'full')"
    );
    $wpdb->query(
        "UPDATE {$custom_fields} SET column_width = 'half'
         WHERE field_type IN ('text','email','tel','number','date')
           AND column_width IN ('', 'full')"
    );

    update_option('qrm_column_width_migrated', '1', false);
}

/**
 * Tabloda sütun var mı? dbDelta henüz ALTER edemediyse migration ertelenir.
 *
 * @param string $table
 * @param string $column
 * @return bool
 */
function qrm_pro_table_has_column($table, $column) {
    global $wpdb;
    $suppress = $wpdb->suppress_errors(true);
    $found = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE %s', $column));
    $wpdb->suppress_errors($suppress);
    return !empty($found);
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

/** Yorum istatistiklerinin saklandığı transient. */
const QRM_PRO_STATS_TRANSIENT = 'qrm_pro_review_stats';

/** İstatistik önbelleğinin ömrü. */
const QRM_PRO_STATS_TTL = 5 * MINUTE_IN_SECONDS;

/**
 * Olumlu/olumsuz kırılımının boş hâli.
 *
 * @param float $threshold Olumlu/olumsuz eşiği.
 * @return array{threshold:float,olumlu:array{total:int,approved:int,pending:int},olumsuz:array{total:int,approved:int,pending:int}}
 */
function qrm_pro_empty_sentiment_stats($threshold) {
    $bos = ['total' => 0, 'approved' => 0, 'pending' => 0];

    return [
        'threshold' => (float) $threshold,
        'olumlu'    => $bos,
        'olumsuz'   => $bos,
    ];
}

/**
 * Tablo yokken (ya da hiç yorum yokken) dönen boş istatistik.
 *
 * @param float      $threshold Google eşiği — önbellek karşılaştırmasında kullanılır.
 * @param float|null $sentiment Olumlu/olumsuz eşiği; null ise ayardan/filtreden okunur.
 * @return array
 */
function qrm_pro_empty_review_stats($threshold = 0.0, $sentiment = null) {
    if ($sentiment === null) {
        $sentiment = qrm_pro_sentiment_threshold();
    }

    return [
        'table_ok'        => false,
        'total'           => 0,
        'approved'        => 0,
        'pending'         => 0,
        'avg'             => 0.0,
        'google_eligible' => 0,
        'threshold'       => (float) $threshold,
        'crit'            => [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0],
        'sentiment'       => qrm_pro_empty_sentiment_stats($sentiment),
    ];
}

/**
 * Yorum tablosunun BÜTÜN sayaçlarını TEK sorguda çeker.
 *
 * Eskiden aynı ekranı basmak için tablo altı ila on kez ayrı ayrı taranıyordu:
 * iki COUNT, genel AVG, Google eşiği sayacı ve her kriter için bir AVG daha
 * (kriterlerin beşi de varsayılan olarak açıktır). Aynı desen ön yüzdeki
 * `[qr_menu_reviews]` kısa kodunda da vardı, yani her ZİYARETÇİ için altı
 * aggregate sorgusu açılıyordu — "Too many connections" hatasının en büyük
 * kaynağı buydu.
 *
 * Hepsi tek bir SELECT'te toplandı: MySQL tabloyu bir kez tarar, bağlantı bir
 * kez kullanılır. Kriter ortalamaları AYARDAN BAĞIMSIZ olarak beşi birden
 * hesaplanır — aynı taramanın içinde maliyetsizdir ve önbelleğin ayarlara göre
 * bölünmesini önler; hangi kriterin ekranda görüneceğine sunum katmanı karar
 * verir.
 *
 * Yorum listesinin üç sekmesinin (tümü / olumlu / olumsuz) sayaçları da aynı
 * taramadan gelir: yalnızca "olumlu" kovası sayılır, "olumsuz" ondan
 * çıkarmayla türetilir — nötr kategori olmadığı için ikisi toplamı her zaman
 * toplam kayıt sayısına eşittir.
 *
 * Yalnızca tablonun VAR OLDUĞU bilindiğinde çağrılır. Sorgu okunamazsa null
 * döner — çağıran o durumu "tablo yok" ile karıştırmamalı ve sonucu
 * önbelleğe yazmamalıdır.
 *
 * @param float      $threshold Google yönlendirme eşiği.
 * @param float|null $sentiment Olumlu/olumsuz eşiği; null ise filtreden okunur.
 * @return array|null
 */
function qrm_pro_fetch_review_stats($threshold, $sentiment = null) {
    global $wpdb;

    if ($sentiment === null) {
        $sentiment = qrm_pro_sentiment_threshold();
    }

    $table = $wpdb->prefix . 'qrm_reviews';

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS approved,
            AVG(CASE WHEN status = 1 THEN rating END) AS avg_rating,
            SUM(CASE WHEN status = 1 AND rating >= %f THEN 1 ELSE 0 END) AS google_eligible,
            SUM(CASE WHEN rating >= %f THEN 1 ELSE 0 END) AS positive_total,
            SUM(CASE WHEN status = 1 AND rating >= %f THEN 1 ELSE 0 END) AS positive_approved,
            AVG(CASE WHEN status = 1 AND rating_1 > 0 THEN rating_1 END) AS crit_1,
            AVG(CASE WHEN status = 1 AND rating_2 > 0 THEN rating_2 END) AS crit_2,
            AVG(CASE WHEN status = 1 AND rating_3 > 0 THEN rating_3 END) AS crit_3,
            AVG(CASE WHEN status = 1 AND rating_4 > 0 THEN rating_4 END) AS crit_4,
            AVG(CASE WHEN status = 1 AND rating_5 > 0 THEN rating_5 END) AS crit_5
         FROM {$table}",
        (float) $threshold,
        (float) $sentiment,
        (float) $sentiment
    ), ARRAY_A);

    if (!is_array($row)) {
        return null;
    }

    $total    = (int) $row['total'];
    $approved = (int) $row['approved'];

    $crit = [];
    for ($i = 1; $i <= 5; $i++) {
        // Hiç oy almamış kriterde AVG() NULL döner; (float) onu 0.0 yapar.
        $crit[$i] = (float) $row['crit_' . $i];
    }

    $olumlu_total    = isset($row['positive_total']) ? (int) $row['positive_total'] : 0;
    $olumlu_approved = isset($row['positive_approved']) ? (int) $row['positive_approved'] : 0;

    return [
        'table_ok'        => true,
        'total'           => $total,
        'approved'        => $approved,
        'pending'         => $total - $approved,
        'avg'             => (float) $row['avg_rating'],
        'google_eligible' => (int) $row['google_eligible'],
        'threshold'       => (float) $threshold,
        'crit'            => $crit,
        'sentiment'       => [
            'threshold' => (float) $sentiment,
            'olumlu'    => [
                'total'    => $olumlu_total,
                'approved' => $olumlu_approved,
                'pending'  => $olumlu_total - $olumlu_approved,
            ],
            'olumsuz'   => [
                'total'    => $total - $olumlu_total,
                'approved' => $approved - $olumlu_approved,
                'pending'  => ($total - $olumlu_total) - ($approved - $olumlu_approved),
            ],
        ],
    ];
}

/**
 * Yorum sayaçları — başlangıç ekranı, yorum listesinin sekmeleri, sol menü
 * rozeti ve ön yüzdeki kısa kod hepsi buradan okur.
 *
 * İki katmanlı önbellek: istek içinde statik memo (aynı sayfa birden çok kez
 * sorar), istekler arasında kısa ömürlü transient. Sayılar yorum eklendiğinde
 * ya da durumu değiştiğinde qrm_pro_flush_review_stats() ile geçersizlenir,
 * yani TTL yalnızca bir emniyet kemeridir.
 *
 * Google eşiği ayardan, olumlu/olumsuz eşiği qrm_pro_sentiment_threshold()'tan
 * gelir; ikisinden biri değiştiğinde saklanan sonuç kendiliğinden kabul edilmez
 * (aşağıdaki eşik karşılaştırmaları) — ayrı bir kanca gerekmez.
 *
 * @param bool $taze true ise önbellekler atlanır (test ve elle tazeleme için).
 * @return array{table_ok:bool,total:int,approved:int,pending:int,avg:float,google_eligible:int,threshold:float,crit:array<int,float>,sentiment:array}
 */
function qrm_pro_review_stats($taze = false) {
    // Memo bilinçli olarak $GLOBALS'ta durur, fonksiyon içi static'te değil:
    // qrm_pro_flush_review_stats() aynı istek içinde onu da temizleyebilmeli
    // (ör. yorum onaylandıktan hemen sonra basılan sayaçlar).
    $memo = isset($GLOBALS['qrm_pro_stats_memo']) ? $GLOBALS['qrm_pro_stats_memo'] : null;

    if (!$taze && is_array($memo)) {
        return $memo;
    }

    $settings  = qrm_pro_get_settings();
    $threshold = (float) $settings['google_review_threshold'];
    $sentiment = qrm_pro_sentiment_threshold();

    // Önbellek, tablo denetiminden ÖNCE okunur: saklanan sonuç yalnızca tablo
    // varken ve sorgu okunabildiğinde yazılır, yani isabet eden bir kayıt zaten
    // "tablo var" demektir. Sıra tersi olsaydı sol menüdeki rozet sayacı her
    // yönetim isteğine bir SHOW TABLES sorgusu eklerdi.
    if (!$taze) {
        $cached = get_transient(QRM_PRO_STATS_TRANSIENT);

        if (is_array($cached) && isset($cached['threshold'], $cached['crit'], $cached['sentiment']['threshold'])
            && abs((float) $cached['threshold'] - $threshold) < 0.0001
            && abs((float) $cached['sentiment']['threshold'] - $sentiment) < 0.0001) {
            $GLOBALS['qrm_pro_stats_memo'] = $cached;
            return $cached;
        }
    }

    if (!qrm_pro_reviews_table_exists()) {
        $GLOBALS['qrm_pro_stats_memo'] = qrm_pro_empty_review_stats($threshold, $sentiment);
        return $GLOBALS['qrm_pro_stats_memo'];
    }

    $stats = qrm_pro_fetch_review_stats($threshold, $sentiment);

    // Sorgu okunamadı: tablo VAR olduğu için ekranlar "tablo bulunamadı"
    // uyarısını basmamalı — sayaçlar sıfır görünür. Böyle bir sonuç
    // önbelleğe de yazılmaz, sonraki istek yeniden dener.
    if (!is_array($stats)) {
        $stats             = qrm_pro_empty_review_stats($threshold, $sentiment);
        $stats['table_ok'] = true;

        $GLOBALS['qrm_pro_stats_memo'] = $stats;

        return $stats;
    }

    set_transient(QRM_PRO_STATS_TRANSIENT, $stats, QRM_PRO_STATS_TTL);
    $GLOBALS['qrm_pro_stats_memo'] = $stats;

    return $stats;
}

/**
 * İstatistik önbelleğini geçersizler.
 *
 * Yorum eklendiğinde, onaylandığında, yayından kaldırıldığında ve silindiğinde
 * çağrılır — sayaçlar bu yüzden TTL'i beklemeden tazelenir.
 *
 * @return void
 */
function qrm_pro_flush_review_stats() {
    unset($GLOBALS['qrm_pro_stats_memo']);
    delete_transient(QRM_PRO_STATS_TRANSIENT);
    if (function_exists('qrm_pro_flush_trend_drop_cache')) {
        qrm_pro_flush_trend_drop_cache();
    }
}
