<?php
/**
 * Modül: QR Masa (qr-masa)
 *
 * Masa kayıtları (CRUD), masa QR adresleri ve yönetim ekranı. Dosyalar eski
 * qr-menu-official eklentisinden aynen taşındı; burada yalnızca yükleme
 * bağlantısı ve varlık kuyruğu var.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require'lar bilinçli olarak bu fonksiyonun içindedir.
 *
 * @return void
 */
function qrms_module_qr_masa_init() {
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';

	require_once __DIR__ . '/class-qmo-masalar.php';

	QRMS_Shortcodes::register(
		'qr-masa',
		array(
			array(
				'tag'   => 'qr_aktif_masa',
				'title' => __( 'Aktif Masa Bilgisi', 'qrms' ),
				'desc'  => __( 'Müşteriye hangi masada olduğunu gösterir ("Masa 7"). Masa bilgisi doğrulanmış oturumdan okunur, adresten değil.', 'qrms' ),
				'note'  => __( 'Yalnızca müşteri masadaki QR kodu okuttuysa görünür.', 'qrms' ),
			),
		)
	);

	if ( is_admin() ) {
		require_once __DIR__ . '/masalar-sayfasi.php';

		QRMS_Admin::register_module_page( 'qr-masa', 'qmo_masalar_sayfasi' );

		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_masa_admin_assets' );
	}
}

/**
 * Masalar ekranının yönetim varlıkları.
 *
 * Eski eklentideki koşullu yükleme korunuyor: varlıklar yalnızca bu modülün
 * kendi sayfası render edilirken yüklenir. Koşul, eski `qmo-masalar` sayfa
 * slug'ı yerine suite'in modül sayfası slug'ına bağlandı.
 *
 * QR kod ve PDF üretimi tarayıcıda yapılır (sunucuya yük binmez).
 *
 * @return void
 */
function qrms_module_qr_masa_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'qr-masa' ) !== $page ) {
		return;
	}

	wp_enqueue_style(
		'qmo-admin',
		QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/css/admin.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/_qmo-ortak/assets/css/admin.css' )
	);

	// Ekranın kendi yerleşimi ve mobil davranışı. Ortak dosyanın ardına
	// alınır: oradaki masaüstü kurallarını daraltarak tamamlar.
	wp_enqueue_style(
		'qmo-admin-masalar',
		QRMS_PLUGIN_URL . 'modules/qr-masa/assets/css/admin-masalar.css',
		array( 'qmo-admin' ),
		QRMS_Helpers::asset_version( 'modules/qr-masa/assets/css/admin-masalar.css' )
	);
	wp_enqueue_script( 'qmo-qrious', 'https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js', array(), '4.0.2', true );
	wp_enqueue_script( 'qmo-jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), '2.5.1', true );
	wp_enqueue_script(
		'qmo-admin-masalar',
		QRMS_PLUGIN_URL . 'modules/qr-masa/assets/js/admin-masalar.js',
		array( 'qmo-qrious', 'qmo-jspdf' ),
		QRMS_Helpers::asset_version( 'modules/qr-masa/assets/js/admin-masalar.js' ),
		true
	);
}
