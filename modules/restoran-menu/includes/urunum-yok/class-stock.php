<?php
/**
 * "Ürünüm Yok" — malzeme bazlı stok motoru.
 *
 * Ürünü menüden ASLA kaldırmaz; mevcut `RMA_Tukendi` bayrağını (bkz.
 * class-tukendi.php) tetikleyerek, o dosyanın zaten sağladığı görünüm/
 * sipariş-engelleme davranışının üzerine biner. Kendi tuttuğu tek veri,
 * ürün başına hangi malzemenin ne zamana kadar tükendi işaretlendiğidir:
 *
 *   _qmo_uy_kayitlar  => [ malzeme_term_id => bitiş_unix_zaman, … ]
 *   _qmo_tukendi_bitis => en geç bitiş zamanı (kayıtların MAX'ı)
 *
 * Birden fazla malzeme aynı ürünü etkiliyorsa ürün, en geç bitiş saatine
 * kadar tükendi kalır (bkz. yeniden_hesapla).
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Urunum_Yok_Stock' ) ) :

class RMA_Urunum_Yok_Stock {

    const META_KAYITLAR = '_qmo_uy_kayitlar';
    const META_BITIS     = '_qmo_tukendi_bitis';

    /**
     * Bir ürünü, bir malzeme yüzünden belirtilen zamana kadar tükendi
     * işaretler. Aynı ürün için başka bir malzeme kaydı varsa dokunulmaz,
     * yalnızca bu malzemenin kaydı eklenir/güncellenir.
     *
     * @param int $product_id
     * @param int $ingredient_term_id
     * @param int $until_ts Unix zaman damgası; geçmişte olamaz.
     * @return bool
     */
    public static function isaretle( $product_id, $ingredient_term_id, $until_ts ) {
        $product_id          = (int) $product_id;
        $ingredient_term_id  = (int) $ingredient_term_id;
        $until_ts            = (int) $until_ts;

        if ( $product_id < 1 || $ingredient_term_id < 1 || $until_ts <= time() ) {
            return false;
        }

        $kayitlar = self::kayitlar( $product_id );
        $kayitlar[ $ingredient_term_id ] = $until_ts;
        update_post_meta( $product_id, self::META_KAYITLAR, $kayitlar );

        self::yeniden_hesapla( $product_id, $kayitlar );

        if ( class_exists( 'RMA_Urunum_Yok_Cron' ) ) {
            RMA_Urunum_Yok_Cron::tekil_planla( $product_id, $until_ts );
        }

        return true;
    }

    /**
     * Ürünün ham malzeme kayıtları (malzeme_term_id => bitiş_zaman).
     *
     * @param int $product_id
     * @return array<int,int>
     */
    public static function kayitlar( $product_id ) {
        $raw = get_post_meta( (int) $product_id, self::META_KAYITLAR, true );
        return is_array( $raw ) ? $raw : [];
    }

    /**
     * Süresi geçmiş kayıtları eler, ürünün tükendi durumunu ve
     * `_qmo_tukendi_bitis` damgasını yeniden kurar.
     *
     * Kayıt kalmadıysa (hepsinin süresi geçmişse) ürün otomatik tekrar
     * aktif edilir — "belirlenen saat geldiğinde stok durumu sıfırlansın"
     * gereksinimi burada karşılanır.
     *
     * @param int        $product_id
     * @param array|null $kayitlar Önceden okunmuşsa tekrar sorgulanmaz.
     * @return bool Ürün hâlâ (malzeme yüzünden) tükendi mi.
     */
    public static function yeniden_hesapla( $product_id, ?array $kayitlar = null ) {
        $product_id = (int) $product_id;

        if ( null === $kayitlar ) {
            $kayitlar = self::kayitlar( $product_id );
        }

        $simdi = time();
        foreach ( $kayitlar as $tid => $until ) {
            if ( (int) $until <= $simdi ) {
                unset( $kayitlar[ $tid ] );
            }
        }

        if ( empty( $kayitlar ) ) {
            delete_post_meta( $product_id, self::META_KAYITLAR );
            delete_post_meta( $product_id, self::META_BITIS );

            // Bilinen basitleştirme: ürün ayrıca ürün düzenleme ekranındaki
            // "Tükendi" kutusuyla ELLE de işaretlenmişse, burada yine de
            // aktif edilir — ikisi aynı `_rma_tukendi` bayrağını paylaşır.
            // Bu fonksiyon yalnızca META_KAYITLAR meta'sı VARKEN çağrıldığı
            // için (yani ürün bu motorun yönetimindeyken) pratikte nadir bir
            // çakışmadır; "basit tutmak için" bilinçli bir tercih.
            if ( class_exists( 'RMA_Tukendi' ) ) {
                RMA_Tukendi::kaydet( $product_id, false );
            }

            return false;
        }

        update_post_meta( $product_id, self::META_KAYITLAR, $kayitlar );
        update_post_meta( $product_id, self::META_BITIS, max( $kayitlar ) );

        if ( class_exists( 'RMA_Tukendi' ) ) {
            RMA_Tukendi::kaydet( $product_id, true );
        }

        return true;
    }

    /**
     * Elle "Şimdi Aktif Et" — tüm malzeme kayıtlarını temizler ve ürünü
     * tekrar aktif eder (kullanıcı yanlışlıkla işaretlemişse geri alma yolu).
     *
     * @param int $product_id
     * @return void
     */
    public static function simdi_aktif_et( $product_id ) {
        $product_id = (int) $product_id;
        delete_post_meta( $product_id, self::META_KAYITLAR );
        delete_post_meta( $product_id, self::META_BITIS );

        if ( class_exists( 'RMA_Tukendi' ) ) {
            RMA_Tukendi::kaydet( $product_id, false );
        }
    }

    /**
     * Bitiş zamanı geçmiş TÜM ürünleri tarar ve tek tek yeniden hesaplar.
     *
     * WP-Cron'un tekil olayı (bkz. class-cron.php tekil_planla) normal
     * şartlarda zaten doğru zamanda tetikler; bu tarama hem saatlik cron
     * yedeği hem de sayfa yüklemesi yedek kontrolü tarafından çağrılır
     * (WP-Cron düşük trafikli sitelerde güvenilir tetiklenmeyebilir).
     *
     * @param int $limit Bir turda en fazla kaç ürün işlenir.
     * @return int İşlenen ürün sayısı.
     */
    public static function suresi_gecmisleri_temizle( $limit = 200 ) {
        $ids = get_posts( [
            'post_type'      => 'rma_menu_item',
            'post_status'    => 'any',
            'posts_per_page' => (int) $limit,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ [
                'key'     => self::META_BITIS,
                'value'   => time(),
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ] ],
        ] );

        foreach ( $ids as $id ) {
            self::yeniden_hesapla( (int) $id );
        }

        return count( $ids );
    }
}

