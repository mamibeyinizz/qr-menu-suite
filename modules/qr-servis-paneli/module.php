<?php
/**
 * Modül: Servis Paneli (qr-servis-paneli)
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * @return void
 */
function qrms_module_qr_servis_paneli_init() {
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';
	require_once __DIR__ . '/includes/class-qrms-sp-veri.php';
	require_once __DIR__ . '/includes/class-qrms-sp-rol.php';
	require_once __DIR__ . '/includes/ajax.php';

	QRMS_SP_Rol::init();

	if ( is_admin() ) {
		require_once __DIR__ . '/includes/admin/panel-sayfasi.php';
		require_once __DIR__ . '/includes/admin/ayarlar-sayfasi.php';

		QRMS_Admin::register_module_page( 'qr-servis-paneli', 'qrms_sp_panel_sayfasi' );
		add_action( 'admin_menu', 'qrms_module_qr_servis_paneli_admin_menu', 20 );
		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_servis_paneli_admin_assets' );
	}
}

/**
 * Ayarlar alt sayfasını kaydeder.
 *
 * @return void
 */
function qrms_module_qr_servis_paneli_admin_menu() {
	global $submenu;

	if ( empty( $submenu[ QRMS_Admin::MENU_SLUG ] ) ) {
		return;
	}

	add_submenu_page(
		QRMS_Admin::MENU_SLUG,
		__( 'Servis Paneli Ayarları', 'qrms' ),
		__( 'Servis Paneli Ayarları', 'qrms' ),
		QRMS_Admin::CAPABILITY,
		'qrms-sp-ayarlar',
		QRMS_Admin::register_module_subpage( 'qr-servis-paneli', 'qrms-sp-ayarlar', 'qrms_sp_ayarlar_sayfasi' )
	);
}

/**
 * Yönetim varlıklarını kuyruğa alır.
 *
 * @return void
 */
function qrms_module_qr_servis_paneli_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$izin = array(
		QRMS_Admin::get_module_page_slug( 'qr-servis-paneli' ),
		'qrms-sp-ayarlar',
	);

	if ( ! in_array( $page, $izin, true ) ) {
		return;
	}

	wp_enqueue_style(
		'qrms-sp-panel',
		QRMS_PLUGIN_URL . 'modules/qr-servis-paneli/assets/css/panel.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-servis-paneli/assets/css/panel.css' )
	);

	wp_enqueue_script(
		'qrms-sp-panel',
		QRMS_PLUGIN_URL . 'modules/qr-servis-paneli/assets/js/panel.js',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-servis-paneli/assets/js/panel.js' ),
		true
	);

	wp_localize_script(
		'qrms-sp-panel',
		'QRMS_SP',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'qrms_sp_panel' ),
			'ayarNonce' => wp_create_nonce( 'qrms_sp_ayarlar' ),
			'i18n'    => array(
				'siparis'  => __( 'Sipariş', 'qrms' ),
				'garson'   => __( 'Garson', 'qrms' ),
				'hesap'    => __( 'Hesap', 'qrms' ),
				'ileri'    => __( 'İleri', 'qrms' ),
				'geri'     => __( 'Geri', 'qrms' ),
				'iptal'    => __( 'İptal', 'qrms' ),
				'yeni'     => __( 'Yeni sipariş', 'qrms' ),
			),
		)
	);
}
