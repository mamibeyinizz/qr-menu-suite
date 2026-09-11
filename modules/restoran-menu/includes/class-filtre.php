<?php
/**
 * Menü filtrelerinin TEK kayıt defteri.
 *
 * Aynı anahtar listesi dört yerde kullanılır ve hepsi buradan beslenir:
 * filtre paneli (trait-frontend.php), sorgu kurucu (trait-ajax.php),
 * aktif filtre chip'leri (rma-frontend.js, sunucudan gelen etiketlerle) ve
 * analitik beyaz listesi (qr-analiz). Liste tek yerde durmasaydı, panele
 * eklenen bir filtre sorguda sessizce yok sayılır ya da analitikte
 * reddedilirdi.
 *
 * SINIF NEDEN BAĞIMSIZ?
 * Karar mantığı WordPress'e hiç dokunmaz; bu sayede RMA_Tukendi /
 * RMA_Porsiyon ile aynı desende doğrudan test edilebilir. Meta okuma
 * çağıranın işidir (trait-ajax.php ürün satırını primed meta cache'ten
 * kurar, ek sorgu doğmaz).
 *
 * İKİ KATMANLI UYGULAMA
 *   A) Sorgu katmanı — "meta değeri tam olarak 1" tipindeki filtreler
 *      WP_Query meta_query'sine girer (indexli). Alerjen hariç tutma
 *      rma_allergen taksonomisinde NOT IN olarak çalışır.
 *   B) PHP katmanı — meta'nın HİÇ OLMAMASI "geçer" demek olan (helal),
 *      serbest metin alanında sayısal karşılaştırma gerektiren (kalori,
 *      acılık) ve kampanyalı gerçek fiyata bakan filtreler sorgu sonrası
 *      uygulanır. Bunlar meta_query'de sessizce yanlış sonuç verirdi:
 *      boş string NUMERIC karşılaştırmada 0'a düşer ve kalorisi hiç
 *      girilmemiş her ürün "300 kcal altı" filtresine takılırdı.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'RMA_Filtre' ) ) {
    return;
}

/**
 * Filtre anahtarları, doğrulama ve karar mantığı.
 */
class RMA_Filtre {

    /** Kalori alanının kabul edilen üst sınırı (kcal). */
    const KALORI_TAVAN = 20000;

    /** Fiyat alanının kabul edilen üst sınırı. */
    const FIYAT_TAVAN = 1000000;

    /** Acılık meta anahtarı. */
    const META_ACI = 'rma_spicy_level';

    /**
     * Acılık kademeleri.
     *
     * Alan eskiden "0-3" idi ve serbest metindi. 0-4'e genişledi; ESKİ
     * DEĞERLER ANLAMINI KORUR (0 acısız, 3 acı), yalnızca üste bir kademe
     * eklendi — veri taşıması gerekmez.
     *
     * @return array<int,string>
     */
    public static function aci_seviyeleri() {
        return array(
            0 => 'Acısız',
            1 => 'Az Acı',
            2 => 'Orta',
            3 => 'Acı',
            4 => 'Çok Acı',
        );
    }

    /**
     * "Meta değeri 1" ile çalışan filtreler: anahtar => meta anahtarı.
     *
     * vegan / vegetarian / gluten_free zaten vardı ve DEĞERLERİ DEĞİŞMEDİ;
     * eski istemcilerin gönderdiği anahtarlar aynen çalışmaya devam eder.
     *
     * @return array<string,string>
     */
    public static function meta_filtreleri() {
        return array(
            'vegan'             => 'rma_is_vegan',
            'vegetarian'        => 'rma_is_vegetarian',
            'gluten_free'       => 'rma_is_gluten_free',
            'sugar_free'        => 'rma_is_sugar_free',
            'badge_popular'     => 'rma_badge_popular',
            'badge_new'         => 'rma_badge_new',
            'badge_recommended' => 'rma_badge_recommended',
            'badge_discount'    => 'rma_badge_discount',
        );
    }

