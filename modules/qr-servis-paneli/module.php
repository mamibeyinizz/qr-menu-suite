<?php
/**
 * Modül: Servis Paneli (qr-servis-paneli)
 *
 * Müşteri siparişleri ile garson/hesap çağrılarının canlı takip ekranı.
 * Kayıtlar Firestore'daki `calls` koleksiyonundadır (qr-chatbot modülü yazar);
 * bu modül onları WordPress içinde görünür ve yönetilebilir hâle getirir.
 *
 * İki ekranı olduğu için hub YOKTUR: modül satırı doğrudan paneli açar,
 * ayarlar alt ekran olarak kaydedilir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/** Panel ekranının sayfa slug'ı. */
const QRMS_SP_PANEL_SAYFA = 'qrms-module-qr-servis-paneli';

/** Ayarlar ekranının sayfa slug'ı. */
const QRMS_SP_AYAR_SAYFA = 'qrms-sp-ayarlar';

/**
 * Modülü başlatır.
 *
 * @return void
 */
function qrms_module_qr_servis_paneli_init() {
	require_once __DIR__ . '/includes/class-qrms-sp-veri.php';
	require_once __DIR__ . '/includes/class-qrms-sp-rol.php';

	QRMS_SP_Rol::kur();

	// Sadeleştirme `init`'te başlar: yetenek kontrolü plugins_loaded'da
	// yapılırsa geçerli kullanıcı, `determine_current_user` filtresini
	// kaydeden eklentiler devreye girmeden önce çözülür.
	add_action( 'init', array( 'QRMS_SP_Rol', 'paneli_sadelestir' ) );

	if ( ! is_admin() ) {
		return;
	}

	require_once __DIR__ . '/includes/ajax.php';
	require_once __DIR__ . '/includes/admin/panel-sayfasi.php';
	require_once __DIR__ . '/includes/admin/ayarlar-sayfasi.php';

	QRMS_Admin::register_module_page( 'qr-servis-paneli', 'qrms_sp_panel_sayfasi' );

	add_action( 'admin_menu', 'qrms_sp_admin_menu' );
	add_action( 'admin_enqueue_scripts', 'qrms_sp_admin_assets' );
}

/**
 * Ayar ekranını gizli alt sayfa olarak kaydeder.
 *
 * @return void
 */
function qrms_sp_admin_menu() {
	if ( ! QRMS_Module_Loader::is_module_active( 'qr-servis-paneli' ) ) {
		return;
	}

	add_submenu_page(
		QRMS_Admin::MENU_SLUG,
		__( 'Servis Paneli Ayarları', 'qrms' ),
		__( 'Servis Paneli Ayarları', 'qrms' ),
		QRMS_Admin::CAPABILITY,
		QRMS_SP_AYAR_SAYFA,
		QRMS_Admin::register_module_subpage( 'qr-servis-paneli', QRMS_SP_AYAR_SAYFA, 'qrms_sp_ayarlar_sayfasi' )
	);
}

/**
 * Bu modülün bir ekranında mıyız?
 *
 * @return bool
 */
function qrms_sp_ekranda_mi() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	return in_array( $page, array( QRMS_SP_PANEL_SAYFA, QRMS_SP_AYAR_SAYFA ), true );
}

/**
 * Yönetim varlıkları.
 *
 * @return void
 */
function qrms_sp_admin_assets() {
	if ( ! qrms_sp_ekranda_mi() ) {
		return;
	}

	wp_enqueue_style(
		'qrms-sp-panel',
		QRMS_PLUGIN_URL . 'modules/qr-servis-paneli/assets/css/panel.css',
		array( 'qrms-admin' ),
		QRMS_Helpers::asset_version( 'modules/qr-servis-paneli/assets/css/panel.css' )
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_SP_PANEL_SAYFA !== $page ) {
		return;
	}

	wp_enqueue_script(
		'qrms-sp-panel',
		QRMS_PLUGIN_URL . 'modules/qr-servis-paneli/assets/js/panel.js',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-servis-paneli/assets/js/panel.js' ),
		true
	);

	$ayar = QRMS_SP_Veri::ayarlar();

	wp_localize_script(
		'qrms-sp-panel',
		'QRMS_SP',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'qrms_sp' ),
			'yenileme'    => (int) $ayar['yenileme'],
			'esikSari'    => (int) $ayar['esik_sari'],
			'esikKirmizi' => (int) $ayar['esik_kirmizi'],
			'sesVarsayilan' => (int) $ayar['ses'],
			'durumlar'    => QRMS_SP_Veri::durumlar(),
			'tipler'      => QRMS_SP_Veri::tipler(),
			'akis'        => QRMS_SP_Veri::akis(),
			'metin'       => array(
				'baglantiYok'  => __( 'Bağlantı yok, yeniden deneniyor…', 'qrms' ),
				'baglantiVar'  => __( 'Bağlantı geri geldi.', 'qrms' ),
				'yeniSiparis'  => __( 'Yeni kayıt', 'qrms' ),
				'bos'          => __( 'Bu sütunda kayıt yok.', 'qrms' ),
				'sesAc'        => __( 'Sesi aç', 'qrms' ),
				'sesKapat'     => __( 'Sesi kapat', 'qrms' ),
				'bildirimIste' => __( 'Masaüstü bildirimi', 'qrms' ),
				'ileri'        => __( 'İleri', 'qrms' ),
				'geri'         => __( 'Geri', 'qrms' ),
				'iptal'        => __( 'İptal', 'qrms' ),
				'not'          => __( 'Not', 'qrms' ),
				'hata'         => __( 'İşlem yapılamadı.', 'qrms' ),
			),
		)
	);
}
