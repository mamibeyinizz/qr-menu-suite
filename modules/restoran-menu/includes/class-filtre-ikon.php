<?php
/**
 * Filtre paneli ikonları — tek çizgi kalınlığı, currentColor stroke.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'RMA_Filtre_Ikon' ) ) {
    return;
}

/**
 * Premium SVG ikon seti (filtre + sıralama paneli).
 */
class RMA_Filtre_Ikon {

    /**
     * İkon anahtarına göre SVG döndürür.
     *
     * @param string $anahtar İkon anahtarı.
     * @return string
     */
    public static function svg( $anahtar ) {
        $anahtar = (string) $anahtar;
        $harita  = self::harita();

        if ( ! isset( $harita[ $anahtar ] ) ) {
            return self::sar( self::uyari() );
        }

        return self::sar( $harita[ $anahtar ] );
    }

    /**
     * Alerjen slug → ikon anahtarı.
     *
     * @param string $slug Alerjen slug.
     * @return string
     */
    public static function alerjen_anahtari( $slug ) {
        $harita = array(
            'gluten'         => 'alerjen-gluten',
            'sut'            => 'alerjen-sut',
            'yumurta'        => 'alerjen-yumurta',
            'findik'         => 'alerjen-findik',
            'yer-fistigi'    => 'alerjen-fistik',
            'soya'           => 'alerjen-soya',
            'balik'          => 'alerjen-balik',
            'kabuklu-deniz'  => 'alerjen-deniz',
            'susam'          => 'alerjen-susam',
            'kereviz'        => 'alerjen-kereviz',
            'hardal'         => 'alerjen-hardal',
            'lupin'          => 'alerjen-lupin',
            'kukurt-dioksit' => 'alerjen-sulfit',
            'yumusakca'      => 'alerjen-yumusakca',
        );

        return isset( $harita[ $slug ] ) ? $harita[ $slug ] : 'uyari';
    }

    /**
     * @param string $paths SVG path/grup içeriği.
     * @return string
     */
    private static function sar( $paths ) {
        return '<svg class="rma-fi" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }

    /**
     * @return array<string,string>
     */
    private static function harita() {
        return array(
            // Sıralama
            'recommended'  => '<path d="M10 2.5l1.6 3.4 3.8.5-2.7 2.6.7 3.8L10 11.8 6.6 12.8l.7-3.8-2.7-2.6 3.8-.5L10 2.5z"/>',
            'az'           => '<path d="M4 6h8M4 10h6M4 14h10"/><path d="M14.5 5.5v9M12 8l2.5-2.5L17 8"/>',
            'price-up'     => '<path d="M10 4v12M6 8l4-4 4 4"/><path d="M4 16h12"/>',
            'price-down'   => '<path d="M10 16V4M6 12l4 4 4-4"/><path d="M4 16h12"/>',
            'protein'      => '<path d="M5 14c1.5-2 3-2 5 0s3.5 2 5 0"/><circle cx="7" cy="7" r="2"/><circle cx="13" cy="7" r="2"/>',
            'carbs'        => '<path d="M4 14c2-3 4-3 6 0s4 3 6 0"/><path d="M7 6c0-1.5 1.3-2.5 3-2.5S13 4.5 13 6"/>',
            'spicy-hot'    => '<path d="M10 17c3 0 5-2.2 5-5.5C15 8 10 4 10 4S5 8 5 11.5C5 14.8 7 17 10 17z"/><path d="M10 4V2.5"/>',
            'spicy-mild'   => '<path d="M10 16c2.2 0 4-1.6 4-4.2C14 9 10 6 10 6S6 9 6 11.8C6 14.4 7.8 16 10 16z"/><path d="M10 6V4.5"/>',

            // Ürün özellikleri
            'popular'      => '<path d="M10 3l1.2 3.6h3.8l-3 2.3 1.2 3.6L10 10.2 6.8 12.5l1.2-3.6-3-2.3h3.8L10 3z"/>',
            'new'          => '<path d="M10 3v4M10 13v4M3 10h4M13 10h4"/><circle cx="10" cy="10" r="2.5"/>',
            'star'         => '<path d="M10 2.8l1.5 3.2 3.5.4-2.6 2.4.6 3.4L10 11.2 7 12.2l.6-3.4-2.6-2.4 3.5-.4L10 2.8z"/>',
            'discount'     => '<path d="M6 4h8l2 2v8l-2 2H6l-2-2V6l2-2z"/><circle cx="8" cy="8" r="1"/><circle cx="12" cy="12" r="1"/><path d="M12 8l-4 4"/>',

            // Alerjenler
            'alerjen-gluten'    => '<path d="M4 10c0-3.3 2.7-6 6-6s6 2.7 6 6-2.7 6-6 6"/><path d="M10 4v12M7 7h6M7 13h6"/>',
            'alerjen-sut'       => '<path d="M6 5h8v10H6z"/><path d="M8 5V3.5h4V5"/><path d="M8 10h4"/>',
            'alerjen-yumurta'   => '<ellipse cx="10" cy="11" rx="5" ry="6"/><path d="M10 5c2 1.5 3 3.5 3 6"/>',
            'alerjen-findik'    => '<ellipse cx="10" cy="12" rx="4" ry="5"/><path d="M10 7V5"/><path d="M8 9h4"/>',
            'alerjen-fistik'    => '<path d="M8 6c-1 2-1 4 0 6s3 3 5 2 2-4 1-6-3-4-6-2z"/>',
            'alerjen-soya'      => '<circle cx="7" cy="10" r="2"/><circle cx="13" cy="10" r="2"/><circle cx="10" cy="14" r="2"/><path d="M10 4v2"/>',
            'alerjen-balik'     => '<path d="M3 10c2-3 5-4 8-3l2-2v6l-2-2c-3 1-6 0-8-3z"/><circle cx="12" cy="9" r=".8" fill="currentColor" stroke="none"/>',
            'alerjen-deniz'     => '<path d="M4 12c2-2 4-2 6 0s4 2 6 0"/><path d="M6 8l2-2 2 2 2-2 2 2"/><path d="M10 14v3"/>',
            'alerjen-susam'     => '<circle cx="7" cy="9" r="1"/><circle cx="13" cy="9" r="1"/><circle cx="10" cy="12" r="1"/><circle cx="8" cy="13" r="1"/><circle cx="12" cy="13" r="1"/>',
            'alerjen-kereviz'   => '<path d="M10 4v12"/><path d="M6 8h8M6 12h8"/><path d="M8 6l2-2 2 2M8 14l2 2 2-2"/>',
            'alerjen-hardal'    => '<path d="M6 6h8v8H6z"/><path d="M8 10h4M8 13h4"/>',
            'alerjen-lupin'     => '<ellipse cx="8" cy="11" rx="2" ry="3"/><ellipse cx="12" cy="11" rx="2" ry="3"/><path d="M10 5v2"/>',
            'alerjen-sulfit'    => '<path d="M7 5h6l1 3-4 9-4-9 1-3z"/><path d="M8 9h4"/>',
            'alerjen-yumusakca' => '<path d="M5 12c1-3 3-4 5-4s4 1 5 4"/><path d="M4 14h12"/>',

            'uyari'        => '<path d="M10 3.5L3 16h14L10 3.5z"/><path d="M10 9v3M10 14.5v.5"/>',
        );
    }

    /**
     * @return string
     */
    private static function uyari() {
        return '<path d="M10 3.5L3 16h14L10 3.5z"/><path d="M10 9v3M10 14.5v.5"/>';
    }
}
