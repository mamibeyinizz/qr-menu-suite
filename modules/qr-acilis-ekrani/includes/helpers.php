<?php
defined( 'ABSPATH' ) || exit;

trait QRMS_AE_Helpers {

    /**
     * Kayıtlı ayarlar, varsayılanlarla tamamlanmış.
     *
     * array_merge SIĞ birleştirir: kayıtta `button_texts` varsa dizinin
     * TAMAMI kayıttan gelir ve içinde eksik olan alt anahtar (ör. 'btn5')
     * varsayılandan gelmez — okuyan her yer "undefined index" verir. Eski bir
     * sürümden gelen ya da elle düzenlenmiş bir option'da bu gerçek bir
     * durumdur; iki dilli `texts_en` de aynı biçimde kırılgandır.
     *
     * Birlik operatörü (+) yalnızca EKSİK anahtarları doldurur: kayıtlı bir
     * değeri asla ezmez ve array_merge'ün aksine sayısal listelere öğe
     * eklemez (footer_icons gibi dört elemanlı listeler bozulmaz).
     *
     * @return array
     */
    private function get_options() {
        $saved = get_option($this->option_name, array());
        $opts  = array_merge($this->defaults, is_array($saved) ? $saved : array());

        foreach ($this->defaults as $key => $default) {
            if (is_array($default) && isset($opts[$key]) && is_array($opts[$key])) {
                $opts[$key] += $default;
            }
        }

        return $opts;
    }

    /**
     * Option'dan sayısal değer okur ve verilen aralığa sıkıştırır.
     *
     * Eski kayıtlarda yeni anahtarlar bulunmaz; isset() kontrolü ve
     * $this->defaults geri düşüşü ile geriye dönük uyum sağlanır.
     */
    private function opt_int($opts, $key, $min, $max, $fallback = null) {
        if ($fallback === null) {
            $fallback = isset($this->defaults[$key]) ? $this->defaults[$key] : $min;
        }
        $value = (isset($opts[$key]) && $opts[$key] !== '') ? absint($opts[$key]) : absint($fallback);
        return max($min, min($max, $value));
    }

    /**
     * Option'dan hex renk okur; geçersiz/boş değerde defaults'a düşer.
     */
    private function opt_hex($opts, $key, $fallback = null) {
        if ($fallback === null) {
            $fallback = isset($this->defaults[$key]) ? $this->defaults[$key] : '#ffffff';
        }
        $value = isset($opts[$key]) ? sanitize_hex_color((string) $opts[$key]) : '';
        return $value ? $value : $fallback;
    }

    /**
     * Option'dan beyaz listeye tabi bir anahtar okur.
     */
    private function opt_choice($opts, $key, $allowed, $fallback = null) {
        if ($fallback === null) {
            $fallback = isset($this->defaults[$key]) ? $this->defaults[$key] : reset($allowed);
        }
        $value = isset($opts[$key]) ? (string) $opts[$key] : '';
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function hex_to_rgba($hex, $alpha = 1) {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return 'rgba(0,0,0,' . $alpha . ')';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, $alpha);
    }

    /**
     * "#0073aa" → "0,115,170". CSS'te rgba(var(--sp-accent-rgb), var(--sp-btn-alpha))
     * şeklinde alfa ile birleştirmek için kullanılır.
     */
    private function hex_to_rgb_triplet($hex, $fallback = '0,115,170') {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return $fallback;
        }
        return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
    }

    private function hex_luminance($hex) {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return 255;
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
    }

    /**
     * "14px 28px" biçimindeki option değerini iki sayısal alana ayırır.
     * Option formatı DEĞİŞMEZ; bu sadece admin formunda göstermek içindir.
     *
     * @return array [dikey, yatay]
     */
    private function parse_padding($value) {
        $parts = preg_split('/\s+/', trim((string) $value));

        $vertical   = isset($parts[0]) ? absint(preg_replace('/[^0-9]/', '', $parts[0])) : 0;
        $horizontal = isset($parts[1]) ? absint(preg_replace('/[^0-9]/', '', $parts[1])) : $vertical;

        if ($vertical <= 0)   $vertical   = 14;
        if ($horizontal <= 0) $horizontal = 28;

        return array(min($vertical, 60), min($horizontal, 90));
    }

    /**
     * "16px" biçimindeki option değerinden sayıyı çıkarır ve 12–24 arasına sıkıştırır.
     */
    private function parse_font_size($value) {
        $size = absint(preg_replace('/[^0-9]/', '', (string) $value));
        if ($size <= 0) $size = 16;
        return max(12, min(24, $size));
    }

    /**
     * QR Çeviri bu istekte konuşulabilir mi?
     *
     * Dil seçici kendi motorunu yazmaz: dil listesi, çerez adı ve ?lang=
     * anahtarı QR Çeviri'den gelir. Modül kapalıysa ya da fonksiyonları
     * yoksa seçici hiç basılmaz.
     *
     * @return bool
     */
    private function ceviri_available() {
        if ( ! class_exists( 'QRMS_Module_Loader' ) || ! QRMS_Module_Loader::is_module_active( 'qr-ceviri' ) ) {
            return false;
        }

        return function_exists( 'qrmenu_get_langs' ) && function_exists( 'rma_ceviri_aktif_diller' );
    }

    /**
     * Splash'taki bayrak seçici DOM'a girsin mi?
     *
     * Kapalıyken (veya gösterilecek dil kalmamışken) markup'a tek bir düğüm
     * bile eklenmez — JS de elementi bulamayınca dinleyici bağlamaz.
     *
     * @param array $opts Ayarlar.
     * @return bool
     */
    private function ceviri_selector_active( $opts ) {
        return ! empty( $opts['ceviri_selector'] ) && count( $this->ceviri_selector_langs( $opts ) ) > 0;
    }

    /**
     * Seçicide gösterilecek dil kodları.
     *
     * Yönetici işaretleriyle QR Çeviri'nin o an açık dillerinin kesişimi.
     * QR Çeviri bir dili kapatırsa splash'ta da kaybolur.
     *
     * @param array $opts Ayarlar.
     * @return string[]
     */
    private function ceviri_selector_langs( $opts ) {
        if ( ! $this->ceviri_available() ) {
            return array();
        }

        $aktif  = rma_ceviri_aktif_diller();
        $secili = isset( $opts['ceviri_selector_langs'] ) && is_array( $opts['ceviri_selector_langs'] )
            ? $opts['ceviri_selector_langs']
            : array();
        $out    = array();

        foreach ( $secili as $kod ) {
            $kod = is_string( $kod ) ? $kod : '';
            if ( '' !== $kod && in_array( $kod, $aktif, true ) && ! in_array( $kod, $out, true ) ) {
                $out[] = $kod;
            }
        }

        return $out;
    }
}
