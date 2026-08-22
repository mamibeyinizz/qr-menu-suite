<?php
/**
 * Ürün Vitrini — tablo şeması, ayar temizliği ve veri erişimi.
 *
 * Vitrin ÜRÜN VERİSİ TUTMAZ: satırlarda yalnızca `rma_menu_item` post ID'si
 * ve sıra numarası durur. Başlık, fiyat ve görsel her render'da CPT'den
 * canlı okunur — ürün düzenlendiğinde vitrin kendiliğinden güncel kalır.
 *
 * Şema, suite'in mevcut "ana tablo + alt tablo" desenini izler
 * (bkz. modules/yorum-feedback/includes/forms/db.php).
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Vitrin_DB' ) ) :

class RMA_Vitrin_DB {

    /** Şema sürümü. Değişince tablolar dbDelta ile tazelenir. */
    const DB_VERSION = '1.1.0';

    /** Sürümün saklandığı option adı. */
    const VERSION_OPTION = 'rma_vitrin_db_version';

    /** Sütun/satır ve hız sınırları — hem admin formu hem sanitize burayı kullanır. */
    const MIN_COLUMNS = 1;
    const MAX_COLUMNS = 6;
    const MIN_ROWS    = 1;
    const MAX_ROWS    = 3;
    const MIN_SPEED   = 1000;
    const MAX_SPEED   = 15000;

    /* Mobilde yan yana görünecek kart sayısı — masaüstü `grid_columns`'tan
       BAĞIMSIZ, ayrı bir sınır: mobilde 3'ten fazla sütun kart genişliğini
       okunmaz hâle getirir (bkz. vitrin.css --qrms-vitrin-card-min). */
    const MIN_MOBILE_COLUMNS = 1;
    const MAX_MOBILE_COLUMNS = 3;

    /**
     * Vitrin tanımları tablosu.
     *
     * @return string
     */
    public static function tablo() {
        global $wpdb;
        return $wpdb->prefix . 'rma_showcases';
    }

    /**
     * Vitrin-ürün bağlantıları tablosu.
     *
     * @return string
     */
    public static function urun_tablo() {
        global $wpdb;
        return $wpdb->prefix . 'rma_showcase_items';
    }

    /**
     * Tabloları oluşturur/tazeler.
     *
     * Suite modüllerinin kendi register_activation_hook'u yoktur (loader
     * plugins_loaded'da yükler), bu yüzden qr-ceviri'deki gibi sürüm
     * guard'ıyla admin_init'ten çağrılır.
     *
     * @return void
     */
    public static function tablo_kur() {
        global $wpdb;

        $tablo   = self::tablo();
        $urunler = self::urun_tablo();
        $collate = $wpdb->get_charset_collate();

        // Ayarlar JSON blob yerine tipli sütunlarda: hepsi sayı/bayrak,
        // doğrudan sorgulanabilir olmaları teşhisi kolaylaştırıyor.
        // `columns` ve `rows` MySQL'de ayrılmış sözcük değildir ama
        // okunurluk için sorgu tarafında backtick ile yazılır.
        $sql_vitrin = "CREATE TABLE {$tablo} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            grid_columns tinyint(2) unsigned NOT NULL DEFAULT 4,
            grid_rows tinyint(2) unsigned NOT NULL DEFAULT 1,
            mobile_columns tinyint(2) unsigned NOT NULL DEFAULT 2,
            autoplay tinyint(1) NOT NULL DEFAULT 0,
            autoplay_speed smallint(5) unsigned NOT NULL DEFAULT 4000,
            drag_enabled tinyint(1) NOT NULL DEFAULT 1,
            show_price tinyint(1) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) {$collate};";

        // UNIQUE showcase_product: aynı ürün bir vitrine iki kez eklenemez.
        $sql_urunler = "CREATE TABLE {$urunler} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            showcase_id mediumint(9) NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY showcase_product (showcase_id, product_id),
            KEY showcase_order (showcase_id, sort_order)
        ) {$collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_vitrin );
        dbDelta( $sql_urunler );

        update_option( self::VERSION_OPTION, self::DB_VERSION, false );
    }

    /**
     * Şema güncel değilse kurar. admin_init'ten çağrılır.
     *
     * @return void
     */
    public static function belki_kur() {
        if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        self::tablo_kur();
    }

    /* =================================================================
       AYAR TEMİZLİĞİ
       WordPress'e bağımlılığı olmayan saf dönüşümler — doğrudan test edilir.
    ================================================================= */

    /**
     * Vitrin varsayılanları.
     *
     * @return array<string,int|string>
     */
    public static function varsayilanlar() {
        return array(
            'title'          => '',
            'grid_columns'   => 4,
            'grid_rows'      => 1,
            'mobile_columns' => 2,
            'autoplay'       => 0,
            'autoplay_speed' => 4000,
            'drag_enabled'   => 1,
            'show_price'     => 1,
        );
    }

    /**
     * Ham form girdisini şemaya uygun, sınırlanmış değerlere çevirir.
     *
     * Sınır dışı ve anlamsız girdiler (boş, metin, negatif) sessizce
     * varsayılana/sınıra çekilir: vitrin ayarları tek tek doğrulama
     * mesajı gösterilecek kadar kritik değil, ama şemayı bozmamalı.
     *
     * @param array $ham Ham `$_POST` benzeri dizi.
     * @return array<string,int|string> Şema sütunlarıyla birebir eşleşen dizi.
     */
    public static function ayarlari_temizle( array $ham ) {
        $v = self::varsayilanlar();

        $baslik = isset( $ham['title'] ) ? trim( (string) $ham['title'] ) : '';

        return array(
            'title'          => '' !== $baslik ? $baslik : 'Ürün Vitrini',
            'grid_columns'   => self::sinirla( $ham['grid_columns'] ?? $v['grid_columns'], self::MIN_COLUMNS, self::MAX_COLUMNS, $v['grid_columns'] ),
            'grid_rows'      => self::sinirla( $ham['grid_rows'] ?? $v['grid_rows'], self::MIN_ROWS, self::MAX_ROWS, $v['grid_rows'] ),
            'mobile_columns' => self::sinirla( $ham['mobile_columns'] ?? $v['mobile_columns'], self::MIN_MOBILE_COLUMNS, self::MAX_MOBILE_COLUMNS, $v['mobile_columns'] ),
            'autoplay'       => self::bayrak( $ham['autoplay'] ?? 0 ),
            'autoplay_speed' => self::sinirla( $ham['autoplay_speed'] ?? $v['autoplay_speed'], self::MIN_SPEED, self::MAX_SPEED, $v['autoplay_speed'] ),
            'drag_enabled'   => self::bayrak( $ham['drag_enabled'] ?? 0 ),
            'show_price'     => self::bayrak( $ham['show_price'] ?? 0 ),
        );
    }

    /**
     * Sayısal değeri [min,max] aralığına çeker.
     *
     * Sayı olmayan girdi varsayılana düşer; aralık dışı sayı en yakın
     * sınıra kırpılır.
     *
     * @param mixed $deger      Ham değer.
     * @param int   $min        Alt sınır.
     * @param int   $max        Üst sınır.
     * @param int   $varsayilan Sayı olmayan girdide kullanılacak değer.
     * @return int
     */
    public static function sinirla( $deger, $min, $max, $varsayilan ) {
        if ( ! is_numeric( $deger ) ) {
            return (int) $varsayilan;
        }

        return (int) max( $min, min( $max, (int) $deger ) );
    }

    /**
     * Checkbox girdisini 0/1'e çevirir.
     *
     * @param mixed $deger Ham değer.
     * @return int
     */
    public static function bayrak( $deger ) {
        return ( '1' === (string) $deger || 1 === $deger || true === $deger || 'on' === $deger || 'yes' === $deger ) ? 1 : 0;
    }

    /**
     * Ürün ID listesini temizler: pozitif tamsayı, tekrarsız, sırası korunur.
     *
     * @param mixed $ham Virgülle ayrılmış dize ya da dizi.
     * @return int[]
     */
    public static function urun_idlerini_temizle( $ham ) {
        if ( is_string( $ham ) ) {
            $ham = '' === trim( $ham ) ? array() : explode( ',', $ham );
        }

        if ( ! is_array( $ham ) ) {
            return array();
        }

        $temiz = array();

        foreach ( $ham as $id ) {
            $id = (int) $id;

            if ( $id > 0 && ! in_array( $id, $temiz, true ) ) {
                $temiz[] = $id;
            }
        }

        return $temiz;
    }

    /* =================================================================
       CRUD
    ================================================================= */

    /**
     * Tüm vitrinler — ürün sayılarıyla birlikte.
     *
     * Ürün sayısı alt sorguyla tek seferde gelir (liste ekranında N+1 yok).
     *
     * @return array<int,object>
     */
    public static function hepsi() {
        global $wpdb;

        $tablo   = self::tablo();
        $urunler = self::urun_tablo();

        return (array) $wpdb->get_results(
            "SELECT v.*, ( SELECT COUNT(*) FROM {$urunler} u WHERE u.showcase_id = v.id ) AS urun_sayisi
             FROM {$tablo} v
             ORDER BY v.id DESC"
        );
    }

    /**
     * Tek vitrin.
     *
     * @param int $id Vitrin ID.
     * @return object|null
     */
    public static function getir( $id ) {
        global $wpdb;

        $id = (int) $id;

        if ( $id <= 0 ) {
            return null;
        }

        $tablo = self::tablo();

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tablo} WHERE id = %d", $id ) );
    }

    /**
     * Vitrindeki ürün ID'leri — kaydedilmiş sıraya göre.
     *
     * @param int $id Vitrin ID.
     * @return int[]
     */
    public static function urun_idleri( $id ) {
        global $wpdb;

        $id = (int) $id;

        if ( $id <= 0 ) {
            return array();
        }

        $urunler = self::urun_tablo();

        $satirlar = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT product_id FROM {$urunler} WHERE showcase_id = %d ORDER BY sort_order ASC, id ASC",
                $id
            )
        );

        return array_map( 'intval', $satirlar );
    }

    /**
     * Vitrin oluşturur ya da günceller ve ürün listesini yeniden yazar.
     *
     * @param int   $id         0 ise yeni kayıt açılır.
     * @param array $ayarlar    ayarlari_temizle() çıktısı.
     * @param int[] $urun_idler Sıralı ürün ID listesi.
     * @return int Kaydın ID'si; hata hâlinde 0.
     */
    public static function kaydet( $id, array $ayarlar, array $urun_idler ) {
        global $wpdb;

        $id    = (int) $id;
        $tablo = self::tablo();
        $simdi = current_time( 'mysql' );

        $veri = array(
            'title'          => $ayarlar['title'],
            'grid_columns'   => $ayarlar['grid_columns'],
            'grid_rows'      => $ayarlar['grid_rows'],
            'mobile_columns' => $ayarlar['mobile_columns'],
            'autoplay'       => $ayarlar['autoplay'],
            'autoplay_speed' => $ayarlar['autoplay_speed'],
            'drag_enabled'   => $ayarlar['drag_enabled'],
            'show_price'     => $ayarlar['show_price'],
            'updated_at'     => $simdi,
        );

        $format = array( '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' );

        if ( $id > 0 && self::getir( $id ) ) {
            $wpdb->update( $tablo, $veri, array( 'id' => $id ), $format, array( '%d' ) );
        } else {
            $veri['created_at'] = $simdi;
            $format[]           = '%s';

            if ( false === $wpdb->insert( $tablo, $veri, $format ) ) {
                return 0;
            }

            $id = (int) $wpdb->insert_id;
        }

        self::urunleri_yaz( $id, $urun_idler );

        return $id;
    }

    /**
     * Vitrinin ürün listesini baştan yazar.
     *
     * Sıra formdan geldiği gibi (dizi indeksi) kaydedilir. Fark hesaplamak
     * yerine sil-yaz: liste küçük (bir vitrin onlarca ürün), ve bu yöntem
     * sıra kaymalarına karşı bağışık.
     *
     * @param int   $id         Vitrin ID.
     * @param int[] $urun_idler Sıralı ürün ID listesi.
     * @return void
     */
    private static function urunleri_yaz( $id, array $urun_idler ) {
        global $wpdb;

        $urunler = self::urun_tablo();

        $wpdb->delete( $urunler, array( 'showcase_id' => $id ), array( '%d' ) );

        $idler = array_values( $urun_idler );

        if ( empty( $idler ) ) {
            return;
        }

        // Ürün başına ayrı INSERT yerine tek sorgu (bkz. RMA_Kampanya_DB::anlik_yaz
        // içindeki aynı gerekçe). Vitrinde N küçüktür ama desen aynı kalsın.
        $degerler = array();
        $params   = array();

        foreach ( $idler as $sira => $urun_id ) {
            $degerler[] = '(%d, %d, %d)';
            $params[]   = (int) $id;
            $params[]   = (int) $urun_id;
            $params[]   = (int) $sira;
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$urunler} (showcase_id, product_id, sort_order)
                 VALUES " . implode( ', ', $degerler ),
                $params
            )
        );
    }

    /**
     * Vitrini ve ürün bağlantılarını siler.
     *
     * @param int $id Vitrin ID.
     * @return bool
     */
    public static function sil( $id ) {
        global $wpdb;

        $id = (int) $id;

        if ( $id <= 0 ) {
            return false;
        }

        $wpdb->delete( self::urun_tablo(), array( 'showcase_id' => $id ), array( '%d' ) );

        return (bool) $wpdb->delete( self::tablo(), array( 'id' => $id ), array( '%d' ) );
    }
}

endif;
