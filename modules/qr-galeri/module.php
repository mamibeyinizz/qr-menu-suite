<?php
/**
 * Modül: QR Galeri (qr-galeri)
 *
 * Bölüm bazlı görsel yönetimi, otomatik WebP, masonry grid, lightbox ve Ajax
 * filtre. Dosyalar QR Menu Gallery Manager eklentisinden aynen taşındı; burada
 * yalnızca yükleme bağlantısı ve suite menüsündeki sayfa kaydı var.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır.
 *
 * @return void
 */
function qrms_module_qr_galeri_init() {
	if ( ! defined( 'QRMGM_VERSION' ) ) {
		define( 'QRMGM_VERSION', '1.0.0' );
	}
	if ( ! defined( 'QRMGM_FILE' ) ) {
		define( 'QRMGM_FILE', __DIR__ . '/class-qrmgm-gallery-manager.php' );
	}
	if ( ! defined( 'QRMGM_URL' ) ) {
		define( 'QRMGM_URL', QRMS_PLUGIN_URL . 'modules/qr-galeri/' );
	}

	require_once __DIR__ . '/class-qrmgm-gallery-manager.php';

	$manager = QRMenu_Gallery_Manager::instance();

	if ( false === get_option( QRMenu_Gallery_Manager::OPTION_SETTINGS ) ) {
		$manager->activate();
	}

	if ( is_admin() ) {
		QRMS_Admin::register_module_page(
			'qr-galeri',
			array( $manager, 'page_sections' )
		);
	}
}
