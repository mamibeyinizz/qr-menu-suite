<?php
/**
 * Dil seçici kısa kodları.
 *
 * HTML yapısı ve sınıf adları v1.7 ile BİREBİR aynı — sitedeki mevcut
 * yerleşim ve özel CSS'ler bozulmasın diye. Tek fark: buton artık sabit
 * 🇹🇷/Türkçe yerine o an görüntülenen dili gösteriyor (sunucu tarafında
 * render edildiği için ilk boyamada doğru dil görünür).
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dropdown HTML'ini üret.
 *
 * @param string $ek_sinif Sarmalayıcıya eklenecek sınıf ("qrmenu-flags-only-mod").
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_dropdown_html' ) ) {
	function rma_ceviri_dropdown_html( $ek_sinif = '' ) {
		$tumu   = qrmenu_get_langs();
		$aktif  = rma_ceviri_aktif_diller();
		$simdi  = rma_get_current_lang();
		$mevcut = isset( $tumu[ $simdi ] ) ? $tumu[ $simdi ] : $tumu['tr'];

		$sinif = 'qrmenu-lang-dropdown' . ( $ek_sinif ? ' ' . $ek_sinif : '' );

		$html  = '<div class="' . esc_attr( $sinif ) . '" data-rma-lang="' . esc_attr( $simdi ) . '">';
		$html .= '<button class="qrmenu-current-btn" type="button" aria-haspopup="listbox" aria-label="Dil seç">';
		$html .= '<div class="qrmenu-current-left">';
		$html .= '<span class="qrmenu-current-flag">' . $mevcut['flag'] . '</span>';
		$html .= '<span class="qrmenu-current-text">' . esc_html( $mevcut['name'] ) . '</span>';
		$html .= '</div>';
		$html .= '<span class="qrmenu-arrow" aria-hidden="true">▼</span>';
		$html .= '</button>';
		$html .= '<div class="qrmenu-options-panel" role="listbox">';

		foreach ( $aktif as $kod ) {
			if ( ! isset( $tumu[ $kod ] ) ) {
				continue;
			}

			$html .= sprintf(
				'<button type="button" role="option" aria-selected="%1$s" onclick="qrmenuTranslate(\'%2$s\',\'%3$s\',\'%4$s\')"><span class="qrmenu-opt-flag">%5$s</span><span class="qrmenu-opt-text">%6$s</span></button>',
				$kod === $simdi ? 'true' : 'false',
				esc_js( $kod ),
				esc_js( $tumu[ $kod ]['flag'] ),
				esc_js( $tumu[ $kod ]['name'] ),
				$tumu[ $kod ]['flag'],
				esc_html( $tumu[ $kod ]['name'] )
			);
		}

		$html .= '</div></div>';

		return $html;
	}
}

add_shortcode( 'qrmenu_flags_only', 'qrmenu_sc_flags_only' );
/**
 * [qrmenu_flags_only] — sadece bayrak, kare buton.
 *
 * @return string
 */
if ( ! function_exists( 'qrmenu_sc_flags_only' ) ) {
	function qrmenu_sc_flags_only() {
		return rma_ceviri_dropdown_html( 'qrmenu-flags-only-mod' );
	}
}

add_shortcode( 'qrmenu_flags_text', 'qrmenu_sc_flags_text' );
/**
 * [qrmenu_flags_text] — bayrak + dil adı.
 *
 * @return string
 */
if ( ! function_exists( 'qrmenu_sc_flags_text' ) ) {
	function qrmenu_sc_flags_text() {
		return rma_ceviri_dropdown_html();
	}
}
