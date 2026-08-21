<?php
/**
 * Modül: Güvenlik Ayarı (qr-masa-oturum-guvenligi)
 *
 * HMAC imzalı masa oturumu, masa doğrulama / kilit ekranı ve oturum
 * limitlerinin yönetim sayfası. Buna ek olarak, uygulamanın (müdür/garson
 * paneli) konuştuğu REST uçlarının Firebase yapılandırması da bu modülün
 * altındadır: ekran v1.0'da qr-analiz modülündeydi, ama yapılandırdığı şey
 * kimlik doğrulama ve şube bağlantısıdır — raporlama değil.
 *
 * Slug (`qr-masa-oturum-guvenligi`) ve sayfa adresleri DEĞİŞMEDİ; yalnızca
 * modülün görünen adı "Güvenlik Ayarı" oldu.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Oturum limitleri ekranının yönetim sayfası slug'ı.
 *
 * v1.0'da bu ekran modülün kendi satırındaydı (qrms-module-qr-masa-oturum-guvenligi).
 * Modül iki ekranlı olunca o slug hub ekranı oldu; oturum limitleri kendi
 * slug'ına taşındı.
 */
const QRMS_GUVENLIK_OTURUM_SAYFA = 'qrms-guvenlik-oturum';

/**
 * Firebase / şube ayar ekranının yönetim sayfası slug'ı.
 *
 * DEĞER BİLİNÇLİ OLARAK ESKİ ADRESTİR: ekran qr-analiz modülünden buraya
 * taşındı, ama `qrms-analiz-ayarlar` adresi canlı sitelerde yer imlerinde ve
 * dahili bağlantılarda duruyor. Sabitin ADI modülün yeni sahibini anlatır,
 * DEĞERİ ise eski adresi kırmamak için korunur.
 */
const QRMS_GUVENLIK_FIREBASE_SAYFA = 'qrms-analiz-ayarlar';

/**
 * Modülü başlatır.
 *
 * QRMS_Module_Loader tarafından `plugins_loaded` (öncelik 20) sırasında
 * argümansız çağrılır. Taşınan dosyalar hook'larını dosya kapsamında
 * kaydettiği için require'lar bilinçli olarak bu fonksiyonun içindedir:
 * dosyanın yüklenmesi = hook'ların kaydı. Bağlanılan kancaların hepsi
 * (`init`, `template_redirect`, `admin_init`, `admin_menu`) plugins_loaded'dan
 * sonra tetiklenir.
 *
 * @return void
 */
function qrms_module_qr_masa_oturum_guvenligi_init() {
	require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/ortak.php';

	// QMO_Oturum sınıfı _qmo-ortak altındadır (ortak.php ile yüklenir):
	// masa oturumu üç modülün de ortak zeminidir. Bu modül oturumun
	// ZORLANMASINDAN sorumludur — kilit ekranı, sahte QR reddi, sayfa kilidi.
	require_once __DIR__ . '/masa-dogrulama.php';

	if ( is_admin() ) {
		require_once __DIR__ . '/oturum-ayarlari.php';

		// Firebase service account / şube kimliği / ana site bayrağı. Form
		// _qmo-ortak altındadır, çünkü aynı option'ları qr-chatbot da kullanır
		// ve iki modül bağımsız lisanslanır.
		require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/firebase-ayarlari.php';
		require_once __DIR__ . '/firebase-ayarlari-sayfasi.php';

		// "Güvenlik Ayarı" satırı, modülün iki ekranını kart olarak listeleyen
		// hub ekranını açar; ekranların kendisi aşağıda ayrı sayfa olarak
		// kaydedilir.
		QRMS_Admin::register_module_page( 'qr-masa-oturum-guvenligi', 'qrms_module_qr_masa_oturum_guvenligi_hub' );

		add_action( 'admin_menu', 'qrms_module_qr_masa_oturum_guvenligi_admin_menu', 20 );

		add_action( 'admin_enqueue_scripts', 'qrms_module_qr_masa_oturum_guvenligi_admin_assets' );
	}
}