endif;

/* =========================================================================
   MERKEZİ SAYAÇLAR — sol menü rozeti, hub kartı ve Genel Bakış kartı BURADAN
   beslenir; sayı tutarsızlığı olmaması için tek kaynak.
========================================================================= */

if ( ! function_exists( 'qmo_tukendi_urun_sayisi' ) ) {
    /**
     * Şu an "Tükendi" durumunda olan (yayınlanmış) ürün sayısı.
     *
     * Ürünüm Yok akışının işaretlediği ürünler dâhil, ürün ekleme ekranından
     * elle işaretlenenler de dâhil — ikisi de aynı `_rma_tukendi` bayrağını
     * kullanır, bu yüzden tek sorgu her ikisini de kapsar.
     *
     * İstek içi statik önbellek: aynı istekte üç ayrı rozet bu fonksiyonu
     * çağırır, sonuç saniyesinde değişmeyeceği için tek sorguya indirilir
     * (bkz. RMA_Tukendi::tukendi_adlari() ile aynı desen).
     *
     * @return int
     */
    function qmo_tukendi_urun_sayisi() {
        static $sayi = null;
        if ( null !== $sayi ) {
            return $sayi;
        }

        $ids = get_posts( [
            'post_type'      => 'rma_menu_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_key'       => '_rma_tukendi',
            'meta_value'     => '1',
        ] );

        $sayi = is_array( $ids ) ? count( $ids ) : 0;
        return $sayi;
    }
}

if ( ! function_exists( 'qmo_yayinlanan_urun_sayisi' ) ) {
    /**
     * Yayındaki menü ürünü sayısı.
     *
     * Genel Bakış analiz şeridi ve başka sayaçlar BURADAN beslenir; istek içi
     * statik önbellek ikinci bir wp_count_posts çağrısını önler.
     *
     * @return int
     */
    function qmo_yayinlanan_urun_sayisi() {
        static $sayi = null;
        if ( null !== $sayi ) {
            return $sayi;
        }

        if ( ! post_type_exists( 'rma_menu_item' ) ) {
            $sayi = 0;
            return $sayi;
        }

        $counts = wp_count_posts( 'rma_menu_item' );
        $sayi   = ( isset( $counts->publish ) ) ? (int) $counts->publish : 0;
        return $sayi;
    }
}

if ( ! function_exists( 'qmo_urunum_yok_eksik_ozet' ) ) {
    /**
     * "Eksik Ürün" kartının verisi: toplam tükendi sayısı + hangi malzemeden
     * kaç ürünün etkilendiği + elle işaretlenmiş (malzemeyle ilgisi olmayan)
     * ürün sayısı.
     *
     * @return array{toplam:int,malzemeler:array<string,int>,elle:int,elle_ids:int[]}
     */
    function qmo_urunum_yok_eksik_ozet() {
        static $ozet = null;
        if ( null !== $ozet ) {
            return $ozet;
        }

        $ozet = [
            'toplam'     => qmo_tukendi_urun_sayisi(),
            'malzemeler' => [],
            'elle'       => 0,
            'elle_ids'   => [],
        ];

        $ids = get_posts( [
            'post_type'      => 'rma_menu_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_key'       => '_rma_tukendi',
            'meta_value'     => '1',
        ] );

        foreach ( (array) $ids as $id ) {
            $kayitlar = RMA_Urunum_Yok_Stock::kayitlar( $id );

            if ( empty( $kayitlar ) ) {
                $ozet['elle']++;
                $ozet['elle_ids'][] = (int) $id;
                continue;
            }

            foreach ( array_keys( $kayitlar ) as $term_id ) {
                $term = get_term( (int) $term_id, 'rma_ingredient' );
                $ad   = ( $term && ! is_wp_error( $term ) ) ? $term->name : ( '#' . $term_id );

                if ( ! isset( $ozet['malzemeler'][ $ad ] ) ) {
                    $ozet['malzemeler'][ $ad ] = 0;
                }
                $ozet['malzemeler'][ $ad ]++;
            }
        }

        arsort( $ozet['malzemeler'] );

        return $ozet;
    }
}
