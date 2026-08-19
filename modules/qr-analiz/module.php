<?php
/**
 * Modül: QR Analiz (qr-analiz)
 *
 * Uygulamanın (admin/müdür paneli) konuştuğu Firebase kimlikli REST yüzeyi:
 *
 *   - `POST /wp-json/qrservis/v1/analytics`     — şube analitiği özeti.
 *   - `POST /wp-json/qrservis/v1/create-user`   — garson/müdür hesabı açma
 *     (yalnızca "ana site" işaretliyse kaydedilir).
 *
 * İkisi de aynı zemine oturur: çağıranın Firebase ID token'ı doğrulanır,
 * Firestore `users/{uid}` dokümanından rolü (admin/müdür) okunur ve yetki
 * buna göre verilir. Bu yüzden create-user ucu chatbot'un değil bu modülün
 * altındadır. Dosyalar eski qr-menu-official eklentisinden aynen taşındı;
 * burada yalnızca yükleme bağlantısı var.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require'lar bilinçli olarak bu fonksiyonun içindedir:
 * dosyanın yüklenmesi = hook'un kaydı. Bağlanılan `rest_api_init` ve
 * `admin_init` kancaları plugins_loaded'dan sonra tetiklenir.
 *
 * @return void
 */
function qrms_module_qr_analiz_init() {
	// QMO_Firestore sınıfı _qmo-ortak altındadır (ortak.php ile yüklenir):
	// her iki REST ucu da çağıranın kimliğini ve rolünü onun üzerinden
	// doğrular (id_token_dogrula → access_token → kullanici_doc).
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';

	require_once __DIR__ . '/rest-analytics.php';
	require_once __DIR__ . '/rest-create-user.php';

	if ( is_admin() ) {
		// Firebase service account / şube kimliği / ana site bayrağı: uçların
		// dayandığı yapılandırma. Form _qmo-ortak altındadır, çünkü aynı
		// option'ları qr-chatbot da kullanır.
		require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/firebase-ayarlari.php';
		require_once __DIR__ . '/ayarlar-sayfasi.php';

		QRMS_Admin::register_module_page( 'qr-analiz', 'qmo_analiz_ayar_sayfasi' );

		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_analiz_admin_assets' );
	}
}

/**
 * Analiz ekranının yönetim varlıkları.
 *
 * Yalnızca bu modülün kendi sayfası render edilirken yüklenir; ortak admin
 * stili durum rozetlerini (qmo-durum-ok / qmo-durum-eksik) biçimlendirir.
 *
 * @return void
 */
function qrms_module_qr_analiz_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'qr-analiz' ) !== $page ) {
		return;
	}

	wp_enqueue_style(
		'qmo-admin',
		QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/css/admin.css',
		array(),
		QRMS_VERSION
	);
}
