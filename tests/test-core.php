<?php
/**
 * Çekirdek, admin menü, hub ve restoran-menu UI testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

/* ---------------------------------------------------------------------------
 * 1. Lisans istemcisi: durum dalları
 * ------------------------------------------------------------------------ */

echo "Lisans istemcisi\n";

qrms_test(
	'active → modüller yazılır, durum ve tarih güncellenir',
	function () {
		qrms_mock_http( 200, array(
			'status'  => 'active',
			'modules' => array( 'restoran-menu', 'qr-masa', 'qr-analiz' ),
		) );

		$result = qrms_validate_license( 'ANAHTAR-1', 'https://full.qrmenuofficial.com' );

		qrms_assert_same( 'active', $result['status'], 'durum' );
		qrms_assert_same( array( 'restoran-menu', 'qr-masa', 'qr-analiz' ), get_option( 'qrms_active_modules' ), 'modüller' );
		qrms_assert_same( 'ANAHTAR-1', get_option( 'qrms_api_key' ), 'api key' );
		qrms_assert_same( 'https://full.qrmenuofficial.com', get_option( 'qrms_server_url' ), 'sunucu' );
		qrms_assert_same( 'active', get_option( 'qrms_last_status' ), 'last_status' );
		qrms_assert_true( get_option( 'qrms_last_sync' ) > 0, 'last_sync' );
	}
);

qrms_test(
	'istek doğru endpoint ve gövde ile gider',
	function () {
		qrms_mock_http( 200, array( 'status' => 'active', 'modules' => array() ) );

		qrms_validate_license( 'ANAHTAR-2', 'https://full.qrmenuofficial.com/' );

		$call = $GLOBALS['qrms_test']['http_calls'][0];
		qrms_assert_same( 'https://full.qrmenuofficial.com/wp-json/qmls/v1/validate', $call['url'], 'endpoint' );
		qrms_assert_same( 15, $call['args']['timeout'], 'timeout' );

		$body = json_decode( $call['args']['body'], true );
		qrms_assert_same( 'ANAHTAR-2', $body['api_key'], 'api_key alanı' );
		qrms_assert_same( 'restoran.test', $body['domain'], 'domain alanı' );
	}
);

qrms_test(
	'invalid (HTTP 404) → modüller korunur',
	function () {
		update_option( 'qrms_active_modules', array( 'restoran-menu', 'qr-masa' ) );
		qrms_mock_http( 404, array( 'status' => 'invalid' ) );

		$result = qrms_validate_license( 'YANLIS', 'https://full.qrmenuofficial.com' );

		qrms_assert_same( 'invalid', $result['status'], 'durum' );
		qrms_assert_same( 'invalid', get_option( 'qrms_last_status' ), 'last_status' );
		qrms_assert_same( array( 'restoran-menu', 'qr-masa' ), get_option( 'qrms_active_modules' ), 'modüller korunmalı' );
		qrms_assert_contains( 'Geçersiz API anahtarı', $result['message'], 'mesaj' );
	}
);

qrms_test(
	'inactive → modüller korunur',
	function () {
		update_option( 'qrms_active_modules', qrms_all_modules() );
		qrms_mock_http( 200, array( 'status' => 'inactive' ) );

		$result = qrms_validate_license( 'ANAHTAR', 'https://full.qrmenuofficial.com' );

		qrms_assert_same( 'inactive', $result['status'], 'durum' );
		qrms_assert_same( qrms_all_modules(), get_option( 'qrms_active_modules' ), 'modüller korunmalı' );
		qrms_assert_contains( 'pasif durumda', $result['message'], 'mesaj' );
	}
);

qrms_test(
	'domain_mismatch → modüller korunur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-galeri' ) );
		qrms_mock_http( 200, array( 'status' => 'domain_mismatch' ) );

		$result = qrms_validate_license( 'ANAHTAR', 'https://full.qrmenuofficial.com' );

		qrms_assert_same( 'domain_mismatch', $result['status'], 'durum' );
		qrms_assert_same( array( 'qr-galeri' ), get_option( 'qrms_active_modules' ), 'modüller korunmalı' );
		qrms_assert_contains( 'başka bir alan adına kayıtlı', $result['message'], 'mesaj' );
	}
);

qrms_test(
	'unreachable → modüller ve son bağlantı tarihi korunur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-chatbot' ) );
		update_option( 'qrms_last_sync', 1000 );
		qrms_mock_http_error();

		$result = qrms_validate_license( 'ANAHTAR', 'https://full.qrmenuofficial.com' );

		qrms_assert_same( 'unreachable', $result['status'], 'durum' );
		qrms_assert_same( 'unreachable', get_option( 'qrms_last_status' ), 'last_status' );
		qrms_assert_same( array( 'qr-chatbot' ), get_option( 'qrms_active_modules' ), 'modüller korunmalı' );
		qrms_assert_same( 1000, get_option( 'qrms_last_sync' ), 'last_sync değişmemeli' );
	}
);

qrms_test(
	'bozuk/beklenmeyen cevap → unreachable gibi ele alınır',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-masa' ) );
		$GLOBALS['qrms_test']['http'] = array( 'response' => array( 'code' => 200 ), 'body' => '<html>hata</html>' );

		$result = qrms_validate_license( 'ANAHTAR', 'https://full.qrmenuofficial.com' );

		qrms_assert_same( 'unreachable', $result['status'], 'durum' );
		qrms_assert_same( array( 'qr-masa' ), get_option( 'qrms_active_modules' ), 'modüller korunmalı' );
	}
);

qrms_test(
	'active → bilinmeyen slug\'lar süzülür, sıra sabit kalır',
	function () {
		qrms_mock_http( 200, array(
			'status'  => 'active',
			'modules' => array( 'qr-analiz', 'sahte-modul', 'restoran-menu', 'restoran-menu' ),
		) );

		qrms_validate_license( 'ANAHTAR', 'https://full.qrmenuofficial.com' );

		qrms_assert_same( array( 'restoran-menu', 'qr-analiz' ), get_option( 'qrms_active_modules' ), 'süzülmüş liste' );
	}
);

qrms_test(
	'sunucu adresi boş bırakılırsa varsayılan kullanılır',
	function () {
		qrms_mock_http( 200, array( 'status' => 'active', 'modules' => array() ) );

		qrms_validate_license( 'ANAHTAR', '' );

		qrms_assert_same( 'https://full.qrmenuofficial.com', get_option( 'qrms_server_url' ), 'varsayılan sunucu' );
	}
);

qrms_test(
	'günlük cron: kayıtlı key ile sessizce yeniden doğrular',
	function () {
		update_option( 'qrms_api_key', 'KAYITLI' );
		update_option( 'qrms_server_url', 'https://staging.qrmenuofficial.com' );
		update_option( 'qrms_active_modules', array( 'qr-masa' ) );
		qrms_mock_http( 200, array( 'status' => 'active', 'modules' => array( 'qr-masa', 'qr-galeri' ) ) );

		QRMS_License_Client::run_daily_sync();

		$call = $GLOBALS['qrms_test']['http_calls'][0];
		qrms_assert_same( 'https://staging.qrmenuofficial.com/wp-json/qmls/v1/validate', $call['url'], 'kayıtlı sunucu kullanılmalı' );
		qrms_assert_same( array( 'qr-masa', 'qr-galeri' ), get_option( 'qrms_active_modules' ), 'liste güncellenmeli' );
	}
);

qrms_test(
	'günlük cron: API anahtarı yoksa istek atılmaz',
	function () {
		QRMS_License_Client::run_daily_sync();

		qrms_assert_same( 0, count( $GLOBALS['qrms_test']['http_calls'] ), 'istek sayısı' );
	}
);

qrms_test(
	'cron aktivasyonda kurulur, deaktivasyonda temizlenir',
	function () {
		QRMS_License_Client::schedule_cron();
		qrms_assert_true( wp_next_scheduled( 'qrms_daily_license_sync' ), 'cron kurulmalı' );

		$first = wp_next_scheduled( 'qrms_daily_license_sync' );
		QRMS_License_Client::schedule_cron();
		qrms_assert_same( $first, wp_next_scheduled( 'qrms_daily_license_sync' ), 'ikinci kez zamanlanmamalı' );

		QRMS_License_Client::unschedule_cron();
		qrms_assert_false( wp_next_scheduled( 'qrms_daily_license_sync' ), 'cron temizlenmeli' );
	}
);

/* ---------------------------------------------------------------------------
 * 2. Bilgilendirme notice'ı (3 gün kuralı)
 * ------------------------------------------------------------------------ */

echo "\nBilgilendirme notice'ı\n";

qrms_test(
	'durum active iken notice gösterilmez',
	function () {
		update_option( 'qrms_last_status', 'active' );
		update_option( 'qrms_last_active_sync', time() - ( 10 * DAY_IN_SECONDS ) );

		qrms_assert_false( QRMS_License_Client::should_show_notice(), 'notice olmamalı' );
	}
);

qrms_test(
	'sorunlu durum ama 3 günden yeni ise notice gösterilmez',
	function () {
		update_option( 'qrms_last_status', 'unreachable' );
		update_option( 'qrms_last_active_sync', time() - ( 2 * DAY_IN_SECONDS ) );

		qrms_assert_false( QRMS_License_Client::should_show_notice(), 'notice olmamalı' );
	}
);

qrms_test(
	'sorunlu durum ve 3 günden eski ise notice gösterilir',
	function () {
		update_option( 'qrms_last_status', 'domain_mismatch' );
		update_option( 'qrms_last_active_sync', time() - ( 4 * DAY_IN_SECONDS ) );

		qrms_assert_true( QRMS_License_Client::should_show_notice(), 'notice gösterilmeli' );
	}
);

qrms_test(
	'hiç başarılı bağlantı olmamışsa notice gösterilmez',
	function () {
		update_option( 'qrms_last_status', 'unreachable' );

		qrms_assert_false( QRMS_License_Client::should_show_notice(), 'notice olmamalı' );
	}
);

qrms_test(
	'notice sadece plugin ekranlarında çizilir',
	function () {
		update_option( 'qrms_last_status', 'inactive' );
		update_option( 'qrms_last_active_sync', time() - ( 5 * DAY_IN_SECONDS ) );
		update_option( 'qrms_last_sync', time() - ( 5 * DAY_IN_SECONDS ) );

		$_GET = array();
		ob_start();
		QRMS_License_Client::maybe_render_notice();
		qrms_assert_same( '', ob_get_clean(), 'dashboard\'da çıkmamalı' );

		$_GET = array( 'page' => 'qrms-settings' );
		ob_start();
		QRMS_License_Client::maybe_render_notice();
		$html = ob_get_clean();

		qrms_assert_contains( 'is-dismissible', $html, 'kapatılabilir notice' );
		qrms_assert_contains( 'Mevcut modülleriniz etkilenmedi', $html, 'mesaj' );
	}
);

/* ---------------------------------------------------------------------------
 * 3. Kurulum sihirbazı
 * ------------------------------------------------------------------------ */

echo "\nKurulum sihirbazı\n";

qrms_test(
	'active cevabı kurulumu tamamlar ve modülleri listeler',
	function () {
		qrms_mock_http( 200, array( 'status' => 'active', 'modules' => array( 'restoran-menu', 'yorum-feedback' ) ) );
		$_POST = array(
			'qrms_submit_license' => '1',
			'qrms_api_key'        => 'ANAHTAR',
			'qrms_server_url'     => 'https://full.qrmenuofficial.com',
		);

		ob_start();
		QRMS_Wizard::render_page();
		$html = ob_get_clean();

		qrms_assert_true( QRMS_Wizard::is_setup_completed(), 'setup_completed' );
		qrms_assert_contains( 'Restoran Menü', $html, 'modül adı' );
		qrms_assert_contains( 'Yorum &amp; Feedback', $html, 'modül adı' );
		qrms_assert_contains( 'Devam Et', $html, 'devam butonu' );
	}
);

qrms_test(
	'invalid cevabı kurulumu tamamlamaz, form tekrar gösterilir',
	function () {
		qrms_mock_http( 404, array( 'status' => 'invalid' ) );
		$_POST = array(
			'qrms_submit_license' => '1',
			'qrms_api_key'        => 'YANLIS',
			'qrms_server_url'     => 'https://full.qrmenuofficial.com',
		);

		ob_start();
		QRMS_Wizard::render_page();
		$html = ob_get_clean();

		qrms_assert_false( QRMS_Wizard::is_setup_completed(), 'setup_completed yazılmamalı' );
		qrms_assert_contains( 'Geçersiz API anahtarı', $html, 'hata mesajı' );
		qrms_assert_contains( 'name="qrms_api_key"', $html, 'form tekrar gösterilmeli' );
	}
);

qrms_test(
	'inactive ve domain_mismatch kurulumu tamamlamaz',
	function () {
		foreach ( array( 'inactive', 'domain_mismatch' ) as $status ) {
			qrms_mock_http( 200, array( 'status' => $status ) );
			$_POST = array(
				'qrms_submit_license' => '1',
				'qrms_api_key'        => 'ANAHTAR',
				'qrms_server_url'     => 'https://full.qrmenuofficial.com',
			);

			ob_start();
			QRMS_Wizard::render_page();
			$html = ob_get_clean();

			qrms_assert_false( QRMS_Wizard::is_setup_completed(), $status . ': setup_completed yazılmamalı' );
			qrms_assert_contains( 'name="qrms_api_key"', $html, $status . ': form tekrar gösterilmeli' );
		}
	}
);

qrms_test(
	'unreachable cevabında "Tekrar Dene" butonu gösterilir',
	function () {
		qrms_mock_http_error();
		$_POST = array(
			'qrms_submit_license' => '1',
			'qrms_api_key'        => 'ANAHTAR',
			'qrms_server_url'     => 'https://full.qrmenuofficial.com',
		);

		ob_start();
		QRMS_Wizard::render_page();
		$html = ob_get_clean();

		qrms_assert_false( QRMS_Wizard::is_setup_completed(), 'setup_completed yazılmamalı' );
		qrms_assert_contains( 'Sunucuya bağlanılamadı', $html, 'hata mesajı' );
		qrms_assert_contains( 'Tekrar Dene', $html, 'tekrar dene butonu' );
	}
);

qrms_test(
	'boş API anahtarı ile sunucuya istek atılmaz',
	function () {
		$_POST = array(
			'qrms_submit_license' => '1',
			'qrms_api_key'        => '   ',
			'qrms_server_url'     => 'https://full.qrmenuofficial.com',
		);

		$result = QRMS_Wizard::handle_submission();

		qrms_assert_same( 'empty', $result['status'], 'durum' );
		qrms_assert_same( 0, count( $GLOBALS['qrms_test']['http_calls'] ), 'istek sayısı' );
	}
);

qrms_test(
	'form varsayılan sunucu adresiyle gelir',
	function () {
		ob_start();
		QRMS_Wizard::render_form();
		$html = ob_get_clean();

		qrms_assert_contains( 'value="https://full.qrmenuofficial.com"', $html, 'varsayılan sunucu' );
		qrms_assert_contains( 'Doğrula ve Kur', $html, 'buton etiketi' );
	}
);

qrms_test(
	'kurulum yapılmamışken aktivasyon sonrası sihirbaza yönlendirilir',
	function () {
		QRMS_Wizard::maybe_flag_activation_redirect();

		$redirected = false;

		try {
			QRMS_Wizard::maybe_redirect_to_wizard();
		} catch ( QRMS_Test_Redirect $e ) {
			$redirected = true;
			qrms_assert_contains( 'page=qrms-wizard', $e->getMessage(), 'hedef adres' );
		}

		qrms_assert_true( $redirected, 'yönlendirme yapılmalı' );
	}
);

qrms_test(
	'kurulum tamamlandıysa bir daha ASLA otomatik yönlendirme olmaz',
	function () {
		update_option( 'qrms_setup_completed', true );
		QRMS_Wizard::maybe_flag_activation_redirect();

		// Bayrak hiç bırakılmamalı.
		qrms_assert_false( get_transient( 'qrms_activation_redirect' ), 'bayrak bırakılmamalı' );

		// Bayrak elle bırakılsa bile yönlendirme olmamalı.
		set_transient( 'qrms_activation_redirect', 1, 60 );
		QRMS_Wizard::maybe_redirect_to_wizard();

		qrms_assert_same( 0, count( $GLOBALS['qrms_test']['redirects'] ), 'yönlendirme olmamalı' );
	}
);

qrms_test(
	'yönlendirme bayrağı tek kullanımlıktır',
	function () {
		QRMS_Wizard::maybe_flag_activation_redirect();

		try {
			QRMS_Wizard::maybe_redirect_to_wizard();
		} catch ( QRMS_Test_Redirect $e ) {
			unset( $e );
		}

		QRMS_Wizard::maybe_redirect_to_wizard(); // İkincisi sessizce dönmeli.

		qrms_assert_same( 1, count( $GLOBALS['qrms_test']['redirects'] ), 'tek yönlendirme' );
	}
);

