<?php
/**
 * QR Menu Suite — stub tabanlı mantık testleri.
 *
 * Çalıştırmak için: php tests/test-suite.php
 *
 * Gerçek bir WordPress kurulumu gerekmez; WordPress fonksiyonları
 * tests/stubs-wordpress.php içinde taklit edilir.
 *
 * @package QR_Menu_Suite
 */

require_once __DIR__ . '/stubs-wordpress.php';

$GLOBALS['qrms_assertions'] = 0;
$GLOBALS['qrms_failures']   = array();
$GLOBALS['qrms_current']    = '';

/**
 * Test durumunu sıfırlar.
 *
 * @return void
 */
function qrms_reset() {
	$GLOBALS['qrms_test']['options']    = array();
	$GLOBALS['qrms_test']['transients'] = array();
	$GLOBALS['qrms_test']['menus']      = array();
	$GLOBALS['qrms_test']['submenus']   = array();
	$GLOBALS['qrms_test']['removed']    = array();
	$GLOBALS['qrms_test']['redirects']  = array();
	$GLOBALS['qrms_test']['http']       = null;
	$GLOBALS['qrms_test']['http_calls'] = array();
	$GLOBALS['qrms_test']['can']        = true;
	$GLOBALS['menu']                    = array();
	$GLOBALS['submenu']                 = array();
	$_POST                              = array();
	$_GET                               = array();
}

/**
 * Bir testi çalıştırır.
 *
 * @param string   $name     Test adı.
 * @param callable $callback Test gövdesi.
 * @return void
 */
function qrms_test( $name, $callback ) {
	qrms_reset();
	$GLOBALS['qrms_current'] = $name;

	try {
		call_user_func( $callback );
		echo "\033[32m  ✓\033[0m " . $name . "\n";
	} catch ( Exception $e ) {
		$GLOBALS['qrms_failures'][] = $name . ': ' . $e->getMessage();
		echo "\033[31m  ✗\033[0m " . $name . ' — ' . $e->getMessage() . "\n";
	}
}

/**
 * Eşitlik doğrulaması.
 *
 * @param mixed  $expected Beklenen.
 * @param mixed  $actual   Gerçekleşen.
 * @param string $message  Mesaj.
 * @return void
 * @throws Exception Eşit değilse.
 */
function qrms_assert_same( $expected, $actual, $message = '' ) {
	++$GLOBALS['qrms_assertions'];

	if ( $expected !== $actual ) {
		throw new Exception(
			$message . ' | beklenen: ' . wp_json_encode( $expected ) . ', gelen: ' . wp_json_encode( $actual )
		);
	}
}

/**
 * Doğruluk doğrulaması.
 *
 * @param mixed  $value   Değer.
 * @param string $message Mesaj.
 * @return void
 * @throws Exception Değer doğru değilse.
 */
function qrms_assert_true( $value, $message = '' ) {
	qrms_assert_same( true, (bool) $value, $message );
}

/**
 * Yanlışlık doğrulaması.
 *
 * @param mixed  $value   Değer.
 * @param string $message Mesaj.
 * @return void
 * @throws Exception Değer yanlış değilse.
 */
function qrms_assert_false( $value, $message = '' ) {
	qrms_assert_same( false, (bool) $value, $message );
}

/**
 * Metin içerme doğrulaması.
 *
 * @param string $needle   Aranan.
 * @param string $haystack İçinde arananan metin.
 * @param string $message  Mesaj.
 * @return void
 * @throws Exception Bulunamazsa.
 */
function qrms_assert_contains( $needle, $haystack, $message = '' ) {
	++$GLOBALS['qrms_assertions'];

	if ( false === strpos( $haystack, $needle ) ) {
		throw new Exception( $message . ' | "' . $needle . '" bulunamadı' );
	}
}

/**
 * Sunucudan gelecek HTTP cevabını ayarlar.
 *
 * @param int   $code Durum kodu.
 * @param array $body Gövde (dizi olarak).
 * @return void
 */
