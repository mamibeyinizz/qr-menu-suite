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

		// "QR Chatbot" satırı hub ekranını açar; formlar aşağıda ayrı
		// sayfa olarak kaydedilir (sol menüde gizli).
		QRMS_Admin::register_module_page( 'qr-chatbot', 'qmo_chatbot_ayar_sayfasi' );

		add_action( 'admin_menu', 'qrms_module_qr_chatbot_admin_menu', 20 );
		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_chatbot_admin_assets' );
	}
}

/**
 * Modülün alt sayfalarını kaydeder — hepsi sol menüde GİZLİDİR.
 *
 * Restoran Menü ile aynı desen: sayfalar gerçek WordPress sayfalarıdır
 * (parent: MENU_SLUG); menüde görünmemeleri hide_module_subpages() ile,
 * route çözüldükten sonra sağlanır. parent_slug = null kullanılmaz —
 * o hook adını değiştirip 403 üretir (bkz. QRMS_Admin::hide_module_subpages).
 *
 * @return void
 */
function qrms_module_qr_chatbot_admin_menu() {
	global $submenu;

	$parent = QRMS_Admin::MENU_SLUG;

	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	foreach ( qmo_chatbot_sayfalar() as $slug => $page ) {
		// Eşleştirmeyi kaydet (sol menü vurgusu, is_module_subpage).
		// Sarmalanmış callback kullanılmaz: geri bağlantısı sayfanın kendi
		// iskeletinde "← QR Chatbot" olarak basılır (hub başlığıyla aynı).
		QRMS_Admin::register_module_subpage( 'qr-chatbot', $slug, $page['render'] );

		add_submenu_page(
			$parent,
			$page['title'],
			$page['title'],
			QRMS_Admin::CAPABILITY,
			$slug,
			$page['render']
		);
	}
}

/**
 * Chatbot hub ve alt sayfalarının yönetim varlıkları.
 *
 * Hub, Restoran Menü hub'ının CSS'ini kopyalamadan kuyruğa alır
 * (modules/restoran-menu/assets/css/hub.css). Form ekranları mevcut
 * qmo-admin + admin-chatbot.js varlıklarını kullanır.
 *
 * @return void
 */
function qrms_module_qr_chatbot_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	$hub_slug = QRMS_Admin::get_module_page_slug( 'qr-chatbot' );
	$altlar   = function_exists( 'qmo_chatbot_sayfalar' ) ? array_keys( qmo_chatbot_sayfalar() ) : array();

	$ortak_css = array(
		'qmo-admin-chatbot',
		QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/css/admin-chatbot.css',
		QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/css/admin-chatbot.css' ),
	);

	if ( $hub_slug === $page ) {
		wp_enqueue_style(
			'rma-hub',
			QRMS_PLUGIN_URL . 'modules/restoran-menu/assets/css/hub.css',
			array( 'qrms-admin' ),
			QRMS_Helpers::asset_version( 'modules/restoran-menu/assets/css/hub.css' )
		);
		wp_enqueue_style( $ortak_css[0], $ortak_css[1], array( 'rma-hub' ), $ortak_css[2] );
		wp_enqueue_script(
			'qmo-admin-hub',
			QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/js/admin-hub.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/admin-hub.js' ),
			true
		);
		wp_localize_script(
			'qmo-admin-hub',
			'qmoChatbotHub',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'acik'    => __( 'Açık', 'qrms' ),
				'kapali'  => __( 'Kapalı', 'qrms' ),
			)
		);
		return;
	}

	if ( ! in_array( $page, $altlar, true ) ) {
		return;
	}

	wp_enqueue_style(
		'qmo-admin',
		QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/css/admin.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/_qmo-ortak/assets/css/admin.css' )
	);

	$admin_deps = array( 'qmo-admin' );
	if ( 'qrms-chatbot-appearance' === $page ) {
		wp_enqueue_style(
			'qmo-chatbot-front',
			QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/css/chatbot.css',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/css/chatbot.css' )
		);
		$admin_deps[] = 'qmo-chatbot-front';
	}

	wp_enqueue_style( $ortak_css[0], $ortak_css[1], $admin_deps, $ortak_css[2] );

	if ( 'qrms-chatbot-quick-replies' === $page ) {
		wp_enqueue_script(
			'qmo-admin-sorular',
			QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/js/admin-sorular.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/admin-sorular.js' ),
			true
		);
		return;
	}

	if ( in_array( $page, array( 'qrms-chatbot-history', 'qrms-chatbot-unanswered' ), true ) ) {
		wp_enqueue_script(
			'qmo-admin-yonetim',
			QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/js/admin-yonetim.js',
			array(),
			QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/admin-yonetim.js' ),
			true
		);
		wp_localize_script(
			'qmo-admin-yonetim',
			'qmoChatbotYonetim',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonceG'  => wp_create_nonce( 'qmo_chatbot_gecmis' ),
				'nonceB'  => wp_create_nonce( 'qmo_chatbot_bilinmeyen' ),
			)
		);
		return;
	}

	$js_sayfalari = array( 'qrms-chatbot-bot-identity', 'qrms-chatbot-appearance' );
	if ( ! in_array( $page, $js_sayfalari, true ) ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'qmo-admin-chatbot',
		QRMS_PLUGIN_URL . 'modules/qr-chatbot/assets/js/admin-chatbot.js',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-chatbot/assets/js/admin-chatbot.js' ),
		true
	);

	$ikonlar = array();
	if ( function_exists( 'qmo_chatbot_hazir_ikonlar' ) ) {
		foreach ( qmo_chatbot_hazir_ikonlar() as $slug => $ikon ) {
			$ikonlar[ $slug ] = $ikon['svg'];
		}
	}

	wp_localize_script(
		'qmo-admin-chatbot',
		'qmoChatbotAdmin',
		array(
			'presets'     => qmo_renk_sablonlari(),
			'defaults'    => qmo_renk_varsayilanlari(),
			'colors'      => function_exists( 'qmo_chatbot_renkleri_coz' ) ? qmo_chatbot_renkleri_coz() : qmo_chatbot_renkleri_oku(),
			'derivedFrom' => function_exists( 'qmo_chatbot_renkleri_turetilsin' ) ? qmo_chatbot_renkleri_turetilsin(
				isset( qmo_chatbot_renkleri_oku()['gemini_main_color'] ) ? qmo_chatbot_renkleri_oku()['gemini_main_color'] : '#8a2be2',
				isset( qmo_chatbot_renkleri_oku()['gemini_chat_bg_color'] ) ? qmo_chatbot_renkleri_oku()['gemini_chat_bg_color'] : '#f8fafc',
				isset( qmo_chatbot_renkleri_oku()['gemini_text_color'] ) ? qmo_chatbot_renkleri_oku()['gemini_text_color'] : '#333333'
			) : array(),
			'icons'       => $ikonlar,
			'defaultIcon' => qmo_varsayilan_ikon(),
			'sizes'       => function_exists( 'qmo_chatbot_boyut_haritasi' ) ? qmo_chatbot_boyut_haritasi() : array(),
			'radii'       => function_exists( 'qmo_chatbot_kose_haritasi' ) ? qmo_chatbot_kose_haritasi() : array(),
			'offsets'     => function_exists( 'qmo_chatbot_yukseklik_haritasi' ) ? qmo_chatbot_yukseklik_haritasi() : array(),
			'widths'      => function_exists( 'qmo_chatbot_genislik_haritasi' ) ? qmo_chatbot_genislik_haritasi() : array(),
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
