<?php
/**
 * QR Chatbot modülü — ön yüz kısa kodları, AJAX uçları ve yönetim sayfası.
 *
 * Dosyalar eski qr-menu-official eklentisindeki includes/ yapısından taşındı;
 * burada yalnızca sabitler ve require zinciri var.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QMO_CHATBOT_DIR' ) ) {
	define( 'QMO_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'QMO_CHATBOT_URL' ) ) {
	define( 'QMO_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
}

require_once QMO_CHATBOT_DIR . 'includes/ajax-chat.php';
require_once QMO_CHATBOT_DIR . 'includes/shortcode-chatbot.php';
require_once QMO_CHATBOT_DIR . 'includes/shortcode-buttons.php';

// Sipariş boru hattı: REST ucu ile chatbot AJAX ucu aynı qmo_siparis_isle()
// fonksiyonuna düşer, bu yüzden ajax-order.php rest-order.php'den sonra
// yüklenir. Garson/hesap çağrıları QMO_Firestore üzerinden yazar.
require_once QMO_CHATBOT_DIR . 'rest-order.php';
require_once QMO_CHATBOT_DIR . 'ajax-order.php';
require_once QMO_CHATBOT_DIR . 'ajax-waiter-bill.php';

add_action( 'wp_enqueue_scripts', 'qmo_chatbot_buton_varliklarini_kaydet', 5 );

if ( is_admin() ) {
	require_once QMO_CHATBOT_DIR . 'includes/admin/admin-sayfa.php';
}

/**
 * Garson / hesap buton varlıklarını kaydet.
 *
 * Ortak assets.php bu handle'ları yorum satırına almıştı (dosyalar henüz
 * yoktu). Kayıt burada yapılır; qmo_asset_enqueue() / qmo_icerikten_yukle()
 * kayıtlı handle'ı yükler.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_buton_varliklarini_kaydet' ) ) {
	function qmo_chatbot_buton_varliklarini_kaydet() {
		static $kayitli = false;
		if ( $kayitli ) {
			return;
		}
		$kayitli = true;

		$url = QMO_CHATBOT_URL . 'assets/';

		// Sürüm dosyanın son değişiklik zamanından gelir; sabit bir sürüm
		// kullanmak dosya değiştiğinde eski kopyanın sunulmasına yol açardı.
		$css = QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/css/buttons.css' );
		$js  = QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/buttons.js' );

		wp_register_style( 'qmo-buttons', $url . 'css/buttons.css', array(), $css );
		wp_register_script( 'qmo-buttons', $url . 'js/buttons.js', array(), $js, true );

		// [qr_garson_hesap] aynı varlıklara bağlanır; ayrı bir tema dosyası yok.
		wp_register_style( 'qmo-garson-hesap', $url . 'css/buttons.css', array(), $css );
		wp_register_script( 'qmo-garson-hesap', $url . 'js/buttons.js', array(), $js, true );
	}
}
