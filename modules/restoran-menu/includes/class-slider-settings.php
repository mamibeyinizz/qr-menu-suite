<?php
/**
 * Öne Çıkan Slider (qmo_one_cikan_slider) — görünüm ayarları.
 *
 * Slider tek bir kısa koddur (vitrin gibi örnek başına satır yok), bu
 * yüzden ayarlar wp_options'ta durur. Temizlik WordPress'e bağımlılığı
 * olmayan saf dönüşümlerdir — doğrudan test edilir.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'QMO_Slider_Settings' ) ) :

class QMO_Slider_Settings {

    /** Option adı. */
    const OPTION = 'qmo_slider_settings';

    /* Slide başlığı boyutu (px). Slider başlığı kart adından büyüktür;
       mevcut clamp(1.1rem … 1.75rem) görünümünün px karşılığı varsayılan
       olarak korunur. Mobil aralık daha dardır. */
    const MIN_TITLE_SIZE        = 14;
    const MAX_TITLE_SIZE        = 48;
    const MIN_TITLE_SIZE_MOBILE = 12;
    const MAX_TITLE_SIZE_MOBILE = 32;

    /**
     * Slider başlığı için yazı tipi seçenekleri.
     *
     * Kısa kodun mevcut fontları: Playfair Display (başlık) ve Manrope
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
     * Varsayılanlar — ayar eklenmeden önceki görünümle aynı.
     *
     * show_title=1: kısa kod niteliği show_title="yes" varsayılanı.
     * show_nav=1: birden fazla slayt varken oklar basılıyordu.
     * Başlık: Playfair 600, #e8c766, ortalı, ~28px / ~18px.
     *
     * @return array<string,int|string>
     */
    public static function varsayilanlar() {
        return array(
            'show_nav'           => 1,
            'show_title'         => 1,
            'title_font'         => 'Playfair Display',
            'title_color'        => '#e8c766',
            'title_size'         => 28,
            'title_size_mobile'  => 18,
            'title_weight'       => 600,
            'title_align'        => 'center',
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
     * @param array $ham Ham `$_POST['qmo_slider_settings']` benzeri dizi.
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
     * diye vitrindeki "boş = CSS varsayılanı" davranışından farklı).
     * Font beyaz listede yoksa Playfair'e çekilir.
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

        $boyut = isset( $ham['title_size'] ) ? absint( $ham['title_size'] ) : $v['title_size'];
        $mobil = isset( $ham['title_size_mobile'] ) ? absint( $ham['title_size_mobile'] ) : $v['title_size_mobile'];

        return array(
            'show_nav'          => self::bayrak( $ham['show_nav'] ?? 0 ),
            'show_title'        => self::bayrak( $ham['show_title'] ?? 0 ),
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
        $a     = is_array( $ayar ) ? $ayar : self::get();
        $stack = self::font_stack( $a['title_font'] );

        return sprintf(
            '--qmo-slider-title-font:%1$s;--qmo-slider-title-color:%2$s;--qmo-slider-title-size:%3$dpx;--qmo-slider-title-size-mobile:%4$dpx;--qmo-slider-title-weight:%5$d;--qmo-slider-title-align:%6$s;',
            $stack,
            $a['title_color'],
            (int) $a['title_size'],
            (int) $a['title_size_mobile'],
            (int) $a['title_weight'],
            $a['title_align']
        );
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
     * Yazı tipi anahtarını beyaz listeye göre doğrular.
     *
     * @param mixed $deger Ham değer.
     * @return string
     */
    public static function yazi_tipi( $deger ) {
        $deger  = trim( (string) $deger );
        $tipler = self::yazi_tipleri();

        return array_key_exists( $deger, $tipler ) ? $deger : 'Playfair Display';
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