/**
 * Modülün ekranları — TEK KAYNAK.
 *
 * Sayfa kaydı ve hub kartları aynı listeden beslenir; sıra kart sırasıdır.
 *
 * @return array<string,array{title:string,render:string,desc:string,icon:string}>
 */
function qrms_module_qr_masa_oturum_guvenligi_sayfalar() {
	return array(
		QRMS_GUVENLIK_OTURUM_SAYFA   => array(
			'title'  => __( 'Oturum Limitleri', 'qrms' ),
			'render' => 'qmo_oturum_ayar_sayfasi',
			'desc'   => __( 'Masa oturumunun ömrü, hareketsizlik limiti ve sayfa kilidi.', 'qrms' ),
			'icon'   => 'dashicons-lock',
		),
		QRMS_GUVENLIK_FIREBASE_SAYFA => array(
			'title'  => __( 'Firebase & Şube Ayarları', 'qrms' ),
			'render' => 'qmo_analiz_ayar_sayfasi',
			'desc'   => __( 'Uygulamanın (müdür/garson paneli) kullandığı REST uçlarının yapılandırması.', 'qrms' ),
			'icon'   => 'dashicons-admin-generic',
		),
	);
}

/**
 * Modülün ekranlarını kaydeder — ikisi de sol menüde GİZLİDİR.
 *
 * Sol menüde yalnızca "Güvenlik Ayarı" satırı durur ve hub ekranını açar;
 * ekranlar gerçek, ayrı WordPress sayfaları olarak kaydolur (bkz.
 * QRMS_Admin::hide_module_subpages).
 *
 * @return void
 */
function qrms_module_qr_masa_oturum_guvenligi_admin_menu() {
	global $submenu;

	$parent = QRMS_Admin::MENU_SLUG;

	// Modül lisansta aktif değilse "Güvenlik Ayarı" satırı hiç kaydolmaz; o
	// zaman ekranlarının da kaydedilmemesi gerekir.
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	foreach ( qrms_module_qr_masa_oturum_guvenligi_sayfalar() as $slug => $page ) {
		add_submenu_page(
			$parent,
			$page['title'],
			$page['title'],
			QRMS_Admin::CAPABILITY,
			$slug,
			QRMS_Admin::register_module_subpage( 'qr-masa-oturum-guvenligi', $slug, $page['render'] )
		);
	}
}

/**
 * "Güvenlik Ayarı" satırının açtığı hub ekranı.
 *
 * @return void
 */
function qrms_module_qr_masa_oturum_guvenligi_hub() {
	$cards = array();

	foreach ( qrms_module_qr_masa_oturum_guvenligi_sayfalar() as $slug => $page ) {
		$cards[] = array(
			'url'   => admin_url( 'admin.php?page=' . $slug ),
			'title' => $page['title'],
			'desc'  => $page['desc'],
			'icon'  => $page['icon'],
		);
	}

	QRMS_Admin::render_hub(
		array(
			'title' => __( 'Güvenlik Ayarı', 'qrms' ),
			'intro' => __( 'Masa oturumunun güvenlik limitleri ve uygulamanın bağlandığı uçların kimlik ayarları.', 'qrms' ),
			'cards' => $cards,
		)
	);
}

/**
 * Modülün ekranlarının yönetim varlıkları.
 *
 * Yalnızca Firebase ayar ekranında gerekir: ortak admin stili durum
 * rozetlerini (qmo-durum-ok / qmo-durum-eksik) biçimlendirir. Hub ekranının
 * ve oturum limitlerinin stili suite'in ortak admin.css'inden gelir.
 *
 * @return void
 */
function qrms_module_qr_masa_oturum_guvenligi_admin_assets() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( QRMS_GUVENLIK_FIREBASE_SAYFA !== $page ) {
		return;
	}

	wp_enqueue_style(
		'qmo-admin',
		QRMS_PLUGIN_URL . 'modules/_qmo-ortak/assets/css/admin.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/_qmo-ortak/assets/css/admin.css' )
	);
}