    /**
     * Hazır kalori eşikleri: anahtar => üst sınır (kcal).
     *
     * @return array<string,int>
     */
    public static function kalori_esikleri() {
        return array(
            'cal_300' => 300,
            'cal_500' => 500,
            'cal_700' => 700,
        );
    }

    /**
     * Diyet bölümünün kartları (panel sırası).
     *
     * @return array<string,array{icon:string,label:string}>
     */
    public static function diyet_kartlari() {
        return array(
            'gluten_free'  => array( 'icon' => '🌾', 'label' => 'Glütensiz' ),
            'vegetarian'   => array( 'icon' => '🥦', 'label' => 'Vejetaryen' ),
            'vegan'        => array( 'icon' => '🌿', 'label' => 'Vegan' ),
            'lactose_free' => array( 'icon' => '🥛', 'label' => 'Laktozsuz' ),
            'sugar_free'   => array( 'icon' => '🍬', 'label' => 'Şekersiz' ),
            'halal'        => array( 'icon' => '☪️', 'label' => 'Helal' ),
            'no_spice'     => array( 'icon' => '🧊', 'label' => 'Acısız' ),
        );
    }

    /**
     * Ürün özelliği kartları (rozetler + stok).
     *
     * @return array<string,array{icon:string,label:string}>
     */
    public static function ozellik_kartlari() {
        return array(
            'badge_popular'     => array( 'icon' => '🔥', 'label' => 'Popüler' ),
            'badge_new'         => array( 'icon' => '✨', 'label' => 'Yeni' ),
            'badge_recommended' => array( 'icon' => '⭐', 'label' => 'Önerilen' ),
            'badge_discount'    => array( 'icon' => '💸', 'label' => 'İndirimli' ),
            'in_stock'          => array( 'icon' => '✅', 'label' => 'Tükendikleri Gizle' ),
        );
    }

    /**
     * Acılık kartları: spicy_0 … spicy_4.
     *
     * @return array<string,array{icon:string,label:string}>
     */
    public static function aci_kartlari() {
        $kartlar = array();
        $ikonlar = array( '🧊', '🌶️', '🌶️🌶️', '🌶️🌶️🌶️', '🔥🌶️' );

        foreach ( self::aci_seviyeleri() as $seviye => $etiket ) {
            $kartlar[ 'spicy_' . $seviye ] = array(
                'icon'  => $ikonlar[ $seviye ],
                'label' => $etiket,
            );
        }

        return $kartlar;
    }

    /**
     * Kalori kartları.
     *
     * @return array<string,array{icon:string,label:string}>
     */
    public static function kalori_kartlari() {
        $kartlar = array();

        foreach ( self::kalori_esikleri() as $anahtar => $esik ) {
            $kartlar[ $anahtar ] = array(
                'icon'  => '🔥',
                'label' => $esik . ' kcal altı',
            );
        }

        return $kartlar;
    }

    /**
     * Alerjen kartları — "hariç tut" anlamındadır.
     *
     * @param array<string,array{label:string,icon:string}> $tanimlar get_allergen_definitions() çıktısı.
     * @return array<string,array{icon:string,label:string}>
     */
    public static function alerjen_kartlari( array $tanimlar ) {
        $kartlar = array();

        foreach ( $tanimlar as $slug => $def ) {
            $kartlar[ 'allergen_' . $slug ] = array(
                'icon'  => isset( $def['icon'] ) ? (string) $def['icon'] : '⚠️',
                'label' => isset( $def['label'] ) ? (string) $def['label'] : (string) $slug,
            );
        }

        return $kartlar;
    }

    /**
     * Geçerli TÜM filtre anahtarları — beyaz listenin tek kaynağı.
     *
     * @param array<string,mixed> $alerjen_tanimlari get_allergen_definitions() çıktısı.
     * @return string[]
     */
    public static function anahtarlar( array $alerjen_tanimlari = array() ) {
        return array_values(
            array_merge(
                array_keys( self::diyet_kartlari() ),
                array_keys( self::aci_kartlari() ),
                array_keys( self::kalori_kartlari() ),
                array_keys( self::ozellik_kartlari() ),
                array_keys( self::alerjen_kartlari( $alerjen_tanimlari ) )
            )
        );
    }

