<?php
/**
 * Ürün Vitrini kalıntı temizliği — TEK SEFERLİK göç.
 *
 * "Ürün Vitrini" özelliği QR Menu Suite'ten tamamen kaldırıldı. 1.1.0 ve
 * öncesinden güncellenen kurulumlarda özelliğin kendi tabloları ile şema
 * sürümü option'ı veritabanında yetim kalır; bu sınıf onları bir kez
 * temizler ve bir bayrak bırakır — sonraki isteklerde tek bir get_option
 * dışında hiçbir maliyet oluşmaz, DROP TABLE her istekte çalışmaz.
 *
 * KAPSAM: yalnızca vitrine ait iki tablo ve iki option. Ürünler,
 * kategoriler, kampanyalar, banner ve slider verilerine dokunulmaz.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Vitrin_Temizlik' ) ) :

class RMA_Vitrin_Temizlik {

    /** Temizliğin bir kez çalıştığını işaretleyen option. */
    const BAYRAK_OPTION = 'rma_vitrin_kaldirildi';

    /** Kaldırılan özelliğin şema sürümü option'ı. */
    const ESKI_VERSION_OPTION = 'rma_vitrin_db_version';

    /**
     * Kaldırılan özelliğin tabloları (wpdb ön eki olmadan).
     *
     * @return array<int,string>
     */
    public static function tablolar() {
        return array( 'rma_showcases', 'rma_showcase_items' );
    }

    /**
     * Temizlik daha önce yapılmadıysa yapar. admin_init'ten çağrılır.
     *
     * @return void
     */
    public static function belki_temizle() {
        if ( get_option( self::BAYRAK_OPTION ) ) {
            return;
        }

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            return;
        }

        self::temizle();
    }

    /**
     * Vitrin tablolarını düşürür ve özelliğe ait option'ları siler.
     *
     * Herhangi bir DROP başarısız olursa (query() false döner) bayrak
     * yazılmaz; böylece temizlik sonraki admin_init'te tekrar denenir.
     *
     * @return void
     */
    public static function temizle() {
        global $wpdb;

        foreach ( self::tablolar() as $tablo ) {
            $ad = $wpdb->prefix . $tablo;

            // Tablo adı sabit listeden gelir, kullanıcı girdisi değildir.
            $sonuc = $wpdb->query( "DROP TABLE IF EXISTS `{$ad}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

            if ( false === $sonuc ) {
                return;
            }
        }

        delete_option( self::ESKI_VERSION_OPTION );

        // Bayrak autoload dışı: yalnızca admin_init'te okunur.
        update_option( self::BAYRAK_OPTION, '1', false );
    }
}

endif;
