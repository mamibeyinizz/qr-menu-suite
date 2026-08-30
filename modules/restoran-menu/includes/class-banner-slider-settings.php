<?php
/**
 * Kampanya Banner (qmo_banner_slider) — görünüm ayarları.
 *
 * Banner tek bir kısa koddur (vitrin gibi örnek başına satır yok), bu
 * yüzden ayarlar wp_options'ta durur. Temizlik WordPress'e bağımlılığı
 * olmayan saf dönüşümlerdir — doğrudan test edilir.
 *
 * Desen QMO_Slider_Settings'in aynısıdır; ayrı bir sınıf olmasının nedeni
 * iki modülün (ürün vitrini slider'ı ve kampanya banner'ı) birbirinden
 * tamamen bağımsız kalması: alan kümeleri, sınırları ve varsayılanları
 * farklıdır.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'QMO_Banner_Slider_Settings' ) ) :

class QMO_Banner_Slider_Settings {

    /** Option adı. */
    const OPTION = 'qmo_banner_slider_settings';

    /* Banner başlığı görselin üstüne biner; ürün slider'ının başlığından
       büyük olabilir, bu yüzden üst sınır daha geniştir. */
    const MIN_TITLE_SIZE        = 14;
    const MAX_TITLE_SIZE        = 64;
    const MIN_TITLE_SIZE_MOBILE = 12;
    const MAX_TITLE_SIZE_MOBILE = 36;

    /* Otomatik geçiş aralığı (ms). 0 = kapalı; aksi hâlde bu aralığa
       kırpılır (kısa koddaki clamp ile aynı sınırlar). */
    const MIN_AUTOPLAY = 1500;
    const MAX_AUTOPLAY = 15000;

    /**
     * Banner başlığı için yazı tipi seçenekleri.
     *
     * Kısa kodun yüklediği fontlar: Playfair Display (başlık) ve Manrope
     * (gövde). TEK KAYNAK: admin açılır listesi, sanitize beyaz listesi
     * ve CSS yığını hep buradan okunur.
     *
     * @return array<string,array{etiket:string,stack:string}>
     */
    public static function yazi_tipleri() {
        return array(
            'Playfair Display' => array(
                'etiket' => 'Playfair Display (serif)',
                'stack'  => "'Playfair Display', Georgia, serif",
            ),
            'Manrope'          => array(
                'etiket' => 'Manrope',
                'stack'  => "'Manrope', system-ui, sans-serif",
            ),
        );
    }

    /**
     * Banner en-boy oranı seçenekleri.
     *
     * Anahtar formda ve option'da saklanan değer, `css` ise CSS
     * aspect-ratio karşılığıdır. TEK KAYNAK: açılır liste, beyaz liste ve
     * --qmo-banner-oran değişkeni buradan üretilir.
     *
     * @return array<string,array{etiket:string,css:string}>
     */
    public static function oranlar() {
        return array(
            '16:9' => array( 'etiket' => '16:9 — Geniş (varsayılan)', 'css' => '16 / 9' ),
            '21:9' => array( 'etiket' => '21:9 — Sinemaskop (daha ince şerit)', 'css' => '21 / 9' ),
            '3:1'  => array( 'etiket' => '3:1 — İnce şerit', 'css' => '3 / 1' ),
            '4:3'  => array( 'etiket' => '4:3 — Yüksek', 'css' => '4 / 3' ),
            '1:1'  => array( 'etiket' => '1:1 — Kare', 'css' => '1 / 1' ),
        );
    }

    /**
     * Slaytlar arası geçiş biçimleri.
     *
     * @return array<string,string>
     */
    public static function gecisler() {
        return array(
            'slide' => 'Kaydırma — slaytlar yana kayar (varsayılan)',
            'fade'  => 'Solma — slaytlar birbirinin içinde erir',
        );
    }

    /**
     * Otomatik geçiş aralığı için hazır seçenekler (ms => etiket).
     *
     * Serbest sayı yerine kısa bir liste sunulur: aradaki her değer
     * zaten [MIN_AUTOPLAY, MAX_AUTOPLAY] aralığına kırpıldığı için
     * listede olmayan bir değer de güvenle kaydedilir.
     *
     * @return array<int,string>
     */
    public static function autoplay_secenekleri() {
        return array(
            0     => 'Kapalı — sadece elle geçilir',
            2000  => '2 saniye',
            3000  => '3 saniye',
            4500  => '4,5 saniye (varsayılan)',
            6000  => '6 saniye',
            8000  => '8 saniye',
            10000 => '10 saniye',
        );
    }

    /**
     * Varsayılanlar — ayar eklenmeden önceki görünümle aynı.
     *
     * show_nav=1: oklar bu sürümle geliyor, tek yeni görünen öğedir.
     * show_title=0: banner başlığı yönetimdeki kayıt adıdır ("Yaz
     * Kampanyası" gibi), görselin üstüne basılması istenmeden açılmaz.
     * Geçiş/oran/autoplay eski davranışın birebir karşılığıdır.
     *
     * @return array<string,int|string>
     */
    public static function varsayilanlar() {
        return array(
            'show_nav'          => 1,
            'show_dots'         => 1,
            'show_title'        => 0,
            'gecis'             => 'slide',
            'oran'              => '16:9',
            'autoplay'          => 4500,
            'title_font'        => 'Playfair Display',
            'title_color'       => '#f5f0e8',
            'title_size'        => 32,
            'title_size_mobile' => 20,
            'title_weight'      => 600,
            'title_align'       => 'center',
        );
    }

    /**
     * Kayıtlı ayarları varsayılanlarla birleştirip temizler.
     *
     * Option hiç yoksa (ilk kurulum) varsayılanlar döner — eski siteler
     * güncellemeyle birlikte görünüm değiştirmez.
     *
     * @return array<string,int|string>
     */
    public static function get() {
        $kayitli = get_option( self::OPTION, null );

        if ( ! is_array( $kayitli ) ) {
            return self::varsayilanlar();
        }

        return self::sanitize( array_merge( self::varsayilanlar(), $kayitli ) );
    }

    /**
     * Ham formu temizleyip option'a yazar.
     *
     * @param array $ham Ham `$_POST['qmo_banner_slider_settings']` benzeri dizi.
     * @return array<string,int|string> Kaydedilen değerler.
     */
    public static function kaydet( array $ham ) {
        $temiz = self::sanitize( $ham );
        update_option( self::OPTION, $temiz, false );

        return $temiz;
    }

    /**
     * Ham girdiyi şemaya uygun, sınırlanmış değerlere çevirir.
     *
     * Checkbox işaretli değilse POST'a hiç gelmez — 0 yazılır. Renk
     * geçersizse varsayılana düşer (boş bırakılınca başlık kaybolmasın
     * diye). Font/oran/geçiş/hizalama beyaz listede yoksa varsayılana
     * çekilir. Autoplay 0 ise "kapalı" demektir ve olduğu gibi kalır;
     * 0'dan büyük her değer [MIN,MAX] aralığına kırpılır.
     *
     * @param array $ham Ham dizi.
     * @return array<string,int|string>
     */
    public static function sanitize( array $ham ) {
        $v = self::varsayilanlar();

        $renk = self::hex_renk( $ham['title_color'] ?? $v['title_color'] );
        if ( '' === $renk ) {
            $renk = $v['title_color'];
        }

        $boyut = isset( $ham['title_size'] ) ? $ham['title_size'] : $v['title_size'];
        $mobil = isset( $ham['title_size_mobile'] ) ? $ham['title_size_mobile'] : $v['title_size_mobile'];

        return array(
            'show_nav'          => self::bayrak( $ham['show_nav'] ?? 0 ),
            'show_dots'         => self::bayrak( $ham['show_dots'] ?? 0 ),
            'show_title'        => self::bayrak( $ham['show_title'] ?? 0 ),
            'gecis'             => self::gecis( $ham['gecis'] ?? $v['gecis'] ),
            'oran'              => self::oran( $ham['oran'] ?? $v['oran'] ),
            'autoplay'          => self::autoplay( $ham['autoplay'] ?? $v['autoplay'], $v['autoplay'] ),
            'title_font'        => self::yazi_tipi( $ham['title_font'] ?? $v['title_font'] ),
            'title_color'       => $renk,
            'title_size'        => self::sinirla( $boyut, self::MIN_TITLE_SIZE, self::MAX_TITLE_SIZE, $v['title_size'] ),
            'title_size_mobile' => self::sinirla( $mobil, self::MIN_TITLE_SIZE_MOBILE, self::MAX_TITLE_SIZE_MOBILE, $v['title_size_mobile'] ),
            'title_weight'      => self::yazi_kalinligi( $ham['title_weight'] ?? $v['title_weight'], $v['title_weight'] ),
            'title_align'       => self::hizalama( $ham['title_align'] ?? $v['title_align'] ),
        );
    }

    /**
     * Kök öğeye yazılacak CSS custom property dizesi.
     *
     * @param array|null $ayar get() çıktısı; null ise taze okunur.
     * @return string
     */
    public static function css_degiskenleri( $ayar = null ) {
        $a = is_array( $ayar ) ? $ayar : self::get();

        return sprintf(
            '--qmo-banner-oran:%1$s;--qmo-banner-title-font:%2$s;--qmo-banner-title-color:%3$s;--qmo-banner-title-size:%4$dpx;--qmo-banner-title-size-mobile:%5$dpx;--qmo-banner-title-weight:%6$d;--qmo-banner-title-align:%7$s;',
            self::oran_css( $a['oran'] ),
            self::font_stack( $a['title_font'] ),
            $a['title_color'],
            (int) $a['title_size'],
            (int) $a['title_size_mobile'],
            (int) $a['title_weight'],
            $a['title_align']
        );
    }

    /**
     * Oran anahtarının CSS aspect-ratio karşılığı.
     *
     * @param string $anahtar oranlar() anahtarı.
     * @return string
     */
    public static function oran_css( $anahtar ) {
        $oranlar = self::oranlar();
        $anahtar = self::oran( $anahtar );

        return $oranlar[ $anahtar ]['css'];
    }

    /**
     * Yazı tipi anahtarının CSS font-family yığını.
     *
     * @param string $anahtar yazi_tipleri() anahtarı.
     * @return string
     */
    public static function font_stack( $anahtar ) {
        $tipler  = self::yazi_tipleri();
        $anahtar = (string) $anahtar;

        if ( isset( $tipler[ $anahtar ] ) ) {
            return $tipler[ $anahtar ]['stack'];
        }

        return $tipler['Playfair Display']['stack'];
    }

    /**
     * Oran anahtarını beyaz listeye göre doğrular.
     *
     * @param mixed $deger Ham değer.
     * @return string
     */
    public static function oran( $deger ) {
        $deger = trim( (string) $deger );

        return array_key_exists( $deger, self::oranlar() ) ? $deger : '16:9';
    }

    /**
     * Geçiş biçimini beyaz listeye göre doğrular.
     *
     * @param mixed $deger Ham değer.
     * @return string
     */
    public static function gecis( $deger ) {
        $deger = strtolower( trim( (string) $deger ) );

        return array_key_exists( $deger, self::gecisler() ) ? $deger : 'slide';
    }

    /**
     * Otomatik geçiş aralığı; 0 kapalı, diğerleri [MIN,MAX] aralığında.
     *
     * @param mixed $deger      Ham değer.
     * @param int   $varsayilan Sayı olmayan girdide kullanılacak değer.
     * @return int
     */
    public static function autoplay( $deger, $varsayilan = 4500 ) {
        if ( ! is_numeric( $deger ) ) {
            return (int) $varsayilan;
        }

        $deger = (int) $deger;

        if ( $deger <= 0 ) {
            return 0;
        }

        return (int) max( self::MIN_AUTOPLAY, min( self::MAX_AUTOPLAY, $deger ) );
    }

    /**
     * Yazı tipi anahtarını beyaz listeye göre doğrular.
     *
     * @param mixed $deger Ham değer.
     * @return string
     */
    public static function yazi_tipi( $deger ) {
        $deger = trim( (string) $deger );

        return array_key_exists( $deger, self::yazi_tipleri() ) ? $deger : 'Playfair Display';
    }

    /**
     * Sayısal değeri [min,max] aralığına çeker.
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
     * Hex renk; geçersizse boş dize.
     *
     * @param mixed $deger Ham değer.
     * @return string
     */
    public static function hex_renk( $deger ) {
        $renk = sanitize_hex_color( trim( (string) $deger ) );

        return $renk ? $renk : '';
    }

    /**
     * Yazı kalınlığını beyaz listeye göre doğrular.
     *
     * @param mixed $deger      Ham değer.
     * @param int   $varsayilan Listede yoksa kullanılacak değer.
     * @return int
     */
    public static function yazi_kalinligi( $deger, $varsayilan = 600 ) {
        $deger = (int) $deger;

        return in_array( $deger, array( 400, 500, 600, 700 ), true ) ? $deger : (int) $varsayilan;
    }

    /**
     * Metin hizalamasını beyaz listeye göre doğrular.
     *
     * @param mixed $deger Ham değer.
     * @return string
     */
    public static function hizalama( $deger ) {
        $deger = strtolower( trim( (string) $deger ) );

        return in_array( $deger, array( 'left', 'center', 'right' ), true ) ? $deger : 'center';
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
}

endif;