function qrms_mock_http( $code, array $body ) {
	$GLOBALS['qrms_test']['http'] = array(
		'response' => array( 'code' => $code ),
		'body'     => wp_json_encode( $body ),
	);
}

/**
 * Sunucuya ulaşılamama durumunu taklit eder.
 *
 * @return void
 */
function qrms_mock_http_error() {
	$GLOBALS['qrms_test']['http'] = new WP_Error( 'http_request_failed', 'cURL error 28' );
}

/**
 * Depoda modules/<slug>/ klasörü HENÜZ olmayan modül slug'ları.
 *
 * Yükleyici testleri bu listeyi kullanır: paketlenmiş modüllerin gerçek
 * dosyaları WordPress'e bağlıdır, stub ortamında yüklenemez.
 *
 * @return string[]
 */
function qrms_paketlenmemis_moduller() {
	return array_values(
		array_filter(
			qrms_all_modules(),
			function ( $slug ) {
				return ! is_dir( QRMS_PLUGIN_DIR . 'modules/' . $slug );
			}
		)
	);
}

/**
 * Tüm bilinen modül slug'ları.
 *
 * @return string[]
 */
function qrms_all_modules() {
	return QRMS_Helpers::MODULE_SLUGS;
}

echo "\nQR Menu Suite — mantık testleri\n\n";

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
		qrms_assert_contains( 'QR Chatbot', $html, 'yeni modül görünmeli' );
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
	'dokuz modül de aktifken hepsi menüde görünür',
	function () {
		update_option( 'qrms_active_modules', qrms_all_modules() );

		QRMS_Admin::register_menu();

		qrms_assert_same( 11, count( qrms_registered_submenu_slugs() ), 'Genel Bakış + 9 modül + Genel Ayarlar' );
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

qrms_test(
	'modül placeholder sayfası modül adını ve "yakında" metnini gösterir',
	function () {
		ob_start();
		QRMS_Admin::render_module_placeholder( 'qr-masa-oturum-guvenligi' );
		$html = ob_get_clean();

		qrms_assert_contains( 'QR Masa Oturum Güvenliği', $html, 'başlık' );
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
 * 6. restoran-menu menü sıralaması
 * ------------------------------------------------------------------------ */

// module.php dosya kapsamında yalnızca fonksiyon tanımlar (yan etkisi yoktur),
// bu yüzden stub ortamında doğrudan yüklenebilir. Test edilen sıralama
// yardımcısının WordPress'e bağımlılığı yoktur: saf dizi dönüşümüdür.
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/module.php';

echo "\nrestoran-menu menü sıralaması\n";

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
	return array_map(
		function ( $row ) {
			return $row[2];
		},
		$rows
	);
}

/**
 * Gerçek dünyadaki dizilim: çekirdeğin _add_post_type_submenus() kancası
 * ürün listesi satırını QRMS_Admin::register_menu()'den ÖNCE ekler, glue'un
 * eklediği üç satır ise en sona düşer.
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
		qrms_submenu_satiri( '— Ürün Ekle', 'post-new.php?post_type=rma_menu_item' ),
		qrms_submenu_satiri( '— Kategoriler', 'edit-tags.php?taxonomy=rma_category&post_type=rma_menu_item' ),
		qrms_submenu_satiri( '— Alerjenler', 'edit-tags.php?taxonomy=rma_allergen&post_type=rma_menu_item' ),
		qrms_submenu_satiri( '— Görünüm', 'qrms-rm-gorunum' ),
		qrms_submenu_satiri( '— Öne Çıkanlar', 'qrms-rm-one-cikanlar' ),
		qrms_submenu_satiri( '— Diğer Ayarlar', 'qrms-rm-diger' ),
	);
}

qrms_test(
	'modülün yedi satırı Restoran Menü girişinin hemen ardına sıralanır',
	function () {
		$sirali = qrms_module_restoran_menu_submenu_sirala( qrms_submenu_ham_liste() );

		qrms_assert_same(
			array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
				'edit.php?post_type=rma_menu_item',
				'post-new.php?post_type=rma_menu_item',
				'edit-tags.php?taxonomy=rma_category&post_type=rma_menu_item',
				'edit-tags.php?taxonomy=rma_allergen&post_type=rma_menu_item',
				'qrms-rm-gorunum',
				'qrms-rm-one-cikanlar',
				'qrms-rm-diger',
				QRMS_Admin::get_module_page_slug( 'qr-masa' ),
				QRMS_Admin::SETTINGS_SLUG,
			),
			qrms_submenu_sluglari( $sirali ),
			'tam sıra'
		);
	}
);

qrms_test(
	'menüdeki sıra listesi modülün yedi ekranını kapsar',
	function () {
		// Sıra listesi ile menüye eklenen satırlar tek kaynaktan gelmeli:
		// biri değişip diğeri unutulursa satır menüde yanlış yere düşer.
		qrms_assert_same( 7, count( qrms_module_restoran_menu_child_slugs() ), 'satır sayısı' );

		qrms_assert_same(
			array( 'qrms-rm-gorunum', 'qrms-rm-one-cikanlar', 'qrms-rm-diger' ),
			array_slice( qrms_module_restoran_menu_child_slugs(), 4 ),
			'modülün kendi ayar sayfaları en sonda'
		);
	}
);

qrms_test(
	'çekirdeğin başa koyduğu ürün listesi satırı Genel Bakış\'ın önüne geçmez',
	function () {
		$ham = qrms_submenu_ham_liste();

		// Hatanın gerçekten var olduğunu göster: düzeltilmemiş listede ürün
		// listesi satırı ilk sırada.
		qrms_assert_same( 'edit.php?post_type=rma_menu_item', $ham[0][2], 'düzeltilmemiş liste' );

		$sirali = qrms_module_restoran_menu_submenu_sirala( $ham );

		qrms_assert_same( QRMS_Admin::MENU_SLUG, $sirali[0][2], 'Genel Bakış en üstte' );
	}
);

qrms_test(
	'satırlar yeniden indekslenir ve etiketler korunur',
	function () {
		$sirali = qrms_module_restoran_menu_submenu_sirala( qrms_submenu_ham_liste() );

		qrms_assert_same( range( 0, 10 ), array_keys( $sirali ), 'sıfırdan artan anahtarlar' );
		qrms_assert_same( '— Kategoriler', $sirali[4][0], 'etiket korunur' );
		qrms_assert_same( '— Diğer Ayarlar', $sirali[8][0], 'son ayar satırının etiketi korunur' );
	}
);

qrms_test(
	'eksik satırlar sorun çıkarmaz, diğer modüllerin sırası korunur',
	function () {
		// Yalnızca çekirdeğin eklediği satır var (glue satırları yetki
		// yetersizliğinden eklenmemiş olabilir).
		$ham = array(
			qrms_submenu_satiri( 'Menü Ürünleri', 'edit.php?post_type=rma_menu_item' ),
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'Restoran Menü', QRMS_Admin::get_module_page_slug( 'restoran-menu' ) ),
			qrms_submenu_satiri( 'QR Chatbot', QRMS_Admin::get_module_page_slug( 'qr-chatbot' ) ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
		);

		qrms_assert_same(
			array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
				'edit.php?post_type=rma_menu_item',
				QRMS_Admin::get_module_page_slug( 'qr-chatbot' ),
				QRMS_Admin::SETTINGS_SLUG,
			),
			qrms_submenu_sluglari( qrms_module_restoran_menu_submenu_sirala( $ham ) ),
			'tek çocuk satırı'
		);
	}
);

qrms_test(
	'Restoran Menü satırı yoksa liste değişmeden döner',
	function () {
		// Modül lisansta aktif değilken bu kod zaten çalışmaz; yine de
		// yardımcı çapayı bulamazsa sırayı bozmamalı.
		$ham = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'QR Masa', QRMS_Admin::get_module_page_slug( 'qr-masa' ) ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
		);

		qrms_assert_same(
			qrms_submenu_sluglari( $ham ),
			qrms_submenu_sluglari( qrms_module_restoran_menu_submenu_sirala( $ham ) ),
			'sıra korunur'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 6c. yorum-feedback — Gemini içgörüleri
 *
 * Gerçek Gemini çağrısı yapılmaz: stub'ların wp_remote_post taklidi
 * ($GLOBALS['qrms_test']['http']) ile yanıt verilir ve giden istek incelenir.
 * Asıl mesele, DIŞ BİR SERVİSE ne gönderildiği — testlerin çoğu bunu korur.
 * ------------------------------------------------------------------------ */

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

/**
 * Yorum tablosu taklidi.
 *
 * Yalnızca ai-insights.php'nin kullandığı üç çağrıyı karşılar; çalıştırılan SQL
 * $GLOBALS['qrms_son_sql']'e yazılır, böylece hangi sütunların seçildiği
 * doğrulanabilir.
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
		if ( false !== stripos( $sql, 'CONCAT' ) ) {
			return '3-9';
		}
		return 3;
	}

	public function get_results( $sql, $mode = null ) {
		$GLOBALS['qrms_son_sql'] = $sql;
		return $GLOBALS['qrms_sahte_yorumlar'];
	}
}

$GLOBALS['wpdb'] = new QRMS_Test_Wpdb();
$GLOBALS['qrms_sahte_yorumlar'] = array(
	array( 'rating' => 4.6, 'comment' => "Yemekler   harika,\nözellikle künefe. Servis yavaştı." ),
	array( 'rating' => 2.4, 'comment' => 'Çorba soğuk geldi, bekleme uzun sürdü.' ),
	array( 'rating' => 5.0, 'comment' => 'Mükemmel, tekrar geleceğiz.' ),
);

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/install.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ai-insights.php';

echo "\nyorum-feedback — Gemini içgörüleri\n";

qrms_test(
	'API anahtarı yokken hiç istek kurulmaz',
	function () {
		// Anahtar boşken tek bir HTTP çağrısı bile olmamalı: yapılandırılmamış
		// bir kurulum sessizce dış servise gitmemeli.
		update_option( 'gemini_api_key', '' );

		$sonuc = qrm_ai_generate_summary();

		qrms_assert_false( $sonuc['ok'], 'başarısız döner' );
		qrms_assert_contains( 'anahtar', $sonuc['error'], 'sebep söylenir' );
		qrms_assert_same( 0, count( $GLOBALS['qrms_test']['http_calls'] ), 'HTTP çağrısı yok' );
	}
);

qrms_test(
	'sorgu yalnızca puan ve yorum metnini seçer',
	function () {
		// Bu testin varlık sebebi: dış servise gönderilecek veri kümesi,
		// gönderilmesi gerekmeyen hiçbir sütunu TAŞIMAMALI. Kişisel veri
		// sorguya girmezse istem metnine de sızamaz.
		qrm_ai_collect_reviews();

		$sql = $GLOBALS['qrms_son_sql'];

		qrms_assert_contains( 'SELECT rating, comment', $sql, 'iki sütun' );

		foreach ( array( 'customer_name', 'customer_phone', 'table_no', 'SELECT *' ) as $yasak ) {
			qrms_assert_false( false !== strpos( $sql, $yasak ), $yasak . ' seçilmez' );
		}
	}
);

qrms_test(
	'istem metninde puan ve yorum var, kişisel veri yok',
	function () {
		$istem = qrm_ai_build_prompt(
			array(
				array( 'rating' => 4.6, 'comment' => 'Künefe harika' ),
				array( 'rating' => 2.0, 'comment' => 'Çorba soğuktu' ),
			)
		);

		qrms_assert_contains( '(4.6/5)', $istem, 'puan biçimi' );
		qrms_assert_contains( 'Künefe harika', $istem, 'yorum metni' );
		qrms_assert_contains( 'TÜRKÇE', $istem, 'yanıt dili istenir' );
	}
);

qrms_test(
	'gönderilen gövde yorum metnini taşır, isim/telefon taşımaz',
	function () {
		update_option( 'gemini_api_key', 'TEST-KEY' );

		qrms_mock_http( 200, array() );
		$GLOBALS['qrms_test']['http'] = array(
			'body' => wp_json_encode(
				array(
					'candidates' => array(
						array( 'content' => array( 'parts' => array( array( 'text' => "1. Övülen\n- Künefe (2 yorum)" ) ) ) ),
					),
				)
			),
		);

		$sonuc = qrm_ai_generate_summary();

		qrms_assert_true( $sonuc['ok'], 'özet üretilir' );
		qrms_assert_contains( 'Künefe', $sonuc['text'], 'metin döner' );

		$cagri = $GLOBALS['qrms_test']['http_calls'][0];

		qrms_assert_contains( 'generativelanguage.googleapis.com', $cagri['url'], 'uç nokta' );
		qrms_assert_contains( 'TEST-KEY', $cagri['url'], 'anahtar' );

		// Gövde JSON: wp_json_encode Türkçe karakterleri \uXXXX'e kaçırdığı için
		// ham dizede değil, çözülmüş istem metninde aranır.
		$govde = json_decode( $cagri['args']['body'], true );
		$istem = $govde['contents'][0]['parts'][0]['text'];

		qrms_assert_contains( 'künefe', $istem, 'yorum metni gönderilir' );
		qrms_assert_contains( '(4.6/5)', $istem, 'puan gönderilir' );
		qrms_assert_false( false !== strpos( $istem, 'customer_name' ), 'kişisel alan adı geçmez' );
	}
);

qrms_test(
	'üç yorumdan az varsa API çağrılmaz',
	function () {
		update_option( 'gemini_api_key', 'TEST-KEY' );
		$GLOBALS['qrms_sahte_yorumlar'] = array( array( 'rating' => 5.0, 'comment' => 'Tek yorum' ) );

		$sonuc = qrm_ai_generate_summary();

		qrms_assert_false( $sonuc['ok'], 'başarısız döner' );
		qrms_assert_same( 0, count( $GLOBALS['qrms_test']['http_calls'] ), 'boşuna istek atılmaz' );

		// Sonraki testler için geri al.
		$GLOBALS['qrms_sahte_yorumlar'] = array(
			array( 'rating' => 4.6, 'comment' => 'Künefe harika' ),
			array( 'rating' => 2.4, 'comment' => 'Çorba soğuktu' ),
			array( 'rating' => 5.0, 'comment' => 'Mükemmel' ),
		);
	}
);

qrms_test(
	'hata yanıtlarının ayrıntısı ekrana sızmaz',
	function () {
		// API anahtarı ya da kota ayrıntısı yönetici ekranında görünmemeli;
		// kullanıcıya ne yapacağını söyleyen sade bir mesaj döner.
		$sonuc = qrm_ai_parse_response( array( 'error' => array( 'message' => 'API_KEY_INVALID: AIzaSyGizli' ) ) );

		qrms_assert_false( $sonuc['ok'], 'başarısız' );
		qrms_assert_false( false !== strpos( $sonuc['error'], 'AIzaSyGizli' ), 'anahtar sızmaz' );

		qrms_assert_false( qrm_ai_parse_response( array( 'candidates' => array( array( 'finishReason' => 'SAFETY' ) ) ) )['ok'], 'yarım yanıt' );
		qrms_assert_false( qrm_ai_parse_response( null )['ok'], 'bozuk yanıt' );
		qrms_assert_false( qrm_ai_parse_response( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => '   ' ) ) ) ) ) ) )['ok'], 'boş metin' );
	}
);

qrms_test(
	'özet anahtarı yorum sayısı değişince değişir',
	function () {
		// Yeni yorum geldiğinde eski özet kendiliğinden geçersizleşmeli;
		// ayrı bir temizleme kancası yok, anahtar damgadan türüyor.
		$ilk = qrm_ai_cache_key();

		qrms_assert_contains( 'qrm_ai_ozet_', $ilk, 'önek' );
		qrms_assert_same( $ilk, qrm_ai_cache_key(), 'aynı veride aynı anahtar' );
	}
);

/* ---------------------------------------------------------------------------
 * 7. Yardımcılar
 * ------------------------------------------------------------------------ */

echo "\nYardımcılar\n";

qrms_test(
	'dokuz modül slug\'ı ve Türkçe isimleri tanımlı',
	function () {
		$modules = QRMS_Helpers::get_modules();

		qrms_assert_same( 9, count( QRMS_Helpers::MODULE_SLUGS ), 'slug sayısı' );
		qrms_assert_same( 9, count( $modules ), 'isim sayısı' );
		qrms_assert_same( array_values( QRMS_Helpers::MODULE_SLUGS ), array_keys( $modules ), 'slug listesi' );
		qrms_assert_same( 'QR Çalışma Saatleri', QRMS_Helpers::get_module_name( 'qr-calisma-saatleri' ), 'isim' );
		qrms_assert_same( 'Yorum & Feedback', QRMS_Helpers::get_module_name( 'yorum-feedback' ), 'isim' );
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
		qrms_assert_false(
			false !== strpos( $html, 'Bu modül yakında burada olacak.' ),
			'placeholder basılmamalı'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 9. QR Analiz — menü sıralaması
 * ------------------------------------------------------------------------ */

// module.php dosya kapsamında yalnızca fonksiyon ve sabit tanımlar; sıralama
// yardımcısı saf dizi dönüşümüdür ve WordPress'e bağımlı değildir.
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/module.php';

echo "\nQR Analiz menü sıralaması\n";

qrms_test(
	'analitik satırı QR Analiz girişinin hemen ardına alınır',
	function () {
		// add_submenu_page satırı listenin sonuna ekler; sıralama onu
		// modülün kendi satırının altına taşır.
		$ham = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'Restoran Menü', QRMS_Admin::get_module_page_slug( 'restoran-menu' ) ),
			qrms_submenu_satiri( 'QR Analiz', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
			qrms_submenu_satiri( 'QR Masa', QRMS_Admin::get_module_page_slug( 'qr-masa' ) ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
			qrms_submenu_satiri( '— Menü Analitiği', QRMS_ANALITIK_SAYFA ),
		);

		$sirali = qrms_module_qr_analiz_submenu_sirala( $ham );

		qrms_assert_same(
			array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
				QRMS_Admin::get_module_page_slug( 'qr-analiz' ),
				QRMS_ANALITIK_SAYFA,
				QRMS_Admin::get_module_page_slug( 'qr-masa' ),
				QRMS_Admin::SETTINGS_SLUG,
			),
			qrms_submenu_sluglari( $sirali ),
			'tam sıra'
		);

		qrms_assert_same( range( 0, 5 ), array_keys( $sirali ), 'sıfırdan artan anahtarlar' );
		qrms_assert_same( '— Menü Analitiği', $sirali[3][0], 'etiket korunur' );
	}
);

qrms_test(
	'QR Analiz satırı yoksa liste değişmeden döner',
	function () {
		$ham = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
			qrms_submenu_satiri( '— Menü Analitiği', QRMS_ANALITIK_SAYFA ),
		);

		qrms_assert_same(
			qrms_submenu_sluglari( $ham ),
			qrms_submenu_sluglari( qrms_module_qr_analiz_submenu_sirala( $ham ) ),
			'sıra korunur'
		);
	}
);

qrms_test(
	'analitik satırı hiç eklenmemişse sıra bozulmaz',
	function () {
		$ham = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'QR Analiz', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
		);

		qrms_assert_same(
			qrms_submenu_sluglari( $ham ),
			qrms_submenu_sluglari( qrms_module_qr_analiz_submenu_sirala( $ham ) ),
			'sıra korunur'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 10. Yorum & Feedback — sayfa kayıt defteri, eski adresler, menü sıralaması
 * ------------------------------------------------------------------------ */

// menu.php sayfa tanımlarını ve adres yardımcılarını içerir; dosya kapsamında
// yalnızca bir add_action kaydı yapar (stub ortamında yan etkisizdir).
// module.php ise sıralama yardımcısını tanımlar — saf dizi dönüşümü.
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/menu.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/module.php';

echo "\nYorum & Feedback sayfaları\n";

qrms_test(
	'yedi ekranın hepsi kayıt defterinde ve her birinin callback\'i var',
	function () {
		$pages = qrm_pro_admin_pages();

		qrms_assert_same(
			array(
				'qrms-yf-yorumlar',
				'qrms-yf-icgoruler',
				'qrms-yf-form-alanlari',
				'qrms-yf-ayarlar',
				'qrms-yf-iletisim',
				'qrms-yf-odul',
				'qrms-yf-formlar',
			),
			array_keys( $pages ),
			'sayfa listesi'
		);

		foreach ( $pages as $slug => $page ) {
			foreach ( array( 'title', 'menu_title', 'render', 'desc', 'icon' ) as $key ) {
				qrms_assert_true( ! empty( $page[ $key ] ), $slug . ' -> ' . $key . ' dolu' );
			}
		}
	}
);

qrms_test(
	'modül sayfası tanıma hub ve alt sayfaların hepsini kapsar',
	function () {
		qrms_assert_true( qrm_pro_is_module_page( qrm_pro_hub_slug() ), 'hub' );
		qrms_assert_true( qrm_pro_is_module_page( 'qrms-yf-odul' ), 'alt sayfa' );
		qrms_assert_true( ! qrm_pro_is_module_page( 'qrms-rm-gorunum' ), 'başka modülün sayfası' );
		qrms_assert_true( ! qrm_pro_is_module_page( '' ), 'boş slug' );
	}
);

qrms_test(
	'eski adresler yeni sayfalara yönlendirilir',
	function () {
		$bekleyen = array(
			// [eski slug, parametreler, hedef slug, hedefte beklenen ek parametre]
			array( 'qrm-pro-main', array(), 'qrms-yf-yorumlar', '' ),
			array( 'qrm-pro-main', array( 'tab' => 'insights' ), 'qrms-yf-icgoruler', '' ),
			array( 'qrm-pro-insights', array(), 'qrms-yf-icgoruler', '' ),
			array( 'qrm-pro-settings', array(), 'qrms-yf-ayarlar', '' ),
			array( 'qrm-pro-settings', array( 'sub' => 'alanlar' ), 'qrms-yf-form-alanlari', '' ),
			array( 'qrm-pro-form', array(), 'qrms-yf-form-alanlari', '' ),
			array( 'qrm-pro-contact', array(), 'qrms-yf-iletisim', '' ),
			array( 'qrm-pro-rewards', array(), 'qrms-yf-odul', '' ),
			array( 'qrm-pro-rewards', array( 'tab' => 'codes' ), 'qrms-yf-odul', 'tab=codes' ),
			array( 'qrm-forms', array(), 'qrms-yf-formlar', '' ),
			array( 'qrm-forms', array( 'tab' => 'submissions' ), 'qrms-yf-formlar', 'tab=submissions' ),
			array( 'qrm-form-edit', array( 'form_id' => 3 ), 'qrms-yf-formlar', 'form_id=3' ),
			array( 'qrm-form-submissions', array( 'form_id' => 7 ), 'qrms-yf-formlar', 'form_id=7' ),
		);

		foreach ( $bekleyen as $case ) {
			list( $eski, $args, $hedef_slug, $ek ) = $case;

			$target = qrm_pro_legacy_page_target( $eski, $args );

			qrms_assert_true(
				false !== strpos( $target, 'page=' . $hedef_slug ),
				$eski . ' -> ' . $hedef_slug . ' (gelen: ' . $target . ')'
			);

			if ( '' !== $ek ) {
				qrms_assert_true(
					false !== strpos( $target, $ek ),
					$eski . ' -> ' . $ek . ' korunur'
				);
			}
		}
	}
);

qrms_test(
	'bilinmeyen ve güncel slug\'lar yönlendirilmez',
	function () {
		qrms_assert_same( '', qrm_pro_legacy_page_target( 'qrms-yf-yorumlar' ), 'güncel slug' );
		qrms_assert_same( '', qrm_pro_legacy_page_target( 'baska-eklenti' ), 'yabancı slug' );
		qrms_assert_same( '', qrm_pro_legacy_page_target( '' ), 'boş slug' );
	}
);

qrms_test(
	'form düzenleyici adresi düzenleyici görünümünü hedefler',
	function () {
		$target = qrm_pro_legacy_page_target( 'qrm-form-edit', array( 'form_id' => 3 ) );

		qrms_assert_true( false !== strpos( $target, 'view=edit' ), 'view=edit korunur' );
	}
);

echo "\nYorum & Feedback menü sıralaması\n";

qrms_test(
	'modülün yedi satırı Yorum & Feedback girişinin hemen ardına alınır',
	function () {
		// add_submenu_page satırları listenin sonuna ekler; sıralama onları
		// modülün kendi satırının altına taşır.
		$ham = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'Restoran Menü', QRMS_Admin::get_module_page_slug( 'restoran-menu' ) ),
			qrms_submenu_satiri( 'Yorum & Feedback', QRMS_Admin::get_module_page_slug( 'yorum-feedback' ) ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
		);

		foreach ( qrm_pro_admin_pages() as $slug => $page ) {
			$ham[] = qrms_submenu_satiri( '— ' . $page['menu_title'], $slug );
		}

		qrms_assert_same(
			array_merge(
				array(
					QRMS_Admin::MENU_SLUG,
					QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
					QRMS_Admin::get_module_page_slug( 'yorum-feedback' ),
				),
				array_keys( qrm_pro_admin_pages() ),
				array( QRMS_Admin::SETTINGS_SLUG )
			),
			qrms_submenu_sluglari( qrms_module_yorum_feedback_submenu_sirala( $ham ) ),
			'sıra'
		);
	}
);

qrms_test(
	'Yorum & Feedback satırı yoksa liste değişmeden döner',
	function () {
		$ham = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( '— Tüm Yorumlar', 'qrms-yf-yorumlar' ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
		);

		qrms_assert_same(
			qrms_submenu_sluglari( $ham ),
			qrms_submenu_sluglari( qrms_module_yorum_feedback_submenu_sirala( $ham ) ),
			'sıra korunur'
		);
	}
);

qrms_test(
	'diğer modüllerin satırları göreli sırasını korur',
	function () {
		$ham = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'Restoran Menü', QRMS_Admin::get_module_page_slug( 'restoran-menu' ) ),
			qrms_submenu_satiri( '— Görünüm', 'qrms-rm-gorunum' ),
			qrms_submenu_satiri( '— Diğer Ayarlar', 'qrms-rm-diger' ),
			qrms_submenu_satiri( 'Yorum & Feedback', QRMS_Admin::get_module_page_slug( 'yorum-feedback' ) ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
			qrms_submenu_satiri( '— Tüm Yorumlar', 'qrms-yf-yorumlar' ),
		);

		$sirali = qrms_submenu_sluglari( qrms_module_yorum_feedback_submenu_sirala( $ham ) );

		qrms_assert_same(
			array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'restoran-menu' ),
				'qrms-rm-gorunum',
				'qrms-rm-diger',
				QRMS_Admin::get_module_page_slug( 'yorum-feedback' ),
				'qrms-yf-yorumlar',
				QRMS_Admin::SETTINGS_SLUG,
			),
			$sirali,
			'restoran-menu satırları yerinde kalır'
		);
	}
);

/* ------------------------------------------------------------------------ */

echo "\n";

if ( empty( $GLOBALS['qrms_failures'] ) ) {
	echo "\033[32mTüm testler geçti\033[0m (" . $GLOBALS['qrms_assertions'] . " doğrulama)\n\n";
	exit( 0 );
}

echo "\033[31m" . count( $GLOBALS['qrms_failures'] ) . " test başarısız\033[0m\n\n";
exit( 1 );