    /**
     * İstemciden gelen filtre listesini temizler.
     *
     * BEYAZ LİSTE: tanınmayan her değer sessizce düşer — XSS yükü, SQL
     * denemesi ya da eski bir istemcinin gönderdiği kaldırılmış anahtar
     * sorguya hiç ulaşmaz. Sonuç SIRALANIR: aynı filtre kümesi farklı
     * sırada gelse bile tek önbellek girdisine düşsün.
     *
     * @param mixed               $ham               İstemci girdisi.
     * @param array<string,mixed> $alerjen_tanimlari get_allergen_definitions() çıktısı.
     * @return string[]
     */
    public static function temizle_anahtarlar( $ham, array $alerjen_tanimlari = array() ) {
        if ( ! is_array( $ham ) ) {
            $ham = ( '' === $ham || null === $ham ) ? array() : array( $ham );
        }

        $izinli = self::anahtarlar( $alerjen_tanimlari );
        $temiz  = array();

        foreach ( $ham as $deger ) {
            if ( ! is_scalar( $deger ) ) {
                continue;
            }

            $deger = (string) $deger;

            if ( in_array( $deger, $izinli, true ) && ! in_array( $deger, $temiz, true ) ) {
                $temiz[] = $deger;
            }
        }

        sort( $temiz );

        return $temiz;
    }

    /**
     * Sayısal aralık girdisini temizler.
     *
     * Negatif, metin ve tavanı aşan değerler kelepçelenir; alt sınır üst
     * sınırdan büyük verilmişse takas edilir (kullanıcı hatası boş sonuç
     * üretmesin). 0 üst sınırı "sınır yok" demektir.
     *
     * @param mixed $min_ham Alt sınır.
     * @param mixed $max_ham Üst sınır.
     * @param int   $tavan   Kabul edilen azami değer.
     * @return array{0:int,1:int}
     */
    public static function temizle_aralik( $min_ham, $max_ham, $tavan ) {
        $tavan = max( 0, (int) $tavan );
        $min   = self::sayiya( $min_ham, $tavan );
        $max   = self::sayiya( $max_ham, $tavan );

        if ( $min > 0 && $max > 0 && $min > $max ) {
            $gecici = $min;
            $min    = $max;
            $max    = $gecici;
        }

        return array( $min, $max );
    }

    /**
     * Tek bir sayısal girdiyi 0..$tavan aralığına kelepçeler.
     *
     * @param mixed $ham   Girdi.
     * @param int   $tavan Üst sınır.
     * @return int
     */
    private static function sayiya( $ham, $tavan ) {
        if ( is_array( $ham ) || is_object( $ham ) || null === $ham ) {
            return 0;
        }

        $ham = trim( (string) $ham );

        if ( '' === $ham || ! is_numeric( $ham ) ) {
            return 0;
        }

        $sayi = (int) round( (float) $ham );

        if ( $sayi < 0 ) {
            return 0;
        }

        return min( $sayi, $tavan );
    }

    /**
     * A katmanı: WP_Query meta_query klozları.
     *
     * @param string[] $anahtarlar Temizlenmiş filtre anahtarları.
     * @return array<int,array{key:string,value:string,compare:string}>
     */
    public static function meta_klozlari( array $anahtarlar ) {
        $harita = self::meta_filtreleri();
        $kloz   = array();

        foreach ( $anahtarlar as $anahtar ) {
            if ( isset( $harita[ $anahtar ] ) ) {
                $kloz[] = array(
                    'key'     => $harita[ $anahtar ],
                    'value'   => '1',
                    'compare' => '=',
                );
            }
        }

        return $kloz;
    }

