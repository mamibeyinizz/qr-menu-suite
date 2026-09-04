<?php
/**
 * Menü Mühendisliği — ortak biçimlendirme yardımcıları.
 *
 * Ekranlar ve AJAX uçları aynı biçimi kullanır; ekran dosyalarından birinde
 * tanımlı olsalardı AJAX isteği o dosya yüklenmediğinde ölümcül hata verirdi.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qrms_mm_para' ) ) {
	/**
	 * Para biçimlendirme.
	 *
	 * @param float $deger Değer.
	 * @return string
	 */
	function qrms_mm_para( $deger ) {
		return number_format_i18n( (float) $deger, 2 ) . ' ₺';
	}
}

if ( ! function_exists( 'qrms_mm_yuzde' ) ) {
	/**
	 * Yüzde biçimlendirme.
	 *
	 * @param float $deger Değer.
	 * @return string
	 */
	function qrms_mm_yuzde( $deger ) {
		return number_format_i18n( (float) $deger, 1 ) . '%';
	}
}