qrms_test(
	'Genel Ayarlar üzerinden yeniden doğrulama modül listesini günceller',
	function () {
		update_option( 'qrms_setup_completed', true );
		update_option( 'qrms_active_modules', array( 'qr-masa' ) );
		qrms_mock_http( 200, array( 'status' => 'active', 'modules' => array( 'qr-masa', 'qr-chatbot' ) ) );

		$_GET  = array( 'page' => 'qrms-settings' );
		$_POST = array(
			'qrms_submit_license' => '1',
			'qrms_api_key'        => 'YENI-ANAHTAR',
			'qrms_server_url'     => 'https://full.qrmenuofficial.com',
		);

		ob_start();
		QRMS_Admin::render_settings();
		$html = ob_get_clean();

		qrms_assert_same( array( 'qr-masa', 'qr-chatbot' ), get_option( 'qrms_active_modules' ), 'liste güncellenmeli' );
		qrms_assert_contains( 'Chatbot Asistan', $html, 'yeni modül görünmeli' );
		qrms_assert_same( 0, count( $GLOBALS['qrms_test']['redirects'] ), 'yönlendirme olmamalı' );
	}
);

/* ---------------------------------------------------------------------------
 * 4. Admin menüsü
 * ------------------------------------------------------------------------ */

echo "\nAdmin menüsü\n";

/**
 * Kayıtlı alt menü slug'larını döndürür.
 *
 * @return string[]
 */
function qrms_registered_submenu_slugs() {
	return array_map(
		function ( $item ) {
			return $item['slug'];
		},
		$GLOBALS['qrms_test']['submenus']
	);
}

qrms_test(
	'modül yokken sadece Genel Bakış ve Genel Ayarlar görünür',
	function () {
		QRMS_Admin::register_menu();

		$slugs = qrms_registered_submenu_slugs();

		qrms_assert_same( array( 'qrms-overview', 'qrms-settings' ), $slugs, 'menü listesi' );

		foreach ( qrms_all_modules() as $slug ) {
			qrms_assert_false(
				in_array( QRMS_Admin::get_module_page_slug( $slug ), $slugs, true ),
				$slug . ' menüde görünmemeli'
			);
		}
	}
);

qrms_test(
	'sadece aktif modüller menüde görünür ve sıra korunur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-analiz', 'restoran-menu', 'qr-ceviri' ) );

		QRMS_Admin::register_menu();

		qrms_assert_same(
			array(
				'qrms-overview',
				'qrms-module-restoran-menu',
				'qrms-module-qr-analiz',
				'qrms-module-qr-ceviri',
				'qrms-settings',
			),
			qrms_registered_submenu_slugs(),
			'menü sırası'
		);
	}
);

qrms_test(
	'tüm modüller aktifken hepsi menüde görünür',
	function () {
		update_option( 'qrms_active_modules', qrms_all_modules() );

		QRMS_Admin::register_menu();

		// Genel Bakış + her modül + Genel Ayarlar. Sayı listeden türetilir ki
		// yeni bir modül eklendiğinde test elle güncellenmek zorunda kalmasın.
		qrms_assert_same(
			count( QRMS_Helpers::MODULE_SLUGS ) + 2,
			count( qrms_registered_submenu_slugs() ),
			'Genel Bakış + modüller + Genel Ayarlar'
		);
	}
);

qrms_test(
	'sihirbaz admin_menu sırasında KAYITLI KALIR (route çözümü buna bağlı)',
	function () {
		// Regresyon: remove_submenu_page() admin_menu içinde çağrılırsa
		// WordPress sayfanın parent'ını bulamaz ve admin.php 403 verir.
		QRMS_Admin::register_menu();
		QRMS_Wizard::register_page();

		qrms_assert_true( in_array( 'qrms-wizard', qrms_registered_submenu_slugs(), true ), 'admin_menu sonunda kayıtlı olmalı' );
		qrms_assert_same( array(), $GLOBALS['qrms_test']['removed'], 'admin_menu sırasında kaldırılmamalı' );

		// Gizleme, route çözüldükten sonra (current_screen) yapılır.
		QRMS_Wizard::hide_page_from_menu();

		qrms_assert_false( in_array( 'qrms-wizard', qrms_registered_submenu_slugs(), true ), 'gizlendikten sonra menüde olmamalı' );
	}
);

qrms_test(
	'sihirbaz gizleme current_screen hook\'una bağlı, admin_menu\'ye değil',
	function () {
		QRMS_Wizard::init();

		$hooks = $GLOBALS['qrms_test']['actions'];

		qrms_assert_true( isset( $hooks['current_screen'] ), 'current_screen hook\'u kayıtlı olmalı' );
		qrms_assert_same(
			1,
			count( $hooks['admin_menu'] ),
			'admin_menu\'ye yalnızca sayfa kaydı bağlanmalı'
		);
	}
);

qrms_test(
	'gizli sihirbaz sayfasının başlığı elle set edilir',
	function () {
		// Regresyon: sayfa $submenu'den çıkınca WordPress başlığı bulamıyor,
		// $title null kalıyor (boş tarayıcı başlığı + PHP 8.1+ deprecation).
		unset( $GLOBALS['title'] );
		$_GET = array( 'page' => 'qrms-wizard' );

		QRMS_Wizard::hide_page_from_menu();

		qrms_assert_same( 'QR Menu Suite Kurulumu', $GLOBALS['title'], 'başlık' );

		unset( $GLOBALS['title'] );
		$_GET = array( 'page' => 'qrms-settings' );

		QRMS_Wizard::hide_page_from_menu();

		qrms_assert_false( isset( $GLOBALS['title'] ), 'diğer ekranlarda başlığa dokunulmamalı' );
	}
);

qrms_test(
	'üst menü konumu tam sayı değil (slot çakışmasına karşı)',
	function () {
		// Regresyon: konum 30 gibi tam sayı olursa, aynı slotu kullanan başka
		// bir plugin menüyü ezebiliyor ve "QR Menü" hiç görünmüyor.
		qrms_assert_false( is_int( QRMS_Admin::MENU_POSITION ), 'konum tam sayı olmamalı' );

		QRMS_Admin::register_menu();

		qrms_assert_same( QRMS_Admin::MENU_POSITION, $GLOBALS['qrms_test']['menus'][0]['position'], 'kayıtlı konum' );
		qrms_assert_true( isset( $GLOBALS['menu'][ (string) QRMS_Admin::MENU_POSITION ] ), 'menü satırı yerinde' );
	}
);

qrms_test(
	'üst menü başka bir plugin tarafından ezilirse geri gelir',
	function () {
		QRMS_Admin::register_menu();

		// Eski nesil plugin davranışı: slotu doğrudan ezer.
		$GLOBALS['menu'][ (string) QRMS_Admin::MENU_POSITION ] = array( 'Başka Plugin', 'manage_options', 'baska-plugin' );

		QRMS_Admin::ensure_menu_registered();

		$slugs = array_map(
			function ( $item ) {
				return $item[2];
			},
			$GLOBALS['menu']
		);

		qrms_assert_true( in_array( QRMS_Admin::MENU_SLUG, $slugs, true ), 'menü geri eklenmeli' );
	}
);

qrms_test(
	'menü yerindeyken emniyet kemeri tekrar eklemez',
	function () {
		QRMS_Admin::register_menu();
		QRMS_Admin::ensure_menu_registered();

		qrms_assert_same( 1, count( $GLOBALS['qrms_test']['menus'] ), 'tek kayıt olmalı' );
	}
);

/**
 * Kuyruğa alınan stil handle'ları.
 *
 * @return string[]
 */
function qrms_stil_handlelari() {
	return array_map(
		function ( $style ) {
			return $style['handle'];
		},
		$GLOBALS['qrms_test']['styles']
	);
}

qrms_test(
	'ekran stili yalnızca eklentinin ekranlarında yüklenir',
	function () {
		$_GET = array();

		QRMS_Admin::enqueue_assets();

		qrms_assert_false( in_array( 'qrms-admin', qrms_stil_handlelari(), true ), 'ekran stili yüklenmemeli' );

		$GLOBALS['qrms_test']['styles'] = array();
		$_GET                           = array( 'page' => 'qrms-overview' );
		QRMS_Admin::enqueue_assets();

		qrms_assert_true( in_array( 'qrms-admin', qrms_stil_handlelari(), true ), 'ekran stili plugin ekranında' );
		qrms_assert_false( in_array( 'qrms-admin-menu', qrms_stil_handlelari(), true ), 'menü stili ekran kuyruğunda değil' );
	}
);

qrms_test(
	'sol menü stili ve betiği HER admin ekranında yüklenir',
	function () {
		// Menü her sayfada görünür; grup başlıkları yalnızca eklentinin
		// ekranlarında kurulsaydı diğer sayfalarda menü dağılırdı.
		$_GET = array();

		QRMS_Admin::enqueue_menu_assets();

		$scripts = array_map(
			function ( $script ) {
				return $script['handle'];
			},
			$GLOBALS['qrms_test']['scripts']
		);

		qrms_assert_true( in_array( 'qrms-admin-menu', qrms_stil_handlelari(), true ), 'menü stili' );
		qrms_assert_true( in_array( 'qrms-admin-menu', $scripts, true ), 'menü betiği' );

		// Renkler tek kaynaktan (get_menu_groups) satır içi stile iner.
		$inline = $GLOBALS['qrms_test']['inline_styles'];

		qrms_assert_same( 1, count( $inline ), 'tek satır içi stil' );
		qrms_assert_contains( '--qrms-menu-accent', $inline[0]['data'], 'renk değişkeni' );

		foreach ( QRMS_Admin::get_menu_groups() as $grup ) {
			qrms_assert_contains( '.qrms-mg-' . $grup['key'], $inline[0]['data'], $grup['key'] . ' rengi' );
		}
	}
);

qrms_test(
	'yetkisiz kullanıcıya menü varlıkları yüklenmez',
	function () {
		$GLOBALS['qrms_test']['can'] = false;

		QRMS_Admin::enqueue_menu_assets();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['styles'], 'stil yok' );
		qrms_assert_same( array(), $GLOBALS['qrms_test']['scripts'], 'betik yok' );
	}
);

qrms_test(
	'sol menüye kural yazan TEK dosya admin-menu.css\'tir',
	function () {
		// Regresyon: sol menüye serbestçe kural yazıldığında çekirdeğin uçan
		// menüsü (folded flyout) bozuluyordu. Menü artık gruplanıyor ama
		// kurallar tek dosyada ve yalnızca KENDİ satırlarımızda kalmalı.
		$menu_css = QRMS_PLUGIN_DIR . 'assets/css/admin-menu.css';

		qrms_assert_true( file_exists( $menu_css ), 'menü stili var' );

		$bulunan = array();
		$gezici  = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( QRMS_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $gezici as $dosya ) {
			if ( 'css' !== strtolower( $dosya->getExtension() ) ) {
				continue;
			}

			$css = file_get_contents( $dosya->getPathname() );
			qrms_assert_true( is_string( $css ) && '' !== $css, $dosya->getFilename() . ' okunmalı' );
			$css = preg_replace( '#/\*.*?\*/#s', '', $css );

			if ( $dosya->getPathname() === $menu_css ) {
				continue;
			}

			if ( preg_match( '/#adminmenu|\.wp-submenu|\.wp-has-submenu/', $css ) ) {
				$bulunan[] = str_replace( QRMS_PLUGIN_DIR, '', $dosya->getPathname() );
			}
		}

		qrms_assert_same( array(), $bulunan, 'başka dosyada sol menü seçicisi yok' );

		$frontend = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-frontend.css' );
		$frontend = preg_replace( '#/\*.*?\*/#s', '', $frontend );
		qrms_assert_false(
			(bool) preg_match( '/^\s*(html|body)\s*\{/m', $frontend ),
			'frontend html/body kuralları wp-admin\'e sızmamalı'
		);
	}
);

qrms_test(
	'menü stilinin HER seçicisi kendi sınıfımızla kapsanır (flyout korunur)',
	function () {
		// Çekirdeğin menü mekaniğine (konum, genişlik, açılma) dokunmamanın
		// makinece kontrol edilebilir tanımı: `.wp-submenu`/`#adminmenu`
		// yalnızca kapsam daraltmak için kullanılabilir, her seçicide bizim
		// bir sınıfımız bulunmalıdır.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/admin-menu.css' );
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );

		$kapsamsiz = array();

		preg_match_all( '/([^{}]+)\{/', $css, $eslesmeler );

		foreach ( $eslesmeler[1] as $secici_grubu ) {
			$secici_grubu = trim( $secici_grubu );

			// @media gibi at-kuralları seçici değildir.
			if ( '' === $secici_grubu || '@' === $secici_grubu[0] ) {
				continue;
			}

			foreach ( explode( ',', $secici_grubu ) as $secici ) {
				$secici = trim( $secici );

				if ( '' !== $secici && false === strpos( $secici, '.qrms-' ) ) {
					$kapsamsiz[] = $secici;
				}
			}
		}

		qrms_assert_same( array(), $kapsamsiz, 'kapsamsız seçici yok' );

		// Katlanmış menüde ve dar ekranda katlama devre dışı kalmalı; yoksa
		// uçan menüde satırlar gizli görünürdü.
		qrms_assert_contains( '.folded #adminmenu', $css, 'katlanmış menü istisnası' );
		qrms_assert_contains( '.auto-fold #adminmenu', $css, 'dar ekran istisnası' );
		qrms_assert_true(
			(bool) preg_match( '/\.folded[^{]*is-hidden[^{]*,\s*\.auto-fold[^{]*is-hidden[^{]*\{[^}]*display:\s*block/s', $css ),
			'gizli satırlar uçan menüde geri gelir'
		);

		// Arka plan koyu temanın kendi rengi olarak kalır: satırlara background
		// yazılmaz, renk yalnızca ikonda ve sol kenar şeridindedir.
		qrms_assert_true(
			(bool) preg_match( '/a\.qrms-menu-item\s*\{[^}]*border-left:\s*3px solid var\( --qrms-menu-accent/s', $css ),
			'sol kenar şeridi'
		);
		qrms_assert_false(
			(bool) preg_match( '/a\.qrms-menu-item\s*\{[^}]*background(-color)?:\s*(?!none)/s', $css ),
			'satır arka planına dokunulmaz'
		);
	}
);

qrms_test(
	'ürün listesi anahtarı kapsamsız input:checked kuralı taşımaz',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-admin-list.css' );
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );

		qrms_assert_false(
			(bool) preg_match( '/^\s*input:checked\s*\+\s*\.rma-slider/m', $css ),
			'kapsamsız input:checked yok'
		);
		qrms_assert_contains( '.rma-switch input:checked + .rma-slider', $css, 'anahtar .rma-switch altında' );
	}
);

qrms_test(
	'ürün listesi hızlı düzenle görsel ve alerjen alanlarını taşır',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-columns.php' );
		qrms_assert_contains( 'quick_edit_custom_box', file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' ), 'quick edit kancası' );
		qrms_assert_contains( 'render_quick_edit_box', $php, 'quick edit markup' );
		qrms_assert_contains( 'save_quick_edit_fields', $php, 'quick edit kayıt' );
		qrms_assert_contains( 'rma_qe_nonce', $php, 'quick edit nonce' );
		qrms_assert_contains( 'rma_qe_thumbnail_id', $php, 'görsel gizli input' );
		qrms_assert_contains( 'set_post_thumbnail', $php, 'görsel kaydı' );
		qrms_assert_contains( "wp_set_object_terms( \$post_id, \$term_ids, 'rma_allergen', false )", $php, 'alerjen kaydı' );
		qrms_assert_contains( 'cat-checklist rma_allergen-checklist', $php, 'kategori checklist kalıbı' );
		qrms_assert_contains( 'name="rma_qe_allergens[]"', $php, 'alerjen checkbox name' );
		qrms_assert_contains( 'add_quick_edit_inline_data', $php, 'inline veri' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );
		qrms_assert_contains( 'inlineEditPost.edit', $js, 'quick edit açılış sarmalı' );
		qrms_assert_contains( 'inlineEditPost.save', $js, 'inline save sarmalı' );
		qrms_assert_contains( 'wp.media', $js, 'medya seçici' );
		qrms_assert_contains( 'ul.rma_allergen-checklist :checkbox', $js, 'alerjen doldurma' );

		$pages = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );
		qrms_assert_contains( 'wp_enqueue_media', $pages, 'liste ekranında media' );
		qrms_assert_contains( 'inline-edit-post', $pages, 'inline edit bağımlılığı' );

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-admin-list.css' );
		qrms_assert_contains( '@media screen and (max-width: 782px)', $css, 'WP admin mobil kırılımı' );
		qrms_assert_contains( 'grid-template-columns: 1fr', $css, 'dar ekranda tek sütun' );
		qrms_assert_contains( 'min-height: 44px', $css, 'dokunma hedefi' );
	}
);

qrms_test(
	'ürün vitrini canlı önizlemesi masaüstünde sticky, overflow ata kırmaz',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );

		qrms_assert_true(
			(bool) preg_match( '/\.rma-admin:has\(#rma-vitrin-form\)\s*\{[^}]*overflow:\s*visible/s', $css ),
			'sticky ata overflow visible'
		);
		qrms_assert_contains( '@media screen and (min-width: 1024px)', $css, 'sticky masaüstü breakpoint' );
		qrms_assert_contains( 'position: sticky', $css, 'önizleme sticky' );
		qrms_assert_contains( 'max-height: calc(100vh - var(--rma-vitrin-sticky-top) - 16px)', $css, 'viewport yüksekliği' );

		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-vitrin-admin.php' );
		qrms_assert_true(
			strpos( $php, 'rma-vitrin-layout-wrap' ) < strpos( $php, '1. Vitrin Adı' ),
			'önizleme sütunu tüm formu sarar'
		);
		qrms_assert_true(
			strpos( $php, '6. Kayma Davranışı' ) < strpos( $php, 'rma-vitrin-layout-preview' ),
			'kayma bölümü sol sütunda, önizlemeden önce'
		);

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );
		qrms_assert_contains( 'initVitrinPreviewSticky', $js, 'admin bar ofseti' );
		qrms_assert_contains( '--rma-vitrin-sticky-top', $js, 'sticky CSS değişkeni' );
	}
);

