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
