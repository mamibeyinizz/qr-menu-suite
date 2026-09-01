<?php
/**
 * Modül: QR Çeviri (qr-ceviri)
 *
 * CSV ile toplu yönetilen, veritabanı tabanlı çoklu dil çeviri sistemi.
 * Sol menüdeki satır bir hub'dır; diller, kapsam, metin toplama, CSV ve
 * sistem durumu kendi alt sayfalarındadır (qr-analiz deseni). Sol menü
 * tek seviyeli kalır — hide_module_subpages() alt satırları düşürür.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require bilinçli olarak bu fonksiyonun içindedir.
 *
 * @return void
 */
function qrms_module_qr_ceviri_init() {
	require_once __DIR__ . '/ceviri.php';

	QRMS_Shortcodes::register(
		'qr-ceviri',
		array(
			array(
				'tag'   => 'qrmenu_flags_only',
				'title' => __( 'Dil Seçici (yalnızca bayrak)', 'qrms' ),
				'desc'  => __( 'Menü dilini değiştiren kare bayrak butonu. Dar alanlar için.', 'qrms' ),
			),
			array(
				'tag'   => 'qrmenu_flags_text',
				'title' => __( 'Dil Seçici (bayrak + dil adı)', 'qrms' ),
				'desc'  => __( 'Menü dilini değiştiren, bayrağın yanında dil adını da yazan seçici.', 'qrms' ),
			),
		)
	);

	if ( is_admin() ) {
		QRMS_Admin::register_module_page( 'qr-ceviri', 'qrms_module_qr_ceviri_hub' );

		add_action( 'admin_menu', 'qrms_module_qr_ceviri_admin_menu', 20 );
		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_ceviri_admin_assets' );
	}
}

/**
 * Yönetim stili — yalnızca bu modülün sayfasında.
 *
 * Taşınan eklentinin yönetim tarafında hiç stil dosyası yoktu; ölçüler
 * markup'a satır içi yazılmıştı ve satır içi stilin kırılım noktası
 * olamadığı için ekran dar ekranda sıkışıyordu. Kurallar artık
 * assets/css/admin.css içinde.
 *
 * @return void
 */
function qrms_module_qr_ceviri_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	$modul = QRMS_Admin::get_module_page_slug( 'qr-ceviri' );
	$bizim = ( $modul === $page )
		|| ( 0 === strpos( $page, 'qrms-cv-' ) )
		|| ( 'qrmenu-translator' === $page );

	if ( ! $bizim ) {
		return;
	}

	wp_enqueue_style(
		'qrms-ceviri-admin',
		QRMS_PLUGIN_URL . 'modules/qr-ceviri/assets/css/admin.css',
		array( 'qrms-admin' ),
		QRMS_Helpers::asset_version( 'modules/qr-ceviri/assets/css/admin.css' )
	);
}