qrms_test(
	'restoran menü hub ızgarası sabit 3 sütun, ikon hizalı ve başlık ayırıcılı',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/hub.css' );

		qrms_assert_contains( '.rma-hub .qrms-hub-grid', $css, 'ızgara bu sayfaya kapsüllü' );
		qrms_assert_true( false === strpos( $css, 'repeat(auto-fit' ), 'auto-fit yok' );
		qrms_assert_true( 1 !== preg_match( '/\.qrms-hub-grid[^{]*\{[^}]*display:\s*grid/', $css ), 'ızgara display:grid yazmaz' );
		qrms_assert_contains( 'display: contents', $css, 'ikon başlıkla aynı satırda' );
		qrms_assert_contains( 'align-self: center', $css, 'ikon dikey orta' );
		qrms_assert_contains( 'border-top: 0.5px solid', $css, 'başlık altı ayırıcı' );
		qrms_assert_contains( 'rgba(201, 168, 76, 0.35)', $css, 'gold palet ayırıcı rengi' );
		qrms_assert_contains( 'repeat(3, minmax(0, 1fr))', $css, 'üç özet kartı' );
		qrms_assert_contains( 'grid-template-columns: 1fr', $css, 'dar ekranda özet alt alta' );
		qrms_assert_contains( '@media screen and (max-width: 600px)', $css, 'telefon kırılımı' );
		qrms_assert_contains( '.rma-hub .qrms-stat-value', $css, 'ortak değer class' );
		qrms_assert_contains( 'font-size: 18px', $css, 'özet değer boyutu' );
		qrms_assert_contains( 'font-weight: 300', $css, 'özet değer ağırlığı' );
	}
);

qrms_test(
	'restoran menü hub kart başlıkları ve üç özet kutusu tanımlı',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		qrms_assert_contains( "'hub_title'  => 'Menü Görünümü'", $php, 'Görünüm netleşir' );
		qrms_assert_contains( "'hub_title'  => 'Stok Durumu'", $php, 'Ürünüm Yok → Stok Durumu' );
		qrms_assert_contains( "isset( \$page['hub_title'] ) ? \$page['hub_title'] : \$page['menu_title']", $php, 'hub başlığı alt sayfa adını bozmaz' );
		qrms_assert_contains( "'label'  => 'Eksik Ürün (Tükendi)'", $php, 'mevcut özet kartı durur' );
		qrms_assert_contains( "'label'  => 'Okunmayan Yorum'", $php, 'yorum özeti' );
		qrms_assert_contains( "'label'  => 'Bugün Görüntülenme'", $php, 'analiz özeti' );
		qrms_assert_contains( '%d okunmayan yorum', $php, 'yorum değeri biçimi' );
		qrms_assert_contains( '%d görüntülenme (bugün)', $php, 'görüntülenme değeri biçimi' );
		qrms_assert_contains( 'qrm_cf_unread_total', $php, 'okunmamış yorum sayacı' );
		qrms_assert_contains( "qrms-yf-formlar", $php, 'yorum form listesi adresi' );
		qrms_assert_contains( "tab' => 'submissions'", $php, 'gönderiler sekmesi' );
		qrms_assert_contains( "get_module_page_url( 'qr-analiz' )", $php, 'QR Analiz adresi' );
		qrms_assert_contains( 'QRMS_Analitik::genel_bakis()', $php, 'istatistiklerle aynı kaynak' );
		qrms_assert_contains( "['mv_bugun']", $php, 'bugünkü menü görüntüleme kovası' );
		qrms_assert_contains( 'echo \'<div class="rma-hub">\'', $php, 'kapsül sarmalayıcı' );
		qrms_assert_contains( "'title' => 'Ürünlerim'", $php, 'Ürünlerim durur' );
		qrms_assert_contains( "'title' => 'Ürün Ekle'", $php, 'Ürün Ekle durur' );

		$hub_fn = substr( $php, strpos( $php, 'function get_hub_cards' ), strpos( $php, 'function get_legacy_page_map' ) - strpos( $php, 'function get_hub_cards' ) );
		$sira   = array(
			"'title' => 'Ürünlerim'",
			"'title' => 'Ürün Ekle'",
			'qrms-rm-urunum-yok',
			'qrms-rm-kampanya',
			"'title' => 'Kategoriler'",
			"'title' => 'Alerjenler'",
			"'title' => 'Malzemeler'",
			'qrms-rm-gorunum',
			'qrms-rm-one-cikanlar',
			'qrms-rm-vitrin',
			'qrms-rm-diger',
		);
		$onceki = -1;
		foreach ( $sira as $parca ) {
			$pos = strpos( $hub_fn, $parca );
			qrms_assert_true( false !== $pos, $parca . ' hub kartında' );
			qrms_assert_true( $pos > $onceki, $parca . ' sırası' );
			$onceki = $pos;
		}

		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/module.php' );
		qrms_assert_contains( "modules/restoran-menu/assets/css/hub.css", $modul, 'hub.css kuyruğa alınır' );
		qrms_assert_contains( "array( 'qrms-admin' )", $modul, 'ortak admin.css sonrası yüklenir' );
	}
);

qrms_test(
	'modül placeholder sayfası modül adını ve "yakında" metnini gösterir',
	function () {
		ob_start();
		QRMS_Admin::render_module_placeholder( 'qr-masa-oturum-guvenligi' );
		$html = ob_get_clean();

		qrms_assert_contains( 'Güvenlik Ayarı', $html, 'başlık' );
		qrms_assert_contains( 'Bu modül yakında burada olacak.', $html, 'metin' );
	}
);

qrms_test(
	'modül kendi sayfasını kaydetmediyse placeholder basılır',
	function () {
		ob_start();
		QRMS_Admin::render_module_page( 'qr-galeri' );
		$html = ob_get_clean();

		qrms_assert_contains( 'Bu modül yakında burada olacak.', $html, 'placeholder' );
	}
);

qrms_test(
	'modül kendi sayfasını kaydettiyse placeholder yerine o basılır',
	function () {
		QRMS_Admin::register_module_page(
			'qr-masa',
			function () {
				echo '<p>Masa yönetim ekranı</p>';
			}
		);

		ob_start();
		QRMS_Admin::render_module_page( 'qr-masa' );
		$html = ob_get_clean();

		qrms_assert_contains( 'Masa yönetim ekranı', $html, 'modül içeriği' );
		qrms_assert_false(
			false !== strpos( $html, 'Bu modül yakında burada olacak.' ),
			'placeholder basılmamalı'
		);
	}
);

qrms_test(
	'bilinmeyen slug veya çağrılamaz callback kaydedilmez',
	function () {
		QRMS_Admin::register_module_page( 'sahte-modul', '__return_true' );
		QRMS_Admin::register_module_page( 'qr-ceviri', 'qrms_olmayan_fonksiyon' );

		qrms_assert_true( null === QRMS_Admin::get_module_page_callback( 'sahte-modul' ), 'bilinmeyen slug' );
		qrms_assert_true( null === QRMS_Admin::get_module_page_callback( 'qr-ceviri' ), 'çağrılamaz callback' );
	}
);

qrms_test(
	'plugin ekranı tespiti sadece qrms sayfalarında true döner',
	function () {
		$_GET = array();
		qrms_assert_false( QRMS_Admin::is_plugin_screen(), 'sayfa yok' );

		$_GET = array( 'page' => 'edit-comments' );
		qrms_assert_false( QRMS_Admin::is_plugin_screen(), 'başka sayfa' );

		$_GET = array( 'page' => 'qrms-module-qr-masa' );
		qrms_assert_true( QRMS_Admin::is_plugin_screen(), 'plugin sayfası' );
	}
);

/* ---------------------------------------------------------------------------
 * 5. Modül yükleyici
 * ------------------------------------------------------------------------ */

echo "\nModül yükleyici\n";

qrms_test(
	'init fonksiyon adı slug\'dan doğru türetilir',
	function () {
		qrms_assert_same( 'qrms_module_restoran_menu_init', QRMS_Module_Loader::get_init_function( 'restoran-menu' ), 'restoran-menu' );
		qrms_assert_same(
			'qrms_module_qr_calisma_saatleri_init',
			QRMS_Module_Loader::get_init_function( 'qr-calisma-saatleri' ),
			'qr-calisma-saatleri'
		);
		qrms_assert_same(
			'qrms_module_qr_masa_oturum_guvenligi_init',
			QRMS_Module_Loader::get_init_function( 'qr-masa-oturum-guvenligi' ),
			'qr-masa-oturum-guvenligi'
		);
	}
);

qrms_test(
	'modül dosyaları yokken yükleme sessizce boş döner',
	function () {
		// Yalnızca modules/ altında henüz paketlenmemiş slug'lar; paketlenmiş
		// modüllerin gerçek dosyaları stub ortamında yüklenemez.
		update_option( 'qrms_active_modules', qrms_paketlenmemis_moduller() );

		qrms_assert_same( array(), QRMS_Module_Loader::load_modules(), 'yüklenen modül olmamalı' );
	}
);

qrms_test(
	'aktif modülün dosyası varsa require edilir ve init fonksiyonu çağrılır',
	function () {
		// qr-calisma-saatleri stub ortamında yüklenebilir (is_admin false iken
		// yalnızca kısa kod ve veri katmanı bağlanır).
		update_option( 'qrms_active_modules', array( 'qr-calisma-saatleri' ) );

		$loaded = QRMS_Module_Loader::load_modules();

		qrms_assert_same( array( 'qr-calisma-saatleri' ), $loaded, 'saatler yüklenmeli' );
		qrms_assert_true( function_exists( 'qrms_module_qr_calisma_saatleri_init' ), 'init tanımlı' );
		qrms_assert_true( isset( $GLOBALS['qrms_test']['shortcodes']['qr_calisma_saatleri'] ), 'kısa kod kayıtlı' );
	}
);

qrms_test(
	'aktif olmayan modülün dosyası olsa bile yüklenmez',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-calisma-saatleri' ) );

		$loaded = QRMS_Module_Loader::load_modules();

		qrms_assert_same( array( 'qr-calisma-saatleri' ), $loaded, 'yalnızca aktif slug' );
		qrms_assert_false( in_array( 'restoran-menu', $loaded, true ), 'pasif dosya yüklenmemeli' );
		qrms_assert_true( file_exists( QRMS_Module_Loader::get_module_file( 'restoran-menu' ) ), 'pasif dosya diskte durur' );
	}
);

/* ---------------------------------------------------------------------------
 * 6. Tek seviyeli sol menü ve ortak hub bileşeni
 *
 * Sol menüde yalnızca modül adları durur; modül alt sayfaları GERÇEK sayfa
 * olarak kaydolur ama menüde boyanmaz. Burada test edilenler bu iki işin
 * çekirdekteki karşılığı: beyaz liste, gizleme, alt sayfa kayıt defteri,
 * menü vurgusu ve hub çıktısı.
 * ------------------------------------------------------------------------ */

echo "\nTek seviyeli sol menü\n";

/**
 * Alt menü satırı üretir ($submenu dizisindeki biçimle aynı).
 *
 * @param string $label Görünen etiket.
 * @param string $slug  Menü slug'ı / dosya adresi.
 * @return array
 */
function qrms_submenu_satiri( $label, $slug ) {
	return array( $label, 'manage_options', $slug );
}

/**
 * Satır listesinden slug'ları çıkarır.
 *
 * @param array $rows Satırlar.
 * @return string[]
 */
function qrms_submenu_sluglari( array $rows ) {
	return array_values(
		array_map(
			function ( $row ) {
				return $row[2];
			},
			$rows
		)
	);
}

/**
 * Gerçek dünyadaki dizilim: çekirdeğin _add_post_type_submenus() kancası ürün
 * listesi satırını QRMS_Admin::register_menu()'den ÖNCE ekler, modüllerin
 * kendi ekranları ise en sona düşer.
 *
 * @return array
 */
function qrms_submenu_ham_liste() {
	return array(
		qrms_submenu_satiri( 'Menü Ürünleri', 'edit.php?post_type=rma_menu_item' ),
		qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
		qrms_submenu_satiri( 'Restoran Menü', QRMS_Admin::get_module_page_slug( 'restoran-menu' ) ),
		qrms_submenu_satiri( 'QR Masa', QRMS_Admin::get_module_page_slug( 'qr-masa' ) ),
		qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
		qrms_submenu_satiri( 'Görünüm', 'qrms-rm-gorunum' ),
		qrms_submenu_satiri( 'Öne Çıkanlar', 'qrms-rm-one-cikanlar' ),
		qrms_submenu_satiri( 'Ürün Vitrini', 'qrms-rm-vitrin' ),
		qrms_submenu_satiri( 'Diğer Ayarlar', 'qrms-rm-diger' ),
		qrms_submenu_satiri( 'Kurulum', 'qrms-wizard' ),
	);
}

qrms_test(
	'beyaz liste Genel Bakış + aktif modüller + Genel Ayarlar\'dan ibarettir',
	function () {
		update_option( 'qrms_active_modules', array( 'restoran-menu', 'qr-masa' ) );

		qrms_assert_same(
			array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
				QRMS_Admin::get_module_page_slug( 'qr-masa' ),
				QRMS_Admin::SETTINGS_SLUG,
			),
			QRMS_Admin::get_menu_row_slugs(),
			'tek seviyeli menü'
		);
	}
);

qrms_test(
	'aktif olmayan modülün satırı beyaz listeye girmez',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-masa' ) );

		qrms_assert_false(
			in_array( QRMS_Admin::get_module_page_slug( 'restoran-menu' ), QRMS_Admin::get_menu_row_slugs(), true ),
			'pasif modül'
		);
	}
);

qrms_test(
	'beyaz liste dışındaki her satır gizlenecekler listesine düşer',
	function () {
		$keep = array(
			QRMS_Admin::MENU_SLUG,
			QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
			QRMS_Admin::get_module_page_slug( 'qr-masa' ),
			QRMS_Admin::SETTINGS_SLUG,
		);

		qrms_assert_same(
			array(
				// Çekirdeğin CPT'den ürettiği satır da kapsanır: modül onun
				// için ayrı bir gizleme kodu yazmaz.
				'edit.php?post_type=rma_menu_item',
				'qrms-rm-gorunum',
				'qrms-rm-one-cikanlar',
				'qrms-rm-vitrin',
				'qrms-rm-diger',
				'qrms-wizard',
			),
			QRMS_Admin::collect_hidden_rows( qrms_submenu_ham_liste(), $keep ),
			'gizlenecek satırlar'
		);
	}
);

qrms_test(
	'boş ve tekrar eden slug\'lar gizlenecekler listesini bozmaz',
	function () {
		$rows = array(
			array( 'Etiket', 'manage_options' ),
			qrms_submenu_satiri( 'Görünüm', 'qrms-rm-gorunum' ),
			qrms_submenu_satiri( 'Görünüm (tekrar)', 'qrms-rm-gorunum' ),
		);

		qrms_assert_same(
			array( 'qrms-rm-gorunum' ),
			QRMS_Admin::collect_hidden_rows( $rows, array() ),
			'tek kayıt'
		);
	}
);

qrms_test(
	'gizleme sonrası menüde yalnızca tek seviyeli liste kalır',
	function () {
		update_option( 'qrms_active_modules', array( 'restoran-menu', 'qr-masa' ) );

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = qrms_submenu_ham_liste();

		QRMS_Admin::hide_module_subpages();

		qrms_assert_same(
			array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
				QRMS_Admin::get_module_page_slug( 'qr-masa' ),
				QRMS_Admin::SETTINGS_SLUG,
			),
			qrms_submenu_sluglari( $GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] ),
			'kalan satırlar'
		);
	}
);

qrms_test(
	'menü hiç kurulmamışsa gizleme sessizce çıkar',
	function () {
		unset( $GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] );

		QRMS_Admin::hide_module_subpages();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['removed'], 'hiçbir satır kaldırılmadı' );
	}
);

/* --- Gruplama (kategori başlıkları + renkler) ---------------------------- */

qrms_test(
	'satırlar grup sırasına girer, gruba yazılmayanlar sona düşer',
	function () {
		$rows = array(
			qrms_submenu_satiri( 'Kısa Kodlar', QRMS_Admin::SHORTCODES_SLUG ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
			qrms_submenu_satiri( 'Bilinmeyen', 'qrms-yeni-satir' ),
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'Restoran Menü', QRMS_Admin::get_module_page_slug( 'restoran-menu' ) ),
		);

		$sirali = QRMS_Admin::build_menu_rows( $rows, QRMS_Admin::get_menu_groups() );

		qrms_assert_same(
			array(
				QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
				QRMS_Admin::get_module_page_slug( 'qr-analiz' ),
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::SHORTCODES_SLUG,
				QRMS_Admin::SETTINGS_SLUG,
				'qrms-yeni-satir',
			),
			qrms_submenu_sluglari( $sirali ),
			'grup sırası'
		);
	}
);

