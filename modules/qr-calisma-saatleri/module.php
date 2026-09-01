<?php
/**
 * Modül: QR Çalışma Saatleri (qr-calisma-saatleri)
 *
 * Restoranın haftalık açılış/kapanış saatleri. Yönetim ekranı suite menüsüne
 * `QRMS_Admin::register_module_page()` ile bağlanır; veriler
 * `qrms_calisma_saatleri` option'ında tutulur. Ön yüzde
 * `[qr_calisma_saatleri]` kısa kodu haftalık tabloyu basar.
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
function qrms_module_qr_calisma_saatleri_init() {
	require_once __DIR__ . '/includes/hours.php';
	require_once __DIR__ . '/includes/renkler.php';
	require_once __DIR__ . '/includes/shortcode.php';

	add_shortcode( 'qr_calisma_saatleri', 'qrms_cs_shortcode' );

	QRMS_Shortcodes::register(
		'qr-calisma-saatleri',
		array(
			array(
				'tag'   => 'qr_calisma_saatleri',
				'title' => __( 'Çalışma Saatleri', 'qrms' ),
				'desc'  => __( 'Haftalık açılış/kapanış saatlerinizi tablo hâlinde gösterir; o günün satırı vurgulanır.', 'qrms' ),
				'attrs' => array(
					array(
						'name'    => 'today',
						'default' => '0',
						'desc'    => __( 'Tüm haftayı değil yalnızca bugünü göstermek için "1" yazın.', 'qrms' ),
					),
					array(
						'name'    => 'fullwidth',
						'default' => '1',
						'desc'    => __( 'Kenardan kenara (100vw) basmak için "1". Dar bir sütunda kullanıyorsanız "0" yazın.', 'qrms' ),
					),
				),
			),
		)
	);

	add_action( 'wp_enqueue_scripts', 'qrms_cs_register_frontend_assets' );

	if ( is_admin() ) {
		require_once __DIR__ . '/includes/admin-sayfa.php';

		QRMS_Admin::register_module_page( 'qr-calisma-saatleri', 'qrms_cs_admin_sayfasi' );

		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_calisma_saatleri_admin_assets' );
	}
}

/**
 * Yönetim varlıkları — yalnızca bu modülün sayfasında.
 *
 * @return void
 */
function qrms_module_qr_calisma_saatleri_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'qr-calisma-saatleri' ) !== $page ) {
		return;
	}

	// Önizleme, kısa kodun GERÇEK stylesheet'ini kullanır; ayrı bir taklit
	// yoktur. Kayıt frontend'de wp_enqueue_scripts'te yapılıyor, burada elle.
	qrms_cs_register_frontend_assets();
	wp_enqueue_style( 'qrms-cs-frontend' );

	// Kayıtlı yazı tipi önizlemede de gerçek fontuyla görünsün. Seçici
	// değiştirildiğinde yeni fontun stylesheet'ini JS ekler (bkz. admin.js).
	qrms_cs_enqueue_font();

	wp_enqueue_style( 'wp-color-picker' );

	wp_enqueue_style(
		'qrms-cs-admin',
		QRMS_PLUGIN_URL . 'modules/qr-calisma-saatleri/assets/css/admin.css',
		array( 'qrms-admin', 'wp-color-picker', 'qrms-cs-frontend' ),
		QRMS_Helpers::asset_version( 'modules/qr-calisma-saatleri/assets/css/admin.css' )
	);

	wp_enqueue_script(
		'qrms-cs-admin',
		QRMS_PLUGIN_URL . 'modules/qr-calisma-saatleri/assets/js/admin.js',
		array( 'jquery', 'wp-color-picker' ),
		QRMS_Helpers::asset_version( 'modules/qr-calisma-saatleri/assets/js/admin.js' ),
		true
	);

	/*
	 * Önizlemedeki saat metnini JS üretir; metinlerin KENDİSİ burada,
	 * PHP tarafında kalır (çeviri tek yerde durur). Biçimlendirmenin
	 * dallanması qrms_cs_format_day() ile birebir aynıdır ve testle
	 * korunur — bkz. tests/test-suite.php.
	 */
	wp_localize_script(
		'qrms-cs-admin',
		'QRMS_CS',
		array(
			'kapali'  => __( 'Kapalı', 'qrms' ),
			'yirmiDort' => __( '24 saat açık', 'qrms' ),
			'aralik'  => __( '%1$s – %2$s', 'qrms' ),
		)
	);
}
