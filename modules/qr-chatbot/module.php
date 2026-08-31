<?php
/**
 * Modül: QR Chatbot (qr-chatbot)
 *
 * [gemini_chatbot] kısa kodu, Gemini AJAX ucu, garson/hesap butonları ve
 * sohbetin tetiklediği masa eylemleri:
 *
 *   - `garson_cagir` / `hesap_iste` / `qrservis_call` (ajax-waiter-bill.php)
 *   - `gemini_bot_siparis` (ajax-order.php) → qmo_siparis_isle()
 *   - `POST /wp-json/qrservis/v1/order` (rest-order.php)
 *   - `[qmo_sepet]` menüden direkt sipariş (shortcode-sepet.php)
 *
 * Bu üç dosya da QMO_Firestore üzerinden yazar: chatbot yanıtındaki
 * [CALL_WAITER] / [CALL_BILL] / [SIPARIS] etiketleri assets/js/chatbot.js
 * içinde tam olarak bu uçlara düşer, dolayısıyla uçlar olmadan modülün sohbet
 * dışındaki yarısı çalışmaz. Dosyalar eski qr-menu-official eklentisinden
 * aynen taşındı; burada yalnızca yükleme bağlantısı ve suite menüsündeki
 * sayfa kaydı var.
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

	require_once __DIR__ . '/chatbot.php';

	// [qmo_sepet] kayıtı: add_shortcode dosya kapsamında çalışır.
	// chatbot.php de aynı dosyayı require_once eder; ikinci çağrı no-op.
	require_once __DIR__ . '/includes/shortcode-sepet.php';

	QRMS_Shortcodes::register(
		'qr-chatbot',
		array(
			array(
				'tag'   => 'gemini_chatbot',
				'title' => __( 'AI Asistan', 'qrms' ),
				'desc'  => __( 'Müşterinin menü hakkında soru sorabildiği, sipariş verebildiği tam ekran yapay zekâ asistanı.', 'qrms' ),
				'note'  => __( 'Yalnızca müşteri masadaki QR kodu okuttuysa açılır; oturum yoksa bilgi kutusu basılır.', 'qrms' ),
			),
			array(
				'tag'   => 'garson_butonu',
				'title' => __( 'Garson Çağır Butonu', 'qrms' ),
				'desc'  => __( 'Müşterinin masaya garson çağırmasını sağlayan tek buton.', 'qrms' ),
				'note'  => __( 'Yalnızca geçerli bir masa oturumu varken görünür.', 'qrms' ),
			),
			array(
				'tag'   => 'hesap_iste_butonu',
				'title' => __( 'Hesap İste Butonu', 'qrms' ),
				'desc'  => __( 'Müşterinin hesap istemesini sağlayan tek buton.', 'qrms' ),
				'note'  => __( 'Yalnızca geçerli bir masa oturumu varken görünür.', 'qrms' ),
			),
			array(
				'tag'   => 'ikili_buton',
				'title' => __( 'Garson + Hesap (ikili buton)', 'qrms' ),
				'desc'  => __( 'Garson çağırma ve hesap isteme butonlarını yan yana basar.', 'qrms' ),
				'note'  => __( 'Yalnızca geçerli bir masa oturumu varken görünür. [qr_garson_hesap] ile aynı çıktıyı verir.', 'qrms' ),
			),
			array(
				'tag'   => 'qr_garson_hesap',
				'title' => __( 'Garson + Hesap (ikinci ad)', 'qrms' ),
				'desc'  => __( '[ikili_buton] kısa kodunun eş anlamlısı; eski sayfalarda kullanılmış olabilir.', 'qrms' ),
				'note'  => __( 'Yalnızca geçerli bir masa oturumu varken görünür.', 'qrms' ),
			),
			array(
				'tag'   => 'qmo_sepet',
				'title' => __( 'QMO Sepet — Menüden Direkt Sipariş', 'qrms' ),
				'desc'  => __( 'Ürün detayından sepete ekleme, alt sepet çubuğu ve siparişi mutfağa gönderme.', 'qrms' ),
				'note'  => __( 'Yalnızca müşteri masadaki QR kodu okuttuysa görünür; yöneticiler test için her zaman görür. Oturum yoksa hiçbir şey basılmaz. Menünün altına otomatik eklemek için Restoran Menü → Diğer Ayarlar içindeki "Sepet ile Sipariş" anahtarını açın; bu kısa kod ayrıca herhangi bir sayfaya elle de yazılabilir.', 'qrms' ),
			),
		)
	);

	if ( is_admin() ) {
		// Firebase service account / şube kimliği: çağrı ve siparişlerin
		// yazılabilmesi için gerekli yapılandırma. Form _qmo-ortak altındadır,
		// çünkü aynı option'ları qr-analiz de kullanır.
		require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/firebase-ayarlari.php';

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
		QRMS_Helpers::asset_version( 'modules/_qmo-ortak/assets/css/admin.css' )
	);

	wp_enqueue_media();

	wp_enqueue_script(
		'qmo-admin-chatbot',
		QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/js/admin-chatbot.js',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/admin-chatbot.js' ),
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
