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
require_once QMO_CHATBOT_DIR . 'includes/shortcode-sepet.php';

// Sipariş boru hattı: REST ucu ile chatbot AJAX ucu aynı qmo_siparis_isle()
// fonksiyonuna düşer, bu yüzden ajax-order.php rest-order.php'den sonra
// yüklenir. Garson/hesap çağrıları QMO_Firestore üzerinden yazar.
require_once QMO_CHATBOT_DIR . 'rest-order.php';
require_once QMO_CHATBOT_DIR . 'ajax-order.php';
require_once QMO_CHATBOT_DIR . 'ajax-waiter-bill.php';
require_once QMO_CHATBOT_DIR . 'ajax-sepet-analitik.php';

add_action( 'wp_enqueue_scripts', 'qmo_chatbot_buton_varliklarini_kaydet', 5 );

if ( is_admin() ) {
	require_once QMO_CHATBOT_DIR . 'includes/admin/admin-sayfa.php';
}

/**
 * Garson / hesap buton ve sepet varlıklarını kaydet.
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

		// [qr_garson_hesap] / [ikili_buton] aynı dosyaları kullanır; ayrı bir
		// tema dosyası yok. Bu handle bir TAKMA ADDIR: kendi kaynağı yoktur,
		// yalnızca qmo-buttons'a bağımlıdır.
		//
		// Eskiden aynı dosyalar ikinci kez, ayrı kaynakla kaydediliyordu.
		// WordPress iki farklı handle'ı iki farklı varlık saydığı için, bir
		// sayfada hem [garson_butonu] hem [ikili_buton] varsa buttons.js İKİ
		// KEZ basılıyor, olay dinleyicileri butonlara iki kez bağlanıyor ve
		// tek tıkta iki AJAX isteği gidiyordu. İkinci istek çağrı ucundaki 60
		// saniyelik hız sınırına takıldığı için müşteri, çağrısı aslında
		// iletilmişken uyarı mesajı görüyordu.
		wp_register_style( 'qmo-garson-hesap', false, array( 'qmo-buttons' ), $css );
		wp_register_script( 'qmo-garson-hesap', false, array( 'qmo-buttons' ), $js, true );

		// [qmo_sepet] — menüden direkt sipariş. Ortak assets.php bu handle'ı
		// yorum satırına almıştı (dosya henüz yoktu); kayıt burada yapılır.
		$sepet_css = QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/css/sepet.css' );
		$sepet_js  = QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/sepet.js' );
		wp_register_style( 'qmo-sepet', $url . 'css/sepet.css', array(), $sepet_css );
		wp_register_script( 'qmo-sepet', $url . 'js/sepet.js', array(), $sepet_js, true );
	}
}

/**
 * buttons.js / chatbot.js için localize metinleri.
 *
 * Anahtarlar JS yedeğiyle aynı Türkçe metne bağlanır; tablo boşsa Türkçe
 * döner. sepet.js qmoSepet.i18n kullanır (shortcode-sepet.php); burada yok.
 *
 * @return array<string,string>
 */
if ( ! function_exists( 'qmo_chat_js_metinleri' ) ) {
	function qmo_chat_js_metinleri() {
		return array(
			'garsonIletildi'     => qmo_ceviri_chat( __( 'Garson çağrınız iletildi.', 'qrms' ) ),
			'hesapIletildi'      => qmo_ceviri_chat( __( 'Hesap talebiniz iletildi.', 'qrms' ) ),
			'istekIletilemedi'   => qmo_ceviri_chat( __( 'İstek iletilemedi, lütfen tekrar deneyin.', 'qrms' ) ),
			'baglantiHatasi'     => qmo_ceviri_chat( __( 'Bağlantı hatası oluştu.', 'qrms' ) ),
			'yaziyor'            => qmo_ceviri_chat( __( 'Yazıyor...', 'qrms' ) ),
			'birHata'            => qmo_ceviri_chat( __( 'Bir hata oluştu, lütfen tekrar deneyin.', 'qrms' ) ),
			'birHataKisa'        => qmo_ceviri_chat( __( 'Bir hata oluştu.', 'qrms' ) ),
			'siparisIletilemedi' => qmo_ceviri_chat( __( 'Siparişiniz iletilemedi, lütfen garsona bildirin.', 'qrms' ) ),
		);
	}
}
