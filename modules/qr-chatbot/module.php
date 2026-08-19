<?php
/**
 * Modül: QR Chatbot (qr-chatbot)
 *
 * [gemini_chatbot] kısa kodu, Gemini AJAX ucu ve sohbetin tetiklediği masa
 * eylemleri:
 *
 *   - `garson_cagir` / `hesap_iste` / `qrservis_call` (ajax-waiter-bill.php)
 *   - `gemini_bot_siparis` (ajax-order.php) → qmo_siparis_isle()
 *   - `POST /wp-json/qrservis/v1/order` (rest-order.php)
 *
 * Bu üç dosya da QMO_Firestore üzerinden yazar: chatbot yanıtındaki
 * [CALL_WAITER] / [CALL_BILL] / [SIPARIS] etiketleri assets/js/chatbot.js
 * içinde tam olarak bu uçlara düşer, dolayısıyla uçlar olmadan modülün sohbet
 * dışındaki yarısı çalışmaz. Dosyalar eski qr-menu-official eklentisinden
 * aynen taşındı; burada yalnızca yükleme bağlantısı var.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require'lar bilinçli olarak bu fonksiyonun içindedir;
 * `wp_ajax_*`, `rest_api_init`, `admin_init`, `wp_enqueue_scripts` ve kısa kod
 * çözümlemesi plugins_loaded'dan sonra gerçekleşir.
 *
 * @return void
 */
function qrms_module_qr_chatbot_init() {
	// QMO_Firestore ve masa oturumu _qmo-ortak altındadır (ortak.php ile
	// yüklenir): garson/hesap çağrısı ve sipariş yazımı sınıfa, masa numarası
	// da doğrulanmış oturuma bağlıdır.
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';

	require_once __DIR__ . '/ajax-chat.php';
	require_once __DIR__ . '/shortcode-chatbot.php';

	// Sipariş boru hattı: REST ucu ile chatbot AJAX ucu aynı qmo_siparis_isle()
	// fonksiyonuna düşer, bu yüzden ajax-order.php rest-order.php'den sonra
	// yüklenir.
	require_once __DIR__ . '/rest-order.php';
	require_once __DIR__ . '/ajax-order.php';
	require_once __DIR__ . '/ajax-waiter-bill.php';

	if ( is_admin() ) {
		// Firebase service account / şube kimliği: çağrı ve siparişlerin
		// yazılabilmesi için gerekli yapılandırma. Form _qmo-ortak altındadır,
		// çünkü aynı option'ları qr-analiz de kullanır.
		require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/firebase-ayarlari.php';
		require_once __DIR__ . '/ayarlar-sayfasi.php';

		QRMS_Admin::register_module_page( 'qr-chatbot', 'qmo_ayarlar_sayfasi' );

		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_chatbot_admin_assets' );
	}
}

/**
 * Chatbot ayar ekranının yönetim varlıkları.
 *
 * Eski eklentideki koşullu yükleme korunuyor: varlıklar yalnızca bu modülün
 * kendi sayfası render edilirken yüklenir. Koşul, eski `qmo-chatbot` sayfa
 * slug'ı yerine suite'in modül sayfası slug'ına bağlandı.
 *
 * @return void
 */
function qrms_module_qr_chatbot_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'qr-chatbot' ) !== $page ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_style(
		'qmo-admin',
		QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/css/admin.css',
		array( 'wp-color-picker' ),
		QRMS_VERSION
	);
	wp_enqueue_script(
		'qmo-admin-settings',
		QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/js/admin-settings.js',
		array( 'jquery', 'wp-color-picker' ),
		QRMS_VERSION,
		true
	);
	wp_localize_script(
		'qmo-admin-settings',
		'qmoAdmin',
		array(
			'presets'   => array_map(
				function ( $p ) {
					return $p['colors'];
				},
				qmo_renk_sablonlari()
			),
			'defaults'  => qmo_renk_varsayilanlari(),
			'ikonSec'   => 'İkon Seç',
			'kullan'    => 'Kullan',
			'uygulandi' => 'şablonu uygulandı. Kaydetmeyi unutmayın!',
		)
	);
}
