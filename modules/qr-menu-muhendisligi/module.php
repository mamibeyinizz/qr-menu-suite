<?php
/**
 * Modül: Menü Mühendisliği (qr-menu-muhendisligi)
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * @return void
 */
function qrms_module_qr_menu_muhendisligi_init() {
	require_once __DIR__ . '/includes/class-qrms-mm-hesap.php';
	require_once __DIR__ . '/includes/class-qrms-mm-maliyet.php';
	require_once __DIR__ . '/includes/ajax.php';
	require_once __DIR__ . '/includes/export-csv.php';

	if ( is_admin() ) {
		require_once __DIR__ . '/includes/admin/hub-sayfasi.php';
		require_once __DIR__ . '/includes/admin/rapor-sayfasi.php';
		require_once __DIR__ . '/includes/admin/maliyet-sayfasi.php';
		require_once __DIR__ . '/includes/admin/malzeme-sayfasi.php';
		require_once __DIR__ . '/includes/admin/ayarlar-sayfasi.php';

		QRMS_Admin::register_module_page( 'qr-menu-muhendisligi', 'qrms_mm_hub_sayfasi' );
		add_action( 'admin_menu', 'qrms_module_qr_menu_muhendisligi_admin_menu', 20 );
		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_menu_muhendisligi_admin_assets' );
	}
}

/**
 * Alt sayfaları kaydeder.
 *
 * @return void
 */
function qrms_module_qr_menu_muhendisligi_admin_menu() {
	global $submenu;

	if ( empty( $submenu[ QRMS_Admin::MENU_SLUG ] ) ) {
		return;
	}

	$sayfalar = array(
		'qrms-mm-rapor'    => array( 'title' => __( 'Menü Mühendisliği Raporu', 'qrms' ), 'render' => 'qrms_mm_rapor_sayfasi' ),
		'qrms-mm-maliyet'  => array( 'title' => __( 'Ürün Maliyetleri', 'qrms' ), 'render' => 'qrms_mm_maliyet_sayfasi' ),
		'qrms-mm-malzeme'  => array( 'title' => __( 'Malzeme Fiyatları', 'qrms' ), 'render' => 'qrms_mm_malzeme_sayfasi' ),
		'qrms-mm-ayarlar'  => array( 'title' => __( 'Ayarlar', 'qrms' ), 'render' => 'qrms_mm_ayarlar_sayfasi' ),
	);

	foreach ( $sayfalar as $slug => $sayfa ) {
		add_submenu_page(
			QRMS_Admin::MENU_SLUG,
			$sayfa['title'],
			$sayfa['title'],
			QRMS_Admin::CAPABILITY,
			$slug,
			QRMS_Admin::register_module_subpage( 'qr-menu-muhendisligi', $slug, $sayfa['render'] )
		);
	}
}

/**
 * Yönetim varlıklarını kuyruğa alır.
 *
 * @return void
 */
function qrms_module_qr_menu_muhendisligi_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$izin = array(
		QRMS_Admin::get_module_page_slug( 'qr-menu-muhendisligi' ),
		'qrms-mm-rapor',
		'qrms-mm-maliyet',
		'qrms-mm-malzeme',
		'qrms-mm-ayarlar',
	);

	if ( ! in_array( $page, $izin, true ) ) {
		return;
	}

	wp_enqueue_style(
		'qrms-mm-admin',
		QRMS_PLUGIN_URL . 'modules/qr-menu-muhendisligi/assets/css/admin.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-menu-muhendisligi/assets/css/admin.css' )
	);

	if ( 'qrms-mm-rapor' === $page ) {
		wp_enqueue_script(
			'qrms-mm-rapor',
			QRMS_PLUGIN_URL . 'modules/qr-menu-muhendisligi/assets/js/rapor.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-menu-muhendisligi/assets/js/rapor.js' ),
			true
		);
	}

	if ( in_array( $page, array( 'qrms-mm-maliyet', 'qrms-mm-malzeme', 'qrms-mm-ayarlar' ), true ) ) {
		wp_enqueue_script(
			'qrms-mm-maliyet',
			QRMS_PLUGIN_URL . 'modules/qr-menu-muhendisligi/assets/js/maliyet.js',
			array( 'jquery' ),
			QRMS_Helpers::asset_version( 'modules/qr-menu-muhendisligi/assets/js/maliyet.js' ),
			true
		);
		wp_localize_script(
			'qrms-mm-maliyet',
			'QRMS_MM',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'qrms_mm_admin' ),
				'i18n'    => array(
					'kaydedildi' => __( 'Kaydedildi.', 'qrms' ),
					'hata'       => __( 'Kayıt başarısız.', 'qrms' ),
				),
			)
		);
	}
}