qrms_test(
	'her satır grup sınıfını ve kategori ikonunu taşır',
	function () {
		$rows = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
			qrms_submenu_satiri( 'Bilinmeyen', 'qrms-yeni-satir' ),
		);

		$sirali = QRMS_Admin::build_menu_rows( $rows, QRMS_Admin::get_menu_groups() );

		// WordPress alt menü dizisinin 4. dizinini <li> ve <a>'nın class'ına
		// geçirir; JavaScript grup başlıklarını bu sınıftan bulur.
		qrms_assert_same( 'qrms-menu-item qrms-mg-araclar', $sirali[0][4], 'grup sınıfı' );
		qrms_assert_contains( 'dashicons-chart-bar', $sirali[0][0], 'modül ikonu' );
		qrms_assert_contains( '<span class="qrms-menu-label">İstatistikler</span>', $sirali[0][0], 'etiket korunur' );

		// Gruplanmayan satıra dokunulmaz.
		qrms_assert_same( 'Bilinmeyen', $sirali[1][0], 'etiket olduğu gibi' );
		qrms_assert_false( isset( $sirali[1][4] ), 'sınıf eklenmez' );

		// Rozet taşıyan etiket (qrms_module_menu_label) bozulmadan içeride kalır.
		$rozetli = QRMS_Admin::build_menu_rows(
			array( qrms_submenu_satiri( 'Yorum <span class="update-plugins">3</span>', QRMS_Admin::get_module_page_slug( 'yorum-feedback' ) ) ),
			QRMS_Admin::get_menu_groups()
		);

		qrms_assert_contains( '<span class="update-plugins">3</span>', $rozetli[0][0], 'rozet korunur' );
	}
);

qrms_test(
	'menüdeki her satır bir ve yalnız bir gruptadır',
	function () {
		// Emniyet kemeri: yeni bir modül eklenip gruplamaya yazılmayı unutursa
		// satır menüden DÜŞMEZ ama sona kayar; bu test o durumu erken yakalar.
		update_option( 'qrms_active_modules', qrms_all_modules() );
		QRMS_Shortcodes::register( 'restoran-menu', array( array( 'tag' => 'restaurant_menu', 'title' => 'Menü' ) ) );

		$gruplu = array();

		foreach ( QRMS_Admin::get_menu_groups() as $grup ) {
			foreach ( $grup['items'] as $slug ) {
				$gruplu[] = $slug;
			}
		}

		qrms_assert_same( array_unique( $gruplu ), $gruplu, 'aynı satır iki grupta olamaz' );
		qrms_assert_same(
			array(),
			array_values( array_diff( QRMS_Admin::get_menu_row_slugs(), $gruplu ) ),
			'gruplanmamış satır yok'
		);
		qrms_assert_same(
			array(),
			array_values( array_diff( $gruplu, QRMS_Admin::get_menu_row_slugs() ) ),
			'gruplarda menüde olmayan satır yok'
		);
	}
);

qrms_test(
	'gruplama admin_head\'de, gizlemeden SONRA çalışır',
	function () {
		QRMS_Admin::init();

		$kancalar   = $GLOBALS['qrms_test']['actions']['admin_head'];
		$oncelikler = $GLOBALS['qrms_test']['priorities']['admin_head'];

		qrms_assert_same( 2, count( $kancalar ), 'gizleme + gruplama' );
		qrms_assert_same( array( 'QRMS_Admin', 'hide_module_subpages' ), $kancalar[0], 'önce gizleme' );
		qrms_assert_same( array( 'QRMS_Admin', 'group_menu_rows' ), $kancalar[1], 'sonra gruplama' );
		qrms_assert_true( $oncelikler[0] < $oncelikler[1], 'gizleme daha erken önceliktedir' );
	}
);

qrms_test(
	'gruplama menü kurulmamışken sessizce çıkar',
	function () {
		unset( $GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] );

		QRMS_Admin::group_menu_rows();

		qrms_assert_false( isset( $GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] ), 'menü kurulmadı' );
	}
);

qrms_test(
	'grup renkleri CSS değişkenine iner, hex olmayan değer sızmaz',
	function () {
		$css = QRMS_Admin::build_menu_accent_css( QRMS_Admin::get_menu_groups() );

		qrms_assert_contains( '#adminmenu .qrms-mg-genel{--qrms-menu-accent:', $css, 'genel grubu' );
		qrms_assert_same( 4, substr_count( $css, '--qrms-menu-accent' ), 'dört grup' );

		// Filtreden geçen bir değer stil dosyasına enjeksiyon yapamaz.
		$kotu = QRMS_Admin::build_menu_accent_css(
			array(
				array(
					'key'    => 'genel',
					'title'  => 'Genel',
					'accent' => 'red;} body{display:none',
					'items'  => array( QRMS_Admin::MENU_SLUG ),
				),
			)
		);

		qrms_assert_same( '', $kotu, 'hex olmayan renk atlanır' );
	}
);

qrms_test(
	'grup paleti üst menünün mavi-mor gradyanıyla çakışmaz',
	function () {
		$renkler = array();

		foreach ( QRMS_Admin::get_menu_groups() as $grup ) {
			qrms_assert_true(
				(bool) preg_match( '/^#[0-9a-f]{6}$/i', $grup['accent'] ),
				$grup['key'] . ' rengi hex'
			);

			$renkler[] = strtolower( $grup['accent'] );
		}

		qrms_assert_same( array_unique( $renkler ), $renkler, 'her grubun rengi ayrı' );
		qrms_assert_same( 4, count( $renkler ), 'dört kategori' );
	}
);

qrms_test(
	'gizleme admin_head\'e bağlıdır — admin_menu\'ye ASLA bağlanmaz',
	function () {
		// Regresyon: satır admin_menu sırasında silinirse WordPress sayfanın
		// üst menüsünü $submenu'de bulamaz, hook adı eşleşmez ve admin.php 403
		// verir. Route çözüldükten sonrasına (admin_head) bağlı kalmalı.
		QRMS_Admin::init();

		$hooks = $GLOBALS['qrms_test']['actions'];

		qrms_assert_true( isset( $hooks['admin_head'] ), 'admin_head kaydı var' );
		qrms_assert_same(
			2,
			count( $hooks['admin_menu'] ),
			'admin_menu\'de yalnızca menü kaydı ve emniyet kemeri olmalı'
		);
	}
);

qrms_test(
	'alt sayfa kaydı modülüne bağlanır ve sayfanın önüne geri bağlantısı koyar',
	function () {
		$callback = QRMS_Admin::register_module_subpage(
			'restoran-menu',
			'qrms-rm-gorunum',
			function () {
				echo '<div class="wrap">içerik</div>';
			}
		);

		qrms_assert_true( QRMS_Admin::is_module_subpage( 'qrms-rm-gorunum' ), 'kayıt defterinde' );
		qrms_assert_false( QRMS_Admin::is_module_subpage( 'qrms-yok' ), 'kayıtsız sayfa' );

		$GLOBALS['title'] = 'Görünüm';

		ob_start();
		call_user_func( $callback );
		$html = ob_get_clean();

		unset( $GLOBALS['title'] );

		qrms_assert_contains( 'qrms-back-link', $html, 'geri bağlantısı' );
		qrms_assert_contains( 'Restoran Menü', $html, 'modül adı' );
		qrms_assert_contains( 'qrms-subpage-current', $html, 'aktif sayfa breadcrumb\'da' );
		qrms_assert_contains( 'Görünüm', $html, 'aktif sayfa adı' );
		qrms_assert_contains( 'page=' . QRMS_Admin::get_module_page_slug( 'restoran-menu' ), $html, 'hub adresi' );
		qrms_assert_contains( 'içerik', $html, 'sayfanın kendi çıktısı' );
	}
);

qrms_test(
	'bilinmeyen modülün alt sayfası kaydedilmez, callback aynen döner',
	function () {
		$callback = 'strlen';

		qrms_assert_same( $callback, QRMS_Admin::register_module_subpage( 'yok-boyle-modul', 'qrms-x', $callback ), 'callback değişmez' );
		qrms_assert_false( QRMS_Admin::is_module_subpage( 'qrms-x' ), 'kayıt yok' );
	}
);

qrms_test(
	'alt sayfadayken sahibi modülün satırı vurgulanır',
	function () {
		QRMS_Admin::register_module_subpage( 'yorum-feedback', 'qrms-yf-odul', 'strlen' );

		$_GET = array( 'page' => 'qrms-yf-odul' );

		qrms_assert_same(
			QRMS_Admin::MENU_SLUG,
			QRMS_Admin::filter_parent_file( 'baska.php' ),
			'üst menü QR Menü üzerinde kalır'
		);
		qrms_assert_same(
			QRMS_Admin::get_module_page_slug( 'yorum-feedback' ),
			QRMS_Admin::filter_submenu_file( 'qrms-yf-odul' ),
			'modül satırı vurgulanır'
		);
	}
);

qrms_test(
	'kayıtsız sayfalarda menü vurgusuna dokunulmaz',
	function () {
		$_GET = array( 'page' => 'baska-eklenti' );

		qrms_assert_same( 'baska.php', QRMS_Admin::filter_parent_file( 'baska.php' ), 'üst menü' );
		qrms_assert_same( 'baska-alt', QRMS_Admin::filter_submenu_file( 'baska-alt' ), 'alt menü' );
	}
);

echo "\nVarlık sürümleri (önbellek kırma)\n";

qrms_test(
	'sürüm eklenti sürümü + dosyanın değişiklik zamanıdır',
	function () {
		$surum = QRMS_Helpers::asset_version( 'assets/css/admin.css' );
		$zaman = filemtime( QRMS_PLUGIN_DIR . 'assets/css/admin.css' );

		qrms_assert_same( QRMS_VERSION . '.' . $zaman, $surum, 'sürüm etiketi' );
		qrms_assert_false( QRMS_VERSION === $surum, 'sabit sürümden farklı' );
	}
);

qrms_test(
	'her dosya sürümünü KENDİ değişiklik zamanından alır',
	function () {
		// Regresyon: tek bir $v değişkenini birden çok dosya için kullanmak,
		// dosyalardan yalnızca biri değiştiğinde diğerinin adresini sabit
		// bırakır ve eski kopya sunulmaya devam ederdi.
		//
		// "İki sürüm birbirinden farklı olmalı" diye bakılmaz: iki dosya aynı
		// saniyede yazıldığında mtime'ları meşru biçimde eşit olabilir ve test
		// dosya sistemi zamanlamasına göre rastgele düşerdi. Asıl kural her
		// sürümün KENDİ dosyasından türemesidir.
		foreach ( array( 'assets/css/admin.css', 'assets/js/admin.js' ) as $yol ) {
			qrms_assert_same(
				QRMS_VERSION . '.' . filemtime( QRMS_PLUGIN_DIR . $yol ),
				QRMS_Helpers::asset_version( $yol ),
				$yol . ' kendi zamanını taşır'
			);
		}
	}
);

qrms_test(
	'okunamayan dosyada eklenti sürümüne düşülür',
	function () {
		qrms_assert_same( QRMS_VERSION, QRMS_Helpers::asset_version( 'assets/css/yok-boyle.css' ), 'geri düşüş' );
		qrms_assert_same( QRMS_VERSION, QRMS_Helpers::asset_version( '' ), 'boş yol' );
	}
);

qrms_test(
	'hub ekranında admin.css önbellek kıran sürümle kuyruğa alınır',
	function () {
		// Regresyon (PR #19 testinde çıktı): admin.css'e .qrms-hub-* kuralları
		// eklendi ama adres "admin.css?ver=1.0.0" olarak sabit kaldığı için
		// tarayıcı/CDN eski kopyayı sunmaya devam etti; hub kartları tamamen
		// stilsiz, tek satıra çökmüş bağlantı metni olarak göründü.
		$_GET = array( 'page' => QRMS_Admin::get_module_page_slug( 'restoran-menu' ) );

		QRMS_Admin::enqueue_assets();

		$bulundu = null;

		foreach ( $GLOBALS['qrms_test']['styles'] as $stil ) {
			if ( 'qrms-admin' === $stil['handle'] ) {
				$bulundu = $stil;
			}
		}

		qrms_assert_true( null !== $bulundu, 'admin.css kuyruğa alındı' );
		qrms_assert_contains( 'assets/css/admin.css', $bulundu['src'], 'kaynak' );
		qrms_assert_same(
			QRMS_Helpers::asset_version( 'assets/css/admin.css' ),
			$bulundu['ver'],
			'sürüm dosyaya göre hesaplanır'
		);
	}
);

qrms_test(
	'modül alt sayfalarında da yüklenir',
	function () {
		// qr-galeri'nin ekranları "qrms" önekini taşımaz; hub stilleri ve geri
		// bağlantısı orada da gerekli.
		QRMS_Admin::register_module_subpage( 'qr-galeri', 'qrmgm-images', 'strlen' );
		$_GET = array( 'page' => 'qrmgm-images' );

		QRMS_Admin::enqueue_assets();

		$handles = array_map(
			function ( $stil ) {
				return $stil['handle'];
			},
			$GLOBALS['qrms_test']['styles']
		);

		qrms_assert_true( in_array( 'qrms-admin', $handles, true ), 'admin.css' );
		qrms_assert_true( in_array( 'dashicons', $handles, true ), 'dashicons' );
	}
);

qrms_test(
	'eklentinin hiçbir varlığı sabit QRMS_VERSION ile kuyruğa alınmaz',
	function () {
		// Sürüklenme koruması: yeni bir wp_enqueue_style/script çağrısı sabit
		// sürümle eklenirse o dosyanın her değişikliği sessizce eski kopyayla
		// sunulur. Kaynak taraması bunu yakalar.
		$hatali = array();

		foreach ( glob( QRMS_PLUGIN_DIR . '{includes,modules}/{,*/,*/*/}*.php', GLOB_BRACE ) as $dosya ) {
			foreach ( file( $dosya ) as $no => $satir ) {
				// Yalnızca sürüm ARGÜMANI olarak geçen kullanımlar; helper'ın
				// kendi gövdesi ve yorumlar sayılmaz.
				if ( preg_match( '/^\s*QRMS_VERSION,?\s*$/', $satir )
					|| preg_match( '/,\s*QRMS_VERSION\s*[,)]/', $satir ) ) {
					$hatali[] = str_replace( QRMS_PLUGIN_DIR, '', $dosya ) . ':' . ( $no + 1 );
				}
			}
		}

		qrms_assert_same( array(), $hatali, 'sabit sürümle kuyruğa alınan varlık yok' );
	}
);

echo "\nHub bileşeni\n";

qrms_test(
	'hub kartları dashicon, başlık, açıklama ve adresle basılır',
	function () {
		ob_start();
		QRMS_Admin::render_hub(
			array(
				'title' => 'Restoran Menü',
				'intro' => 'Ne yapmak istiyorsanız kartına dokunun.',
				'cards' => array(
					array(
						'url'   => admin_url( 'admin.php?page=qrms-rm-gorunum' ),
						'title' => 'Görünüm',
						'desc'  => 'Menünüzün renkleri ve yazı tipleri.',
						'icon'  => 'dashicons-art',
					),
				),
			)
		);
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'kart ızgarası' );
		qrms_assert_contains( 'dashicons dashicons-art', $html, 'dashicon sınıfı' );
		qrms_assert_contains( 'Görünüm', $html, 'kart başlığı' );
		qrms_assert_contains( 'Menünüzün renkleri', $html, 'kart açıklaması' );
		qrms_assert_contains( 'page=qrms-rm-gorunum', $html, 'kart adresi' );
		qrms_assert_contains( 'Ne yapmak istiyorsanız', $html, 'giriş metni' );
	}
);

qrms_test(
	'ikonu verilmeyen kart yine de bir dashicon alır',
	function () {
		// Regresyon: ikon anahtarı unutulunca kart "dashicons" sınıfını tek
		// başına alır ve kutu karakteri gibi boş bir alan bırakırdı.
		ob_start();
		QRMS_Admin::render_hub( array( 'cards' => array( array( 'url' => '#', 'title' => 'X' ) ) ) );
		$html = ob_get_clean();

		qrms_assert_contains( 'dashicons dashicons-admin-generic', $html, 'varsayılan ikon' );
	}
);

qrms_test(
	'rozet, uyarı, özet kutusu ve vurgu rengi yalnızca verildiklerinde basılır',
	function () {
		ob_start();
		QRMS_Admin::render_hub( array( 'title' => 'Boş', 'cards' => array() ) );
		$sade = ob_get_clean();

		qrms_assert_false( false !== strpos( $sade, 'qrms-hub-badge' ), 'rozet yok' );
		qrms_assert_false( false !== strpos( $sade, 'qrms-hub-stats' ), 'özet yok' );
		qrms_assert_false( false !== strpos( $sade, '--qrms-hub-accent' ), 'vurgu rengi yok' );

		ob_start();
		QRMS_Admin::render_hub(
			array(
				'accent' => '#c9a84c',
				'notice' => '<div class="notice notice-error"><p>Tablo yok.</p></div>',
				'stats'  => array( array( 'label' => 'Onay Bekleyen', 'value' => 3, 'url' => admin_url( 'admin.php?page=x' ) ) ),
				'cards'  => array( array( 'url' => '#', 'title' => 'Formlar', 'badge' => '2 yeni' ) ),
			)
		);
		$dolu = ob_get_clean();

		qrms_assert_contains( '--qrms-hub-accent:#c9a84c', $dolu, 'modül vurgu rengi' );
		qrms_assert_contains( 'Tablo yok.', $dolu, 'uyarı' );
		qrms_assert_contains( 'Onay Bekleyen', $dolu, 'özet etiketi' );
		qrms_assert_contains( '2 yeni', $dolu, 'kart rozeti' );
		qrms_assert_contains( 'class="qrms-stat-value"', $dolu, 'linkli özet ortak class' );

		ob_start();
		QRMS_Admin::render_hub(
			array(
				'stats' => array( array( 'label' => 'Eksik Ürün (Tükendi)', 'value' => 0 ) ),
				'cards' => array(),
			)
		);
		$urlsiz = ob_get_clean();
		qrms_assert_contains( '<span class="qrms-stat-value">', $urlsiz, 'linksiz özet ortak class' );
	}
);