    /**
     * A katmanı: rma_allergen NOT IN listesine girecek slug'lar.
     *
     * `lactose_free` ayrı bir meta DEĞİLDİR: "süt / laktoz" alerjeni
     * taşımayan ürün demektir ve mevcut taksonomi klozuna eklenir. Böylece
     * ikinci bir veri alanı ve ikinci bir sorgu doğmaz.
     *
     * @param string[] $anahtarlar     Temizlenmiş filtre anahtarları.
     * @param string[] $izinli_sluglar Tanımlı alerjen slug'ları.
     * @return string[]
     */
    public static function haric_alerjenler( array $anahtarlar, array $izinli_sluglar ) {
        $haric = array();

        foreach ( $anahtarlar as $anahtar ) {
            if ( 'lactose_free' === $anahtar ) {
                $slug = 'sut';
            } elseif ( 0 === strpos( $anahtar, 'allergen_' ) ) {
                $slug = substr( $anahtar, strlen( 'allergen_' ) );
            } else {
                continue;
            }

            if ( in_array( $slug, $izinli_sluglar, true ) && ! in_array( $slug, $haric, true ) ) {
                $haric[] = $slug;
            }
        }

        return $haric;
    }

    /**
     * B katmanı bağlamı — sorgu sonrası uygulanacak kurallar.
     *
     * Boş dizi dönerse hiçbir PHP filtresi yoktur ve çağıran döngüde tek
     * bir `if` ile bütün katmanı atlar (filtre kullanmayan menüde maliyet
     * sıfır kalır).
     *
     * @param string[] $anahtarlar Temizlenmiş filtre anahtarları.
     * @param array    $aralik     ['cal' => [min,max], 'price' => [min,max]].
     * @return array<string,mixed>
     */
    public static function php_baglami( array $anahtarlar, array $aralik = array() ) {
        $ctx = array();

        if ( in_array( 'halal', $anahtarlar, true ) ) {
            $ctx['halal'] = true;
        }

        if ( in_array( 'in_stock', $anahtarlar, true ) ) {
            $ctx['in_stock'] = true;
        }

        // no_spice, spicy_0 ile aynı şeydir: iki ayrı kural üretmez.
        $aci = array();

        foreach ( $anahtarlar as $anahtar ) {
            if ( 'no_spice' === $anahtar ) {
                $aci[] = 0;
            } elseif ( 0 === strpos( $anahtar, 'spicy_' ) ) {
                $aci[] = (int) substr( $anahtar, strlen( 'spicy_' ) );
            }
        }

        if ( $aci ) {
            $aci = array_values( array_unique( $aci ) );
            sort( $aci );
            $ctx['spicy'] = $aci;
        }

        // Hazır kalori eşiklerinden EN DARI kazanır; özel aralık da aynı
        // üst sınıra yarışır. Kullanıcı "500 altı" ve "300 altı"nı birlikte
        // seçerse sonuç 300 altıdır (kesişim), boş liste değil.
        list( $kal_min, $kal_max ) = isset( $aralik['cal'] ) ? $aralik['cal'] : array( 0, 0 );

        foreach ( self::kalori_esikleri() as $anahtar => $esik ) {
            if ( in_array( $anahtar, $anahtarlar, true ) ) {
                $kal_max = ( $kal_max > 0 ) ? min( $kal_max, $esik ) : $esik;
            }
        }

        if ( $kal_min > 0 || $kal_max > 0 ) {
            $ctx['cal'] = array( $kal_min, $kal_max );
        }

        list( $fiy_min, $fiy_max ) = isset( $aralik['price'] ) ? $aralik['price'] : array( 0, 0 );

        if ( $fiy_min > 0 || $fiy_max > 0 ) {
            $ctx['price'] = array( $fiy_min, $fiy_max );
        }

        return $ctx;
    }

