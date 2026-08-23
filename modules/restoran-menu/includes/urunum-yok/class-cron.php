<?php
/**
 * "Ürünüm Yok" — otomatik yeniden aktifleştirme zamanlaması.
 *
 * Üç katmanlı güvenlik:
 *  1) Her işaretlemede TEKİL bir WP-Cron olayı, tam bitiş saatinde çalışır
 *     (hassas, ama WP-Cron sayfa ziyareti tetiklemesine bağlıdır).
 *  2) Saatlik bir SÜPÜRGE cron'u, kaçırılan tekil olayları yakalar.
 *  3) Sayfa yüklemesinde çalışan HAFİF bir yedek kontrol (en fazla dakikada
 *     bir), trafiği düşük sitelerde WP-Cron hiç tetiklenmese bile süresi
 *     geçmiş kayıtların er ya da geç temizlenmesini garanti eder.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Urunum_Yok_Cron' ) ) :

class RMA_Urunum_Yok_Cron {

    const HOOK_TEKIL    = 'qmo_uy_tekil_aktive';
    const HOOK_SUPURGE  = 'qmo_uy_supurge';
    const FALLBACK_TRANSIENT = 'qmo_uy_fallback_kontrol';

    public static function init() {
        add_action( self::HOOK_TEKIL, [ __CLASS__, 'tekil_calistir' ] );
        add_action( self::HOOK_SUPURGE, [ 'RMA_Urunum_Yok_Stock', 'suresi_gecmisleri_temizle' ] );

        add_action( 'init', [ __CLASS__, 'supurge_planla' ], 5 );

        // Sayfa yüklemesi yedeği — WP-Cron güvenilir tetiklenmese bile
        // süresi geçmiş kayıtlar bir sonraki ziyarette temizlenir.
        add_action( 'init', [ __CLASS__, 'sayfa_yuku_yedek_kontrol' ], 20 );
    }

    /**
     * Belirli bir ürün için tam bitiş saatinde çalışacak tekil olay planlar.
     *
     * Aynı ürün için farklı zamanlarda birden çok tekil olay biriktirilmesi
     * zararsızdır: her biri yeniden_hesapla()'yı çağırır, o da METİN kayıttan
     * (canlı veriden) okuyup doğru MAX bitiş zamanını kendisi hesaplar.
     *
     * @param int $product_id
     * @param int $timestamp
     * @return void
     */
    public static function tekil_planla( $product_id, $timestamp ) {
        wp_schedule_single_event( (int) $timestamp, self::HOOK_TEKIL, [ (int) $product_id ] );
    }

    public static function tekil_calistir( $product_id ) {
        if ( class_exists( 'RMA_Urunum_Yok_Stock' ) ) {
            RMA_Urunum_Yok_Stock::yeniden_hesapla( (int) $product_id );
        }
    }

    public static function supurge_planla() {
        if ( wp_next_scheduled( self::HOOK_SUPURGE ) ) {
            return;
        }
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK_SUPURGE );
    }

    public static function supurge_iptal() {
        wp_clear_scheduled_hook( self::HOOK_SUPURGE );
    }

    /**
     * Sayfa yüklemesi yedek kontrolü — en fazla dakikada bir çalışır
     * (transient kilidi), her isteği yavaşlatmaz.
     *
     * @return void
     */
    public static function sayfa_yuku_yedek_kontrol() {
        if ( false !== get_transient( self::FALLBACK_TRANSIENT ) ) {
            return;
        }
        set_transient( self::FALLBACK_TRANSIENT, 1, MINUTE_IN_SECONDS );

        if ( class_exists( 'RMA_Urunum_Yok_Stock' ) ) {
            RMA_Urunum_Yok_Stock::suresi_gecmisleri_temizle();
        }
    }
}

endif;