qrms_test(
	'hub çıktısı kaçış uygular',
	function () {
		ob_start();
		QRMS_Admin::render_hub(
			array(
				'title' => '<script>x</script>',
				'cards' => array( array( 'url' => '#', 'title' => '<b>kalın</b>', 'desc' => '<i>eğik</i>' ) ),
			)
		);
		$html = ob_get_clean();

		qrms_assert_false( false !== strpos( $html, '<script>' ), 'başlık kaçırıldı' );
		qrms_assert_false( false !== strpos( $html, '<b>kalın</b>' ), 'kart başlığı kaçırıldı' );
		qrms_assert_false( false !== strpos( $html, '<i>eğik</i>' ), 'açıklama kaçırıldı' );
	}
);

qrms_test(
	'card_groups verilince kartlar başlıklı bölümlere ayrılır',
	function () {
		ob_start();
		QRMS_Admin::render_hub(
			array(
				'title'       => 'Restoran Menü',
				'card_groups' => array(
					array(
						'title' => 'Ürünler',
						'cards' => array(
							array( 'url' => '#', 'title' => 'Ürünlerim' ),
							array( 'url' => '#', 'title' => 'Ürün Ekle' ),
						),
					),
					array(
						'title' => 'Görünüm',
						'cards' => array(
							array( 'url' => '#', 'title' => 'Menü Görünümü' ),
						),
					),
				),
			)
		);
		$html = ob_get_clean();

		qrms_assert_contains( '<h2 class="qrms-hub-group-title">', $html, 'grup başlığı elementi' );
		qrms_assert_contains( 'qrms-hub-group-divider', $html, 'dekoratif ayırıcı' );

		// İki grup başlığı da basıldı, DOĞRU sırada ve her biri kendi
		// kartlarından ÖNCE geliyor (başlık -> o gruba ait ızgara).
		$grup1_baslik = strpos( $html, 'Ürünler' );
		$grup1_kart   = strpos( $html, 'Ürünlerim' );
		$grup2_baslik = strpos( $html, 'Görünüm' );
		$grup2_kart   = strpos( $html, 'Menü Görünümü' );

		qrms_assert_true( false !== $grup1_baslik && false !== $grup1_kart && false !== $grup2_baslik && false !== $grup2_kart, 'tüm başlık ve kartlar basıldı' );
		qrms_assert_true( $grup1_baslik < $grup1_kart, 'Ürünler başlığı kendi kartlarından önce' );
		qrms_assert_true( $grup1_kart < $grup2_baslik, 'birinci grup ikinciden önce biter' );
		qrms_assert_true( $grup2_baslik < $grup2_kart, 'Görünüm başlığı kendi kartından önce' );

		// card_groups verilince TAM OLARAK iki ızgara basılır (grup başına
		// bir tane) — eski düz tek-ızgara yolu devreye girmez.
		qrms_assert_same( 2, preg_match_all( '/class="qrms-hub-grid(?: |")/', $html ), 'grup başına bir ızgara' );
		qrms_assert_false( false !== strpos( $html, 'qrms-hub-grid--has-partial-row' ), 'eksik satır modifier yok' );
	}
);

qrms_test(
	'card_groups verilmeyince eski düz tek ızgara davranışı bozulmaz',
	function () {
		// Diğer modüllerin hepsi hâlâ düz `cards` ile çağırıyor (bkz.
		// yorum-feedback, qr-masa-oturum-guvenligi, qr-galeri, qr-acilis-ekrani);
		// card_groups eklenmeden önceki davranışları AYNI kalmalı.
		ob_start();
		QRMS_Admin::render_hub(
			array(
				'cards' => array( array( 'url' => '#', 'title' => 'Tek Kart' ) ),
			)
		);
		$html = ob_get_clean();

		qrms_assert_contains( 'Tek Kart', $html, 'düz kart basılır' );
		qrms_assert_false( false !== strpos( $html, 'qrms-hub-grid--has-partial-row' ), 'eksik satır modifier yok' );
		qrms_assert_false( false !== strpos( $html, 'qrms-hub-group-title' ), 'grup başlığı yok' );
	}
);

qrms_test(
	'eksik satırda partial-row class eklenmez — grid leftover sola yaslanır',
	function () {
		$kart = static function ( $ad ) {
			return array( 'url' => '#', 'title' => $ad );
		};

		ob_start();
		QRMS_Admin::render_hub(
			array(
				'card_groups' => array(
					array(
						'title' => 'Ürünler',
						'cards' => array( $kart( 'Ürünlerim' ), $kart( 'Ürün Ekle' ), $kart( 'Stok Durumu' ), $kart( 'Fiyat Kampanyaları' ) ),
					),
					array(
						'title' => 'Ürün Materyalleri',
						'cards' => array( $kart( 'Kategoriler' ), $kart( 'Alerjenler' ), $kart( 'Malzemeler' ) ),
					),
				),
			)
		);
		$html = ob_get_clean();

		preg_match_all( '/<div class="([^"]*qrms-hub-grid[^"]*)">/', $html, $m );
		qrms_assert_same( 2, count( $m[1] ), 'iki ızgara' );
		qrms_assert_false( false !== strpos( $m[1][0], 'qrms-hub-grid--has-partial-row' ), '4 kartlı Ürünler — modifier yok' );
		qrms_assert_false( false !== strpos( $m[1][1], 'qrms-hub-grid--has-partial-row' ), '3 kartlı Materyaller — modifier yok' );
	}
);

qrms_test(
	'restoran menü hub grup başlıkları doğru sırada ve dark gold paletiyle tanımlı',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		$hub_fn = substr( $php, strpos( $php, 'function get_hub_cards' ), strpos( $php, 'function get_legacy_page_map' ) - strpos( $php, 'function get_hub_cards' ) );
		$sira   = array(
			"'title' => 'Ürünler'",
			"'title' => 'Ürünlerim'",
			"'title' => 'Ürün Ekle'",
			'qrms-rm-urunum-yok',
			'qrms-rm-kampanya',
			"'title' => 'Ürün Materyalleri'",
			"'title' => 'Kategoriler'",
			"'title' => 'Alerjenler'",
			"'title' => 'Malzemeler'",
			"'title' => 'Görünüm'",
			'qrms-rm-gorunum',
			'qrms-rm-one-cikanlar',
			'qrms-rm-vitrin',
			'qrms-rm-diger',
		);
		$onceki = -1;
		foreach ( $sira as $parca ) {
			$pos = strpos( $hub_fn, $parca );
			qrms_assert_true( false !== $pos, $parca . ' hub kartında' );
			qrms_assert_true( $pos > $onceki, $parca . ' sırası' );
			$onceki = $pos;
		}

		qrms_assert_contains( "'card_groups' => \$this->get_hub_cards()", $php, 'hub gruplu kartları alır' );

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/hub.css' );
		qrms_assert_contains( '.rma-hub .qrms-hub-group-title', $css, 'grup başlığı kuralı' );
		qrms_assert_contains( "font-family: 'Playfair Display', Georgia, serif;", $css, 'serif marka fontu' );
		qrms_assert_contains( 'color: #1d2327;', $css, 'kart başlığıyla aynı ink rengi' );
		qrms_assert_contains( 'rgba(201, 168, 76,', $css, 'ayırıcıda muted gold tonu' );
		qrms_assert_contains( '.rma-hub .qrms-hub-group-title:first-of-type', $css, 'ilk grupta fazla boşluk yok' );
	}
);

qrms_test(
	'hub kartlarında emoji ikon kullanılmaz',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/admin.css' );

		qrms_assert_contains( '.qrms-hub-grid', $css, 'kart ızgarası kuralı' );
		qrms_assert_contains( 'display: grid', $css, 'ızgara CSS Grid — leftover sola yaslanır' );
		qrms_assert_contains( 'grid-template-columns: repeat(3, minmax(0, 1fr))', $css, 'masaüstü üç sütun' );
		qrms_assert_false( false !== strpos( $css, 'justify-content: center' ), 'eksik satır ortalanmaz' );
		qrms_assert_false( false !== strpos( $css, 'display: flex !important' ), 'kart ızgarası flex değil' );
		qrms_assert_contains( 'max-width: 1200px', $css, 'tablet kırılımı' );
		qrms_assert_contains( 'max-width: 782px', $css, 'WP admin mobil kırılımı' );
		qrms_assert_contains( 'pointer: coarse', $css, 'dokunmatik hedef büyütmesi' );
	}
);

echo "\nGenel Bakış — kategoriler\n";

qrms_test(
	'her modül kategorilerde tam olarak bir kez geçer',
	function () {
		$gecenler = array();

		foreach ( QRMS_Admin::get_overview_groups() as $grup ) {
			foreach ( $grup['items'] as $kalem ) {
				if ( QRMS_Helpers::is_valid_module( $kalem ) ) {
					$gecenler[] = $kalem;
				}
			}
		}

		qrms_assert_same( array(), array_diff( QRMS_Helpers::MODULE_SLUGS, $gecenler ), 'kategorisiz modül yok' );
		qrms_assert_same( count( $gecenler ), count( array_unique( $gecenler ) ), 'modül iki kategoride birden değil' );

		// Çekirdek kalemler modül slug'ıyla çakışmamalı.
		qrms_assert_false( QRMS_Helpers::is_valid_module( QRMS_Admin::OVERVIEW_CORE_SHORTCODES ), 'kısa kodlar modül değil' );
		qrms_assert_false( QRMS_Helpers::is_valid_module( QRMS_Admin::OVERVIEW_CORE_SETTINGS ), 'ayarlar modül değil' );
	}
);

qrms_test(
	'her modülün kart ikonu ve açıklaması tanımlı',
	function () {
		foreach ( QRMS_Helpers::MODULE_SLUGS as $slug ) {
			qrms_assert_contains( 'dashicons-', QRMS_Helpers::get_module_icon( $slug ), $slug . ' ikonu' );
			qrms_assert_true( '' !== QRMS_Helpers::get_module_description( $slug ), $slug . ' açıklaması' );
		}

		// Emoji değil, dashicons: hub bileşeniyle aynı kural.
		$json = wp_json_encode( QRMS_Helpers::get_module_meta() );

		qrms_assert_same( 0, preg_match( '/\\\\ud[89ab][0-9a-f]{2}/i', $json ), 'meta tablosunda emoji yok' );
	}
);

qrms_test(
	'kartlar lisans durumunu taşır, pasif modülün adresi olmaz',
	function () {
		$gruplar = QRMS_Admin::build_overview_groups( array( 'restoran-menu' ), false );
		$kartlar = array();

		foreach ( $gruplar as $grup ) {
			foreach ( $grup['cards'] as $kart ) {
				$kartlar[ $kart['title'] ] = $kart;
			}
		}

		qrms_assert_same( 'active', $kartlar['Restoran Menü']['state'], 'aktif modül' );
		qrms_assert_contains( 'page=qrms-module-restoran-menu', $kartlar['Restoran Menü']['url'], 'aktif kartın adresi' );

		qrms_assert_same( 'passive', $kartlar['İstatistikler']['state'], 'pasif modül' );
		qrms_assert_same( '', $kartlar['İstatistikler']['url'], 'pasif kart adres taşımaz' );

		// Çekirdek sayfalar lisansa bağlı değildir.
		qrms_assert_same( 'core', $kartlar['Genel Ayarlar']['state'], 'genel ayarlar her zaman açık' );
		qrms_assert_false( isset( $kartlar['Kısa Kodlar'] ), 'kısa kod yokken kartı da yok' );

		// Kısa kod varsa kart görünür — sol menüdeki satırla aynı koşul.
		$kisa_kodlu = QRMS_Admin::build_overview_groups( array(), true );
		$basliklar  = array();

		foreach ( $kisa_kodlu as $grup ) {
			foreach ( $grup['cards'] as $kart ) {
				$basliklar[] = $kart['title'];
			}
		}

		qrms_assert_true( in_array( 'Kısa Kodlar', $basliklar, true ), 'kısa kod varken kartı da var' );
	}
);

qrms_test(
	'kategori sayacı yalnızca modülleri sayar',
	function () {
		$gruplar = QRMS_Admin::build_overview_groups( array( 'qr-analiz' ), true );
		$araclar = null;
		$genel   = null;

		foreach ( $gruplar as $grup ) {
			if ( 'Araçlar' === $grup['title'] ) {
				$araclar = $grup;
			}
			if ( 'Genel' === $grup['title'] ) {
				$genel = $grup;
			}
		}

		qrms_assert_true( null !== $araclar, 'Araçlar kategorisi bulundu' );
		qrms_assert_true( null !== $genel, 'Genel kategorisi bulundu' );

		// Araçlar'da yedi modül var; yalnızca qr-analiz aktif.
		qrms_assert_same( 7, count( $araclar['cards'] ), 'araç kart sayısı' );
		qrms_assert_same( 7, $araclar['total'], 'sayaçta yalnızca modüller' );
		qrms_assert_same( 1, $araclar['active'], 'aktif modül sayısı' );

		// Genel'de iki çekirdek kart (Kısa Kodlar + Ayarlar); modül yok.
		qrms_assert_same( 2, count( $genel['cards'] ), 'çekirdek kart sayısı' );
		qrms_assert_same( 0, $genel['total'], 'çekirdek kalemler sayıma girmez' );
		qrms_assert_same( 0, $genel['active'], 'aktif modül yok' );
	}
);

qrms_test(
	'Genel Bakış kategorileri kart ızgarasında basar',
	function () {
		update_option( 'qrms_active_modules', array( 'restoran-menu', 'qr-analiz' ) );

		ob_start();
		QRMS_Admin::render_overview();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'kart ızgarası' );
		qrms_assert_contains( 'qrms-overview-group-title', $html, 'kategori başlığı' );
		qrms_assert_contains( 'Menü Yönetimi', $html, 'ilk kategori' );
		qrms_assert_contains( 'Araçlar', $html, 'ikinci kategori' );
		qrms_assert_contains( 'Görünüm &amp; Erişim', $html, 'üçüncü kategori' );
		qrms_assert_contains( 'dashicons dashicons-food', $html, 'modül ikonu' );
		qrms_assert_contains( 'Ürünler, kategoriler', $html, 'kart açıklaması' );
		qrms_assert_contains( 'page=qrms-module-restoran-menu', $html, 'aktif kartın adresi' );
		qrms_assert_contains( '1/2 aktif', $html, 'kategori sayacı' );

		// Aktif kart bağlantı, pasif kart tıklanamaz kutu.
		qrms_assert_contains( 'qrms-overview-card-active', $html, 'aktif kart' );
		qrms_assert_contains( 'qrms-overview-card-passive', $html, 'pasif kart' );
		qrms_assert_contains( 'Pasif', $html, 'pasif etiketi' );
		qrms_assert_false(
			false !== strpos( $html, 'page=qrms-module-qr-galeri' ),
			'pasif modüle bağlantı verilmez'
		);

		// Eski düz liste kalmadı.
		qrms_assert_false( false !== strpos( $html, 'qrms-module-list' ), 'düz liste kaldırıldı' );
	}
);

qrms_test(
	'aktif modül yokken lisans uyarısı çıkar ve modüller pasif görünür',
	function () {
		ob_start();
		QRMS_Admin::render_overview();
		$html = ob_get_clean();

		qrms_assert_contains( 'Lisansı Doğrula', $html, 'lisans butonu' );
		qrms_assert_contains( 'page=qrms-settings', $html, 'ayarlar adresi' );
		qrms_assert_contains( '0/2 aktif', $html, 'sayaç sıfır' );
		qrms_assert_false( false !== strpos( $html, 'qrms-overview-card-active' ), 'aktif kart yok' );
	}
);

qrms_test(
	'Genel Bakış ızgarası dar ekranda tek sütuna düşer',
	function () {
		// Kart görselinin kendisi hub bileşeninden geldiği için kırılım
		// noktaları da ortaktır; burada Genel Bakış'a özgü kurallar aranır.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/admin.css' );

		qrms_assert_contains( '.qrms-overview-group-title', $css, 'kategori başlığı kuralı' );
		qrms_assert_contains( '.qrms-overview-card-passive', $css, 'pasif kart kuralı' );
		qrms_assert_contains( '.qrms-overview-card-passive:hover', $css, 'pasif kartta hover geri alınır' );

		// Izgara grid; telefonda kartlar tek sütuna düşer.
		qrms_assert_contains( 'grid-template-columns: 1fr', $css, 'telefonda tek sütun' );
		qrms_assert_contains( 'max-width: 782px', $css, 'WP admin mobil kırılımı' );
	}
);

