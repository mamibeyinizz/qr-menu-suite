<?php
/**
 * Modül: QR Chatbot (qr-chatbot)
 *
 * [gemini_chatbot] kısa kodu, Gemini AJAX ucu ve yönetim ayarları.
 * Dosyalar eski qr-menu-official eklentisinden taşındı; burada yalnızca
 * yükleme bağlantısı ve suite menüsündeki sayfa kaydı var.
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
 * `wp_ajax_*`, `wp_enqueue_scripts` ve kısa kod çözümlemesi plugins_loaded'dan
 * sonra gerçekleşir.
 *
 * @return void
 */
function qrms_module_qr_chatbot_init() {
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';

	require_once __DIR__ . '/chatbot.php';

	if ( is_admin() ) {
		QRMS_Admin::register_module_page( 'qr-chatbot', 'qmo_chatbot_ayar_sayfasi' );
		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_chatbot_admin_assets' );
	}
}

/**
 * Chatbot ayarları ekranının yönetim varlıkları.
 *
 * @return void
 */
function qrms_module_qr_chatbot_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_Admin::get_module_page_slug( 'qr-chatbot' ) !== $page ) {
		return;
	}

	wp_enqueue_style(
		'qmo-admin',
		QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/css/admin.css',
		array(),
		QRMS_VERSION
	);

	wp_enqueue_media();

	wp_enqueue_script(
		'qmo-admin-chatbot',
		QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/js/admin-chatbot.js',
		array(),
		QRMS_VERSION,
		true
	);

	wp_localize_script(
		'qmo-admin-chatbot',
		'qmoChatbotAdmin',
		array(
			'presets'     => qmo_renk_sablonlari(),
			'defaults'    => qmo_renk_varsayilanlari(),
			'colors'      => qmo_chatbot_renkleri_oku(),
			'defaultIcon' => qmo_varsayilan_ikon(),
			'initial'     => array(
				'botName'     => get_option( 'gemini_bot_name', 'Asistan' ),
				'welcome'     => get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' ),
				'placeholder' => get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' ),
				'iconUrl'     => get_option( 'gemini_bot_icon', '' ),
			),
			'strings'     => array(
				'presetApplied' => 'Renk şablonu uygulandı. Değişiklikleri kaydetmeyi unutmayın.',
				'selectIcon'    => 'Bot ikonu seç',
				'useIcon'       => 'Bu görseli kullan',
			),
		)
	);
}
