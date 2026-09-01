<?php
/**
 * Toplu Fiyat Kampanyası — fiyat çözümleme ve ön yüz gösterimi.
 *
 * Menüde görünen fiyat, ürünün meta verisinden DEĞİL, her render'da
 * "orijinal fiyat + aktif kampanya kuralı" birleştirilerek üretilir. Ürünün
 * `rma_price` (kombin ürünlerde `_qmo_kombin_fiyat`) alanına hiçbir zaman
 * yazılmaz; kampanya kaldırıldığında fiyatlar aynı saniyede orijinaline döner.
 *
 * Modülün DÖRT fiyat gösterim noktası (menü kartı, ürün modalı, ürün vitrini,
 * öne çıkan slider) bu sınıfın tek girişini kullanır: fiyat_html().
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Kampanya' ) ) :

class RMA_Kampanya {

    /** İstek içi bellek: kampanya sorgusu istek başına bir kez çalışır. */
    private static $memo = array();

    /**
     * İstek içi belleği temizler.
     *
     * Kaydetme/aktifleştirme/geri alma sonrası çağrılır — aynı istekte
     * ekrana basılan özet taze veriyi görsün.
     *
     * @return void
     */
    public static function bellegi_bosalt() {
        self::$memo = array();
    }

    /**
     * Durumu 'active' olan HAM kayıt (zaman penceresi değerlendirilmeden).
     *
     * @return array|null
     */
    private static function ham_aktif() {
        if ( array_key_exists( 'ham', self::$memo ) ) {
            return self::$memo['ham'];
        }

        self::$memo['ham'] = null;

        // Tablo henüz kurulmadıysa (yeni kurulum, admin_init hiç çalışmamış)
        // sorgu bile açılmaz. Sürüm option'ı autoload olduğu için bu kontrol
        // ek sorgu doğurmaz.
        if ( ! class_exists( 'RMA_Kampanya_DB' ) || ! get_option( RMA_Kampanya_DB::VERSION_OPTION ) ) {
            return null;
        }

        $kayit = RMA_Kampanya_DB::aktif_kayit();

        if ( $kayit ) {
            self::$memo['ham'] = (array) $kayit;
        }

        return self::$memo['ham'];
    }

    /**
     * Şu an gerçekten geçerli olan kampanya.
     *
     * v1'de "durumu aktif olan" ile aynıdır; zaman alanları (İkinci Faz)
     * dolduğunda tarih/saat/gün penceresi de burada değerlendirilir.
     *
     * @return array|null
     */
    public static function aktif() {
        if ( array_key_exists( 'aktif', self::$memo ) ) {
            return self::$memo['aktif'];
        }

        $ham = self::ham_aktif();

        self::$memo['aktif'] = ( $ham && RMA_Kampanya_DB::aktif_mi( $ham ) ) ? $ham : null;

        return self::$memo['aktif'];
    }

    /**
     * Menü önbelleği için kampanya imzası.
     *
     * Önbellek anahtarına girer (bkz. RMA_Helpers_Trait::cache_key): kampanya
     * açılıp kapandığında ya da kuralı değiştiğinde önbelleğe alınmış menü
     * HTML'i kendiliğinden geçersizleşir.
     *
     * Zaman kısıtı olan kampanyalarda imzaya 5 dakikalık bir zaman kovası
     * eklenir — İkinci Fazdaki Happy Hour başlayıp bittiğinde önbellek en geç
     * 5 dakika içinde kendiliğinden döner, ek kod gerekmez.
     *
     * @return string
     */
    public static function imza() {
        $ham = self::ham_aktif();

        if ( ! $ham ) {
            return '0';
        }

        $imza = (int) $ham['id'] . '-' . (string) ( $ham['updated_at'] ?? '' );

        if ( self::zaman_kisitli_mi( $ham ) ) {
            $simdi = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time();
            $imza .= '-' . (int) floor( $simdi / ( 5 * 60 ) );
        }

        return $imza;
    }

    /**
     * Kampanyanın tarih/saat/gün kısıtı var mı? (İkinci Faz alanları)
     *
     * @param array $kampanya Kampanya kaydı.
     * @return bool
     */
    private static function zaman_kisitli_mi( array $kampanya ) {
        foreach ( array( 'starts_at', 'ends_at', 'daily_start', 'daily_end' ) as $alan ) {
            $deger = trim( (string) ( $kampanya[ $alan ] ?? '' ) );

            if ( '' !== $deger && '0000-00-00 00:00:00' !== $deger ) {
                return true;
            }
        }

        return (int) ( $kampanya['days_mask'] ?? 0 ) > 0;
    }

    /**
     * Ürünün kampanyanın uygulanacağı TABAN fiyatı.
     *
     * Kombin ürünlerde taban, bileşenlerin toplamı değil paket fiyatıdır
     * (`_qmo_kombin_fiyat`) — müşterinin gerçekten ödediği tutar odur.
     *
     * @param int $id Ürün ID.
     * @return array{ham:string,kombin:bool}
     */
    public static function taban_fiyat( $id ) {
        $kombin = class_exists( 'QMO_Kombin_Meta' ) && QMO_Kombin_Meta::is_kombin( $id );

        if ( $kombin ) {
            $paket = get_post_meta( $id, '_qmo_kombin_fiyat', true );

            if ( '' !== (string) $paket && is_numeric( $paket ) ) {
                return array( 'ham' => (string) $paket, 'kombin' => true );
            }
        }

        return array( 'ham' => (string) get_post_meta( $id, 'rma_price', true ), 'kombin' => false );
    }

    /**
     * Ürünün fiyat durumu: orijinal, kampanyalı ve kampanyanın kendisi.
     *
     * @param int $id Ürün ID.
     * @return array{ham:string,orijinal:float|null,yeni:float|null,aktif:bool,kombin:bool,kampanya:array|null}
     */
    public static function fiyat_bilgisi( $id ) {
        $id    = (int) $id;
        $taban = self::taban_fiyat( $id );

        $bilgi = array(
            'ham'      => $taban['ham'],
            'orijinal' => is_numeric( $taban['ham'] ) ? (float) $taban['ham'] : null,
            'yeni'     => null,
            'aktif'    => false,
            'kombin'   => $taban['kombin'],
            'kampanya' => null,
        );

        $kampanya = self::aktif();

        if ( ! $kampanya || null === $bilgi['orijinal'] ) {
            return $bilgi;
        }

        if ( ! RMA_Kampanya_DB::kapsamda_mi( $id, $kampanya, self::kategori_idleri( $id ) ) ) {
            return $bilgi;
        }

        $yeni = RMA_Kampanya_DB::yeni_fiyat( $bilgi['orijinal'], $kampanya );

        // Kural fiyatı değiştirmiyorsa (tutar 0, ya da yuvarlama sonrası aynı)
        // kampanya gösterimi açılmaz: üstü çizili aynı fiyat müşteriyi yanıltır.
        if ( null === $yeni || abs( $yeni - $bilgi['orijinal'] ) < 0.005 ) {
            return $bilgi;
        }

        $bilgi['yeni']     = $yeni;
        $bilgi['aktif']    = true;
        $bilgi['kampanya'] = $kampanya;

        return $bilgi;
    }

    /**
     * Ürünün rma_category terim ID'leri (terim cache'inden).
     *
     * @param int $id Ürün ID.
     * @return int[]
     */
    private static function kategori_idleri( $id ) {
        $terimler = get_the_terms( $id, 'rma_category' );

        if ( is_wp_error( $terimler ) || empty( $terimler ) ) {
            return array();
        }

        return array_map(
            static function ( $t ) {
                return (int) $t->term_id;
            },
            $terimler
        );
    }

    /**
     * Ürünün ön yüzde basılacak fiyat HTML'i — DÖRT gösterim noktasının ortak girişi.
     *
     * Aktif kampanya yoksa çıktı, kampanya özelliği eklenmeden önceki hâliyle
     * BİREBİR aynıdır (kombin HTML'i varsa o, yoksa ham fiyat + " ₺").
     *
     * @param int   $id     Ürün ID.
     * @param array $arglar 'sinif' => yeni fiyat span'ine eklenecek sınıf.
     * @return string
     */
    public static function fiyat_html( $id, array $arglar = array() ) {
        $id    = (int) $id;
        $sinif = isset( $arglar['sinif'] ) ? trim( (string) $arglar['sinif'] ) : '';
        $bilgi = self::fiyat_bilgisi( $id );

        if ( ! $bilgi['aktif'] ) {
            // Kampanya öncesi davranış: kombin fiyat gösterimi önceliklidir.
            if ( function_exists( 'qmo_get_kombin_price_html' ) ) {
                $kombin_html = qmo_get_kombin_price_html( $id );

                if ( '' !== $kombin_html ) {
                    return $kombin_html;
                }
            }

            if ( '' === $bilgi['ham'] ) {
                return '';
            }

            $duz = esc_html( self::fiyat_yazi( $bilgi['ham'] ) );

            return '' !== $sinif ? '<span class="' . esc_attr( $sinif ) . '">' . $duz . '</span>' : $duz;
        }

        $html = '';

        // Kampanya aktifken kombin ürünlerde de TEK üstü çizili fiyat gösterilir:
        // kampanya öncesi paket fiyatı. Bileşen toplamı burada kasten devre dışı —
        // iki ayrı üstü çizili fiyat müşteriyi yanıltırdı.
        if ( ! empty( $bilgi['kampanya']['show_old_price'] ) ) {
            $html .= '<span class="rma-price-old">' . esc_html( self::fiyat_yazi( $bilgi['orijinal'] ) ) . '</span>';
        }

        $yeni_sinif = trim( 'rma-price-new ' . $sinif );

        $html .= '<span class="' . esc_attr( $yeni_sinif ) . '">' . esc_html( self::fiyat_yazi( $bilgi['yeni'] ) ) . '</span>';

        return $html;
    }

    /**
     * İndirim rozeti — menü kartındaki rozet şeridine eklenir.
     *
     * Yalnızca İNDİRİM kampanyalarında basılır: "%15 zam" rozeti pazarlama
     * açısından anlamsız olurdu. Zamlı üründe üstü çizili eski fiyat yine görünür.
     *
     * @param int $id Ürün ID.
     * @return string
     */
    public static function rozet_html( $id ) {
        $bilgi = self::fiyat_bilgisi( $id );

        if ( ! $bilgi['aktif'] || 'decrease' !== ( $bilgi['kampanya']['direction'] ?? '' ) ) {
            return '';
        }

        $k     = $bilgi['kampanya'];
        $tutar = RMA_Kampanya_DB::tutar_temizle( $k['amount'] ?? 0 );

        $etiket = ( 'fixed' === ( $k['calc_type'] ?? 'percent' ) )
            ? self::fiyat_yazi( $tutar, '-{n} ₺' )
            : self::fiyat_yazi( $tutar, '%{n}' );

        return '<span class="rma-badge campaign">' . esc_html( $etiket ) . '</span>';
    }

    /**
     * Ön yüz fiyat yazısı — çeviri yardımcısı varsa dile göre, yoksa TR bicimle.
     *
     * @param float|int|string $deger Tutar veya ham meta.
     * @param string           $kalip ui_string kalıbı.
     * @return string
     */
    public static function fiyat_yazi( $deger, $kalip = '{n} ₺' ) {
        if ( function_exists( 'rma_ceviri_fiyat' ) && is_numeric( $deger ) ) {
            return rma_ceviri_fiyat( $deger, $kalip );
        }

        $sayi = class_exists( 'RMA_Kampanya_DB' )
            ? RMA_Kampanya_DB::bicimle( is_numeric( $deger ) ? $deger : 0 )
            : (string) $deger;

        if ( ! is_numeric( $deger ) ) {
            $sayi = (string) $deger;
        }

        return str_replace( '{n}', $sayi, $kalip );
    }
}

endif;

/**
 * Ürünün ŞU AN geçerli fiyatı (kampanya uygulanmış hâli), sayı olarak.
 *
 * Modül dışından okunabilsin diye global köprü — deseni
 * `qmo_get_kombin_price_html()` ile aynı. Fiyatı ham meta'dan okuyan başka
 * bileşenler (örn. yapay zekâ sohbet botunun menü JSON'u) kampanya aktifken
 * eski fiyatı söylemesin diye bunu kullanmalıdır.
 *
 * @param int $post_id Ürün ID.
 * @return string Fiyat (boş string = fiyat yok).
 */
function rma_get_effective_price( $post_id ) {
    if ( ! class_exists( 'RMA_Kampanya' ) ) {
        return (string) get_post_meta( $post_id, 'rma_price', true );
    }

    $bilgi = RMA_Kampanya::fiyat_bilgisi( $post_id );

    if ( $bilgi['aktif'] ) {
        return RMA_Kampanya_DB::bicimle( $bilgi['yeni'] );
    }

    return $bilgi['ham'];
}
