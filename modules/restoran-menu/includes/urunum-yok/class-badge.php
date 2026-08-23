<?php
/**
 * "Ürünüm Yok" — kırmızı rozet köprüleri.
 *
 * Üç gösterim yeri de `qmo_tukendi_urun_sayisi()` merkezi sayacını çağırır:
 *  1) Sol admin menüsünde "Restoran Menü" satırı  -> `qrms_module_menu_label`
 *     filtresi (yorum-feedback modülünün zaten kullandığı desenin aynısı).
 *  2) Restoran Menü hub kartı                     -> trait-urunum-yok-admin.php
 *     içindeki render_eksik_urun_notice() + render_admin_page()'in 'stats'.
 *  3) Genel Bakış (qrms-overview) modül kartı      -> `qrms_module_overview_badge`
 *     filtresi (bkz. includes/class-admin.php).
 *
 * Bu dosya yalnızca 1) ve 3)'ü kaydeder; ikisi de var olan filtre
 * noktalarına bağlanır, hiçbir çekirdek dosyanın davranışını değiştirmez.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Urunum_Yok_Badge' ) ) :

class RMA_Urunum_Yok_Badge {

    public static function init() {
        add_filter( 'qrms_module_menu_label', [ __CLASS__, 'sol_menu_rozeti' ], 10, 2 );
        add_filter( 'qrms_module_overview_badge', [ __CLASS__, 'genel_bakis_rozeti' ], 10, 2 );
    }

    /**
     * Sol admin menüsündeki "Restoran Menü" satırına rozet ekler.
     *
     * WordPress'in kendi `<span class="update-plugins">` desenidir (bkz.
     * bekleyen eklenti güncellemesi rozeti); admin.css'te zaten stillenmiştir,
     * ek CSS gerekmez.
     *
     * @param string $label Modülün görünen etiketi.
     * @param string $slug  Modül slug'ı.
     * @return string
     */
    public static function sol_menu_rozeti( $label, $slug ) {
        if ( 'restoran-menu' !== $slug || ! function_exists( 'qmo_tukendi_urun_sayisi' ) ) {
            return $label;
        }

        $sayi = qmo_tukendi_urun_sayisi();
        if ( $sayi < 1 ) {
            return $label;
        }

        return $label . ' <span class="update-plugins count-' . (int) $sayi . '" title="Tükendi ürün sayısı">'
            . '<span class="update-count">' . (int) $sayi . '</span></span>';
    }

    /**
     * Genel Bakış'taki "Restoran Menü" kartına rozet metni ekler.
     *
     * @param string $badge Önceki rozet metni (varsayılan boş).
     * @param string $slug  Modül slug'ı.
     * @return string
     */
    public static function genel_bakis_rozeti( $badge, $slug ) {
        if ( 'restoran-menu' !== $slug || ! function_exists( 'qmo_tukendi_urun_sayisi' ) ) {
            return $badge;
        }

        $sayi = qmo_tukendi_urun_sayisi();
        return $sayi > 0 ? (string) $sayi : $badge;
    }
}

endif;
