<?php
/**
 * Modül: QR Chatbot (qr-chatbot)
 *
 * [gemini_chatbot] kısa kodu ve Gemini AJAX ucu. Dosyalar eski
 * qr-menu-official eklentisinden aynen taşındı; burada yalnızca yükleme
 * bağlantısı var.
 *
 * Not: Chatbot'un Gemini ayar sayfası bu göçte taşınmadı, bu yüzden modülün
 * admin menüsündeki sayfası şimdilik placeholder olarak kalır. Ayarlar
 * (gemini_api_key vb.) option'lardan okunmaya devam eder.
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

	require_once __DIR__ . '/ajax-chat.php';
	require_once __DIR__ . '/shortcode-chatbot.php';
}