    /**
     * B katmanının tek karar noktası.
     *
     * Saf fonksiyondur: $satir çağıran tarafından primed meta cache'ten
     * kurulur, burada hiçbir sorgu açılmaz.
     *
     * @param array $satir Ürün satırı: spicy, calories, price, alcohol,
     *                     pork (string/int) ve tukendi (bool).
     * @param array $ctx   php_baglami() çıktısı.
     * @return bool Ürün filtrelerden geçiyor mu.
     */
    public static function satir_gecer( array $satir, array $ctx ) {
        if ( ! $ctx ) {
            return true;
        }

        if ( ! empty( $ctx['in_stock'] ) && ! empty( $satir['tukendi'] ) ) {
            return false;
        }

        // HELAL: alan İŞARETLİ DEĞİLSE ürün geçer. Alkol/domuz meta'sı
        // kurulumların çoğunda hiç yazılmamıştır; "meta yok = helal değil"
        // saymak menüyü boşaltırdı.
        if ( ! empty( $ctx['halal'] ) ) {
            if ( self::bayrak( $satir, 'alcohol' ) || self::bayrak( $satir, 'pork' ) ) {
                return false;
            }
        }

        if ( isset( $ctx['spicy'] ) ) {
            // Boş / geçersiz acılık = 0 (acısız). Alan yıllarca serbest
            // metindi; "3 kademe" gibi bir değer 3 sayılmaz, 0 sayılır.
            $seviye = self::seviye( isset( $satir['spicy'] ) ? $satir['spicy'] : '' );

            if ( ! in_array( $seviye, $ctx['spicy'], true ) ) {
                return false;
            }
        }

        if ( isset( $ctx['cal'] ) && ! self::aralikta( isset( $satir['calories'] ) ? $satir['calories'] : '', $ctx['cal'] ) ) {
            return false;
        }

        if ( isset( $ctx['price'] ) && ! self::aralikta( isset( $satir['price'] ) ? $satir['price'] : '', $ctx['price'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Checkbox meta'sının "işaretli" olup olmadığı.
     *
     * @param array  $satir Ürün satırı.
     * @param string $alan  Alan adı.
     * @return bool
     */
    private static function bayrak( array $satir, $alan ) {
        return isset( $satir[ $alan ] ) && '1' === (string) $satir[ $alan ];
    }

    /**
     * Acılık meta'sını 0-4 aralığına indirger.
     *
     * @param mixed $ham Meta değeri.
     * @return int
     */
    public static function seviye( $ham ) {
        if ( is_array( $ham ) || is_object( $ham ) ) {
            return 0;
        }

        $ham = trim( (string) $ham );

        if ( '' === $ham || ! is_numeric( $ham ) ) {
            return 0;
        }

        $seviye = (int) $ham;

        return max( 0, min( 4, $seviye ) );
    }

    /**
     * Sayısal alanın aralığa düşüp düşmediği.
     *
     * DEĞER GİRİLMEMİŞSE ÜRÜN ELENİR. Kalorisi bilinmeyen bir ürünü
     * "300 kcal altı" listesinde göstermek yanlış beyan olurdu; bu yüzden
     * boş alan "0" sayılmaz.
     *
     * @param mixed $ham    Meta değeri.
     * @param array $aralik [min, max]; 0 = sınır yok.
     * @return bool
     */
    private static function aralikta( $ham, array $aralik ) {
        if ( is_array( $ham ) || is_object( $ham ) ) {
            return false;
        }

        // Fiyatta "1.234,50" gibi yerelleştirilmiş biçim gelebilir.
        $ham = str_replace( ',', '.', trim( (string) $ham ) );

        if ( '' === $ham || ! is_numeric( $ham ) ) {
            return false;
        }

        $deger = (float) $ham;
        $min   = isset( $aralik[0] ) ? (float) $aralik[0] : 0.0;
        $max   = isset( $aralik[1] ) ? (float) $aralik[1] : 0.0;

        if ( $min > 0 && $deger < $min ) {
            return false;
        }

        if ( $max > 0 && $deger > $max ) {
            return false;
        }

        return true;
    }
}
