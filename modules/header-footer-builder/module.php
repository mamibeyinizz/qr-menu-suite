<?php
/**
 * Modül: Header Footer Builder (header-footer-builder)
 *
 * Elementor kısa kod uyumlu header/footer oluşturucu. Ayarlar
 * `hfb_header_options`, `hfb_footer_options` ve `hfb_hamburger_options`
 * option'larında tutulur. Kısa kodlar: [hfb_header], [hfb_footer].
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * @return void
 */
function qrms_module_header_footer_builder_init() {
	require_once __DIR__ . '/includes/class-header-footer-builder.php';

	QRMS_Header_Footer_Builder::init();
}

/**
 * HFB footer garson/hesap çubuğu bu sayfada kısa kod çıktısını ezer mi?
 *
 * [hfb_footer] + call_enabled açıkken qr-chatbot'un [qr_garson_hesap] /
 * [ikili_buton] kısa kodları boş döner; çift sabit çubuk oluşmaz.
 *
 * @return bool
 */
function qrms_hfb_footer_call_claims_page() {
	$hfb = QRMS_Header_Footer_Builder::instance();

	if ( ! $hfb ) {
		return false;
	}

	return $hfb->footer_call_claims_shortcode_output();
}

/**
 * Footer blok başlıklarını ve metin bloklarını çeviri kataloğuna ekler.
 *
 * Footer düzeni (satır → sütun → blok) yöneticinin yazdığı menü başlıkları
 * ("Kurumsal") ve metin blokları içerir; bunlar çeviriden hiç geçmiyor ve
 * her dilde Türkçe kalıyordu. Ön yüz artık bunları rma_ceviri_modul(
 * 'ui_string', … ) ile çevirir (anahtar metinden türer); bu filtre onları
 * CSV dışa aktarımına taşır. Yalnızca dışa aktarma / yönetim ekranlarında
 * çalışır.
 *
 * @param string[] $metinler Mevcut katalog.
 * @return string[]
 */
function qrms_hfb_ceviri_katalogu( $metinler ) {
	$opts = get_option( 'hfb_footer_options', array() );
	$rows = ( is_array( $opts ) && isset( $opts['layout']['rows'] ) && is_array( $opts['layout']['rows'] ) ) ? $opts['layout']['rows'] : array();

	$ek = array();
	foreach ( $rows as $row ) {
		foreach ( ( isset( $row['cols'] ) && is_array( $row['cols'] ) ) ? $row['cols'] : array() as $col ) {
			foreach ( ( isset( $col['blocks'] ) && is_array( $col['blocks'] ) ) ? $col['blocks'] : array() as $block ) {
				if ( ! is_array( $block ) || empty( $block['type'] ) ) {
					continue;
				}
				if ( 'menu' === $block['type'] && isset( $block['title'] ) ) {
					$ek[] = $block['title'];
				}
				if ( 'text' === $block['type'] && isset( $block['content'] ) ) {
					$ek[] = $block['content'];
				}
			}
		}
	}

	$ek = array_filter( array_map( 'trim', array_map( 'strval', $ek ) ), 'strlen' );

	return array_values( array_unique( array_merge( (array) $metinler, $ek ) ) );
}
add_filter( 'rma_ceviri_ui_metinleri', 'qrms_hfb_ceviri_katalogu' );