qrms_test(
	'Genel Bakış kart ızgarası bölümleri taşırmadan ayırır, alt liste tıklanabilir durur',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/admin.css' );

		// Taşma: height:100% + content-box padding bir sonraki başlığı örterdi.
		qrms_assert_contains( 'box-sizing: border-box', $css, 'hub border-box' );
		qrms_assert_false(
			(bool) preg_match( '/\.qrms-hub-grid\s*>\s*\.qrms-hub-card\s*\{[^}]*height:\s*100%/s', $css ),
			'kart height:100% yok'
		);
		qrms_assert_contains( 'display: flow-root', $css, 'kategori BFC' );
		qrms_assert_contains( 'position: static', $css, 'başlık sticky değil' );
		qrms_assert_false(
			(bool) preg_match( '/\.qrms-overview-group-title\s*\{[^}]*position:\s*sticky/s', $css ),
			'kategori başlığı sticky değil'
		);

		// Alt liste açıklamadan ince çizgiyle ayrılır, hover arka planı var.
		qrms_assert_contains( '.qrms-hub-links', $css, 'alt liste kuralı' );
		qrms_assert_true(
			(bool) preg_match( '/\.qrms-hub-links\s*\{[^}]*border-top:/s', $css ),
			'liste ayırıcı çizgi'
		);
		qrms_assert_contains( 'font-family: dashicons', $css, 'madde işaretçisi dashicon' );
		qrms_assert_contains( 'background: #f0f6fc', $css, 'hover arka plan' );
		qrms_assert_false(
			(bool) preg_match( '/\.qrms-hub-card-has-links\s*\{[^}]*justify-content:\s*space-between/s', $css ),
			'açıklama-liste uçurumu yok'
		);
	}
);

qrms_test(
	'sol menü ve Genel Bakış aynı taksonomiden türer',
	function () {
		$nav   = QRMS_Admin::get_nav_groups();
		$menu  = QRMS_Admin::get_menu_groups();
		$over  = QRMS_Admin::get_overview_groups();

		qrms_assert_same( count( $nav ), count( $menu ), 'menü grup sayısı' );
		qrms_assert_same( count( $nav ), count( $over ), 'genel bakış grup sayısı' );

		$anahtarlar = array();
		foreach ( $nav as $i => $grup ) {
			qrms_assert_same( $grup['key'], $menu[ $i ]['key'], $grup['key'] . ' menü anahtarı' );
			qrms_assert_same( $grup['key'], $over[ $i ]['key'], $grup['key'] . ' bakış anahtarı' );
			qrms_assert_same( $grup['title'], $menu[ $i ]['title'], $grup['key'] . ' menü başlığı' );
			qrms_assert_same( $grup['title'], $over[ $i ]['title'], $grup['key'] . ' bakış başlığı' );
			qrms_assert_same( $grup['accent'], $menu[ $i ]['accent'], $grup['key'] . ' rengi' );
			$anahtarlar[] = $grup['key'];
		}

		qrms_assert_false( in_array( 'gelismis', $anahtarlar, true ), 'Gelişmiş grubu kalktı' );
		qrms_assert_same(
			array( 'menu-yonetimi', 'araclar', 'gorunum', 'genel' ),
			$anahtarlar,
			'grup sırası'
		);
	}
);

qrms_test(
	'Güvenlik Ayarı Araçlar\'da QR Kod Oluştur\'un hemen altındadır; Kısa Kodlar Genel\'dedir',
	function () {
		$araclar = null;
		$genel   = null;

		foreach ( QRMS_Admin::get_nav_groups() as $grup ) {
			if ( 'araclar' === $grup['key'] ) {
				$araclar = $grup['items'];
			}
			if ( 'genel' === $grup['key'] ) {
				$genel = $grup['items'];
			}
		}

		qrms_assert_true( is_array( $araclar ), 'Araçlar var' );
		$masa = array_search( 'qr-masa', $araclar, true );
		$guv  = array_search( 'qr-masa-oturum-guvenligi', $araclar, true );
		qrms_assert_true( false !== $masa && false !== $guv, 'her iki kalem Araçlar\'da' );
		qrms_assert_same( $masa + 1, $guv, 'Güvenlik, QR Kod Oluştur\'un hemen altında' );

		qrms_assert_true( in_array( QRMS_Admin::OVERVIEW_CORE_SHORTCODES, $genel, true ), 'Kısa Kodlar Genel\'de' );
		qrms_assert_true( in_array( QRMS_Admin::SETTINGS_SLUG, QRMS_Admin::get_menu_groups()[3]['items'], true ), 'Ayarlar menüde Genel\'de' );
		qrms_assert_true( in_array( QRMS_Admin::SHORTCODES_SLUG, QRMS_Admin::get_menu_groups()[3]['items'], true ), 'Kısa Kodlar menüde Genel\'de' );
	}
);

qrms_test(
	'analiz şeridi değeri 0 olan dikkat kutusunu basmaz, pasif modül kutusunu basmaz',
	function () {
		$bos = QRMS_Admin::get_overview_stats( array() );
		qrms_assert_same( array(), $bos, 'aktif modül yokken şerit yok' );

		$sadece_ceviri = QRMS_Admin::get_overview_stats( array( 'qr-ceviri' ) );
		qrms_assert_same( array(), $sadece_ceviri, 'şeritte yeri olmayan modül kutu üretmez' );
	}
);

qrms_test(
	'hub satırlı özet kutuları iki başlık altında basılır, boş satır düşer',
	function () {
		ob_start();
		QRMS_Admin::render_hub(
			array(
				'stats' => array(
					array(
						'title' => 'Dikkat gerektirenler',
						'class' => 'is-attention',
						'items' => array(
							array( 'label' => 'Tükendi Ürün', 'value' => 3, 'url' => 'https://example.test/tukendi', 'accent' => '#d63638' ),
						),
					),
					array(
						'title' => 'Durum',
						'items' => array(),
					),
				),
				'cards' => array(),
			)
		);
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-stats-stacked', $html, 'satırlı şerit' );
		qrms_assert_contains( 'Dikkat gerektirenler', $html, 'dikkat başlığı' );
		qrms_assert_contains( 'Tükendi Ürün', $html, 'dikkat kutusu' );
		qrms_assert_contains( 'href="https://example.test/tukendi"', $html, 'kutu tıklanabilir' );
		qrms_assert_false( false !== strpos( $html, 'Durum' ), 'boş durum satırı basılmaz' );
	}
);

qrms_test(
	'hub kartı 5\'ten uzun alt listeyi keser ve +N daha bağlar',
	function () {
		$kart = array(
			'url'   => 'https://example.test/hub',
			'title' => 'Restoran Menü',
			'desc'  => 'Açıklama',
			'icon'  => 'dashicons-food',
			'state' => 'active',
			'links' => array(
				array( 'url' => 'https://example.test/a', 'title' => 'Ürünlerim' ),
				array( 'url' => 'https://example.test/b', 'title' => 'Ürün Ekle' ),
			),
			'more'  => array( 'url' => 'https://example.test/hub', 'label' => '+7 daha' ),
		);

		ob_start();
		QRMS_Admin::render_hub( array( 'cards' => array( $kart ) ) );
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-card-has-links', $html, 'alt liste sarmalayıcısı' );
		qrms_assert_contains( 'qrms-hub-links', $html, 'alt liste' );
		qrms_assert_contains( 'Ürünlerim', $html, 'alt madde' );
		qrms_assert_contains( '+7 daha', $html, 'daha bağlantısı' );
		qrms_assert_contains( 'qrms-hub-links-more', $html, 'daha sınıfı' );
		// İç içe <a> yok: kart div, gövde ayrı bağlantı.
		qrms_assert_contains( '<div class="qrms-hub-card', $html, 'kart blok eleman' );
		qrms_assert_contains( 'qrms-hub-card-main', $html, 'gövde bağlantısı' );
	}
);

qrms_test(
	'alt liste 5\'ten uzunsa kartta ilk 5 ve +N daha durur',
	function () {
		add_filter(
			'qrms_module_overview_links',
			function ( $links, $slug ) {
				if ( 'restoran-menu' !== $slug ) {
					return $links;
				}
				$out = array();
				for ( $i = 1; $i <= 7; $i++ ) {
					$out[] = array( 'url' => 'https://example.test/' . $i, 'title' => 'Ekran ' . $i );
				}
				return $out;
			},
			10,
			2
		);

		$gruplar = QRMS_Admin::build_overview_groups( array( 'restoran-menu' ), false );
		$kart    = null;
		foreach ( $gruplar as $grup ) {
			foreach ( $grup['cards'] as $aday ) {
				if ( 'Restoran Menü' === $aday['title'] ) {
					$kart = $aday;
				}
			}
		}

		qrms_assert_true( is_array( $kart ), 'kart bulundu' );
		qrms_assert_same( 5, count( $kart['links'] ), 'ilk 5' );
		qrms_assert_same( 'Ekran 1', $kart['links'][0]['title'], 'ilk madde' );
		qrms_assert_same( '+2 daha', $kart['more']['label'], 'kalan sayısı' );
		qrms_assert_contains( 'page=qrms-module-restoran-menu', $kart['more']['url'], 'daha hub\'a gider' );
	}
);

qrms_test(
	'üst menü Genel Bakış\'tayken gruplar açık gelsin diye openAll basılır',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'assets/js/admin-menu.js' );
		qrms_assert_contains( 'AYAR.openAll', $js, 'openAll okunur' );
		qrms_assert_contains( '! AYAR.openAll && ! acikSayfa', $js, 'Genel Bakış\'ta hepsi açık' );
		qrms_assert_contains( 'AYAR.overviewUrl', $js, 'üst menü Genel Bakış\'a gider' );
		qrms_assert_contains( 'toplevel_page_qrms-overview', $js, 'üst menü seçicisi' );
	}
);

qrms_test(
	'üst menü varlığı Genel Bakış adresini ve openAll bayrağını taşır',
	function () {
		$_GET = array( 'page' => QRMS_Admin::MENU_SLUG );
		QRMS_Admin::enqueue_menu_assets();

		$lokal = null;
		foreach ( $GLOBALS['qrms_test']['localized'] as $kayit ) {
			if ( 'qrmsMenu' === $kayit['name'] ) {
				$lokal = $kayit['data'];
			}
		}

		qrms_assert_true( is_array( $lokal ), 'qrmsMenu lokalize' );
		qrms_assert_true( ! empty( $lokal['openAll'] ), 'Genel Bakış\'ta openAll' );
		qrms_assert_contains( 'page=' . QRMS_Admin::MENU_SLUG, $lokal['overviewUrl'], 'üst menü adresi' );

		$GLOBALS['qrms_test']['localized'] = array();
		$GLOBALS['qrms_test']['scripts']   = array();
		$GLOBALS['qrms_test']['styles']    = array();
		$_GET                              = array( 'page' => 'qrms-module-restoran-menu' );
		QRMS_Admin::enqueue_menu_assets();

		$lokal = null;
		foreach ( $GLOBALS['qrms_test']['localized'] as $kayit ) {
			if ( 'qrmsMenu' === $kayit['name'] ) {
				$lokal = $kayit['data'];
			}
		}

		qrms_assert_true( is_array( $lokal ), 'modül sayfasında da lokalize' );
		qrms_assert_false( ! empty( $lokal['openAll'] ), 'modül sayfasında openAll kapalı' );
	}
);

qrms_test(
	'Genel grup başlığı diğerleriyle aynı ağırlıkta (gri metin değil, accent border)',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/admin-menu.css' );
		qrms_assert_contains( 'border-left: 3px solid var( --qrms-menu-accent', $css, 'grup başlığında sol şerit' );
		qrms_assert_contains( '.qrms-menu-group-title', $css, 'başlık sınıfı' );
		qrms_assert_contains( 'letter-spacing: 0.07em', $css, 'letter-spacing' );
		qrms_assert_contains( 'font-weight: 600', $css, 'font-weight' );
		qrms_assert_contains( 'color: #c3c4c7', $css, 'başlık metni diğerleriyle aynı gri' );
		// Başlık metni accent rengine bağlanmaz; Genel gri olduğu için kaybolmasın.
		qrms_assert_false(
			(bool) preg_match( '/\.qrms-menu-group-toggle\s*\{[^}]*color:\s*var\(\s*--qrms-menu-accent/s', $css ),
			'başlık metni accent rengi değil'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 6b. Ürün Vitrini — ayar temizliği
 *
 * Sınıf yalnızca tanım içerir (dosya kapsamında kanca kaydetmez), bu yüzden
 * doğrudan require edilebilir. Test edilenler $wpdb'ye dokunmayan saf
 * dönüşümler: yönetim formundan gelen ham girdiyi şemaya uygun değerlere
 * çeviren yol.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-vitrin-db.php';

echo "\nÜrün Vitrini ayarları\n";

qrms_test(
	'sütun ve satır sayısı şemanın sınırlarına kırpılır',
	function () {
		$temiz = RMA_Vitrin_DB::ayarlari_temizle(
			array(
				'title'        => 'Şefin Önerileri',
				'grid_columns' => 99,
				'grid_rows'    => 0,
			)
		);

		qrms_assert_same( RMA_Vitrin_DB::MAX_COLUMNS, $temiz['grid_columns'], 'üst sınır' );
		qrms_assert_same( RMA_Vitrin_DB::MIN_ROWS, $temiz['grid_rows'], 'alt sınır' );
		qrms_assert_same( 'Şefin Önerileri', $temiz['title'], 'başlık korunur' );
	}
);

qrms_test(
	'mobil sütun sayısı kendi sınırlarına kırpılır, masaüstü sütunundan bağımsızdır',
	function () {
		$temiz = RMA_Vitrin_DB::ayarlari_temizle(
			array(
				'grid_columns'   => 6,
				'mobile_columns' => 99,
			)
		);

		qrms_assert_same( RMA_Vitrin_DB::MAX_MOBILE_COLUMNS, $temiz['mobile_columns'], 'üst sınır' );
		qrms_assert_same( 6, $temiz['grid_columns'], 'masaüstü sütunu etkilenmez' );

		$bozuk = RMA_Vitrin_DB::ayarlari_temizle( array( 'mobile_columns' => 'çok' ) );
		qrms_assert_same( 2, $bozuk['mobile_columns'], 'varsayılana düşer' );

		$sifir = RMA_Vitrin_DB::ayarlari_temizle( array( 'mobile_columns' => 0 ) );
		qrms_assert_same( RMA_Vitrin_DB::MIN_MOBILE_COLUMNS, $sifir['mobile_columns'], 'alt sınır' );
	}
);

qrms_test(
	'mobil satır sayısı kendi sınırlarına kırpılır, masaüstü satırından bağımsızdır',
	function () {
		$temiz = RMA_Vitrin_DB::ayarlari_temizle(
			array(
				'grid_rows'   => 3,
				'mobile_rows' => 99,
			)
		);

		qrms_assert_same( RMA_Vitrin_DB::MAX_MOBILE_ROWS, $temiz['mobile_rows'], 'üst sınır' );
		qrms_assert_same( 3, $temiz['grid_rows'], 'masaüstü satırı etkilenmez' );

		$sifir = RMA_Vitrin_DB::ayarlari_temizle( array( 'mobile_rows' => 0 ) );
		qrms_assert_same( RMA_Vitrin_DB::MIN_MOBILE_ROWS, $sifir['mobile_rows'], 'alt sınır' );
	}
);

qrms_test(
	'kart boyutu ayarları (boşluk, min-genişlik, görsel oranı) masaüstü/mobil ayrı sınırlanır',
	function () {
		$temiz = RMA_Vitrin_DB::ayarlari_temizle(
			array(
				'desktop_gap'         => 9999,
				'desktop_card_min'    => 1,
				'desktop_image_ratio' => 1,
				'mobile_gap'          => -5,
				'mobile_card_min'     => 9999,
				'mobile_image_ratio'  => 9999,
			)
		);

		qrms_assert_same( RMA_Vitrin_DB::MAX_GAP, $temiz['desktop_gap'], 'masaüstü boşluk üst sınırı' );
		qrms_assert_same( RMA_Vitrin_DB::MIN_DESKTOP_CARD_MIN, $temiz['desktop_card_min'], 'masaüstü min-genişlik alt sınırı' );
		qrms_assert_same( RMA_Vitrin_DB::MIN_IMAGE_RATIO, $temiz['desktop_image_ratio'], 'masaüstü görsel oranı alt sınırı' );
		qrms_assert_same( RMA_Vitrin_DB::MIN_GAP, $temiz['mobile_gap'], 'mobil boşluk alt sınırı' );
		qrms_assert_same( RMA_Vitrin_DB::MAX_MOBILE_CARD_MIN, $temiz['mobile_card_min'], 'mobil min-genişlik üst sınırı — masaüstünden bağımsız' );
		qrms_assert_same( RMA_Vitrin_DB::MAX_IMAGE_RATIO, $temiz['mobile_image_ratio'], 'mobil görsel oranı üst sınırı' );

		$bozuk = RMA_Vitrin_DB::ayarlari_temizle( array( 'desktop_gap' => 'çok' ) );
		qrms_assert_same( 16, $bozuk['desktop_gap'], 'varsayılana düşer' );
	}
);

qrms_test(
	'yazı tipi ayarları sınırlanır; kalınlık/hizalama/font beyaz listeden geçer',
	function () {
		$temiz = RMA_Vitrin_DB::ayarlari_temizle(
			array(
				'title_size'          => 999,
				'title_size_mobile'   => 999,
				'price_size'          => 1,
				'price_size_mobile'   => 1,
				'title_weight'        => 850,
				'title_align'         => 'justify',
				'title_font'          => 'Comic Sans',
			)
		);

		// Mobil aralık masaüstünden BAĞIMSIZ ve daha dardır: dar kartta
		// büyük ad iki satırı aşıp kırpılır.
		qrms_assert_same( RMA_Vitrin_DB::MAX_FONT_SIZE, $temiz['title_size'], 'masaüstü üst sınır' );
		qrms_assert_same( RMA_Vitrin_DB::MAX_MOBILE_FONT_SIZE, $temiz['title_size_mobile'], 'mobil üst sınır' );
		qrms_assert_same( RMA_Vitrin_DB::MIN_FONT_SIZE, $temiz['price_size'], 'masaüstü alt sınır' );
		qrms_assert_same( RMA_Vitrin_DB::MIN_MOBILE_FONT_SIZE, $temiz['price_size_mobile'], 'mobil alt sınır' );

		// Beyaz liste dışı değerler CSS'e yazılmadan önce varsayılana düşer.
		qrms_assert_same( 600, $temiz['title_weight'], 'kalınlık varsayılana düşer' );
		qrms_assert_same( 'left', $temiz['title_align'], 'hizalama varsayılana düşer' );
		qrms_assert_same( '', $temiz['title_font'], 'bilinmeyen font tema fontuna düşer' );

		$gecerli = RMA_Vitrin_DB::ayarlari_temizle(
			array(
				'title_weight' => 700,
				'title_align'  => 'center',
				'title_font'   => 'Playfair Display',
				'price_align'  => 'right',
			)
		);

		qrms_assert_same( 700, $gecerli['title_weight'], 'geçerli kalınlık korunur' );
		qrms_assert_same( 'center', $gecerli['title_align'], 'geçerli hizalama korunur' );
		qrms_assert_same( 'Playfair Display', $gecerli['title_font'], 'geçerli font korunur' );
		qrms_assert_same( 'flex-end', RMA_Vitrin_DB::hizalama_justify( $gecerli['price_align'] ), 'fiyat hizası flex karşılığına çevrilir' );

		// Varsayılanlar ayar eklenmeden önceki sabit .95rem ≈ 15px görünümü
		// korur: eski vitrinler güncellemeyle birlikte değişmez.
		$vars = RMA_Vitrin_DB::varsayilanlar();
		qrms_assert_same( 15, $vars['title_size'], 'masaüstü varsayılanı eski görünümle aynı' );
		qrms_assert_same( 700, $vars['price_weight'], 'fiyat kalınlığı eski görünümle aynı' );
	}
);

qrms_test(
	'yazı tipi listesi tek kaynaktır; yalnızca Google fontları istek doğurur',
	function () {
		$tipler = RMA_Vitrin_DB::yazi_tipleri();

		// Tema fontu ve sistem yığınları dış istek yapmamalı — vitrin
		// gereksiz bir font indirmesi başlatmaz.
		qrms_assert_same( '', $tipler['']['google'], 'tema fontu istek doğurmaz' );
		qrms_assert_same( '', $tipler['system']['google'], 'sistem fontu istek doğurmaz' );
		qrms_assert_same( '', $tipler['Georgia']['google'], 'Georgia istek doğurmaz' );
		qrms_assert_true( '' !== $tipler['Playfair Display']['google'], 'Playfair Google fontu' );

		// Spec, menü modülünün haritasıyla birebir aynı olmalı: iki modül
		// aynı sayfadaysa tarayıcı aynı adresi ikinci kez indirmesin.
		$menu = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		foreach ( array( 'Playfair Display', 'Inter', 'Poppins', 'Montserrat' ) as $aile ) {
			qrms_assert_contains( "'" . $tipler[ $aile ]['google'] . "'", $menu, $aile . ': menüyle aynı spec' );
		}

		// Frontend CSS değişkenleri ve admin önizlemesi aynı isimleri kullanır.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/vitrin.css' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );

		foreach ( array( '--qrms-vitrin-card-font', '--qrms-vitrin-title-size', '--qrms-vitrin-price-justify' ) as $degisken ) {
			qrms_assert_contains( $degisken, $css, $degisken . ' frontend' );
			qrms_assert_contains( $degisken, $js, $degisken . ' önizleme' );
		}

		// Mobil değerler breakpoint'te temel değişkenlere çevrilir (kart
		// boyutu ayarlarındaki desenin aynısı).
		qrms_assert_contains( '--qrms-vitrin-title-size: var(--qrms-vitrin-title-size-mobile)', $css, 'mobil boyut devri' );
	}
);

qrms_test(
	'kaydetme sihirbazdan çıkıp vitrin listesine döner',
	function () {
		// Sihirbazın son adımı kaydetmektir; kullanıcıyı düzenleme formuna
		// geri atmak onu aynı sihirbazın 1. adımında bırakıyor ve kaydın
		// gerçekleşip gerçekleşmediğini belirsiz kılıyordu.
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-vitrin-admin.php' );

		qrms_assert_contains(
			"wp_safe_redirect( \$this->vitrin_url( array( 'vitrin_msg' => 'kaydedildi' ) ) );",
			$php,
			'liste adresine yönlendirir'
		);
		qrms_assert_false(
			strpos( $php, "'vitrin' => \$kayit_id, 'vitrin_msg' => 'kaydedildi'" ) !== false,
			'düzenleme formuna geri dönmez'
		);

		// Yetki/nonce akışı değişmedi: her iki handler da aynı ortak
		// girişten geçer.
		qrms_assert_contains( '$this->vitrin_yetki_kontrol();', $php, 'nonce + yetki kontrolü yerinde' );
		qrms_assert_contains( 'check_admin_referer( $this->vitrin_nonce_action )', $php, 'nonce eylemi aynı' );

		// Bildirim listede basılır.
		qrms_assert_true(
			strpos( $php, '$this->vitrin_notice();' ) < strpos( $php, 'Vitrinlerim' ),
			'liste ekranı bildirimi basar'
		);
		qrms_assert_contains( "'kaydedildi' => array( 'success', 'Vitrin kaydedildi.' )", $php, 'başarı bildirimi' );
	}
);

qrms_test(
	'kayma hızı sınırlanır, sayı olmayan girdi varsayılana düşer',
	function () {
		$hizli = RMA_Vitrin_DB::ayarlari_temizle( array( 'autoplay_speed' => 10 ) );
		qrms_assert_same( RMA_Vitrin_DB::MIN_SPEED, $hizli['autoplay_speed'], 'alt sınır' );

		$yavas = RMA_Vitrin_DB::ayarlari_temizle( array( 'autoplay_speed' => 999999 ) );
		qrms_assert_same( RMA_Vitrin_DB::MAX_SPEED, $yavas['autoplay_speed'], 'üst sınır' );

		$bozuk = RMA_Vitrin_DB::ayarlari_temizle( array( 'autoplay_speed' => 'hızlı' ) );
		qrms_assert_same( 4000, $bozuk['autoplay_speed'], 'varsayılana düşer' );
	}
);

qrms_test(
	'işaretlenmemiş kutular 0, işaretliler 1 olur',
	function () {
		// İşaretsiz checkbox $_POST'a hiç gelmez; handler 0 geçirir.
		$kapali = RMA_Vitrin_DB::ayarlari_temizle( array() );
		qrms_assert_same( 0, $kapali['autoplay'], 'otomatik kayma kapalı' );
		qrms_assert_same( 0, $kapali['drag_enabled'], 'sürükleme kapalı' );

		$acik = RMA_Vitrin_DB::ayarlari_temizle( array( 'autoplay' => '1', 'show_price' => 'on' ) );
		qrms_assert_same( 1, $acik['autoplay'], 'otomatik kayma açık' );
		qrms_assert_same( 1, $acik['show_price'], 'fiyat açık' );
	}
);

qrms_test(
	'boş başlık yerine varsayılan ad konur',
	function () {
		// Şemada title NOT NULL; boş bırakılırsa liste ekranında adsız bir
		// satır görünürdü.
		$temiz = RMA_Vitrin_DB::ayarlari_temizle( array( 'title' => '   ' ) );

		qrms_assert_same( 'Ürün Vitrini', $temiz['title'], 'varsayılan ad' );
	}
);

qrms_test(
	'ürün sırası temizlenir: sıra korunur, tekrar ve geçersiz kayıt düşer',
	function () {
		// Sıra formdan virgüllü dize olarak gelir (gizli #rma-vitrin-order).
		qrms_assert_same(
			array( 12, 5, 40 ),
			RMA_Vitrin_DB::urun_idlerini_temizle( '12,5,12,0,40,-3,abc' ),
			'dize girdi'
		);

		qrms_assert_same(
			array( 7, 9 ),
			RMA_Vitrin_DB::urun_idlerini_temizle( array( '7', 9, '9' ) ),
			'dizi girdi'
		);

		qrms_assert_same( array(), RMA_Vitrin_DB::urun_idlerini_temizle( '' ), 'boş girdi' );
	}
);

/* ---------------------------------------------------------------------------
 * 6b-2. Öne Çıkan Slider — görünüm ayarları
 *
 * QMO_Slider_Settings WordPress'e bağımlılığı olmayan saf dönüşümler
 * içerir (checkbox → 0/1, renk → hex, font → beyaz liste). Kısa kod ve
 * admin sihirbazı bu sınıfı okur; option hiç yoksa eski görünüm korunur.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-slider-settings.php';

echo "\nÖne Çıkan Slider — görünüm ayarları\n";

qrms_test(
	'slider varsayılanları mevcut görünümü korur',
	function () {
		$v = QMO_Slider_Settings::varsayilanlar();

		qrms_assert_same( 1, $v['show_nav'], 'oklar açık' );
		qrms_assert_same( 1, $v['show_title'], 'başlık açık' );
		qrms_assert_same( 'Playfair Display', $v['title_font'], 'Playfair' );
		qrms_assert_same( '#e8c766', $v['title_color'], 'gold-soft' );
		qrms_assert_same( 28, $v['title_size'], 'masaüstü punto' );
		qrms_assert_same( 18, $v['title_size_mobile'], 'mobil punto' );
		qrms_assert_same( 600, $v['title_weight'], 'semibold' );
		qrms_assert_same( 'center', $v['title_align'], 'ortalı' );
	}
);

qrms_test(
	'slider sanitize: checkbox kapalı 0, açık 1 olur',
	function () {
		$kapali = QMO_Slider_Settings::sanitize( array() );
		qrms_assert_same( 0, $kapali['show_nav'], 'ok kapalı' );
		qrms_assert_same( 0, $kapali['show_title'], 'başlık kapalı' );

		$acik = QMO_Slider_Settings::sanitize( array( 'show_nav' => '1', 'show_title' => 'on' ) );
		qrms_assert_same( 1, $acik['show_nav'], 'ok açık' );
		qrms_assert_same( 1, $acik['show_title'], 'başlık açık' );
	}
);

qrms_test(
	'slider sanitize: renk, font, punto, kalınlık ve hizalama temizlenir',
	function () {
		$temiz = QMO_Slider_Settings::sanitize(
			array(
				'title_color'       => 'red',
				'title_font'        => 'Comic Sans',
				'title_size'        => 999,
				'title_size_mobile' => 1,
				'title_weight'      => 850,
				'title_align'       => 'justify',
			)
		);

		qrms_assert_same( '#e8c766', $temiz['title_color'], 'geçersiz renk varsayılana düşer' );
		qrms_assert_same( 'Playfair Display', $temiz['title_font'], 'bilinmeyen font Playfair\'e düşer' );
		qrms_assert_same( QMO_Slider_Settings::MAX_TITLE_SIZE, $temiz['title_size'], 'masaüstü üst sınır' );
		qrms_assert_same( QMO_Slider_Settings::MIN_TITLE_SIZE_MOBILE, $temiz['title_size_mobile'], 'mobil alt sınır' );
		qrms_assert_same( 600, $temiz['title_weight'], 'kalınlık varsayılana düşer' );
		qrms_assert_same( 'center', $temiz['title_align'], 'hizalama varsayılana düşer' );

		$gecerli = QMO_Slider_Settings::sanitize(
			array(
				'title_color'       => '#c9a84c',
				'title_font'        => 'Manrope',
				'title_size'        => 22,
				'title_size_mobile' => 16,
				'title_weight'      => 700,
				'title_align'       => 'right',
			)
		);

		qrms_assert_same( '#c9a84c', $gecerli['title_color'], 'geçerli renk' );
		qrms_assert_same( 'Manrope', $gecerli['title_font'], 'geçerli font' );
		qrms_assert_same( 22, $gecerli['title_size'], 'geçerli punto' );
		qrms_assert_same( 16, $gecerli['title_size_mobile'], 'geçerli mobil punto' );
		qrms_assert_same( 700, $gecerli['title_weight'], 'geçerli kalınlık' );
		qrms_assert_same( 'right', $gecerli['title_align'], 'geçerli hizalama' );
	}
);

qrms_test(
	'slider option yokken get() varsayılanları döner',
	function () {
		$ayar = QMO_Slider_Settings::get();
		qrms_assert_same( 1, $ayar['show_nav'], 'kayıt yokken oklar açık kalır' );
		qrms_assert_same( 1, $ayar['show_title'], 'kayıt yokken başlık açık kalır' );
	}
);

qrms_test(
	'slider CSS değişkenleri ve kısa kod ayarları bağlanır',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/frontend-slider.css' );
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-slider.php' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );
		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-vitrin-admin.php' );

		foreach ( array( '--qmo-slider-title-font', '--qmo-slider-title-color', '--qmo-slider-title-size', '--qmo-slider-title-size-mobile', '--qmo-slider-title-weight', '--qmo-slider-title-align' ) as $degisken ) {
			qrms_assert_contains( $degisken, $css, $degisken . ' frontend' );
			qrms_assert_contains( $degisken, $js, $degisken . ' önizleme' );
		}

		qrms_assert_contains( 'QMO_Slider_Settings::get', $php, 'kısa kod ayar okur' );
		qrms_assert_contains( '$show_nav', $php, 'ok bloğu ayara bağlı' );
		qrms_assert_contains( 'Ok navigasyonunu göster', $admin, 'ok checkbox' );
		qrms_assert_contains( 'Slide başlığını göster', $admin, 'başlık checkbox' );
		qrms_assert_contains( 'handle_slider_settings_save', $admin, 'kaydetme ucu' );
		qrms_assert_contains( 'check_admin_referer( $this->slider_nonce_action )', $admin, 'nonce' );
		qrms_assert_contains( 'initSliderPreview', $js, 'canlı önizleme' );
	}
);

qrms_test(
	'slider kaydetme ucu ve önbellek kancası kayıtlı',
	function () {
		$boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		qrms_assert_contains( "admin_post_qmo_slider_kaydet", $boot, 'kaydetme ucu' );
		qrms_assert_contains( "update_option_qmo_slider_settings", $boot, 'önbellek kancası' );
	}
);

/* ---------------------------------------------------------------------------
 * 6c. yorum-feedback — ortak $wpdb taklidi
 *
 * Kaldırılan "Detaylı İçgörüler" ekranıyla birlikte Gemini özeti de gitti
 * (ai-insights.php); bu bölümdeki testler de onunla birlikte kaldırıldı.
 * Aşağıdaki taklit ve require'lar duruyor: sonraki bölümler modülün gerçek
 * settings.php/install.php dosyalarına ve bir $wpdb'ye ihtiyaç duyuyor.
 * ------------------------------------------------------------------------ */

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Yorum tablosu taklidi — yalnızca tablo varlığı ve basit sayımlar.
 */
class QRMS_Test_Wpdb {
	public $prefix = 'wp_';

	public function prepare( $sql, ...$args ) {
		foreach ( $args as $a ) {
			$sql = preg_replace( '/%d/', (string) (int) $a, $sql, 1 );
		}
		return $sql;
	}

	public function esc_like( $t ) {
		return $t;
	}

	public function get_var( $sql ) {
		if ( false !== stripos( $sql, 'SHOW TABLES' ) ) {
			return 'wp_qrm_reviews';
		}
		return 3;
	}

	public function get_row( $sql, $mode = null ) {
		$GLOBALS['qrms_son_sql'] = $sql;
		return null;
	}

	public function get_results( $sql, $mode = null ) {
		$GLOBALS['qrms_son_sql'] = $sql;
		return array();
	}
}

$GLOBALS['wpdb'] = new QRMS_Test_Wpdb();

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/install.php';

// qrm_pro_reviews_table_exists() sonucu istek başına bir kez hesaplanıp
// fonksiyon içi static'te tutulur — test sürecinin tamamı tek "istek" sayılır.
// Sonraki bölümlerin $wpdb taklitleri SHOW TABLES'a yanıt vermediği için
// static burada, tablonun VAR olduğu bilinen taklitle sabitlenir.
qrm_pro_reviews_table_exists();

/* ---------------------------------------------------------------------------
 * 7. Yardımcılar
 * ------------------------------------------------------------------------ */

echo "\nYardımcılar\n";

qrms_test(
	'on bir modül slug\'ı ve Türkçe isimleri tanımlı',
	function () {
		$modules = QRMS_Helpers::get_modules();

		qrms_assert_same( 11, count( QRMS_Helpers::MODULE_SLUGS ), 'slug sayısı' );
		qrms_assert_same( 11, count( $modules ), 'isim sayısı' );
		qrms_assert_same( array_values( QRMS_Helpers::MODULE_SLUGS ), array_keys( $modules ), 'slug listesi' );
		qrms_assert_same( 'Çalışma Saatleri', QRMS_Helpers::get_module_name( 'qr-calisma-saatleri' ), 'isim' );
		qrms_assert_same( 'Yorum & Feedback', QRMS_Helpers::get_module_name( 'yorum-feedback' ), 'isim' );

		// Görünen adlar işi anlatır, eklentinin adını değil; slug'lar aynı kaldı.
		qrms_assert_same( 'QR Kod Oluştur', QRMS_Helpers::get_module_name( 'qr-masa' ), 'qr kod adı' );
		qrms_assert_same( 'İstatistikler', QRMS_Helpers::get_module_name( 'qr-analiz' ), 'istatistik adı' );
		qrms_assert_same( 'Fotoğraf Galerisi', QRMS_Helpers::get_module_name( 'qr-galeri' ), 'galeri adı' );
		qrms_assert_same( 'Dil / Çeviri Ayarları', QRMS_Helpers::get_module_name( 'qr-ceviri' ), 'çeviri adı' );
		qrms_assert_same( 'Chatbot Asistan', QRMS_Helpers::get_module_name( 'qr-chatbot' ), 'chatbot adı' );

		// Görünen ad sadeleşti ama slug (lisans sözleşmesinin anahtarı) aynı kaldı.
		qrms_assert_same( 'Güvenlik Ayarı', QRMS_Helpers::get_module_name( 'qr-masa-oturum-guvenligi' ), 'güvenlik adı' );
		qrms_assert_true( QRMS_Helpers::is_valid_module( 'qr-masa-oturum-guvenligi' ), 'slug korundu' );
	}
);

qrms_test(
	'domain www öneki olmadan gönderilir',
	function () {
		qrms_assert_same( 'restoran.test', QRMS_Helpers::get_site_domain(), 'domain' );
	}
);

qrms_test(
	'sunucu adresi normalize edilir',
	function () {
		qrms_assert_same(
			'https://full.qrmenuofficial.com',
			QRMS_License_Client::normalize_server_url( 'https://full.qrmenuofficial.com/' ),
			'sondaki eğik çizgi'
		);
		qrms_assert_same(
			'https://staging.qrmenuofficial.com',
			QRMS_License_Client::normalize_server_url( 'staging.qrmenuofficial.com' ),
			'şema eklenir'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 8. QR Çalışma Saatleri
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/includes/hours.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/includes/admin-sayfa.php';

echo "\nQR Çalışma Saatleri\n";

qrms_test(
	'eksik veya bozuk girdi varsayılan haftalık tabloya tamamlanır',
	function () {
		$hours = qrms_cs_sanitize( array( 'monday' => array( 'open' => '9:30', 'close' => 'bogus' ) ) );

		qrms_assert_same( 7, count( $hours ), 'yedi gün' );
		qrms_assert_same( '09:30', $hours['monday']['open'], 'saat pad' );
		qrms_assert_same( '22:00', $hours['monday']['close'], 'geçersiz kapanış varsayılan' );
		qrms_assert_false( $hours['monday']['closed'], 'kapalı değil' );
		qrms_assert_same( '09:00', $hours['sunday']['open'], 'eksik gün varsayılan' );
	}
);

qrms_test(
	'kapalı gün açık kabul edilmez; normal ve gece yarısını aşan mesai doğru çözülür',
	function () {
		$hours = qrms_cs_defaults();
		$hours['monday']['closed'] = true;
		$hours['tuesday']['open']  = '09:00';
		$hours['tuesday']['close'] = '17:00';
		$hours['wednesday']['open']  = '18:00';
		$hours['wednesday']['close'] = '02:00';
		$hours['thursday']['closed'] = true;
		$hours['friday']['open']     = '00:00';
		$hours['friday']['close']    = '00:00';

		// 2026-08-17 Pazartesi 12:00 UTC.
		qrms_assert_false( qrms_cs_is_open_at( strtotime( '2026-08-17 12:00:00 UTC' ), $hours ), 'pazartesi kapalı' );
		// 2026-08-18 Salı 12:00 UTC — 09–17 arasında.
		qrms_assert_true( qrms_cs_is_open_at( strtotime( '2026-08-18 12:00:00 UTC' ), $hours ), 'salı öğlen açık' );
		qrms_assert_false( qrms_cs_is_open_at( strtotime( '2026-08-18 20:00:00 UTC' ), $hours ), 'salı akşam kapalı' );
		// Çarşamba 18:00–02:00: akşam açık, öğlen kapalı; Perşembe 01:00 hâlâ açık.
		qrms_assert_true( qrms_cs_is_open_at( strtotime( '2026-08-19 20:00:00 UTC' ), $hours ), 'çarşamba gece açık' );
		qrms_assert_false( qrms_cs_is_open_at( strtotime( '2026-08-19 01:00:00 UTC' ), $hours ), 'çarşamba sabah kapalı' );
		qrms_assert_false( qrms_cs_is_open_at( strtotime( '2026-08-19 12:00:00 UTC' ), $hours ), 'çarşamba öğlen kapalı' );
		qrms_assert_true( qrms_cs_is_open_at( strtotime( '2026-08-20 01:00:00 UTC' ), $hours ), 'perşembe 01:00 önceki günden açık' );
		qrms_assert_false( qrms_cs_is_open_at( strtotime( '2026-08-20 03:00:00 UTC' ), $hours ), 'perşembe 03:00 kapalı' );
		qrms_assert_true( qrms_cs_is_open_at( strtotime( '2026-08-21 15:00:00 UTC' ), $hours ), 'cuma 24 saat' );
	}
);

qrms_test(
	'yönetim kaydı option\'a sanitize edilmiş tablo yazar',
	function () {
		$_POST = array(
			'qrms_cs_kaydet' => '1',
			'qrms_cs_nonce'  => 'test-nonce',
			'qrms_cs'        => array(
				'saturday' => array(
					'closed' => '1',
					'open'   => '11:00',
					'close'  => '15:00',
				),
			),
		);

		qrms_assert_true( qrms_cs_handle_save(), 'kaydedildi' );

		$stored = get_option( QRMS_CS_OPTION );
		qrms_assert_true( ! empty( $stored['saturday']['closed'] ), 'cumartesi kapalı' );
		qrms_assert_same( '11:00', $stored['saturday']['open'], 'saat korundu' );
		qrms_assert_same( 7, count( $stored ), 'tam hafta' );
	}
);

qrms_test(
	'modül sayfası kayıtlı callback ile saat formunu basar',
	function () {
		QRMS_Admin::register_module_page( 'qr-calisma-saatleri', 'qrms_cs_admin_sayfasi' );

		ob_start();
		QRMS_Admin::render_module_page( 'qr-calisma-saatleri' );
		$html = ob_get_clean();

		qrms_assert_contains( 'QR Çalışma Saatleri', $html, 'başlık' );
		qrms_assert_contains( 'name="qrms_cs[monday][closed]"', $html, 'pazartesi kapalı' );
		qrms_assert_contains( '[qr_calisma_saatleri]', $html, 'kısa kod' );
		qrms_assert_contains( '[qr_calisma_saatleri fullwidth="0"]', $html, 'dar sütun kısa kodu' );
		qrms_assert_false(
			false !== strpos( $html, 'Bu modül yakında burada olacak.' ),
			'placeholder basılmamalı'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 8b. Kısa kod kayıt defteri ve rehber ekranı
 * ------------------------------------------------------------------------ */

echo "\nKısa kod rehberi\n";

qrms_test(
	'geçerli tanım eksiksiz biçime getirilir',
	function () {
		$kod = QRMS_Shortcodes::normalize(
			array(
				'tag'   => 'restaurant_menu',
				'title' => 'Restoran Menüsü',
				'desc'  => 'Menüyü basar.',
				'attrs' => array( array( 'name' => 'show_search', 'default' => 'yes', 'desc' => 'Arama kutusu.' ) ),
			)
		);

		qrms_assert_same( 'restaurant_menu', $kod['tag'], 'tag' );
		qrms_assert_same( '[restaurant_menu]', $kod['usage'], 'usage verilmezse tag\'dan üretilir' );
		qrms_assert_same( '', $kod['note'], 'note varsayılanı' );
		qrms_assert_same( 1, count( $kod['attrs'] ), 'parametre sayısı' );
		qrms_assert_same( 'yes', $kod['attrs'][0]['default'], 'parametre varsayılanı' );
	}
);

qrms_test(
	'köşeli parantezle yazılan tag temizlenir',
	function () {
		// Tanımı yazan kişi "[qr_aktif_masa]" yazabilir; kart iki kez parantez
		// basmamalı.
		$kod = QRMS_Shortcodes::normalize( array( 'tag' => '[qr_aktif_masa]', 'title' => 'Masa' ) );

		qrms_assert_same( 'qr_aktif_masa', $kod['tag'], 'tag' );
		qrms_assert_same( '[qr_aktif_masa]', $kod['usage'], 'usage' );
	}
);

qrms_test(
	'eksik tanım ve bozuk parametre atılır',
	function () {
		qrms_assert_same( null, QRMS_Shortcodes::normalize( array( 'title' => 'Ad yok' ) ), 'tag yok' );
		qrms_assert_same( null, QRMS_Shortcodes::normalize( array( 'tag' => 'x' ) ), 'başlık yok' );
		qrms_assert_same( null, QRMS_Shortcodes::normalize( array( 'tag' => '[]', 'title' => 'Boş' ) ), 'boş tag' );
		qrms_assert_same( null, QRMS_Shortcodes::normalize( 'metin' ), 'dizi değil' );

		$kod = QRMS_Shortcodes::normalize(
			array(
				'tag'   => 'x',
				'title' => 'X',
				'attrs' => array( array( 'desc' => 'adı yok' ), 'metin', array( 'name' => 'id' ) ),
			)
		);

		qrms_assert_same( 1, count( $kod['attrs'] ), 'yalnızca adı olan parametre kalır' );
		qrms_assert_same( 'id', $kod['attrs'][0]['name'], 'kalan parametre' );
	}
);

qrms_test(
	'bilinmeyen modülün kısa kodları kaydedilmez',
	function () {
		QRMS_Shortcodes::register( 'yok-boyle-modul', array( array( 'tag' => 'x', 'title' => 'X' ) ) );

		qrms_assert_false( array_key_exists( 'yok-boyle-modul', QRMS_Shortcodes::all() ), 'kayıt yok' );
	}
);

qrms_test(
	'gruplar modül sırasına göre dizilir',
	function () {
		// Kayıt sırası ne olursa olsun rehber MODULE_SLUGS sırasını izler;
		// kullanıcı her açtığında aynı düzeni görür.
		QRMS_Shortcodes::register( 'qr-chatbot', array( array( 'tag' => 'gemini_chatbot', 'title' => 'Asistan' ) ) );
		QRMS_Shortcodes::register( 'restoran-menu', array( array( 'tag' => 'restaurant_menu', 'title' => 'Menü' ) ) );

		$sira = array_keys( QRMS_Shortcodes::all() );

		qrms_assert_true(
			array_search( 'restoran-menu', $sira, true ) < array_search( 'qr-chatbot', $sira, true ),
			'restoran-menu qr-chatbot\'tan önce'
		);
	}
);

qrms_test(
	'rehber kartları kodu, kopyala butonunu ve parametreleri basar',
	function () {
		QRMS_Shortcodes::register(
			'restoran-menu',
			array(
				array(
					'tag'   => 'qrms_urun_vitrini',
					'title' => 'Ürün Vitrini',
					'desc'  => 'Seçtiğiniz ürünleri kayan bir şeritte gösterir.',
					'usage' => '[qrms_urun_vitrini id="1"]',
					'note'  => 'Vitrin numarası zorunludur.',
					'attrs' => array( array( 'name' => 'id', 'default' => '', 'desc' => 'Vitrin numarası.' ) ),
				),
			)
		);

		ob_start();
		QRMS_Shortcodes::render_page();
		$html = ob_get_clean();

		qrms_assert_contains( 'Restoran Menü', $html, 'modül başlığı' );
		qrms_assert_contains( '[qrms_urun_vitrini id=', $html, 'örnek kullanım' );
		qrms_assert_contains( 'data-qrms-copy=', $html, 'kopyala butonu' );
		qrms_assert_contains( 'Vitrin numarası zorunludur.', $html, 'koşul notu' );
		qrms_assert_contains( 'Parametreler', $html, 'parametre başlığı' );
	}
);

qrms_test(
	'hiç kısa kod yoksa menü satırı da kaydedilmez',
	function () {
		// Regresyon: boş bir rehber sayfası menüde yer kaplamamalı; menü kaydı
		// ile beyaz liste aynı koşulu kullanır, yoksa satır kaydolur ama
		// beyaz listede olmadığı için admin_head'de gizlenirdi.
		qrms_assert_false( QRMS_Shortcodes::has_any(), 'kayıt boş' );

		update_option( 'qrms_active_modules', array( 'restoran-menu' ) );
		QRMS_Admin::register_menu();

		qrms_assert_false(
			in_array( QRMS_Admin::SHORTCODES_SLUG, qrms_registered_submenu_slugs(), true ),
			'menüde satır yok'
		);
		qrms_assert_false(
			in_array( QRMS_Admin::SHORTCODES_SLUG, QRMS_Admin::get_menu_row_slugs(), true ),
			'beyaz listede yok'
		);
	}
);

qrms_test(
	'kısa kod varken satır menüye ve beyaz listeye birlikte girer',
	function () {
		QRMS_Shortcodes::register( 'restoran-menu', array( array( 'tag' => 'restaurant_menu', 'title' => 'Menü' ) ) );
		update_option( 'qrms_active_modules', array( 'restoran-menu' ) );

		QRMS_Admin::register_menu();

		qrms_assert_true(
			in_array( QRMS_Admin::SHORTCODES_SLUG, qrms_registered_submenu_slugs(), true ),
			'menüde satır var'
		);
		qrms_assert_true(
			in_array( QRMS_Admin::SHORTCODES_SLUG, QRMS_Admin::get_menu_row_slugs(), true ),
			'beyaz listede var'
		);

		// Genel Ayarlar'dan ÖNCE gelmeli.
		$slugs = qrms_registered_submenu_slugs();

		qrms_assert_true(
			array_search( QRMS_Admin::SHORTCODES_SLUG, $slugs, true ) < array_search( QRMS_Admin::SETTINGS_SLUG, $slugs, true ),
			'Kısa Kodlar, Genel Ayarlar\'ın üstünde'
		);
	}
);

/**
 * Kaynak ağacında GERÇEKTEN kayıtlı olan kısa kod adları.
 *
 * add_shortcode() çağrılarını tarar. Tek dolaylı çağrı shortcode-vitrin.php
 * içindeki `self::SHORTCODE` sabitidir; o da aynı dosyadan çözülür.
 *
 * @return string[]
 */
function qrms_kaynaktaki_kisa_kodlar() {
	$tags = array();

	foreach ( glob( QRMS_PLUGIN_DIR . 'modules/*/{,*/,*/*/}*.php', GLOB_BRACE ) as $dosya ) {
		$kaynak = (string) file_get_contents( $dosya );

		if ( false === strpos( $kaynak, 'add_shortcode(' ) ) {
			continue;
		}

		preg_match_all( "/add_shortcode\(\s*'([^']+)'/", $kaynak, $duz );
		$tags = array_merge( $tags, $duz[1] );

		// add_shortcode( self::SHORTCODE, ... ) — sabit aynı dosyadadır.
		if ( preg_match( '/add_shortcode\(\s*self::SHORTCODE\b/', $kaynak )
			&& preg_match( "/const\s+SHORTCODE\s*=\s*'([^']+)'/", $kaynak, $sabit ) ) {
			$tags[] = $sabit[1];
		}
	}

	$tags = array_values( array_unique( $tags ) );
	sort( $tags );

	return $tags;
}

/**
 * Modüllerin rehbere BİLDİRDİĞİ kısa kod adları.
 *
 * Kayıtlar module.php dosyalarındaki QRMS_Shortcodes::register() çağrılarında
 * durur; modülleri çalıştırmadan okunabilsin diye kaynak taranır.
 *
 * @return string[]
 */
function qrms_bildirilen_kisa_kodlar() {
	$tags = array();

	// Kayıt çoğunlukla module.php'dedir; header-footer-builder gibi modüller
	// bunu kendi sınıf dosyasında yapar, o yüzden modülün tüm PHP'si taranır.
	foreach ( glob( QRMS_PLUGIN_DIR . 'modules/*/{,*/,*/*/}*.php', GLOB_BRACE ) as $dosya ) {
		$kaynak = (string) file_get_contents( $dosya );

		if ( false === strpos( $kaynak, 'QRMS_Shortcodes::register(' ) ) {
			continue;
		}

		preg_match_all( "/'tag'\s*=>\s*'([^']+)'/", $kaynak, $eslesme );
		$tags = array_merge( $tags, $eslesme[1] );
	}

	$tags = array_values( array_unique( $tags ) );
	sort( $tags );

	return $tags;
}

qrms_test(
	'rehber kaydı kaynaktaki add_shortcode çağrılarıyla birebir örtüşür',
	function () {
		// Sürüklenme koruması: yeni bir kısa kod eklenip rehbere bildirilmezse
		// (ya da kaldırılan bir kod rehberde kalırsa) bu test düşer.
		$kaynakta   = qrms_kaynaktaki_kisa_kodlar();
		$bildirilen = qrms_bildirilen_kisa_kodlar();

		qrms_assert_same( 21, count( $kaynakta ), 'kaynaktaki kısa kod sayısı' );
		qrms_assert_same( $kaynakta, $bildirilen, 'bildirilen liste kaynakla aynı' );
	}
);

/* ---------------------------------------------------------------------------
 * 8c. [qmo_sepet] — modal class uyumu ve masa oturumu kısıtı
 * ------------------------------------------------------------------------ */

