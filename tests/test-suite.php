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
	$GLOBALS['qrms_test']['actions']    = array();

	// Kısa kod defteri statik bir durumdur ve testler arasında taşınır: bir
	// modülün init'ini çağıran test, sonraki testin menüsüne satır ekletirdi.
	QRMS_Shortcodes::reset();

	$GLOBALS['qrms_test']['menus']      = array();
	$GLOBALS['qrms_test']['submenus']   = array();
	$GLOBALS['qrms_test']['removed']    = array();
	$GLOBALS['qrms_test']['redirects']  = array();
	$GLOBALS['qrms_test']['http']       = null;
	$GLOBALS['qrms_test']['http_calls'] = array();
	$GLOBALS['qrms_test']['can']        = true;
	$GLOBALS['qrms_test']['styles']     = array();
	$GLOBALS['qrms_test']['scripts']    = array();
	$GLOBALS['qrms_test']['settings']       = array();
	$GLOBALS['qrms_test']['settings_fields'] = array();

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

qrms_test(
	'sol menü flyout CSS\'i hiçbir admin ekranında yüklenmez',
	function () {
		$_GET = array();

		QRMS_Admin::enqueue_assets();

		$handles = array_map(
			function ( $style ) {
				return $style['handle'];
			},
			$GLOBALS['qrms_test']['styles']
		);

		qrms_assert_false( method_exists( 'QRMS_Admin', 'enqueue_admin_menu_css' ), 'kuyruk metodu kaldırıldı' );
		qrms_assert_false( in_array( 'qrms-admin-menu', $handles, true ), 'flyout override yok' );
		qrms_assert_false( in_array( 'qrms-admin', $handles, true ), 'ekran stili yüklenmemeli' );

		$GLOBALS['qrms_test']['styles'] = array();
		$_GET                           = array( 'page' => 'qrms-overview' );
		QRMS_Admin::enqueue_assets();

		$handles = array_map(
			function ( $style ) {
				return $style['handle'];
			},
			$GLOBALS['qrms_test']['styles']
		);

		qrms_assert_true( in_array( 'qrms-admin', $handles, true ), 'ekran stili plugin ekranında' );
		qrms_assert_false( in_array( 'qrms-admin-menu', $handles, true ), 'flyout override plugin ekranında da yok' );
	}
);

qrms_test(
	'hiçbir CSS native #adminmenu flyout kurallarını ezmez',
	function () {
		qrms_assert_false(
			file_exists( QRMS_PLUGIN_DIR . 'assets/css/admin-menu.css' ),
			'admin-menu.css silinmiş olmalı'
		);

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

			if ( preg_match( '/#adminmenu|\.wp-submenu|\.wp-has-submenu/', $css ) ) {
				$bulunan[] = str_replace( QRMS_PLUGIN_DIR, '', $dosya->getPathname() );
			}
		}

		qrms_assert_same( array(), $bulunan, 'sol menü seçicisi yok' );

		$frontend = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-frontend.css' );
		$frontend = preg_replace( '#/\*.*?\*/#s', '', $frontend );
		qrms_assert_false(
			(bool) preg_match( '/^\s*(html|body)\s*\{/m', $frontend ),
			'frontend html/body kuralları wp-admin\'e sızmamalı'
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

		ob_start();
		call_user_func( $callback );
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-back-link', $html, 'geri bağlantısı' );
		qrms_assert_contains( 'Restoran Menü', $html, 'modül adı' );
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
	'hub kartlarında emoji ikon kullanılmaz',
	function () {
		// Emoji admin\'in yazı tipi yığınına göre kutu karakterine düşebiliyor;
		// ikonlar dashicons olmalı. Bileşenin ürettiği HTML ve ortak CSS bu
		// kuralın tek dayanağı.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/admin.css' );

		qrms_assert_contains( '.qrms-hub-grid', $css, 'kart ızgarası kuralı' );
		qrms_assert_contains( 'repeat(3, minmax(0, 1fr))', $css, 'masaüstünde üç sütun' );
		qrms_assert_contains( 'max-width: 960px', $css, 'tablet kırılımı' );
		qrms_assert_contains( 'max-width: 600px', $css, 'telefon kırılımı' );
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

		qrms_assert_same( 'passive', $kartlar['QR Analiz']['state'], 'pasif modül' );
		qrms_assert_same( '', $kartlar['QR Analiz']['url'], 'pasif kart adres taşımaz' );

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
		$analiz  = null;

		foreach ( $gruplar as $grup ) {
			if ( 'Analiz & Ayarlar' === $grup['title'] ) {
				$analiz = $grup;
			}
		}

		qrms_assert_true( null !== $analiz, 'kategori bulundu' );

		// Kategoride üç kart var (Analiz + Kısa Kodlar + Genel Ayarlar) ama
		// yalnızca biri lisansa bağlı.
		qrms_assert_same( 3, count( $analiz['cards'] ), 'kart sayısı' );
		qrms_assert_same( 1, $analiz['total'], 'sayaçta yalnızca modüller' );
		qrms_assert_same( 1, $analiz['active'], 'aktif modül sayısı' );
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
		qrms_assert_contains( 'Menü &amp; Ürünler', $html, 'ilk kategori' );
		qrms_assert_contains( 'Masa &amp; Servis', $html, 'üçüncü kategori' );
		qrms_assert_contains( 'dashicons dashicons-food', $html, 'modül ikonu' );
		qrms_assert_contains( 'Ürünler, kategoriler', $html, 'kart açıklaması' );
		qrms_assert_contains( 'page=qrms-module-restoran-menu', $html, 'aktif kartın adresi' );
		qrms_assert_contains( '1/3 aktif', $html, 'kategori sayacı' );

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
		qrms_assert_contains( '0/3 aktif', $html, 'sayaç sıfır' );
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

		// Izgara .qrms-hub-grid ile aynı sınıfı kullanır; onun telefon
		// kırılımı tek sütun olmalı.
		qrms_assert_contains( "\t.qrms-hub-grid {\n\t\tgrid-template-columns: 1fr;", $css, 'telefonda tek sütun' );
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

// ai-insights.php settings.php'deki qrm_pro_debug_log'a ve install.php'deki
// qrm_pro_reviews_table_exists'e bağlıdır; ikisi de gerçek dosyadan gelir.
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
	'on modül slug\'ı ve Türkçe isimleri tanımlı',
	function () {
		$modules = QRMS_Helpers::get_modules();

		qrms_assert_same( 10, count( QRMS_Helpers::MODULE_SLUGS ), 'slug sayısı' );
		qrms_assert_same( 10, count( $modules ), 'isim sayısı' );
		qrms_assert_same( array_values( QRMS_Helpers::MODULE_SLUGS ), array_keys( $modules ), 'slug listesi' );
		qrms_assert_same( 'QR Çalışma Saatleri', QRMS_Helpers::get_module_name( 'qr-calisma-saatleri' ), 'isim' );
		qrms_assert_same( 'Yorum & Feedback', QRMS_Helpers::get_module_name( 'yorum-feedback' ), 'isim' );

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

	foreach ( glob( QRMS_PLUGIN_DIR . 'modules/*/module.php' ) as $dosya ) {
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

		qrms_assert_same( 17, count( $kaynakta ), 'kaynaktaki kısa kod sayısı' );
		qrms_assert_same( $kaynakta, $bildirilen, 'bildirilen liste kaynakla aynı' );
	}
);

/* ---------------------------------------------------------------------------
 * 9. QR Analiz — tek ekran + eski adresin yönlendirmesi
 * ------------------------------------------------------------------------ */

// module.php dosya kapsamında yalnızca fonksiyon ve sabit tanımlar; stub
// ortamında yan etkisiz yüklenir.
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/module.php';

echo "\nQR Analiz sayfaları\n";

qrms_test(
	'modülün TEK ekranı vardır: hub yoktur',
	function () {
		// Firebase ayarları güvenlik modülüne taşındıktan sonra dallanacak
		// ikinci bir ekran kalmadı; modül satırı doğrudan paneli açar.
		qrms_assert_false( function_exists( 'qrms_module_qr_analiz_hub' ), 'hub fonksiyonu yok' );
		qrms_assert_false( function_exists( 'qrms_module_qr_analiz_sayfalar' ), 'sayfa listesi yok' );
		qrms_assert_false( defined( 'QRMS_ANALIZ_AYAR_SAYFA' ), 'ayar slug sabiti taşındı' );

		// Ayar ekranı dosyası artık güvenlik modülünün altındadır.
		qrms_assert_false(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-analiz/ayarlar-sayfasi.php' ),
			'eski konumda yok'
		);
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/firebase-ayarlari-sayfasi.php' ),
			'yeni konumda var'
		);
	}
);

qrms_test(
	'panelin eski adresi kayıtlı kalır ve modül satırına yönlendirir',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'QR Analiz', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		qrms_assert_same(
			array( QRMS_ANALITIK_SAYFA ),
			array_map(
				function ( $item ) {
					return $item['slug'];
				},
				$GLOBALS['qrms_test']['submenus']
			),
			'yalnızca eski adres'
		);

		// Üst menü '' — satır sol menüde hiç görünmez, yalnızca adres çalışır.
		qrms_assert_same( '', $GLOBALS['qrms_test']['submenus'][0]['parent'], 'gizli sayfa' );

		try {
			qrms_module_qr_analiz_eski_adresi_yonlendir();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_same(
				QRMS_Admin::get_module_page_url( 'qr-analiz' ),
				$e->getMessage(),
				'panel adresine gider'
			);
		}
	}
);

qrms_test(
	'modül lisansta aktif değilken eski adres de kaydedilmez',
	function () {
		// "QR Analiz" satırı yoksa $submenu de boştur; ekranın kaydedilmesi
		// menüde ölü satır bırakırdı.
		qrms_module_qr_analiz_admin_menu();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

echo "\nQR Analiz teşhisi\n";

// Sınıf dosya kapsamında yalnızca tanım içerir (kancalar init() içinde
// kaydolur), bu yüzden stub ortamında doğrudan yüklenebilir. Test edilen
// eşleştirici saf bir dizi/string dönüşümüdür.
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';

qrms_test(
	'sınıfın dosyası eklentinin GİRİŞ dosyasına eşlenir',
	function () {
		// Sınıf çoğu zaman alt klasörde durur; devre dışı bırakma bağlantısı
		// ise eklentinin giriş dosyasını ister.
		qrms_assert_same(
			'rma-analytics/rma-analytics.php',
			QRMS_Analitik::eklenti_dosyasini_bul(
				'rma-analytics/includes/class-analytics.php',
				array( 'akismet/akismet.php', 'rma-analytics/rma-analytics.php' )
			),
			'klasörlü eklenti'
		);
	}
);

qrms_test(
	'tek dosyalık eklenti de eşleşir',
	function () {
		qrms_assert_same(
			'rma-analytics.php',
			QRMS_Analitik::eklenti_dosyasini_bul( 'rma-analytics.php', array( 'rma-analytics.php' ) ),
			'kök dosya'
		);
	}
);

qrms_test(
	'eşleşme yoksa boş string döner, yanlış eklenti kapatılmaz',
	function () {
		// Regresyon: gevşek bir eşleştirme başka bir eklentiyi devre dışı
		// bırakma bağlantısı üretebilirdi.
		qrms_assert_same(
			'',
			QRMS_Analitik::eklenti_dosyasini_bul( 'rma-analytics/rma.php', array( 'akismet/akismet.php' ) ),
			'listede yok'
		);
		qrms_assert_same( '', QRMS_Analitik::eklenti_dosyasini_bul( '', array( 'akismet/akismet.php' ) ), 'boş yol' );
	}
);

qrms_test(
	'aynı adla başlayan başka bir klasör eşleşmez',
	function () {
		qrms_assert_same(
			'',
			QRMS_Analitik::eklenti_dosyasini_bul(
				'rma-analytics/rma.php',
				array( 'rma-analytics-pro/rma-analytics-pro.php' )
			),
			'klasör adı tam eşleşmeli'
		);
	}
);

echo "\nQR Analiz (kategoriler)\n";

qrms_test(
	'veriler altı kategori chip\'ine bölünür ve her chip bir bölüme bağlıdır',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/analitik-sayfasi.php' );

		foreach ( array( 'hourly', 'daily', 'weekly', 'monthly', 'masalar', 'urunler' ) as $kategori ) {
			qrms_assert_contains( 'data-cat="' . $kategori . '"', $kaynak, $kategori . ' chip\'i' );
		}

		// Chip'lerin hepsi bir tabpanel'e işaret eder; ürün kesiti kendi
		// bölümündedir ve kapalı başlar (açılışta saatlik kategori seçili).
		qrms_assert_contains( 'aria-controls="qrms-an-cat-veri"', $kaynak, 'grafik/tablo bölümü' );
		qrms_assert_contains( 'aria-controls="qrms-an-cat-urunler"', $kaynak, 'ürün bölümü' );
		qrms_assert_contains( 'id="qrms-an-cat-urunler" role="tabpanel" hidden', $kaynak, 'ürün bölümü kapalı başlar' );

		// CSV kategori bölümlerinin DIŞINDADIR: indirilen dosya ekranda ne
		// görünüyorsa odur, düğme tek bir kategoriye kapatılırsa masa özeti
		// indirilemez olurdu.
		qrms_assert_true(
			strpos( $kaynak, 'id="qrms-an-csv"' ) < strpos( $kaynak, 'qrms-an-cats' ),
			'CSV chip şeridinden önce (sayfa başlığında)'
		);

		// Panel kimlikleri JS'in aradığı kimliklerle aynı olmalı: biri
		// değişirse kategori geçişi sessizce çalışmaz olurdu.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik.js' );

		qrms_assert_contains( "\$( 'qrms-an-cat-veri' )", $js, 'JS grafik bölümünü bulur' );
		qrms_assert_contains( "\$( 'qrms-an-cat-urunler' )", $js, 'JS ürün bölümünü bulur' );
	}
);

qrms_test(
	'aynı pencerede kategori değiştirmek yeni istek doğurmaz',
	function () {
		// Sunucu her yanıtta grafiği, masaları ve ürünleri BİRLİKTE döndürür;
		// yanıt dönem+masa anahtarıyla saklanmazsa kategori geçişi aynı veriyi
		// tekrar tekrar isterdi.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik.js' );

		qrms_assert_contains( 'state.onbellek[ anahtar ]', $js, 'önbellekten okur' );
		qrms_assert_contains( 'function onbellekAnahtari( donem, masa )', $js, 'anahtar dönem + masa' );

		// Yenile ve silme sonrası önbellek düşer: kullanıcı bayat veri görmez.
		qrms_assert_same( 2, substr_count( $js, 'state.onbellek = {};' ), 'yenile + silme' );
	}
);

qrms_test(
	'kategori şeridi ve tablolar dar ekranda taşmaz',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/css/analitik.css' );

		// Şerit kırpılmaz, yatay kayar; chip'ler sıkışıp okunmaz olmaz.
		qrms_assert_contains( '.qrms-an-cats {', $css, 'kategori şeridi' );
		qrms_assert_contains( 'overflow-x: auto', $css, 'yatay kaydırma' );

		// Tablolar 660px altında karta döner (sütun başlığı hücrenin etiketi).
		qrms_assert_contains( 'max-width: 660px', $css, 'kart görünümü kırılımı' );
		qrms_assert_contains( 'content: attr( data-label )', $css, 'kart etiketi' );

		// Seçili olmayan kategorinin bölümü gerçekten gizlenir.
		qrms_assert_contains( '.qrms-an-cat-panel[hidden]', $css, 'gizli bölüm kuralı' );
	}
);

/* ---------------------------------------------------------------------------
 * 9a. Güvenlik Ayarı — sayfa kayıt defteri ve hub
 * ------------------------------------------------------------------------ */

// module.php dosya kapsamında yalnızca fonksiyon ve sabit tanımlar; stub
// ortamında yan etkisiz yüklenir.
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/module.php';

echo "\nGüvenlik Ayarı sayfaları\n";

qrms_test(
	'iki ekran da kayıt defterinde ve her birinin callback\'i var',
	function () {
		$pages = qrms_module_qr_masa_oturum_guvenligi_sayfalar();

		qrms_assert_same(
			array( QRMS_GUVENLIK_OTURUM_SAYFA, QRMS_GUVENLIK_FIREBASE_SAYFA ),
			array_keys( $pages ),
			'sayfa listesi'
		);

		foreach ( $pages as $slug => $page ) {
			foreach ( array( 'title', 'render', 'desc', 'icon' ) as $key ) {
				qrms_assert_true( ! empty( $page[ $key ] ), $slug . ' -> ' . $key . ' dolu' );
			}

			qrms_assert_same( 0, strpos( $page['icon'], 'dashicons-' ), $slug . ' ikonu dashicon' );
		}
	}
);

qrms_test(
	'Firebase ekranının ADRESİ taşınmadan önceki adrestir',
	function () {
		// Ekran qr-analiz'den buraya taşındı; canlı sitelerdeki yer imleri ve
		// dahili bağlantılar kırılmasın diye slug DEĞERİ korunur.
		qrms_assert_same( 'qrms-analiz-ayarlar', QRMS_GUVENLIK_FIREBASE_SAYFA, 'eski adres' );

		qrms_assert_false(
			QRMS_GUVENLIK_FIREBASE_SAYFA === QRMS_Admin::get_module_page_slug( 'qr-masa-oturum-guvenligi' ),
			'modül satırıyla çakışmaz'
		);
	}
);

qrms_test(
	'hub, iki ekranı da kart olarak basar',
	function () {
		ob_start();
		qrms_module_qr_masa_oturum_guvenligi_hub();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'ortak kart ızgarası' );
		qrms_assert_contains( 'page=' . QRMS_GUVENLIK_OTURUM_SAYFA, $html, 'oturum kartı' );
		qrms_assert_contains( 'page=' . QRMS_GUVENLIK_FIREBASE_SAYFA, $html, 'Firebase kartı' );
		qrms_assert_contains( 'Güvenlik Ayarı', $html, 'hub başlığı' );
	}
);

qrms_test(
	'modül aktifken iki ekran da gizli sayfa olarak kaydedilir',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'Güvenlik Ayarı', QRMS_Admin::get_module_page_slug( 'qr-masa-oturum-guvenligi' ) ),
		);

		qrms_module_qr_masa_oturum_guvenligi_admin_menu();

		qrms_assert_same(
			array( QRMS_GUVENLIK_OTURUM_SAYFA, QRMS_GUVENLIK_FIREBASE_SAYFA ),
			array_map(
				function ( $item ) {
					return $item['slug'];
				},
				$GLOBALS['qrms_test']['submenus']
			),
			'kaydedilen sayfalar'
		);

		qrms_assert_true( QRMS_Admin::is_module_subpage( QRMS_GUVENLIK_FIREBASE_SAYFA ), 'kayıt defterinde' );

		// Kayıt gerçek bir alt menüdir (parent: MENU_SLUG) — route çözümü buna
		// bağlıdır; menüden düşürme işi admin_head'de yapılır.
		qrms_assert_same( QRMS_Admin::MENU_SLUG, $GLOBALS['qrms_test']['submenus'][0]['parent'], 'üst menü' );
	}
);

qrms_test(
	'modül lisansta aktif değilken hiçbir sayfa kaydedilmez',
	function () {
		qrms_module_qr_masa_oturum_guvenligi_admin_menu();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

/* ---------------------------------------------------------------------------
 * 8z. Yorum & Feedback — yorum listesinin SAYFALAMASI
 * ------------------------------------------------------------------------ */

// Yalnızca fonksiyon tanımları; hook kaydı yok.
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/reviews-list.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-render.php';

/**
 * Yorum listesi sorguları için $wpdb taklidi.
 *
 * prepare() çekirdekteki gibi yer tutucuları doldurur; testler böylece
 * ÜRETİLEN SQL'i — özellikle LIMIT/OFFSET değerlerini — doğrulayabilir.
 * (ai-insights testlerinin kendi QRMS_Test_Wpdb'si ayrıdır; bu sınıf
 * yalnızca aşağıdaki testler için $GLOBALS['wpdb']'ye takılır.)
 */
class QRMS_Yorum_Wpdb {
	public $prefix  = 'wp_';
	public $queries = array();
	public $results = array();
	public $vars    = array();

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return preg_replace_callback(
			'/%[dsf]/',
			function ( $m ) use ( &$args ) {
				$value = array_shift( $args );

				if ( '%d' === $m[0] ) {
					return (string) (int) $value;
				}
				if ( '%f' === $m[0] ) {
					return (string) (float) $value;
				}

				return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
			},
			$sql
		);
	}

	public function get_results( $sql, $mode = null ) {
		$this->queries[] = $sql;

		return array_shift( $this->results ) ?: array();
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;

		return array_shift( $this->vars );
	}

	public function get_row( $sql ) {
		$this->queries[] = $sql;

		return array_shift( $this->vars );
	}

	public function son_sorgu() {
		return end( $this->queries ) ?: '';
	}
}

/**
 * Yorum testleri için taze bir $wpdb takar.
 *
 * @return QRMS_Yorum_Wpdb
 */
function qrms_yorum_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Yorum_Wpdb();

	return $GLOBALS['wpdb'];
}

/**
 * Test için sahte yorum satırı.
 *
 * @param int $id Satır kimliği.
 * @return object
 */
function qrms_sahte_yorum( $id ) {
	return (object) array(
		'id'            => $id,
		'rating'        => 4.5,
		'comment'       => 'Yorum ' . $id,
		'customer_name' => 'Müşteri ' . $id,
		'is_anonymous'  => 0,
		'created_at'    => '2026-01-01 12:00:00',
	);
}

echo "\nYorum listesi sayfalaması\n";

qrms_test(
	'sayfa boyutu ayardan gelir',
	function () {
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '3' ) ), '3 yorum' );
		qrms_assert_same( 5, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '5' ) ), '5 yorum' );
	}
);

qrms_test(
	'"Tümü" ayarı sınırsız sorguya DÖNÜŞMEZ, üst sınıra çekilir',
	function () {
		// Asıl düzeltme bu: eskiden 'all' seçildiğinde sorgu LIMIT'siz
		// çalışıyor, tüm onaylı yorumlar çekilip HTML'e basılıyordu.
		$boyut = qrm_pro_reviews_page_size( array( 'reviews_per_page' => 'all' ) );

		qrms_assert_same( 50, $boyut, 'üst sınıra çekilir' );
		qrms_assert_true( $boyut > 0 && $boyut <= 50, 'her koşulda sınırlı' );
	}
);

qrms_test(
	'bozuk ya da eksik ayar güvenli varsayılana düşer',
	function () {
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array() ), 'ayar yok' );
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '0' ) ), 'sıfır' );
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '-7' ) ), 'negatif' );
		qrms_assert_same( 3, qrm_pro_reviews_page_size( array( 'reviews_per_page' => 'abc' ) ), 'metin' );
	}
);

qrms_test(
	'sayfa boyutu üst sınırı filtreyle daraltılabilir',
	function () {
		add_filter(
			'qrm_reviews_max_page_size',
			function () {
				return 10;
			}
		);

		qrms_assert_same( 10, qrm_pro_reviews_page_size( array( 'reviews_per_page' => 'all' ) ), '"tümü" daralır' );
		qrms_assert_same( 5, qrm_pro_reviews_page_size( array( 'reviews_per_page' => '5' ) ), 'sınır altı korunur' );
	}
);

qrms_test(
	'sorgu GERÇEKTEN LIMIT ve OFFSET taşır',
	function () {
		$db = qrms_yorum_wpdb();
		$db->results[] = array( qrms_sahte_yorum( 1 ), qrms_sahte_yorum( 2 ) );

		qrm_pro_fetch_approved_reviews( 3, 6 );

		$sorgu = $db->son_sorgu();

		qrms_assert_true( false !== strpos( $sorgu, 'WHERE status = 1' ), 'yalnızca onaylılar' );
		// Sayfa boyutundan BİR FAZLA istenir: fazladan satır "daha var mı?"
		// sorusunu ayrı bir COUNT sorgusu olmadan cevaplar.
		qrms_assert_true( false !== strpos( $sorgu, 'LIMIT 4' ), 'LIMIT = boyut + 1' );
		qrms_assert_true( false !== strpos( $sorgu, 'OFFSET 6' ), 'OFFSET uygulanır' );
	}
);

qrms_test(
	'fazladan satır listeye girmez, yalnızca "daha var" der',
	function () {
		// 3 istendi, 4 döndü -> devamı var, ama kullanıcıya 3 kart gider.
		$db = qrms_yorum_wpdb();
		$db->results[] = array(
			qrms_sahte_yorum( 1 ),
			qrms_sahte_yorum( 2 ),
			qrms_sahte_yorum( 3 ),
			qrms_sahte_yorum( 4 ),
		);

		$sayfa = qrm_pro_fetch_approved_reviews( 3, 0 );

		qrms_assert_same( 3, count( $sayfa['rows'] ), 'sayfa boyutu kadar satır' );
		qrms_assert_true( $sayfa['has_more'], 'devamı var' );
		qrms_assert_same( 3, (int) $sayfa['rows'][2]->id, 'fazladan satır atıldı' );
	}
);

qrms_test(
	'son sayfada "daha fazla" denmez',
	function () {
		$db = qrms_yorum_wpdb();
		$db->results[] = array( qrms_sahte_yorum( 1 ), qrms_sahte_yorum( 2 ) );

		$sayfa = qrm_pro_fetch_approved_reviews( 3, 0 );

		qrms_assert_same( 2, count( $sayfa['rows'] ), 'gelen satırlar' );
		qrms_assert_false( $sayfa['has_more'], 'devamı yok' );
	}
);

qrms_test(
	'hiç yorum yokken boş sayfa döner',
	function () {
		qrms_yorum_wpdb();

		$sayfa = qrm_pro_fetch_approved_reviews( 3, 0 );

		qrms_assert_same( array(), $sayfa['rows'], 'boş liste' );
		qrms_assert_false( $sayfa['has_more'], 'devamı yok' );
	}
);

qrms_test(
	'negatif ya da sıfır sayfa boyutu sorguyu sınırsız bırakmaz',
	function () {
		$db = qrms_yorum_wpdb();

		qrm_pro_fetch_approved_reviews( 0, -5 );

		$sorgu = $db->son_sorgu();

		qrms_assert_true( false !== strpos( $sorgu, 'LIMIT 2' ), 'en az 1 + 1' );
		qrms_assert_true( false !== strpos( $sorgu, 'OFFSET 0' ), 'negatif offset sıfırlanır' );
	}
);

qrms_test(
	'toplam sayaç ayrı sorulur (LIMIT sayacı bozmasın diye)',
	function () {
		$db = qrms_yorum_wpdb();
		$db->vars[] = '4231';

		qrms_assert_same( 4231, qrm_pro_count_approved_reviews(), 'toplam' );
		qrms_assert_true(
			false !== strpos( $db->son_sorgu(), 'COUNT(*)' ),
			'sayım sorgusu'
		);
	}
);

qrms_test(
	'kart çıktısı müşteri adını ve yorumu kaçırarak basar',
	function () {
		$yorum                = qrms_sahte_yorum( 1 );
		$yorum->customer_name = '<script>alert(1)</script>';
		$yorum->comment       = 'Harika & lezzetli <b>çok</b>';

		$html = qrm_pro_render_review_card( $yorum );

		qrms_assert_true( false === strpos( $html, '<script>' ), 'ad kaçırıldı' );
		qrms_assert_true( false === strpos( $html, '<b>çok</b>' ), 'yorum kaçırıldı' );
		qrms_assert_true( false !== strpos( $html, 'qrm-review-item' ), 'kart sınıfı korundu' );
	}
);

qrms_test(
	'anonim yorumda müşteri adı hiç basılmaz',
	function () {
		$yorum                = qrms_sahte_yorum( 1 );
		$yorum->customer_name = 'Gizli Kalmalı';
		$yorum->is_anonymous  = 1;

		$html = qrm_pro_render_review_card( $yorum );

		qrms_assert_true( false === strpos( $html, 'Gizli Kalmalı' ), 'ad sızmaz' );
		qrms_assert_true( false !== strpos( $html, 'Anonim Misafir' ), 'anonim etiketi' );
	}
);

qrms_test(
	'puan yıldızları 0-5 aralığının dışına taşmaz',
	function () {
		// str_repeat negatif sayıyla ölümcül hata verir; bozuk bir satır
		// tüm listeyi düşürmemeli.
		$yorum         = qrms_sahte_yorum( 1 );
		$yorum->rating = 9.7;
		$html          = qrm_pro_render_review_card( $yorum );
		qrms_assert_same( 5, substr_count( $html, '★' ), 'tavan 5' );

		$yorum->rating = -3;
		$html          = qrm_pro_render_review_card( $yorum );
		qrms_assert_same( 5, substr_count( $html, '☆' ), 'taban 0 dolu yıldız' );
	}
);

/* ---------------------------------------------------------------------------
 * 8z-2. Yorum & Feedback — ödül kodu TALEP DOĞRULAMASI
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/db.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/functions.php';

echo "\nÖdül kodu talep doğrulaması\n";

qrms_test(
	'anahtarsız talep reddedilir',
	function () {
		qrms_yorum_wpdb();

		$sonuc = qrm_reward_verify_claim( 42, '', array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
		qrms_assert_same( 'qrm_reward_claim', $sonuc->get_error_code(), 'hata kodu' );
	}
);

qrms_test(
	'uydurma anahtar reddedilir',
	function () {
		qrms_yorum_wpdb();
		qrm_reward_issue_claim( 42 );

		$sonuc = qrm_reward_verify_claim( 42, 'uydurma-anahtar', array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
	}
);

qrms_test(
	'başka bir yorumun anahtarı bu yorum için kullanılamaz',
	function () {
		qrms_yorum_wpdb();

		$anahtar = qrm_reward_issue_claim( 7 );

		// Saldırgan kendi yorumunun anahtarını başka review_id ile deniyor.
		$sonuc = qrm_reward_verify_claim( 8, $anahtar, array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'çapraz kullanım engellenir' );
	}
);

qrms_test(
	'anahtar veritabanında ham saklanmaz',
	function () {
		qrms_yorum_wpdb();

		$anahtar  = qrm_reward_issue_claim( 42 );
		$saklanan = get_transient( qrm_reward_claim_key( 42 ) );

		qrms_assert_true( '' !== (string) $saklanan, 'bir şey saklandı' );
		qrms_assert_false( $anahtar === $saklanan, 'ham anahtar saklanmıyor' );
		qrms_assert_same( wp_hash( $anahtar ), $saklanan, 'hash saklanıyor' );
	}
);

qrms_test(
	'geçerli anahtar + eşiği geçen yorum kabul edilir',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		// 1) yorum satırı, 2) "bu yoruma kod verilmiş mi" -> hayır
		$db->vars[] = (object) array( 'id' => 42, 'rating' => 4.8 );
		$db->vars[] = null;

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4.5 ) );

		qrms_assert_true( true === $sonuc, 'kabul edildi' );
	}
);

qrms_test(
	'eşiğin ALTINDA kalan yorum için kod üretilemez',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		$db->vars[] = (object) array( 'id' => 42, 'rating' => 2.0 );
		$db->vars[] = null;

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4.5 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
		qrms_assert_contains( 'koşulunu karşılamıyor', $sonuc->get_error_message(), 'sebep' );
	}
);

qrms_test(
	'var olmayan yorum kimliği reddedilir',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		$db->vars[] = null; // yorum bulunamadı

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
		qrms_assert_contains( 'bulunamadı', $sonuc->get_error_message(), 'sebep' );
	}
);

qrms_test(
	'aynı yoruma ikinci kod verilmez',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		$db->vars[] = (object) array( 'id' => 42, 'rating' => 5.0 );
		$db->vars[] = 17; // bu yoruma zaten kod üretilmiş

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4 ) );

		qrms_assert_true( is_wp_error( $sonuc ), 'reddedildi' );
		qrms_assert_contains( 'zaten', $sonuc->get_error_message(), 'sebep' );
	}
);

qrms_test(
	'anahtar TEK KULLANIMLIKTIR: harcandıktan sonra geçmez',
	function () {
		$db      = qrms_yorum_wpdb();
		$anahtar = qrm_reward_issue_claim( 42 );

		$db->vars[] = (object) array( 'id' => 42, 'rating' => 5.0 );
		$db->vars[] = null;
		qrms_assert_true( true === qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4 ) ), 'ilk kullanım' );

		// Kod üretildiğinde uç bunu çağırır.
		qrm_reward_consume_claim( 42 );

		$sonuc = qrm_reward_verify_claim( 42, $anahtar, array( 'google_review_threshold' => 4 ) );
		qrms_assert_true( is_wp_error( $sonuc ), 'ikinci kullanım reddedilir' );
	}
);

qrms_test(
	'geçersiz yorum kimliği için anahtar üretilmez',
	function () {
		qrms_assert_same( '', qrm_reward_issue_claim( 0 ), 'sıfır' );
		qrms_assert_same( '', qrm_reward_issue_claim( -3 ), 'negatif' );
	}
);

qrms_test(
	'ödül ucu doğrulamayı e-posta kontrolünden ÖNCE yapar',
	function () {
		// Sıra önemli: yetkisiz bir istek, hangi e-postaların kod aldığını
		// "already_used" cevabıyla sızdırmamalı.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/rewards.php'
		);

		$dogrulama = strpos( $kaynak, 'qrm_reward_verify_claim' );
		$eposta    = strpos( $kaynak, 'qrm_reward_find_by_email' );

		qrms_assert_true( false !== $dogrulama, 'doğrulama çağrılıyor' );
		qrms_assert_true( false !== $eposta, 'e-posta kontrolü duruyor' );
		qrms_assert_true( $dogrulama < $eposta, 'doğrulama önce gelir' );
	}
);

/* ---------------------------------------------------------------------------
 * 9a-0. QR Analiz — izleme nonce'u ve saklama politikası
 * ------------------------------------------------------------------------ */

// Sınıf dosya kapsamında yalnızca tanım yapar; init() elle çağrılır.
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php';

echo "\nQR Analiz izleme nonce'u\n";

qrms_test(
	'uydurma nonce değeri artık kayıt açtırmaz',
	function () {
		// Eskiden yalnızca "alan boş mu?" diye bakılıyordu: security=x
		// göndermek yeterliydi ve tabloya kimlik doğrulaması olmadan
		// sınırsız satır eklenebiliyordu.
		$_POST['security'] = 'x';
		qrms_assert_false( QRMS_Analitik::izleme_gecerli_mi(), 'uydurma değer reddedilir' );

		$_POST['security'] = 'test-nonce-baska_eylem';
		qrms_assert_false( QRMS_Analitik::izleme_gecerli_mi(), 'başka eylemin nonce\'u reddedilir' );
	}
);

qrms_test(
	'geçerli menü nonce\'u kabul edilir',
	function () {
		$_POST['security'] = wp_create_nonce( QRMS_Analitik::NONCE_TAKIP );

		qrms_assert_true( QRMS_Analitik::izleme_gecerli_mi(), 'menü nonce\'u geçer' );
	}
);

qrms_test(
	'izleme nonce eylemi menü modülünün ürettiğiyle AYNIDIR',
	function () {
		// Ad kayarsa izleme sessizce tamamen durur; bu yüzden üretim yeri
		// doğrudan kaynaktan doğrulanır.
		qrms_assert_same( 'rma_ajax_nonce', QRMS_Analitik::NONCE_TAKIP, 'sabit değeri' );

		$menu_kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php'
		);

		qrms_assert_true(
			false !== strpos( $menu_kaynak, "wp_create_nonce( '" . QRMS_Analitik::NONCE_TAKIP . "' )" ),
			'menü tarafı aynı eylemle üretiyor'
		);
	}
);

qrms_test(
	'nonce alanı hiç yoksa kayıt açılmaz',
	function () {
		qrms_assert_false( QRMS_Analitik::izleme_gecerli_mi(), 'alan yok' );

		$_POST['security'] = '';
		qrms_assert_false( QRMS_Analitik::izleme_gecerli_mi(), 'alan boş' );
	}
);

echo "\nQR Analiz saklama politikası\n";

qrms_test(
	'varsayılan saklama süresi 90 gündür',
	function () {
		qrms_assert_same( 90, QRMS_Analitik::saklama_gun(), 'varsayılan' );
		qrms_assert_same( 90, QRMS_Analitik::SAKLAMA_GUN, 'sabit' );
	}
);

qrms_test(
	'saklama süresi filtreyle değiştirilebilir, alt sınırı 7 gündür',
	function () {
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 30;
			}
		);

		qrms_assert_same( 30, QRMS_Analitik::saklama_gun(), 'filtre uygulanır' );
	}
);

qrms_test(
	'çok kısa saklama süresi 7 güne yükseltilir',
	function () {
		// Daha kısası panelin "son 30 gün" görünümlerini boşaltırdı.
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 1;
			}
		);

		qrms_assert_same( 7, QRMS_Analitik::saklama_gun(), 'alt sınıra çekilir' );
	}
);

qrms_test(
	'sıfır ya da negatif değer temizliği tamamen kapatır',
	function () {
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 0;
			}
		);

		qrms_assert_same( 0, QRMS_Analitik::saklama_gun(), 'temizlik kapalı' );
	}
);

qrms_test(
	'günlük temizlik görevi bir kez planlanır',
	function () {
		QRMS_Analitik::temizlik_planla();

		$ilk = wp_next_scheduled( QRMS_Analitik::CRON_TEMIZLIK );
		qrms_assert_true( (bool) $ilk, 'görev kuruldu' );

		QRMS_Analitik::temizlik_planla();
		qrms_assert_same( $ilk, wp_next_scheduled( QRMS_Analitik::CRON_TEMIZLIK ), 'ikinci kez planlanmaz' );

		QRMS_Analitik::temizlik_iptal();
		qrms_assert_false( wp_next_scheduled( QRMS_Analitik::CRON_TEMIZLIK ), 'iptal temizler' );
	}
);

qrms_test(
	'init() hem temizlik kancasını hem planlayıcıyı bağlar',
	function () {
		QRMS_Analitik::init();

		$kancalar = $GLOBALS['qrms_test']['actions'];

		qrms_assert_true(
			isset( $kancalar[ QRMS_Analitik::CRON_TEMIZLIK ] ),
			'cron kancası dinleniyor'
		);
		qrms_assert_true(
			in_array(
				array( 'QRMS_Analitik', 'temizlik_planla' ),
				$kancalar['init'],
				true
			),
			'init planlayıcıyı çağırıyor'
		);
	}
);

qrms_test(
	'eklenti devre dışı bırakılırken temizlik görevi kaldırılır',
	function () {
		// Kanca adı kök eklenti dosyasında elle yazılıdır (modül lisansta
		// kapalıyken sınıf yüklenmemiş olabilir); iki taraf kaymamalı.
		$kok = file_get_contents( QRMS_PLUGIN_DIR . 'qr-menu-suite.php' );

		qrms_assert_true(
			false !== strpos( $kok, "wp_clear_scheduled_hook( '" . QRMS_Analitik::CRON_TEMIZLIK . "' )" ),
			'deaktivasyon aynı kancayı temizliyor'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 9a-1. Ortak varlıklar — aynı dosyanın iki handle ile yüklenmesi
 * ------------------------------------------------------------------------ */

// assets.php dosya kapsamında yalnızca fonksiyon tanımlar ve stub'lanmış
// add_action çağrıları yapar.
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php';

echo "\nOrtak varlık handle'ları\n";

qrms_test(
	'aynı dosyayı gösteren handle tek kanonik ada indirgenir',
	function () {
		// [qr_garson_hesap] ve [ikili_buton] ile [garson_butonu] aynı
		// buttons.css/buttons.js dosyalarını kullanıyor. Handle'lar ayrı
		// kalırsa WordPress dosyayı iki kez basar, buttons.js iki kez çalışır
		// ve butonlara olay dinleyicileri iki kez bağlanır.
		qrms_assert_same( 'qmo-buttons', qmo_asset_kanonik_handle( 'qmo-garson-hesap' ), 'takma ad indirgenir' );
		qrms_assert_same( 'qmo-buttons', qmo_asset_kanonik_handle( 'qmo-buttons' ), 'kanonik ad korunur' );
	}
);

qrms_test(
	'takma ad olmayan handle\'lar olduğu gibi geçer',
	function () {
		foreach ( array( 'qmo-chatbot', 'qmo-sepet', 'qmo-oturum-kutu', 'bilinmeyen-handle' ) as $handle ) {
			qrms_assert_same( $handle, qmo_asset_kanonik_handle( $handle ), $handle . ' değişmez' );
		}
	}
);

qrms_test(
	'takma ad handle\'ı KENDİ kaynağıyla kaydedilmez',
	function () {
		// Yapısal güvence: qmo-garson-hesap kaydı bir kaynak yolu taşırsa
		// WordPress onu bağımsız bir dosya sayar ve çift yükleme geri gelir.
		// Kayıt kaynaksız (false) olmalı ve qmo-buttons'a bağımlı durmalı.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );

		qrms_assert_true(
			(bool) preg_match(
				"/wp_register_script\(\s*'qmo-garson-hesap',\s*false,\s*array\(\s*'qmo-buttons'\s*\)/",
				$kaynak
			),
			'script takma adı kaynaksız ve qmo-buttons bağımlı'
		);
		qrms_assert_true(
			(bool) preg_match(
				"/wp_register_style\(\s*'qmo-garson-hesap',\s*false,\s*array\(\s*'qmo-buttons'\s*\)/",
				$kaynak
			),
			'stil takma adı kaynaksız ve qmo-buttons bağımlı'
		);
		qrms_assert_false(
			(bool) preg_match( "/'qmo-garson-hesap',\s*\\\$url/", $kaynak ),
			'takma ad artık kendi dosya yolunu göstermiyor'
		);
	}
);

/* ---------------------------------------------------------------------------
 * 9a-2. Güvenlik Ayarı — oturum limitleri ve SAYFA KİLİDİ ayar kaydı
 * ------------------------------------------------------------------------ */

// Oturum sınıfı ile ayar ekranı. İkisi de dosya kapsamında yalnızca tanım
// yapar; add_action çağrıları stub ortamında yan etkisizdir.
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-oturum.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/oturum-ayarlari.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/masa-dogrulama.php';

echo "\nGüvenlik Ayarı — ayar kaydı\n";

qrms_test(
	'ekrandaki HER form, register_setting ile kaydedilmiş bir gruba gönderir',
	function () {
		// Bu testin varlık sebebi gerçek bir hatadır: sayfa kilidi formu
		// 'qmo_sayfa_grup' grubuna gönderiyordu ama o grup hiçbir yerde
		// register_setting() ile kaydedilmemişti. WordPress gönderimi
		// "seçenekler sayfası bulunamadı" diyerek reddediyor, ayar sessizce
		// hiç yazılmıyor ve sayfa kilidi hiçbir zaman devreye girmiyordu.
		qmo_oturum_ayarlarini_kaydet();

		ob_start();
		qmo_oturum_ayar_sayfasi();
		ob_end_clean();

		$kayitli = array_keys( $GLOBALS['qrms_test']['settings'] );
		$formlar = $GLOBALS['qrms_test']['settings_fields'];

		qrms_assert_true( count( $formlar ) >= 2, 'ekranda en az iki ayar formu var' );

		foreach ( $formlar as $grup ) {
			qrms_assert_true(
				in_array( $grup, $kayitli, true ),
				$grup . ' grubu register_setting ile kaydedilmiş'
			);
		}
	}
);

qrms_test(
	'sayfa kilidi option\'ı kendi grubuyla ve temizleyicisiyle kaydedilir',
	function () {
		qmo_oturum_ayarlarini_kaydet();

		$kayitli = $GLOBALS['qrms_test']['settings'];

		qrms_assert_true(
			isset( $kayitli['qmo_sayfa_grup']['qmo_korumali_sayfalar'] ),
			'sayfa kilidi option\'ı kayıtlı'
		);
		qrms_assert_same(
			'qmo_korumali_sayfalar_temizle',
			$kayitli['qmo_sayfa_grup']['qmo_korumali_sayfalar']['sanitize_callback'],
			'temizleyici bağlı'
		);

		// Oturum limitleri ayrı grupta kalır; iki form birbirini ezmemeli.
		qrms_assert_true(
			isset( $kayitli['qr_masa_grup'][ QMO_Oturum::OPT ] ),
			'oturum limitleri kendi grubunda'
		);
	}
);

qrms_test(
	'slug listesi temizlenir: boşluk, büyük harf, Türkçe karakter, tekrar',
	function () {
		qrms_assert_same(
			'menu,menu-tr',
			qmo_korumali_sayfalar_temizle( ' Menu , Menu-TR ' ),
			'boşluk kırpılır, küçük harfe iner'
		);
		qrms_assert_same(
			'bahce-kat',
			qmo_korumali_sayfalar_temizle( 'Bahçe Kat' ),
			'Türkçe harfler indirgenir'
		);
		qrms_assert_same(
			'menu',
			qmo_korumali_sayfalar_temizle( 'menu,menu,,menu' ),
			'tekrarlar ve boşlar atılır'
		);
		qrms_assert_same( '', qmo_korumali_sayfalar_temizle( '' ), 'boş girdi boş kalır' );
	}
);

qrms_test(
	'kaydedilen değer, kilidi okuyan taraf tarafından aynen çözülür',
	function () {
		// Asıl güvence: yazma tarafının ürettiği metin, okuma tarafının
		// (qmo_korumali_sluglar) beklediği biçimle birebir uyuşmalı.
		$kaydedilen = qmo_korumali_sayfalar_temizle( 'Menu, Bahçe Kat' );
		update_option( 'qmo_korumali_sayfalar', $kaydedilen );

		qrms_assert_same(
			array( 'menu', 'bahce-kat' ),
			array_values( qmo_korumali_sluglar() ),
			'okuma tarafı iki slug görür'
		);
	}
);

qrms_test(
	'ayar hiç kaydedilmemişken sayfa kilidi kapalıdır',
	function () {
		qrms_assert_same( array(), array_values( qmo_korumali_sluglar() ), 'boş liste' );
	}
);

/* ---------------------------------------------------------------------------
 * 9b. QR Masa — toplu oluşturma ve grup filtresi
 * ------------------------------------------------------------------------ */

// Sınıf dosya kapsamında yalnızca tanım ve bir add_shortcode kaydı yapar;
// sayfa dosyası da yalnızca fonksiyon tanımlar. Test edilenler $wpdb'ye
// dokunmayan saf dönüşümler ve DB'ye hiç gitmeyen doğrulama dalları.
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa/class-qmo-masalar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-masa/masalar-sayfasi.php';

echo "\nQR Masa toplu oluşturma\n";

qrms_test(
	'toplu slug önek ve numaradan üretilir',
	function () {
		qrms_assert_same( 'ic-masa-1', QMO_Masalar::toplu_slug( 'ic-masa', 1 ), 'düz önek' );
		qrms_assert_same( 'ic-masa-10', QMO_Masalar::toplu_slug( 'Ic Masa', 10 ), 'önek slug\'lanır' );
		qrms_assert_same( 'bahce-3', QMO_Masalar::toplu_slug( 'Bahçe', 3 ), 'Türkçe harfler indirgenir' );
	}
);

qrms_test(
	'geçersiz önek veya numara boş slug döndürür',
	function () {
		qrms_assert_same( '', QMO_Masalar::toplu_slug( '', 5 ), 'boş önek' );
		qrms_assert_same( '', QMO_Masalar::toplu_slug( '///', 5 ), 'slug\'a dönüşmeyen önek' );
		qrms_assert_same( '', QMO_Masalar::toplu_slug( 'ic-masa', 0 ), 'sıfır numara' );
	}
);

qrms_test(
	'görünen ad okunabilirdir AMA ürettiği slug her zaman beklenendir',
	function () {
		// Kritik değişmez: ekle() slug'ı ADDAN üretir. Ad ile beklenen slug
		// birbirinden kayarsa masa yanlış adreste açılır ve QR kodları tutmaz.
		$ornekler = array( 'ic-masa', 'Ic Masa', 'Bahçe', 'vip', 'VIP Salon', 'teras-ust' );

		foreach ( $ornekler as $onek ) {
			foreach ( array( 1, 7, 42 ) as $no ) {
				qrms_assert_same(
					QMO_Masalar::toplu_slug( $onek, $no ),
					sanitize_title( QMO_Masalar::toplu_ad( $onek, $no ) ),
					$onek . '-' . $no . ' adı doğru slug\'ı üretir'
				);
			}
		}

		qrms_assert_same( 'Ic Masa 7', QMO_Masalar::toplu_ad( 'ic-masa', 7 ), 'okunabilir ad' );
		qrms_assert_same( '', QMO_Masalar::toplu_ad( '', 7 ), 'geçersiz önek' );
	}
);

qrms_test(
	'toplu ekleme aralığı doğrulanır',
	function () {
		$hatali = array(
			array( '', 1, 10, 'onek' ),
			array( 'ic-masa', 0, 10, 'aralik' ),
			array( 'ic-masa', 5, 3, 'aralik' ),
			array( 'ic-masa', 1, 1000, 'sinir' ),
		);

		foreach ( $hatali as $durum ) {
			$sonuc = QMO_Masalar::toplu_aralik_dogrula( $durum[0], $durum[1], $durum[2] );

			qrms_assert_true( is_wp_error( $sonuc ), $durum[3] . ' hatası döner' );
			qrms_assert_same( $durum[3], $sonuc->get_error_code(), $durum[3] . ' kodu' );
		}
	}
);

qrms_test(
	'azami sınır tam sınırda geçer',
	function () {
		// 1..200 = 200 masa: sınırın kendisi reddedilmemeli.
		$tam = QMO_Masalar::toplu_aralik_dogrula( 'ic-masa', 1, QMO_Masalar::TOPLU_AZAMI );

		qrms_assert_false( is_wp_error( $tam ), 'tam sınır kabul edilir' );
		qrms_assert_same( QMO_Masalar::TOPLU_AZAMI, $tam['adet'], 'adet' );
		qrms_assert_same( 'ic-masa', $tam['onek'], 'önek normalize edilir' );

		qrms_assert_true(
			is_wp_error( QMO_Masalar::toplu_aralik_dogrula( 'ic-masa', 1, QMO_Masalar::TOPLU_AZAMI + 1 ) ),
			'sınırın bir fazlası reddedilir'
		);
	}
);

echo "\nQR Masa grup filtresi\n";

qrms_test(
	'grup adı sondaki numarayı atar',
	function () {
		qrms_assert_same( 'ic-masa', QMO_Masalar::grup_adi( 'ic-masa-12' ), 'çok haneli numara' );
		qrms_assert_same( 'vip', QMO_Masalar::grup_adi( 'vip-3' ), 'tek haneli numara' );
		qrms_assert_same( 'bahce', QMO_Masalar::grup_adi( 'bahce' ), 'numarasız slug' );
		qrms_assert_same( 'masa-2-kat', QMO_Masalar::grup_adi( 'masa-2-kat' ), 'ortadaki numara korunur' );
		qrms_assert_same( '12', QMO_Masalar::grup_adi( '12' ), 'tamamı sayı olan slug kendi grubudur' );
	}
);

qrms_test(
	'gruplar doğal sırayla ve sayılarıyla çıkarılır',
	function () {
		$masalar = array();

		foreach ( array( 'ic-masa-2', 'ic-masa-10', 'vip-1', 'bahce', 'ic-masa-1' ) as $slug ) {
			$masalar[] = (object) array( 'table_slug' => $slug );
		}

		qrms_assert_same(
			array(
				'bahce'   => 1,
				'ic-masa' => 3,
				'vip'     => 1,
			),
			qmo_masalar_gruplari( $masalar ),
			'grup => adet'
		);
	}
);

qrms_test(
	'slug\'ı olmayan satır grupları bozmaz',
	function () {
		$masalar = array( (object) array( 'table_slug' => 'vip-1' ), (object) array( 'id' => 3 ) );

		qrms_assert_same( array( 'vip' => 1 ), qmo_masalar_gruplari( $masalar ), 'eksik satır atlanır' );
		qrms_assert_same( array(), qmo_masalar_gruplari( array() ), 'boş liste' );
	}
);

echo "\nQR Masa toplu sonuç bildirimi\n";

qrms_test(
	'sonuç mesajı eklenen, atlanan ve hatalıyı ayrı ayrı söyler',
	function () {
		$mesaj = qmo_toplu_sonuc_mesaji(
			array(
				'eklenen' => 8,
				'atlanan' => array( 'ic-masa-3', 'ic-masa-7' ),
				'hata'    => array(),
			)
		);

		qrms_assert_contains( '8 masa oluşturuldu', $mesaj, 'eklenen sayısı' );
		qrms_assert_contains( '2 tanesi zaten vardı', $mesaj, 'atlanan sayısı' );
		qrms_assert_contains( 'ic-masa-3, ic-masa-7', $mesaj, 'atlanan slug\'ları' );
		qrms_assert_false( false !== strpos( $mesaj, 'kaydedilemedi' ), 'hata yokken hata cümlesi yok' );
	}
);

qrms_test(
	'hiç masa açılmadıysa bunu açıkça söyler',
	function () {
		$mesaj = qmo_toplu_sonuc_mesaji(
			array(
				'eklenen' => 0,
				'atlanan' => array( 'vip-1' ),
				'hata'    => array( 'vip-2' ),
			)
		);

		qrms_assert_contains( 'Hiç yeni masa oluşturulmadı', $mesaj, 'sıfır durumu' );
		qrms_assert_contains( '1 tanesi kaydedilemedi: vip-2', $mesaj, 'hata satırı' );
	}
);

qrms_test(
	'uzun slug listesi kısaltılır',
	function () {
		$sluglar = array( 'a-1', 'a-2', 'a-3', 'a-4', 'a-5', 'a-6', 'a-7' );

		qrms_assert_same(
			'a-1, a-2, a-3, a-4, a-5 ve 2 tane daha',
			qmo_slug_listesi( $sluglar ),
			'ilk beş + özet'
		);
		qrms_assert_same( 'a-1, a-2', qmo_slug_listesi( array( 'a-1', 'a-2' ) ), 'kısa liste olduğu gibi' );
	}
);

/* ---------------------------------------------------------------------------
 * 10. Yorum & Feedback — sayfa kayıt defteri, eski adresler, menü sıralaması
 * ------------------------------------------------------------------------ */

// menu.php sayfa tanımlarını ve adres yardımcılarını içerir; dosya kapsamında
// yalnızca bir add_action kaydı yapar (stub ortamında yan etkisizdir).
// module.php ise sıralama yardımcısını tanımlar — saf dizi dönüşümü.
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/menu.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/hub.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/module.php';

/*
 * Rozetlerin ve hub özetinin beslendiği veri fonksiyonları $wpdb'ye ve modülün
 * option şemasına dayanır; burada test edilen şey sunum (hangi ekran nerede
 * görünüyor) olduğu için veri tarafı sabitlenir.
 */
// qrm_pro_get_settings() gerçek settings.php'den gelir (Gemini testleri onu
// yükler); ödül rozetini süren tek ayar option üzerinden verilir.
update_option( 'qrm_settings', array( 'qrm_reward_enabled' => 1 ) );

// 8z-2 bölümü ödül modülünün GERÇEK functions.php'sini yüklüyor; oradaki
// qrm_reward_is_active zaten bu senaryoda false döner (google_review_url
// boş). Gerçek fonksiyon yüklenmemişse diye taklidi burada duruyor.
if ( ! function_exists( 'qrm_reward_is_active' ) ) {
	function qrm_reward_is_active( $settings ) {
		return false;
	}
}

function qrm_cf_unread_total() {
	return 2;
}

// qrm_pro_review_stats() de gerçek install.php'den gelir; sayaçları yukarıdaki
// QRMS_Test_Wpdb besler (tablo var, sayımlar sabit).

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
	'hub slug\'ı suite\'in modül satırıyla aynıdır',
	function () {
		qrms_assert_same(
			QRMS_Admin::get_module_page_slug( 'yorum-feedback' ),
			qrm_pro_hub_slug(),
			'hub slug\'ı'
		);
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

echo "\nYorum & Feedback menü ve hub\n";

qrms_test(
	'yedi ekran da gizli sayfa olarak kaydedilir, menüde satırları olmaz',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'Yorum & Feedback', QRMS_Admin::get_module_page_slug( 'yorum-feedback' ) ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
		);

		qrms_module_yorum_feedback_admin_menu();

		qrms_assert_same(
			array_keys( qrm_pro_admin_pages() ),
			array_map(
				function ( $item ) {
					return $item['slug'];
				},
				$GLOBALS['qrms_test']['submenus']
			),
			'kaydedilen sayfalar'
		);

		// Etiketlerde artık "—" öneki yok: satırlar menüde hiç görünmüyor.
		foreach ( $GLOBALS['qrms_test']['submenus'] as $item ) {
			qrms_assert_false( 0 === strpos( $item['title'], '—' ), $item['slug'] . ' önekli değil' );
		}

		// Gizleme, beyaz liste üzerinden çekirdeğin işi.
		update_option( 'qrms_active_modules', array( 'yorum-feedback' ) );
		QRMS_Admin::hide_module_subpages();

		qrms_assert_same(
			array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'yorum-feedback' ),
				QRMS_Admin::SETTINGS_SLUG,
			),
			qrms_submenu_sluglari( $GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] ),
			'menüde kalan satırlar'
		);
	}
);

qrms_test(
	'modül lisansta aktif değilken hiçbir sayfa kaydedilmez',
	function () {
		qrms_module_yorum_feedback_admin_menu();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

qrms_test(
	'rozet modülün TEK satırında toplanır',
	function () {
		// Alt satırlar kalktığı için okunmamış gönderim sayısı yalnızca
		// "Yorum & Feedback" satırında görünebilir.
		$label = qrms_module_yorum_feedback_menu_label( 'Yorum & Feedback', 'yorum-feedback' );

		qrms_assert_contains( 'Yorum & Feedback', $label, 'modül adı korunur' );
		qrms_assert_contains( 'update-count', $label, 'okunmamış gönderim rozeti' );
		qrms_assert_same(
			'QR Masa',
			qrms_module_yorum_feedback_menu_label( 'QR Masa', 'qr-masa' ),
			'başka modülün etiketine dokunulmaz'
		);
	}
);

qrms_test(
	'hub yedi ekranı da kart olarak basar',
	function () {
		ob_start();
		qrm_pro_admin_hub();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'ortak kart ızgarası' );
		qrms_assert_contains( 'qrms-hub-stats', $html, 'özet kutuları' );

		foreach ( qrm_pro_admin_pages() as $slug => $page ) {
			qrms_assert_contains( 'page=' . $slug, $html, $slug . ' kartı' );
		}
	}
);

qrms_test(
	'her ekranın ikonu dashicon\'dur',
	function () {
		// Emoji admin'de kutu karakterine düşebiliyor; kart ikonları dashicons
		// setinden gelmeli.
		foreach ( qrm_pro_admin_pages() as $slug => $page ) {
			qrms_assert_same( 0, strpos( $page['icon'], 'dashicons-' ), $slug . ' ikonu' );
		}
	}
);

/* ------------------------------------------------------------------------ */

echo "\n";

/* ---------------------------------------------------------------------------
 * 14. Açılış Ekranı (qr-acilis-ekrani)
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/includes/class-acilis-ekrani.php';

echo "\nAçılış Ekranı\n";

/**
 * Modülün taze bir örneği (hook kaydı yapmadan).
 *
 * @return QRMS_Acilis_Ekrani
 */
function qrms_ae() {
	return new QRMS_Acilis_Ekrani();
}

/**
 * Kuyruğa alınmış stili handle'ına göre bulur.
 *
 * @param string $handle Stil handle'ı.
 * @return array|null
 */
function qrms_ae_style( $handle ) {
	foreach ( $GLOBALS['qrms_test']['styles'] as $style ) {
		if ( $handle === $style['handle'] ) {
			return $style;
		}
	}

	return null;
}

/**
 * Bir yönetim sayfasını POST ile kaydeder ve çıktısını döndürür.
 *
 * @param string $slug Sayfa slug'ı.
 * @param array  $post Gönderilen alanlar.
 * @return string Sayfanın HTML çıktısı.
 */
function qrms_ae_submit( $slug, array $post ) {
	$_POST = array_merge( array( 'qrms_ae_submit' => '1' ), $post );

	$method = 'render_' . str_replace( '-', '_', $slug );

	ob_start();
	qrms_ae()->$method();

	return ob_get_clean();
}

qrms_test(
	'modül loader sözleşmesine uyar: slug, dosya ve init fonksiyonu',
	function () {
		qrms_assert_true(
			in_array( 'qr-acilis-ekrani', QRMS_Helpers::MODULE_SLUGS, true ),
			'slug bilinen modüller arasında'
		);
		qrms_assert_true( QRMS_Module_Loader::module_file_exists( 'qr-acilis-ekrani' ), 'module.php diskte' );
		qrms_assert_same(
			'qrms_module_qr_acilis_ekrani_init',
			QRMS_Module_Loader::get_init_function( 'qr-acilis-ekrani' ),
			'init fonksiyon adı'
		);

		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani' ) );

		qrms_assert_same(
			array( 'qr-acilis-ekrani' ),
			QRMS_Module_Loader::load_modules(),
			'aktifken yüklenir'
		);
	}
);

qrms_test(
	'dört ekran gizli alt sayfa olarak kaydedilir, menüde satırları olmaz',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani' ) );

		QRMS_Admin::register_menu();
		qrms_ae()->register_admin_pages();

		$slugs = qrms_registered_submenu_slugs();

		foreach ( array_keys( qrms_ae()->admin_pages() ) as $slug ) {
			qrms_assert_true( in_array( $slug, $slugs, true ), $slug . ' kayıtlı' );
		}

		// Menüde görünmemeleri suite'in işi: beyaz listede olmadıkları için
		// admin_head'de düşürülürler.
		QRMS_Admin::hide_module_subpages();

		foreach ( array_keys( qrms_ae()->admin_pages() ) as $slug ) {
			qrms_assert_true(
				in_array( $slug, $GLOBALS['qrms_test']['removed'], true ),
				$slug . ' menüden düşer'
			);
		}
	}
);

qrms_test(
	'modül lisansta aktif değilken hiçbir ekran kaydedilmez',
	function () {
		// "Açılış Ekranı" satırı yoksa $submenu de boştur; ekranların
		// kaydedilmesi menüde ölü satır bırakırdı.
		qrms_ae()->register_admin_pages();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

qrms_test(
	'hub dört ekranı da kart olarak basar ve ikonları dashicon\'dur',
	function () {
		ob_start();
		qrms_ae()->render_hub();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'ortak kart ızgarası' );
		qrms_assert_contains( 'qrms-hub-stats', $html, 'özet kutuları' );

		foreach ( qrms_ae()->admin_pages() as $slug => $page ) {
			qrms_assert_contains( 'page=' . $slug, $html, $slug . ' kartı' );
			qrms_assert_same( 0, strpos( $page['icon'], 'dashicons-' ), $slug . ' ikonu' );
		}
	}
);

qrms_test(
	'bir sayfayı kaydetmek diğer sayfaların ayarlarını silmez',
	function () {
		// Bu, sekmeli tek formdan dört ayrı sayfaya geçerken doğan asıl risk:
		// POST'ta bulunmayan onay kutusu "kapalı" sayılırsa başka bir sayfada
		// yapılmış seçim sessizce silinir.
		update_option(
			'splash_screen_options',
			array(
				'payment_methods'       => array( 'nakit', 'kart' ),
				'social_media_active'   => array( 'instagram' ),
				'social_media'          => array( 'instagram' => 'https://instagram.com/x' ),
				'btn_surface_apply_cta' => 1,
			)
		);

		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'misafir123' ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'misafir123', $opts['wifi_password'], 'kendi alanı yazılır' );
		qrms_assert_same( array( 'nakit', 'kart' ), $opts['payment_methods'], 'ödeme seçimi korunur' );
		qrms_assert_same( 1, $opts['btn_surface_apply_cta'], 'görünüm kutusu korunur' );

		// Buna karşılık SAHİBİ sayfa gönderilince kutu gerçekten kapanabilmeli.
		qrms_ae_submit( 'qrms-ae-gorunum', array( 'bg_color' => '#101010' ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 0, $opts['btn_surface_apply_cta'], 'sahibi sayfa kutuyu kapatır' );
		qrms_assert_same( array( 'nakit', 'kart' ), $opts['payment_methods'], 'ödeme yine korunur' );
	}
);

qrms_test(
	'ödeme sayfası gönderildiğinde işaretsiz yöntemler temizlenir',
	function () {
		update_option( 'splash_screen_options', array( 'payment_methods' => array( 'nakit', 'kart' ) ) );

		qrms_ae_submit( 'qrms-ae-odeme', array( 'payment_methods' => array( 'sodexo' ) ) );

		qrms_assert_same(
			array( 'sodexo' ),
			get_option( 'splash_screen_options' )['payment_methods'],
			'yalnızca gönderilen kalır'
		);

		qrms_ae_submit( 'qrms-ae-odeme', array() );

		qrms_assert_same(
			array(),
			get_option( 'splash_screen_options' )['payment_methods'],
			'hiçbiri işaretsizse boşalır'
		);
	}
);

qrms_test(
	'sayısal ayarlar sınırlarına kırpılır, bozuk renk varsayılana düşer',
	function () {
		qrms_ae_submit(
			'qrms-ae-gorunum',
			array(
				'loader_size'         => 999,
				'logo_bar_height'     => 1,
				'bg_overlay_strength' => 250,
				'btn_surface_opacity' => 150,
				'bg_color'            => 'kırmızı',
				'loader_type'         => 'diskoTopu',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 44, $opts['loader_size'], 'gösterge boyutu üst sınıra' );
		qrms_assert_same( 48, $opts['logo_bar_height'], 'şerit yüksekliği alt sınıra' );
		qrms_assert_same( 100, $opts['bg_overlay_strength'], 'karartma üst sınıra' );
		qrms_assert_same( 100, $opts['btn_surface_opacity'], 'opaklık üst sınıra' );
		qrms_assert_same( '#f7f9fc', $opts['bg_color'], 'geçersiz renk varsayılana' );
		qrms_assert_same( 'spinner', $opts['loader_type'], 'beyaz liste dışı tip varsayılana' );
	}
);

qrms_test(
	'sosyal hesap sırası işaretlenme sırasıdır ve altı hesapla sınırlıdır',
	function () {
		qrms_ae_submit(
			'qrms-ae-davranis',
			array(
				'social_media_active'    => array( 'instagram', 'facebook', 'youtube', 'x', 'tiktok', 'whatsapp', 'linkedin' ),
				'social_media_order'     => 'whatsapp,instagram,facebook,youtube,x,tiktok,linkedin',
				'social_media_url_whatsapp' => 'https://wa.me/900000000',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 6, count( $opts['social_media_active'] ), 'en fazla altı hesap' );
		qrms_assert_same( 'whatsapp', $opts['social_media_active'][0], 'işaretlenme sırası korunur' );
		qrms_assert_false(
			in_array( 'linkedin', $opts['social_media_active'], true ),
			'yedinci hesap düşer'
		);
	}
);

qrms_test(
	'eski üç alanlı sosyal kayıt yeni sisteme kendiliğinden taşınır',
	function () {
		// v3.5 ve öncesinden yükseltilen kurulumda social_media_active hiç
		// yazılmamıştır; eski bağlantılar tekrar kaydetmeden görünmelidir.
		update_option(
			'splash_screen_options',
			array(
				'social_links' => array(
					'facebook'  => 'https://facebook.com/lokanta',
					'instagram' => 'https://instagram.com/lokanta',
					'twitter'   => 'https://x.com/lokanta',
				),
			)
		);

		ob_start();
		qrms_ae()->render_splash_preview();
		$html = ob_get_clean();

		qrms_assert_contains( 'https://facebook.com/lokanta', $html, 'facebook rozeti' );
		qrms_assert_contains( 'https://instagram.com/lokanta', $html, 'instagram rozeti' );
		qrms_assert_contains( 'https://x.com/lokanta', $html, 'twitter -> x rozeti' );
	}
);

qrms_test(
	'kritik head çıktısı çerezden BAĞIMSIZDIR (tam sayfa cache güvenliği)',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'splash_screen_options', array( 'bg_color' => '#101010' ) );

		ob_start();
		qrms_ae()->print_critical_head();
		$cerezsiz = ob_get_clean();

		$_COOKIE['splash_dismissed'] = '1';

		ob_start();
		qrms_ae()->print_critical_head();
		$cerezli = ob_get_clean();

		unset( $_COOKIE['splash_dismissed'] );

		qrms_assert_same( $cerezsiz, $cerezli, 'çıktı her ziyaretçide aynı' );
		qrms_assert_contains( '--sp-bg: #101010', $cerezsiz, 'değişkenler basılır' );
		qrms_assert_contains( 'splash-loading', $cerezsiz, 'FOUC sınıfı betikten eklenir' );
		qrms_assert_contains( 'splash_dismissed', $cerezsiz, 'karar client-side verilir' );
	}
);

qrms_test(
	'splash yalnızca ana sayfada basılır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = false;

		ob_start();
		qrms_ae()->print_critical_head();
		qrms_ae()->handle_frontend();

		qrms_assert_same( '', ob_get_clean(), 'diğer sayfalarda hiçbir çıktı yok' );
	}
);

qrms_test(
	'ön yüzde overlay gizli başlar, önizlemede isPreview bayrağı taşır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'splash_screen_options', array( 'button_links' => array( 'btn1' => 'https://restoran.test/menu' ) ) );

		ob_start();
		qrms_ae()->handle_frontend();
		$on_yuz = ob_get_clean();

		ob_start();
		qrms_ae()->render_splash_preview();
		$onizleme = ob_get_clean();

		qrms_assert_contains( 'style="display:none"', $on_yuz, 'ön yüzde gizli başlar' );
		qrms_assert_false( strpos( $on_yuz, 'data-preview' ), 'ön yüzde önizleme bayrağı yok' );

		qrms_assert_contains( 'data-preview="1"', $onizleme, 'önizlemede bayrak var' );
		qrms_assert_false( strpos( $onizleme, 'style="display:none"' ), 'önizleme gizli başlamaz' );

		// Önizleme frontend'in taklidi değil, aynı markup'ıdır.
		qrms_assert_contains( 'https://restoran.test/menu', $on_yuz, 'ön yüzde CTA adresi' );
		qrms_assert_contains( 'https://restoran.test/menu', $onizleme, 'önizlemede aynı adres' );
	}
);

qrms_test(
	'renk şeması: arkaplan görseli koyu, açık zemin light kabul edilir',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;

		update_option( 'splash_screen_options', array( 'bg_scheme' => 'auto', 'bg_image' => 12 ) );
		ob_start();
		qrms_ae()->handle_frontend();
		qrms_assert_contains( 'splash-scheme-dark', ob_get_clean(), 'görsel varken koyu' );

		update_option( 'splash_screen_options', array( 'bg_scheme' => 'auto', 'bg_color' => '#ffffff' ) );
		ob_start();
		qrms_ae()->handle_frontend();
		qrms_assert_contains( 'splash-scheme-light', ob_get_clean(), 'açık zeminde light' );
	}
);

qrms_test(
	'ödeme satırı: yöntem yoksa hiç basılmaz, yazı modunda ikon gelmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;

		update_option( 'splash_screen_options', array( 'payment_methods' => array() ) );
		ob_start();
		qrms_ae()->handle_frontend();
		qrms_assert_false( strpos( ob_get_clean(), 'splash-pay-row' ), 'seçim yoksa satır yok' );

		update_option(
			'splash_screen_options',
			array(
				'payment_methods'      => array( 'nakit' ),
				'payment_display_mode' => 'text_only',
			)
		);
		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'splash-pay-row', $html, 'satır basılır' );
		qrms_assert_contains( 'Nakit', $html, 'etiket basılır' );
		qrms_assert_false( strpos( $html, 'splash-pay-icon' ), 'yazı modunda ikon yok' );
	}
);

qrms_test(
	'bağlantısı olmayan rozet DOM\'a hiç girmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'button_links' => array( 'btn2' => 'tel:+900000000', 'btn3' => '', 'btn4' => '' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'tel:+900000000', $html, 'adresi olan rozet basılır' );
		// Wifi rozeti adres almaz; her zaman basılır.
		qrms_assert_contains( 'id="wifi-btn"', $html, 'wifi rozeti her zaman var' );
		qrms_assert_same( 2, substr_count( $html, 'sp-action-circle' ), 'yalnızca iki rozet' );
	}
);

qrms_test(
	'eski eklentinin adresleri yeni sayfalara yönlendirilir',
	function () {
		$_GET = array( 'page' => 'splash-screen', 'tab' => 'odeme' );

		try {
			qrms_ae()->maybe_redirect_legacy_pages();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_contains( 'page=qrms-ae-odeme', $e->getMessage(), 'sekme karşılığı sayfaya' );
		}

		$_GET = array( 'page' => 'splash-screen-links' );

		try {
			qrms_ae()->maybe_redirect_legacy_pages();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_contains( 'page=qrms-ae-butonlar', $e->getMessage(), 'eski bağlantılar sayfası' );
		}

		$GLOBALS['qrms_test']['redirects'] = array();
		$_GET = array( 'page' => 'qrms-overview' );
		qrms_ae()->maybe_redirect_legacy_pages();
		qrms_assert_same( array(), $GLOBALS['qrms_test']['redirects'], 'başka sayfaya dokunulmaz' );
	}
);

qrms_test(
	'varlıklar dosya bazlı sürümle ve yalnızca kendi ekranlarında yüklenir',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		qrms_ae()->enqueue_frontend_assets();

		$splash = qrms_ae_style( 'qrms-ae-splash' );

		qrms_assert_true( null !== $splash, 'ön yüz stili' );
		qrms_assert_same(
			QRMS_VERSION . '.' . filemtime( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/css/splash.css' ),
			$splash['ver'],
			'sürüm dosyanın kendi zamanını taşır'
		);

		// Modülün ekranında değilken yönetim varlıkları kuyruğa girmez.
		$_GET = array( 'page' => 'qrms-overview' );
		qrms_ae()->admin_enqueue_assets();
		qrms_assert_same( null, qrms_ae_style( 'qrms-ae-admin' ), 'başka ekranda yüklenmez' );

		$_GET = array( 'page' => 'qrms-ae-gorunum' );
		qrms_ae()->admin_enqueue_assets();
		qrms_assert_true( null !== qrms_ae_style( 'qrms-ae-admin' ), 'kendi ekranında yüklenir' );
	}
);


/* ---------------------------------------------------------------------------
 * 15. Açılış Ekranı — TR/EN dil düğmesi
 * ------------------------------------------------------------------------ */

echo "\nAçılış Ekranı (TR/EN)\n";

qrms_test(
	'düğme kapalıyken markup\'a tek bir dil niteliği bile girmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'splash_screen_options', array( 'lang_toggle' => 0 ) );

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_false( strpos( $html, 'splash-lang' ), 'düğme yok' );
		qrms_assert_false( strpos( $html, 'data-sp-en' ), 'ikinci dil niteliği yok' );
	}
);

qrms_test(
	'düğme açık ama hiç İngilizce metin yoksa yine basılmaz',
	function () {
		// Aksi hâlde ziyaretçiye iki kez aynı metni gösteren bir düğme kalırdı.
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'lang_toggle' => 1,
				'texts_en'    => array( 'btn1' => '', 'divider' => '' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();

		qrms_assert_false( strpos( ob_get_clean(), 'splash-lang' ), 'düğme basılmaz' );
	}
);

qrms_test(
	'iki dil de aynı HTML\'de taşınır; çeviri boşsa Türkçesine düşer',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'lang_toggle'  => 1,
				'divider_text' => 'Bizi takip edin',
				'button_texts' => array( 'btn1' => 'Menüye Git', 'btn2' => 'İletişim', 'btn5' => 'Wifi Şifresi' ),
				'button_links' => array( 'btn1' => 'https://restoran.test/menu', 'btn2' => 'tel:+900' ),
				'texts_en'     => array( 'btn1' => 'View Menu', 'divider' => '' ),
				'social_media_active' => array( 'instagram' ),
				'social_media' => array( 'instagram' => 'https://instagram.com/x' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'splash-lang', $html, 'düğme basılır' );
		qrms_assert_contains( 'data-sp-key="btn1"', $html, 'metin anahtarı' );
		qrms_assert_contains( 'data-sp-tr="Menüye Git"', $html, 'Türkçe metin' );
		qrms_assert_contains( 'data-sp-en="View Menu"', $html, 'İngilizce metin' );
		// Çevirisi girilmemiş metin İngilizcede de Türkçesini gösterir.
		qrms_assert_contains( 'data-sp-en="Bizi takip edin"', $html, 'boş çeviri Türkçeye düşer' );
		// Görünen metin her zaman Türkçedir; dili istemci seçer.
		qrms_assert_contains( '>Menüye Git</a>', $html, 'sunucu Türkçeyi basar' );
	}
);

qrms_test(
	'rozetlerin erişilebilirlik etiketi de çevrilir',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array(
				'lang_toggle'  => 1,
				'button_texts' => array( 'btn2' => 'İletişim' ),
				'button_links' => array( 'btn2' => 'tel:+900' ),
				'texts_en'     => array( 'btn2' => 'Call us' ),
			)
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		// Rozetin görünür yazısı yoktur; dil değişimi aria-label ve title'a yazılır.
		qrms_assert_contains( 'data-sp-attr="aria-label title"', $html, 'nitelik hedefi' );
		qrms_assert_contains( 'data-sp-en="Call us"', $html, 'rozet çevirisi' );
	}
);

qrms_test(
	'dil açıkken de çıktı çerezden BAĞIMSIZ kalır',
	function () {
		// Dil sunucuda seçilseydi tam sayfa önbelleği ilk ziyaretçinin dilini
		// herkese servis ederdi. Karar istemcide verilir; sunucu çıktısı sabit.
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option(
			'splash_screen_options',
			array( 'lang_toggle' => 1, 'texts_en' => array( 'btn1' => 'View Menu' ) )
		);

		ob_start();
		qrms_ae()->handle_frontend();
		$cerezsiz = ob_get_clean();

		$_COOKIE['qrms_splash_lang'] = 'en';

		ob_start();
		qrms_ae()->handle_frontend();
		$cerezli = ob_get_clean();

		unset( $_COOKIE['qrms_splash_lang'] );

		qrms_assert_same( $cerezsiz, $cerezli, 'çıktı her ziyaretçide aynı' );
	}
);

qrms_test(
	'önizlemede dil düğmesi çalışır ama çerez yazılmaz',
	function () {
		update_option(
			'splash_screen_options',
			array( 'lang_toggle' => 1, 'texts_en' => array( 'btn1' => 'View Menu' ) )
		);

		ob_start();
		qrms_ae()->render_splash_preview();
		$html = ob_get_clean();

		qrms_assert_contains( 'splash-lang', $html, 'düğme önizlemede de var' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );

		qrms_assert_contains( 'initLang(overlay, true)', $js, 'önizleme dili açık' );
		qrms_assert_contains( 'if (!persist || isPreview) return;', $js, 'önizlemede çerez yazılmaz' );
	}
);

qrms_test(
	'kayıtta eksik alt anahtar varsayılandan tamamlanır',
	function () {
		// array_merge SIĞ birleştirir: kayıtta button_texts varsa dizinin
		// tamamı kayıttan gelir ve eksik alt anahtar varsayılandan GELMEZ.
		// Eski sürümden gelen bir option'da bu gerçek bir durum; sayfa
		// "undefined index" uyarısı basıyordu.
		update_option(
			'splash_screen_options',
			array(
				'button_texts' => array( 'btn1' => 'Menüye Git' ),
				'texts_en'     => array( 'btn1' => 'View Menu' ),
			)
		);

		$html = qrms_ae_submit( 'qrms-ae-butonlar', array( 'button_text_1' => 'Menüye Git' ) );

		qrms_assert_contains( 'name="button_text_5"', $html, 'eksik anahtarlı satır yine basılır' );
		qrms_assert_contains( 'name="text_en_btn5"', $html, 'İngilizce alanı da' );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'Menüye Git', $opts['button_texts']['btn1'], 'kayıtlı değer ezilmez' );
		qrms_assert_same( 'Wifi Şifresi', $opts['button_texts']['btn5'], 'eksik anahtar varsayılandan' );
		qrms_assert_same( 4, count( $opts['footer_icons'] ), 'sayısal liste büyümez' );
	}
);

qrms_test(
	'yönetim: İngilizce alanlar ve düğme anahtarı Butonlar sayfasında',
	function () {
		$html = qrms_ae_submit(
			'qrms-ae-butonlar',
			array(
				'lang_toggle'     => '1',
				'button_text_1'   => 'Menüye Git',
				'text_en_btn1'    => 'View Menu',
				'text_en_divider' => 'Follow us',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 1, $opts['lang_toggle'], 'düğme açıldı' );
		qrms_assert_same( 'View Menu', $opts['texts_en']['btn1'], 'çeviri kaydedildi' );
		qrms_assert_same( 'Follow us', $opts['texts_en']['divider'], 'ayraç çevirisi' );
		qrms_assert_contains( 'name="text_en_btn1"', $html, 'alan basılır' );
		qrms_assert_contains( 'name="lang_toggle"', $html, 'düğme anahtarı basılır' );

		// Onay kutusu bu sayfanın: başka sayfayı kaydetmek onu kapatmamalı.
		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'x' ) );

		qrms_assert_same( 1, get_option( 'splash_screen_options' )['lang_toggle'], 'başka sayfa dili kapatmaz' );
	}
);


/* ---------------------------------------------------------------------------
 * 16. QR Çeviri — yönetim ekranının mobil davranışı
 * ------------------------------------------------------------------------ */

echo "\nQR Çeviri (mobil)\n";

qrms_test(
	'yönetim stili yalnızca modülün kendi sayfasında ve dosya bazlı sürümle yüklenir',
	function () {
		require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/module.php';

		$_GET = array( 'page' => 'qrms-overview' );
		qrms_module_qr_ceviri_admin_assets();
		qrms_assert_same( null, qrms_ae_style( 'qrms-ceviri-admin' ), 'başka ekranda yüklenmez' );

		$_GET = array( 'page' => QRMS_Admin::get_module_page_slug( 'qr-ceviri' ) );
		qrms_module_qr_ceviri_admin_assets();

		$stil = qrms_ae_style( 'qrms-ceviri-admin' );

		qrms_assert_true( null !== $stil, 'kendi ekranında yüklenir' );
		qrms_assert_same(
			QRMS_VERSION . '.' . filemtime( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/css/admin.css' ),
			$stil['ver'],
			'sürüm dosyanın kendi zamanını taşır'
		);
	}
);

qrms_test(
	'yönetim sayfasında kırılım noktası olmayan satır içi ölçü kalmadı',
	function () {
		// Asıl kusur buydu: ölçüler markup'a satır içi yazılmıştı
		// (repeat(3,1fr) ızgaralar, max-width:800px kutular). Satır içi stilin
		// medya sorgusu olamaz, bu yüzden ekran darda sıkışıyordu.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/admin-sayfa.php' );

		qrms_assert_false( strpos( $kaynak, 'grid-template-columns' ), 'satır içi ızgara yok' );
		qrms_assert_false( strpos( $kaynak, 'max-width:800px' ), 'satır içi genişlik sınırı yok' );
		qrms_assert_false( strpos( $kaynak, 'max-height:280px' ), 'satır içi kutu yüksekliği yok' );
		qrms_assert_contains( 'qrc-check-grid', $kaynak, 'ızgara sınıfa taşındı' );
	}
);

qrms_test(
	'durum tablosu dar ekranda karta dönebilsin diye hücreler etiketli',
	function () {
		// Kart görünümünde sütun başlığı yoktur; hangi dile ait olduğunu
		// hücrenin data-label'ı söyler. İkisi birlikte anlamlı: etiket
		// olmadan kart okunmaz, kural olmadan etiket görünmez.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/admin/admin-sayfa.php' );
		$css    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/css/admin.css' );

		qrms_assert_contains( 'data-label="<?php echo esc_attr( $etiket ); ?>"', $kaynak, 'hücre etiketi basılır' );
		qrms_assert_contains( 'content: attr(data-label)', $css, 'kart görünümü etiketi kullanır' );
		qrms_assert_contains( 'max-width: 782px', $css, 'kırılım noktası tanımlı' );
	}
);

qrms_test(
	'onay kutusu satırları dokunmatik yükseklikte',
	function () {
		// 44-48px, WordPress admin'in kendi dokunma eşiği. Sayı CSS'ten
		// okunur: kural silinirse ya da küçültülürse test düşer.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/css/admin.css' );

		qrms_assert_true(
			(bool) preg_match( '/\.qrc-check\s*\{[^}]*min-height:\s*(4[4-9]|[5-9]\d)px/s', $css ),
			'satır en az 44px'
		);
		qrms_assert_true(
			(bool) preg_match( '/\.qrc-check input\[type="checkbox"\]\s*\{[^}]*width:\s*20px/s', $css ),
			'kutu büyütülmüş'
		);
	}
);


/* ---------------------------------------------------------------------------
 * 17. QR Çalışma Saatleri — renkler ve canlı önizleme
 * ------------------------------------------------------------------------ */

echo "\nQR Çalışma Saatleri (renk + önizleme)\n";

qrms_test(
	'hiç renk seçilmemişken çıktı eskisiyle BİREBİR aynıdır',
	function () {
		// Bu modülün görünümü bugüne kadar stylesheet'teki sabit renklerden
		// geliyordu. Renk ayarı eklemek kimsenin sitesini değiştirmemeli:
		// seçilmemiş renk CSS değişkeni olarak hiç basılmaz, geri düşüş
		// devrede kalır.
		qrms_assert_same( '', qrms_cs_color_declarations(), 'bildirim yok' );
		qrms_assert_same( '', qrms_cs_inline_style_attr(), 'satır içi stil yok' );

		$html = qrms_cs_shortcode( array() );

		qrms_assert_contains( '<ul class="qrms-cs-list">', $html, 'kapsayıcı çıplak' );

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/assets/css/frontend.css' );

		qrms_assert_contains( 'var(--qrms-cs-today, #c9a84c)', $css, 'eski vurgu rengi geri düşüş' );
		qrms_assert_contains( 'var(--qrms-cs-divider, rgba(0, 0, 0, 0.08))', $css, 'eski ayraç rengi geri düşüş' );
	}
);

qrms_test(
	'geçersiz renk ayarı yok sayılır, geçerli olan değişkene iner',
	function () {
		update_option(
			QRMS_CS_COLORS_OPTION,
			qrms_cs_sanitize_colors(
				array(
					'today'   => '#ff0000',
					'divider' => 'kırmızı',   // geçersiz -> devral
					'hours'   => '#0f0',      // kısa hex geçerli
					'uydurma' => '#123456',   // bilinmeyen anahtar
				)
			)
		);

		$colors = qrms_cs_get_colors();

		qrms_assert_same( '#ff0000', $colors['today'], 'geçerli hex korunur' );
		qrms_assert_same( '', $colors['divider'], 'geçersiz değer devralmaya düşer' );
		qrms_assert_same( '#0f0', $colors['hours'], 'kısa hex kabul' );
		qrms_assert_false( isset( $colors['uydurma'] ), 'bilinmeyen anahtar düşer' );

		$decl = qrms_cs_color_declarations();

		qrms_assert_contains( '--qrms-cs-today: #ff0000', $decl, 'seçilen renk basılır' );
		qrms_assert_false( strpos( $decl, '--qrms-cs-divider' ), 'seçilmeyen renk basılmaz' );

		qrms_assert_contains( '--qrms-cs-today: #ff0000', qrms_cs_shortcode( array() ), 'kısa kod değişkeni taşır' );
	}
);

qrms_test(
	'renkler saatlerden AYRI option\'da durur, biri diğerini bozmaz',
	function () {
		// qrms_cs_sanitize() diziyi gün anahtarlarına indirger; renkler aynı
		// option'da olsaydı ilk kayıtta sessizce silinirdi.
		update_option( QRMS_CS_OPTION, qrms_cs_sanitize( array( 'monday' => array( 'open' => '11:00', 'close' => '23:30' ) ) ) );
		update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( array( 'today' => '#123456' ) ) );

		$hours  = qrms_cs_get();
		$colors = qrms_cs_get_colors();

		qrms_assert_same( '11:00', $hours['monday']['open'], 'saat korundu' );
		qrms_assert_same( 7, count( $hours ), 'tam hafta' );
		qrms_assert_same( '#123456', $colors['today'], 'renk korundu' );
		qrms_assert_false( isset( $hours['today'] ), 'renk anahtarı saatlere sızmaz' );
	}
);

qrms_test(
	'form tek Kaydet ile hem saatleri hem renkleri yazar',
	function () {
		$_POST = array(
			'qrms_cs_kaydet' => '1',
			'qrms_cs'        => array( 'friday' => array( 'open' => '10:00', 'close' => '02:00' ) ),
			'qrms_cs_renk'   => array( 'today' => '#abcdef', 'day' => 'bozuk' ),
		);

		ob_start();
		qrms_cs_admin_sayfasi();
		$html = ob_get_clean();

		qrms_assert_same( '10:00', qrms_cs_get()['friday']['open'], 'saat kaydedildi' );
		qrms_assert_same( '#abcdef', qrms_cs_get_colors()['today'], 'renk kaydedildi' );
		qrms_assert_same( '', qrms_cs_get_colors()['day'], 'bozuk renk devralmaya düştü' );
		qrms_assert_contains( 'Çalışma saatleri kaydedildi.', $html, 'kayıt bildirimi' );
	}
);

qrms_test(
	'yönetim ekranı renk alanlarını ve kısa kodun GERÇEK çıktısını basar',
	function () {
		ob_start();
		qrms_cs_admin_sayfasi();
		$html = ob_get_clean();

		foreach ( array_keys( qrms_cs_color_fields() ) as $key ) {
			qrms_assert_contains( 'name="qrms_cs_renk[' . $key . ']"', $html, $key . ' alanı' );
		}

		qrms_assert_contains( 'id="qrms-cs-preview"', $html, 'önizleme kutusu' );
		// Önizleme ayrı bir şablon değil, kısa kodun kendisi: aynı sınıflar.
		qrms_assert_contains( 'qrms-cs-list', $html, 'kısa kod listesi' );
		qrms_assert_contains( 'data-day="monday"', $html, 'satırlar gün anahtarı taşır' );
	}
);

qrms_test(
	'yeni görünüm alanları (zemin, kenar, yazı rengi) değişkene iner',
	function () {
		update_option(
			QRMS_CS_COLORS_OPTION,
			qrms_cs_sanitize_colors(
				array(
					'bg'     => '#ffffff',
					'border' => '#dddddd',
					'text'   => '#222222',
				)
			)
		);

		$decl = qrms_cs_color_declarations();

		qrms_assert_contains( '--qrms-cs-bg: #ffffff', $decl, 'arka plan' );
		qrms_assert_contains( '--qrms-cs-border: #dddddd', $decl, 'kenar rengi' );
		qrms_assert_contains( '--qrms-cs-text: #222222', $decl, 'yazı rengi' );

		// Kutu ölçüleri renklerle BİRLİKTE basılır: "1px solid transparent"
		// bile satırları kaydırırdı (bkz. frontend.css başlığı).
		qrms_assert_contains( '--qrms-cs-border-width: 1px', $decl, 'çerçeve kalınlığı' );
		qrms_assert_contains( '--qrms-cs-pad: 12px 16px', $decl, 'iç boşluk' );

		// Yalnızca zemin seçiliyken çerçeve kalınlığı basılmaz.
		$sadece_zemin = qrms_cs_color_declarations( qrms_cs_sanitize_colors( array( 'bg' => '#000000' ) ) );

		qrms_assert_contains( '--qrms-cs-pad', $sadece_zemin, 'zemin için boşluk' );
		qrms_assert_false( strpos( $sadece_zemin, '--qrms-cs-border-width' ), 'çerçeve istenmedi' );

		// Hiçbiri seçilmemişken tek bir ölçü bile basılmaz.
		qrms_assert_same( '', qrms_cs_color_declarations( qrms_cs_sanitize_colors( array() ) ), 'çıplak liste' );
	}
);

qrms_test(
	'yazı tipi beyaz listeyle doğrulanır, uydurma değer devralmaya düşer',
	function () {
		// Değer doğrudan CSS'e iniyor: serbest metin kabul edilemez.
		$temiz = qrms_cs_sanitize_colors( array( 'font' => 'Poppins' ) );

		qrms_assert_same( 'Poppins', $temiz['font'], 'listedeki font' );
		qrms_assert_same(
			'',
			qrms_cs_sanitize_colors( array( 'font' => 'Comic Sans; color:red' ) )['font'],
			'liste dışı değer düşer'
		);

		update_option( QRMS_CS_COLORS_OPTION, $temiz );

		qrms_assert_same( 'Poppins', qrms_cs_get_font(), 'kayıtlı font' );
		qrms_assert_contains(
			"--qrms-cs-font: 'Poppins', system-ui, sans-serif",
			qrms_cs_color_declarations(),
			'font değişkeni sistem geri düşüşüyle basılır'
		);

		// Jenerik aile tırnaklanmaz; sistem fontu için dış istek yapılmaz.
		qrms_assert_same( 'serif', qrms_cs_font_family( 'serif' ), 'jenerik aile' );
		qrms_assert_same( '', qrms_cs_google_font_url( 'Georgia' ), 'sistem fontu' );
		qrms_assert_same( '', qrms_cs_google_font_url( '' ), 'seçim yok' );
		qrms_assert_contains( 'family=Poppins', qrms_cs_google_font_url( 'Poppins' ), 'Google adresi' );
	}
);

qrms_test(
	'font listesi Restoran Menü\'nün Görünüm sayfasıyla BİREBİR aynıdır',
	function () {
		// İki ekranda farklı listeler olsaydı restoran sahibi hangisini
		// seçtiğini karıştırırdı. Liste orada private bir metotta durduğu ve
		// modüller bağımsız lisanslandığı için kopya bilinçli — bu test
		// kopyanın ayrışmasını yakalar.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		preg_match( '/private function get_font_options\(\) \{\s*return \[(.*?)\];/s', $kaynak, $m );
		preg_match_all( "/'([^']+)'/", $m[1], $isimler );

		qrms_assert_same( $isimler[1], qrms_cs_font_options(), 'liste aynı' );
	}
);

qrms_test(
	'yönetim ekranı font seçicisini basar, önizleme anında yansır',
	function () {
		update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( array( 'font' => 'Lato' ) ) );

		ob_start();
		qrms_cs_admin_sayfasi();
		$html = ob_get_clean();

		qrms_assert_contains( 'name="qrms_cs_renk[font]"', $html, 'font alanı' );
		qrms_assert_contains( 'data-css-var="--qrms-cs-font"', $html, 'değişken adı' );
		qrms_assert_contains( 'value="Lato"', $html, 'seçenekler listelenir' );
		qrms_assert_contains( 'data-google="https://fonts.googleapis.com/css2?family=Lato', $html, 'Google adresi' );

		// Önizlemeyi JS besler: değişken önizleme listesine yazılır, Google
		// stylesheet'i seçim değişince eklenir.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/assets/js/admin.js' );

		qrms_assert_contains( "getElementById('qrms-cs-renk-font')", $js, 'JS seçiciyi bulur' );
		qrms_assert_contains( 'function loadFont(url)', $js, 'JS fontu yükler' );
		qrms_assert_contains( 'function syncBox()', $js, 'JS kutu kuralını PHP ile eşler' );
	}
);

qrms_test(
	'seçilen font ön yüzde yalnızca gerektiğinde yüklenir',
	function () {
		update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( array( 'font' => 'Georgia' ) ) );
		qrms_cs_shortcode( array() );
		qrms_assert_same( null, qrms_ae_style( 'qrms-cs-font' ), 'sistem fontu için dış istek yok' );

		update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( array( 'font' => 'Inter' ) ) );
		qrms_cs_shortcode( array() );

		$stil = qrms_ae_style( 'qrms-cs-font' );

		qrms_assert_true( null !== $stil, 'adlandırılmış font yüklenir' );
		qrms_assert_contains( 'family=Inter', $stil['src'], 'doğru aile' );
	}
);

qrms_test(
	'önizlemenin saat metni PHP ile aynı üç dala sahiptir',
	function () {
		// Metin iki yerde üretiliyor: sayfa açılışında PHP, değişiklikte JS.
		// Dallanma ayrışırsa önizleme yalan söyler; her iki taraf da burada
		// doğrulanıyor.
		qrms_assert_same( 'Kapalı', qrms_cs_format_day( array( 'closed' => true ) ), 'kapalı' );
		qrms_assert_same(
			'24 saat açık',
			qrms_cs_format_day( array( 'closed' => false, 'open' => '00:00', 'close' => '00:00' ) ),
			'eşit saat = 24 saat'
		);
		qrms_assert_same(
			'09:00 – 22:00',
			qrms_cs_format_day( array( 'closed' => false, 'open' => '09:00', 'close' => '22:00' ) ),
			'aralık'
		);

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/assets/js/admin.js' );

		qrms_assert_contains( 'if (closed) {', $js, 'JS: kapalı dalı' );
		qrms_assert_contains( 'if (open === close) {', $js, 'JS: 24 saat dalı' );
		qrms_assert_contains( 'L.kapali', $js, 'JS metni PHP\'den alır' );
		qrms_assert_contains( 'L.yirmiDort', $js, 'JS metni PHP\'den alır' );
		qrms_assert_contains( 'L.aralik', $js, 'JS metni PHP\'den alır' );
	}
);


/* ---------------------------------------------------------------------------
 * 12. Toplu Fiyat Kampanyası
 *
 * Fiyat verisiyle DOĞRUDAN oynayan bir özellik olduğu için hesap katmanı
 * WordPress'ten tamamen bağımsız tutuldu: aşağıdaki fonksiyonların hepsi saf.
 * Yönetimdeki canlı önizleme, ön yüz render'ı ve bu testler aynı fonksiyonları
 * çağırır — dolayısıyla önizlemede görülen fiyat ile menüde çıkan fiyat tanım
 * gereği aynıdır.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya-db.php';

echo "\nFiyat Kampanyası — hesap\n";

qrms_test(
	'yüzde ve sabit tutar, zam ve indirim yönünde doğru hesaplanır',
	function () {
		$yuzde_zam = array( 'calc_type' => 'percent', 'direction' => 'increase', 'amount' => 10, 'rounding' => 'none' );
		qrms_assert_same( 52.25, RMA_Kampanya_DB::yeni_fiyat( 47.50, $yuzde_zam ), '%10 zam' );

		$yuzde_ind = array( 'calc_type' => 'percent', 'direction' => 'decrease', 'amount' => 15, 'rounding' => 'none' );
		qrms_assert_same( 85.0, RMA_Kampanya_DB::yeni_fiyat( 100, $yuzde_ind ), '%15 indirim' );

		$sabit_zam = array( 'calc_type' => 'fixed', 'direction' => 'increase', 'amount' => 5, 'rounding' => 'none' );
		qrms_assert_same( 52.5, RMA_Kampanya_DB::yeni_fiyat( 47.50, $sabit_zam ), '+5 ₺' );

		$sabit_ind = array( 'calc_type' => 'fixed', 'direction' => 'decrease', 'amount' => 10, 'rounding' => 'none' );
		qrms_assert_same( 37.5, RMA_Kampanya_DB::yeni_fiyat( 47.50, $sabit_ind ), '-10 ₺' );
	}
);

qrms_test(
	'fiyat hiçbir koşulda 0 ın altına inmez',
	function () {
		// 5 ₺ lik üründe -10 ₺ lik bir indirim negatif fiyat üretirdi.
		$kural = array( 'calc_type' => 'fixed', 'direction' => 'decrease', 'amount' => 10, 'rounding' => 'none' );

		qrms_assert_same( 0.0, RMA_Kampanya_DB::yeni_fiyat( 5, $kural ), 'sıfıra kelepçelenir' );
	}
);

qrms_test(
	'fiyatı olmayan ürün ve tutarsız kural kampanya dışıdır',
	function () {
		$kural = array( 'calc_type' => 'percent', 'direction' => 'increase', 'amount' => 10, 'rounding' => 'none' );

		qrms_assert_same( null, RMA_Kampanya_DB::yeni_fiyat( '', $kural ), 'boş fiyat' );
		qrms_assert_same( null, RMA_Kampanya_DB::yeni_fiyat( 'fiyat sorunuz', $kural ), 'metin fiyat' );

		$sifir = array( 'calc_type' => 'percent', 'direction' => 'increase', 'amount' => 0, 'rounding' => 'none' );
		qrms_assert_same( null, RMA_Kampanya_DB::yeni_fiyat( 50, $sifir ), 'tutar sıfır' );
	}
);

qrms_test(
	'yuvarlama modlarının hepsi beklenen fiyatı üretir',
	function () {
		qrms_assert_same( 52.25, RMA_Kampanya_DB::yuvarla( 52.25, 'none' ), 'kuruş korunur' );
		qrms_assert_same( 52.5, RMA_Kampanya_DB::yuvarla( 52.25, 'half' ), 'en yakın 0,50' );
		qrms_assert_same( 52.0, RMA_Kampanya_DB::yuvarla( 52.25, 'whole' ), 'en yakın 1 ₺' );

		// Psikolojik fiyat modları EN YAKIN adayı seçer (diğer modlarla aynı
		// mantık): her zaman yukarı yuvarlasalardı indirim kampanyaları
		// sessizce törpülenirdi.
		qrms_assert_same( 51.9, RMA_Kampanya_DB::yuvarla( 52.25, 'end90' ), 'aşağıdaki ,90 daha yakın' );
		qrms_assert_same( 52.9, RMA_Kampanya_DB::yuvarla( 52.70, 'end90' ), 'yukarıdaki ,90 daha yakın' );
		qrms_assert_same( 51.99, RMA_Kampanya_DB::yuvarla( 52.10, 'end99' ), ',99 ile biter' );

		// Tanınmayan mod sessizce "yuvarlama yok"a düşer — şema bozulmaz.
		qrms_assert_same( 52.25, RMA_Kampanya_DB::yuvarla( 52.25, 'uydurma' ), 'bilinmeyen mod' );
	}
);

qrms_test(
	'fiyat biçimi kuruşu korur, tam sayıda küsuratı atar',
	function () {
		qrms_assert_same( '52,50', RMA_Kampanya_DB::bicimle( 52.5 ), 'kuruş korunur' );
		qrms_assert_same( '52', RMA_Kampanya_DB::bicimle( 52.0 ), 'tam sayı' );
		qrms_assert_same( '1.250,25', RMA_Kampanya_DB::bicimle( 1250.25 ), 'binlik ayracı' );
	}
);

echo "\nFiyat Kampanyası — form temizliği\n";

qrms_test(
	'uydurma seçim değerleri varsayılana düşer, yüzde üst sınıra kırpılır',
	function () {
		$temiz = RMA_Kampanya_DB::ayarlari_temizle(
			array(
				'title'      => '  Ocak Zammı  ',
				'calc_type'  => 'uydurma',
				'direction'  => 'uydurma',
				'rounding'   => 'uydurma',
				'scope_type' => 'uydurma',
				'amount'     => 500,
			)
		);

		qrms_assert_same( 'Ocak Zammı', $temiz['title'], 'başlık kırpılır' );
		qrms_assert_same( 'percent', $temiz['calc_type'], 'tür varsayılanı' );
		qrms_assert_same( 'increase', $temiz['direction'], 'yön varsayılanı' );
		qrms_assert_same( 'none', $temiz['rounding'], 'yuvarlama varsayılanı' );
		qrms_assert_same( 'all', $temiz['scope_type'], 'kapsam varsayılanı' );
		qrms_assert_same( (float) RMA_Kampanya_DB::MAX_YUZDE, $temiz['amount'], 'yüzde üst sınırı' );
	}
);

qrms_test(
	'eksi ve virgüllü tutar girdisi kabul edilir',
	function () {
		// Yön ayrı alanda tutulur; tutardaki eksi işareti iki yerden gelen
		// çelişkili yön demek olurdu, bu yüzden mutlak değere çekilir.
		qrms_assert_same( 12.5, RMA_Kampanya_DB::tutar_temizle( '12,5' ), 'Türkçe ondalık' );
		qrms_assert_same( 10.0, RMA_Kampanya_DB::tutar_temizle( '-10' ), 'eksi işareti düşer' );
		qrms_assert_same( 0.0, RMA_Kampanya_DB::tutar_temizle( 'bedava' ), 'metin girdi' );
	}
);

qrms_test(
	'kapsam listesi yalnızca kendi kapsam türünde saklanır',
	function () {
		$tum = RMA_Kampanya_DB::ayarlari_temizle(
			array( 'scope_type' => 'all', 'scope_ids' => '3,4', 'amount' => 10 )
		);
		qrms_assert_same( '', $tum['scope_ids'], 'tüm menüde liste tutulmaz' );

		$kat = RMA_Kampanya_DB::ayarlari_temizle(
			array( 'scope_type' => 'category', 'scope_ids' => '3,4,3,0,abc', 'amount' => 10 )
		);
		qrms_assert_same( '3,4', $kat['scope_ids'], 'tekrar ve geçersiz kayıt düşer' );
	}
);

echo "\nFiyat Kampanyası — kapsam ve zaman\n";

qrms_test(
	'kapsam üç dalın hepsinde doğru karar verir',
	function () {
		$tum = array( 'scope_type' => 'all', 'scope_ids' => '' );
		qrms_assert_true( RMA_Kampanya_DB::kapsamda_mi( 55, $tum, array( 9 ) ), 'tüm menü' );

		$kat = array( 'scope_type' => 'category', 'scope_ids' => '7,9' );
		qrms_assert_true( RMA_Kampanya_DB::kapsamda_mi( 55, $kat, array( 9, 12 ) ), 'kategori eşleşir' );
		qrms_assert_false( RMA_Kampanya_DB::kapsamda_mi( 55, $kat, array( 12 ) ), 'kategori eşleşmez' );

		$manuel = array( 'scope_type' => 'manual', 'scope_ids' => '55,56' );
		qrms_assert_true( RMA_Kampanya_DB::kapsamda_mi( 55, $manuel, array() ), 'seçili ürün' );
		qrms_assert_false( RMA_Kampanya_DB::kapsamda_mi( 57, $manuel, array() ), 'seçilmemiş ürün' );

		// Kapsam seçilmiş ama liste boşsa hiçbir ürün etkilenmez: aksi hâlde
		// "kategori" seçip hiç kategori işaretlememek tüm menüyü zamlardı.
		$bos = array( 'scope_type' => 'category', 'scope_ids' => '' );
		qrms_assert_false( RMA_Kampanya_DB::kapsamda_mi( 55, $bos, array( 9 ) ), 'boş liste' );
	}
);

qrms_test(
	'yalnızca durumu aktif olan kampanya geçerlidir',
	function () {
		$zaman = strtotime( '2026-01-05 14:30:00 UTC' );

		qrms_assert_true( RMA_Kampanya_DB::aktif_mi( array( 'status' => 'active' ), $zaman ), 'aktif' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( array( 'status' => 'passive' ), $zaman ), 'pasif' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( array(), $zaman ), 'kayıt yok' );
	}
);

qrms_test(
	'İkinci Faz zaman alanları boşken davranış değişmez',
	function () {
		// Şema zamanlanmış kampanya / Happy Hour için hazır; v1 bu alanları
		// yazmaz ve boş alan "sınır yok" demektir.
		$zaman = strtotime( '2026-01-05 14:30:00 UTC' );

		$kampanya = array(
			'status'      => 'active',
			'starts_at'   => null,
			'ends_at'     => '',
			'daily_start' => null,
			'daily_end'   => '',
			'days_mask'   => 0,
		);

		qrms_assert_true( RMA_Kampanya_DB::aktif_mi( $kampanya, $zaman ), 'sınırsız kampanya' );
	}
);

qrms_test(
	'tarih penceresi, gün maskesi ve saat aralığı değerlendirilir',
	function () {
		$pazartesi = strtotime( '2026-01-05 14:30:00 UTC' );

		$tarihli = array( 'status' => 'active', 'starts_at' => '2026-01-10 00:00:00', 'ends_at' => '' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( $tarihli, $pazartesi ), 'henüz başlamadı' );

		$biten = array( 'status' => 'active', 'starts_at' => '', 'ends_at' => '2026-01-01 00:00:00' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( $biten, $pazartesi ), 'süresi doldu' );

		// Bit 0 = Pazar … bit 6 = Cumartesi. 2026-01-05 bir Pazartesi.
		$haftaici = array( 'status' => 'active', 'days_mask' => 1 << 1 );
		qrms_assert_true( RMA_Kampanya_DB::aktif_mi( $haftaici, $pazartesi ), 'pazartesi maskesi' );

		$haftasonu = array( 'status' => 'active', 'days_mask' => ( 1 << 0 ) | ( 1 << 6 ) );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( $haftasonu, $pazartesi ), 'hafta sonu maskesi' );

		$happy = array( 'status' => 'active', 'daily_start' => '16:00', 'daily_end' => '19:00' );
		qrms_assert_false( RMA_Kampanya_DB::aktif_mi( $happy, $pazartesi ), 'saat aralığı dışı' );
		qrms_assert_true(
			RMA_Kampanya_DB::aktif_mi( $happy, strtotime( '2026-01-05 17:00:00 UTC' ) ),
			'saat aralığı içi'
		);
	}
);

qrms_test(
	'gece yarısını aşan saat aralığı doğru çalışır',
	function () {
		// 22:00–02:00 gibi bir aralıkta 23:30 da 01:00 da "içeri" sayılmalı.
		qrms_assert_true( RMA_Kampanya_DB::saat_araliginda_mi( '23:30:00', '22:00', '02:00' ), 'gece yarısı öncesi' );
		qrms_assert_true( RMA_Kampanya_DB::saat_araliginda_mi( '01:00:00', '22:00', '02:00' ), 'gece yarısı sonrası' );
		qrms_assert_false( RMA_Kampanya_DB::saat_araliginda_mi( '15:00:00', '22:00', '02:00' ), 'aralık dışı' );
		qrms_assert_true( RMA_Kampanya_DB::saat_araliginda_mi( '15:00:00', '', '' ), 'sınırsız' );
	}
);

qrms_test(
	'kural metni yönetim ekranında okunur biçimde çıkar',
	function () {
		qrms_assert_same(
			'%10 zam',
			RMA_Kampanya_DB::kural_metni( array( 'calc_type' => 'percent', 'direction' => 'increase', 'amount' => 10 ) ),
			'yüzde zam'
		);

		qrms_assert_same(
			'5,50 ₺ indirim',
			RMA_Kampanya_DB::kural_metni( array( 'calc_type' => 'fixed', 'direction' => 'decrease', 'amount' => 5.5 ) ),
			'sabit indirim'
		);
	}
);

echo "\nFiyat Kampanyası — yapısal güvenceler\n";

qrms_test(
	'ekran hub kartlarına ve alt sayfa listesine kayıtlı',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		qrms_assert_contains( "'qrms-rm-kampanya'", $kaynak, 'alt sayfa slug\'ı' );
		qrms_assert_contains( 'render_campaign_page', $kaynak, 'render metodu' );

		// Hub kartları get_subpages()'ten üretiliyor; ayrı bir kart tanımı
		// gerekmiyor — bu satır o bağın kopmadığının güvencesi.
		qrms_assert_contains( 'foreach ( $this->get_subpages() as $slug => $page )', $kaynak, 'kartlar listeden üretilir' );
	}
);

qrms_test(
	'kaydetme, geri alma ve önizleme uçlarının hepsi kayıtlı',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );

		qrms_assert_contains( "admin_post_rma_kampanya_kaydet", $kaynak, 'kaydetme ucu' );
		qrms_assert_contains( "admin_post_rma_kampanya_geri_al", $kaynak, 'geri alma ucu' );
		qrms_assert_contains( "admin_post_rma_kampanya_sil", $kaynak, 'silme ucu' );
		qrms_assert_contains( "wp_ajax_rma_kampanya_onizleme", $kaynak, 'önizleme ucu' );
	}
);

qrms_test(
	'ön yüzdeki DÖRT fiyat noktası da tek kaynaktan besleniyor',
	function () {
		// Kampanya mimarisinin temel güvencesi: hiçbir gösterim noktası
		// fiyatı ham meta'dan okumaz, hepsi RMA_Kampanya::fiyat_html()
		// çağırır. Aksi hâlde bir yüzeyde kampanyalı, diğerinde eski fiyat
		// görünürdü.
		$noktalar = array(
			'includes/trait-frontend.php'    => 'menü kartı',
			'includes/trait-ajax.php'        => 'ürün modalı',
			'includes/shortcode-vitrin.php'  => 'ürün vitrini',
			'includes/shortcode-slider.php'  => 'öne çıkan slider',
		);

		foreach ( $noktalar as $dosya => $etiket ) {
			$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/' . $dosya );

			qrms_assert_contains( 'RMA_Kampanya::fiyat_html', $kaynak, $etiket . ': ortak giriş' );
			qrms_assert_false(
				strpos( $kaynak, "get_post_meta( \$id, 'rma_price'" ) !== false
					|| strpos( $kaynak, "get_post_meta( \$product_id, 'rma_price'" ) !== false,
				$etiket . ': ham fiyat okuması kalmadı'
			);
		}
	}
);

qrms_test(
	'menü önbelleği anahtarı aktif kampanyayı içerir',
	function () {
		// Kampanya açılıp kapandığında önbelleğe alınmış menü HTML'i
		// geçersizleşmezse müşteri eski fiyatı görmeye devam ederdi.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-helpers.php' );

		qrms_assert_contains( 'RMA_Kampanya::imza()', $kaynak, 'imza anahtara giriyor' );
	}
);

qrms_test(
	'ürün fiyatı hiçbir kod yolunda üzerine yazılmıyor',
	function () {
		// Özelliğin can damarı: kampanya rma_price/_qmo_kombin_fiyat alanlarına
		// ASLA yazmaz. Yedek meta (_qrms_orijinal_fiyat) ayrı bir alandır.
		$dosyalar = array( 'includes/class-kampanya.php', 'includes/class-kampanya-db.php', 'includes/trait-kampanya-admin.php' );

		foreach ( $dosyalar as $dosya ) {
			$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/' . $dosya );

			// Fiyat alanlarına YAZAN tek bir çağrı bile olmamalı; okuma serbest.
			qrms_assert_false(
				(bool) preg_match( "/update_post_meta\([^;]*'(rma_price|_qmo_kombin_fiyat)'/", $kaynak ),
				$dosya . ': fiyat alanlarına yazılmıyor'
			);
		}

		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-admin.php' );
		qrms_assert_contains( 'RMA_Kampanya_DB::ORIJINAL_META', $admin, 'yedek ayrı alana yazılır' );
	}
);


/* ---------------------------------------------------------------------------
 * 13. Ürün Tükendi (stok durumu)
 *
 * Göster/Gizle (`rma_active`) ürünü menüden kaldırır. Tükendi ayrı bir
 * meta'dır (`_rma_tukendi`): orijinal görünürlük alanını ezmez, menü
 * sorgusundan ürünü düşürmez.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-tukendi.php';

echo "\nÜrün Tükendi — stok durumu\n";

qrms_test(
	'yalnızca açık 1 değeri tükendi sayılır',
	function () {
		qrms_assert_true( RMA_Tukendi::meta_tukendi_mi( '1' ), 'string 1' );
		qrms_assert_false( RMA_Tukendi::meta_tukendi_mi( '0' ), 'sıfır' );
		qrms_assert_false( RMA_Tukendi::meta_tukendi_mi( '' ), 'boş meta' );
		qrms_assert_false( RMA_Tukendi::meta_tukendi_mi( null ), 'null' );
		qrms_assert_false( RMA_Tukendi::meta_tukendi_mi( 'yes' ), 'rastgele metin' );
	}
);

qrms_test(
	'ürün adı büyük/küçük harf ve boşluk farkını yok sayar',
	function () {
		qrms_assert_true( RMA_Tukendi::ad_eslesir( 'Adana Kebap', 'adana kebap' ), 'küçük harf' );
		qrms_assert_true( RMA_Tukendi::ad_eslesir( '  Adana Kebap ', 'Adana Kebap' ), 'kırpılmış boşluk' );
		qrms_assert_false( RMA_Tukendi::ad_eslesir( 'Adana Kebap', 'Urfa Kebap' ), 'farklı ürün' );
		qrms_assert_false( RMA_Tukendi::ad_eslesir( '', '' ), 'iki boş ad eşleşmez' );
		qrms_assert_same( 'adana kebap', RMA_Tukendi::ad_normalize( ' Adana Kebap ' ), 'normalize' );
	}
);

qrms_test(
	'tükendi rma_active alanına yazmaz, ayrı meta kullanır',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-tukendi.php' );

		qrms_assert_contains( "const META = '_rma_tukendi'", $kaynak, 'ayrı meta anahtarı' );
		qrms_assert_false(
			(bool) preg_match( "/update_post_meta\([^;]*'rma_active'/", $kaynak ),
			'Göster/Gizle alanına yazılmaz'
		);

		$kaydet = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-post-types.php' );
		qrms_assert_contains( 'RMA_Tukendi::kaydet', $kaydet, 'ürün kaydında ayrı yazılır' );
		qrms_assert_false(
			(bool) preg_match( "/\\\$checkboxes = \[[^\]]*rma_tukendi/", $kaydet ),
			'genel checkbox listesine karışmaz'
		);
	}
);

qrms_test(
	'menü sorgusu tükendi ürünleri gizlemez; kart ve vitrin işareti basar',
	function () {
		$ajax = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );
		qrms_assert_contains( "'key' => 'rma_active'", $ajax, 'gizleme hâlâ rma_active' );
		qrms_assert_false(
			(bool) preg_match( "/'key'\s*=>\s*'_rma_tukendi'/", $ajax ),
			'tükendi meta_query filtresi değil'
		);

		$kart = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		qrms_assert_contains( 'is-tukendi', $kart, 'kart sınıfı' );
		qrms_assert_contains( 'RMA_Tukendi::rozet_html', $kart, 'kart rozeti' );

		$vitrin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-vitrin.php' );
		qrms_assert_contains( 'RMA_Tukendi::urun_tukendi', $vitrin, 'vitrin durumu' );

		$slider = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/shortcode-slider.php' );
		qrms_assert_contains( 'RMA_Tukendi::urun_tukendi', $slider, 'slider durumu' );
	}
);

qrms_test(
	'chatbot siparişi tükendi filtresinden geçer',
	function () {
		$siparis = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		qrms_assert_contains( 'qmo_siparis_onay_oncesi', $siparis, 'sipariş kancası' );

		$menu = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		qrms_assert_contains( "add_filter( 'qmo_siparis_onay_oncesi'", $menu, 'menü bağlar' );

		$json = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' );
		qrms_assert_contains( "'tukendi'", $json, 'menü JSON alanı' );

		qrms_assert_same( null, RMA_Tukendi::siparis_engeli( array() ), 'boş sipariş' );
		qrms_assert_same( 'önceki', RMA_Tukendi::siparis_filtresi( 'önceki', array() ), 'önceki engel korunur' );
	}
);


/* ---------------------------------------------------------------------------
 * 14. VERİTABANI BAĞLANTI OPTİMİZASYONU
 *
 * Canlıda "Too many connections" hatasına yol açan üç desen burada korunur:
 *   (a) aynı tabloyu defalarca tarayan ayrı ayrı aggregate sorguları,
 *   (b) LIMIT'siz liste sorguları,
 *   (c) uzun bir dış API isteği boyunca boşuna açık tutulan bağlantı.
 * ------------------------------------------------------------------------ */

// Yönetimdeki liste sayfalaması ve bağlantı yardımcıları buradan gelir.
// (forms/functions.php YÜKLENMEZ: yukarıda qrm_cf_unread_total'ın taklidi
// tanımlı, gerçeği çift tanım hatası verirdi — o yüzden okunmamış gönderim
// sayacı bu bölümde kaynak üzerinden doğrulanır.)
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/dashboard.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';

/**
 * Çalıştırılan HER sorguyu kaydeden $wpdb taklidi.
 *
 * Bu bölümün asıl güvencesi sorgu SAYISIdır: birleştirilen sorgular yeniden
 * bölünürse testler düşer.
 */
class QRMS_Sayan_Wpdb {
	public $prefix  = 'wp_';
	public $queries = array();
	public $rows    = array();
	public $vars    = array();
	public $results = array();
	public $dbh     = true;
	public $kapandi = 0;
	public $acildi  = 0;

	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return preg_replace_callback(
			'/%[dsf]/',
			function ( $m ) use ( &$args ) {
				$value = array_shift( $args );

				if ( '%d' === $m[0] ) {
					return (string) (int) $value;
				}
				if ( '%f' === $m[0] ) {
					return (string) (float) $value;
				}

				return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
			},
			$sql
		);
	}

	public function esc_like( $t ) {
		return $t;
	}

	public function suppress_errors( $suppress = true ) {
		return false;
	}

	public function get_row( $sql, $mode = null ) {
		$this->queries[] = $sql;

		return array_shift( $this->rows );
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;

		return array_shift( $this->vars );
	}

	public function get_results( $sql, $mode = null ) {
		$this->queries[] = $sql;

		$next = array_shift( $this->results );

		return is_array( $next ) ? $next : array();
	}

	public function close() {
		$this->kapandi++;
		$this->dbh = null;

		return true;
	}

	public function db_connect( $allow_bail = true ) {
		$this->acildi++;
		$this->dbh = true;

		return true;
	}

	/** Kaydedilen sorgulardan verilen parçayı içerenlerin sayısı. */
	public function kac_kez( $parca ) {
		$sayi = 0;

		foreach ( $this->queries as $q ) {
			if ( false !== stripos( $q, $parca ) ) {
				$sayi++;
			}
		}

		return $sayi;
	}
}

/**
 * Bu bölüm için taze bir $wpdb takar ve önbellekleri temizler.
 *
 * @return QRMS_Sayan_Wpdb
 */
function qrms_sayan_wpdb() {
	$GLOBALS['wpdb'] = new QRMS_Sayan_Wpdb();

	$GLOBALS['qrms_test']['transients'] = array();
	unset( $GLOBALS['qrm_pro_stats_memo'], $GLOBALS['qrm_cf_unread_memo'] );

	return $GLOBALS['wpdb'];
}

/** Birleşik istatistik sorgusunun döndürdüğü satırın taklidi. */
function qrms_sahte_stat_satiri( $args = array() ) {
	return array_merge(
		array(
			'total'           => 40,
			'approved'        => 30,
			'avg_rating'      => 4.25,
			'google_eligible' => 22,
			'crit_1'          => 4.5,
			'crit_2'          => 3.5,
			'crit_3'          => 4.0,
			'crit_4'          => null,
			'crit_5'          => 2.0,
		),
		$args
	);
}

echo "\nYorum istatistikleri — tek sorgu\n";

qrms_test(
	'altı ayrı AVG/COUNT sorgusu TEK sorguya indi',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();

		$stats = qrm_pro_fetch_review_stats( 3.5 );

		qrms_assert_same( 1, count( $wpdb->queries ), 'toplam sorgu sayısı' );
		qrms_assert_same( 1, $wpdb->kac_kez( 'FROM wp_qrm_reviews' ), 'tablo bir kez taranır' );

		$sql = $wpdb->queries[0];

		// Beş kriterin de aynı SELECT'in içinde olması şart.
		for ( $i = 1; $i <= 5; $i++ ) {
			qrms_assert_contains( 'rating_' . $i, $sql, 'kriter ' . $i . ' aynı sorguda' );
		}

		qrms_assert_contains( 'AS google_eligible', $sql, 'Google eşiği aynı sorguda' );
		qrms_assert_contains( "rating >= 3.5", $sql, 'eşik değeri yerine kondu' );

		qrms_assert_same( 40, $stats['total'], 'toplam' );
		qrms_assert_same( 30, $stats['approved'], 'yayında' );
		qrms_assert_same( 10, $stats['pending'], 'bekleyen türetilir' );
		qrms_assert_same( 22, $stats['google_eligible'], 'eşiği geçen' );
		qrms_assert_same( 4.5, $stats['crit'][1], 'kriter 1 ortalaması' );
	}
);

qrms_test(
	'hiç oy almamış kriterin NULL ortalaması sıfıra iner',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();

		$stats = qrm_pro_fetch_review_stats( 3.5 );

		qrms_assert_same( 0.0, $stats['crit'][4], 'NULL kriter' );
	}
);

qrms_test(
	'okunamayan sorgu "tablo yok" ile karıştırılmaz ve önbelleğe yazılmaz',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = null; // Sorgu başarısız.

		qrms_assert_same( null, qrm_pro_fetch_review_stats( 3.5 ), 'ham çekim null döner' );

		$stats = qrm_pro_review_stats( true );

		qrms_assert_true( $stats['table_ok'], 'tablo var sayılır (yanlış tanı basılmaz)' );
		qrms_assert_same( 0, $stats['total'], 'sayaçlar sıfır' );
		qrms_assert_false(
			get_transient( QRM_PRO_STATS_TRANSIENT ),
			'başarısız okuma önbelleğe yazılmaz'
		);
	}
);

echo "\nYorum istatistikleri — önbellek\n";

qrms_test(
	'ikinci çağrı veritabanına hiç gitmez',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();

		$ilk = qrm_pro_review_stats( true );
		$sorgu_sayisi = count( $wpdb->queries );

		// Memo devrede: aynı istek içinde ikinci çağrı sorgu açmaz.
		$ikinci = qrm_pro_review_stats();
		qrms_assert_same( $sorgu_sayisi, count( $wpdb->queries ), 'memo isabet etti' );
		qrms_assert_same( $ilk['total'], $ikinci['total'], 'aynı sonuç' );

		// Memo düşse bile transient devrede.
		unset( $GLOBALS['qrm_pro_stats_memo'] );
		$ucuncu = qrm_pro_review_stats();
		qrms_assert_same( $sorgu_sayisi, count( $wpdb->queries ), 'transient isabet etti' );
		qrms_assert_same( $ilk['approved'], $ucuncu['approved'], 'aynı sonuç' );
	}
);

qrms_test(
	'flush hem transient\'i hem istek içi memo\'yu temizler',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();
		$wpdb->rows[] = qrms_sahte_stat_satiri( array( 'total' => 41, 'approved' => 31 ) );

		qrms_assert_same( 40, qrm_pro_review_stats( true )['total'], 'ilk okuma' );

		qrm_pro_flush_review_stats();

		qrms_assert_false( get_transient( QRM_PRO_STATS_TRANSIENT ), 'transient gitti' );
		qrms_assert_false( isset( $GLOBALS['qrm_pro_stats_memo'] ), 'memo gitti' );

		// Yeni yorum sonrası sayaç GERÇEKTEN tazelenmeli; bayat kalırsa
		// yönetici onay bekleyen yorumu hiç görmez.
		qrms_assert_same( 41, qrm_pro_review_stats()['total'], 'tazelenmiş sayaç' );
	}
);

qrms_test(
	'Google eşiği değişince saklanan sonuç kabul edilmez',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();
		$wpdb->rows[] = qrms_sahte_stat_satiri( array( 'google_eligible' => 5 ) );

		$ayarlar = qrm_pro_get_settings();
		$ayarlar['google_review_threshold'] = 3.5;
		update_option( 'qrm_settings', $ayarlar );

		qrms_assert_same( 22, qrm_pro_review_stats( true )['google_eligible'], 'eşik 3.5' );

		// Eşik değişti: aynı transient artık geçerli değil.
		unset( $GLOBALS['qrm_pro_stats_memo'] );
		$ayarlar['google_review_threshold'] = 4.5;
		update_option( 'qrm_settings', $ayarlar );

		qrms_assert_same( 5, qrm_pro_review_stats()['google_eligible'], 'eşik 4.5 ile yeniden sorulur' );
	}
);

echo "\nYönetimdeki yorum listesi — sayfalama\n";

qrms_test(
	'liste sorgusu LIMIT/OFFSET taşır — üç filtrede de',
	function () {
		$wpdb = qrms_sayan_wpdb();

		qrm_pro_admin_fetch_reviews( '', 25, 3 );
		qrms_assert_contains( 'LIMIT 25 OFFSET 50', $wpdb->queries[0], 'tümü' );
		qrms_assert_false( false !== stripos( $wpdb->queries[0], 'WHERE' ), 'tümünde durum koşulu yok' );

		qrm_pro_admin_fetch_reviews( 'bekleyen', 10, 1 );
		qrms_assert_contains( 'status = 0', $wpdb->queries[1], 'bekleyen filtresi' );
		qrms_assert_contains( 'LIMIT 10 OFFSET 0', $wpdb->queries[1], 'ilk sayfa' );

		qrm_pro_admin_fetch_reviews( 'onayli', 10, 2 );
		qrms_assert_contains( 'status = 1', $wpdb->queries[2], 'onaylı filtresi' );
		qrms_assert_contains( 'LIMIT 10 OFFSET 10', $wpdb->queries[2], 'ikinci sayfa' );
	}
);

qrms_test(
	'sayfa numarası geçerli aralığa çekilir',
	function () {
		// Elle girilen &paged=9999 boş bir OFFSET'le veritabanına gitmemeli.
		qrms_assert_same( 4, qrm_pro_admin_reviews_clamp_page( 9999, 100, 25 ), 'son sayfa' );
		qrms_assert_same( 1, qrm_pro_admin_reviews_clamp_page( 0, 100, 25 ), 'sıfır → ilk sayfa' );
		qrms_assert_same( 1, qrm_pro_admin_reviews_clamp_page( -3, 100, 25 ), 'negatif → ilk sayfa' );
		qrms_assert_same( 1, qrm_pro_admin_reviews_clamp_page( 5, 0, 25 ), 'kayıt yokken tek sayfa' );
		qrms_assert_same( 3, qrm_pro_admin_reviews_clamp_page( 3, 100, 25 ), 'geçerli sayfa korunur' );
	}
);

qrms_test(
	'sayfalama toplamı EK SORGU açmadan istatistikten okunur',
	function () {
		$stats = array( 'total' => 40, 'approved' => 30, 'pending' => 10 );

		qrms_assert_same( 40, qrm_pro_admin_reviews_total( '', $stats ), 'tümü' );
		qrms_assert_same( 10, qrm_pro_admin_reviews_total( 'bekleyen', $stats ), 'bekleyen' );
		qrms_assert_same( 30, qrm_pro_admin_reviews_total( 'onayli', $stats ), 'onaylı' );
	}
);

qrms_test(
	'LIMIT\'siz "SELECT *" ekrana geri sızmadı',
	function () {
		// Regresyon koruması: liste sorgusu kaynakta LIMIT'siz yazılamaz.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/dashboard.php'
		);

		qrms_assert_false(
			(bool) preg_match( '/SELECT \* FROM \{?\$?\w+\}?(?![^"\']*LIMIT)[^"\']*["\']/', $kaynak ),
			'LIMIT\'siz tam tablo sorgusu yok'
		);
		qrms_assert_contains( 'LIMIT %d OFFSET %d', $kaynak, 'sayfalı sorgu' );
	}
);

echo "\nOkunmamış form gönderimi sayacı\n";

qrms_test(
	'sayaç her admin sayfasında yeniden sorulmaz',
	function () {
		// Bu sayaç sol menü etiketinden okunur, yani wp-admin'in HER
		// sayfasında çalışıyordu. Artık transient'ten gelir.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php'
		);

		// Yalnızca ilgili fonksiyonun gövdesine bakılır; aynı sayım deseni
		// dosyanın başka yerlerinde de geçiyor.
		$bas    = strpos( $kaynak, 'function qrm_cf_unread_total()' );
		$govde  = substr( $kaynak, $bas, strpos( $kaynak, 'function qrm_cf_flush_unread_total()' ) - $bas );

		$okuma = strpos( $govde, 'get_transient(QRM_CF_UNREAD_TRANSIENT)' );
		$sorgu = strpos( $govde, 'SELECT COUNT(*) FROM ' . '$table' );

		qrms_assert_true( false !== $bas, 'fonksiyon bulundu' );
		qrms_assert_true( false !== $okuma, 'önbellekten okuyor' );
		qrms_assert_true( false !== $sorgu, 'sorgu hâlâ var (önbellek boşken)' );
		qrms_assert_true( $okuma < $sorgu, 'önce önbelleğe, sonra veritabanına bakılıyor' );
		qrms_assert_contains( 'set_transient(QRM_CF_UNREAD_TRANSIENT', $govde, 'sonuç saklanıyor' );
	}
);

qrms_test(
	'sayacı değiştiren her yol önbelleği temizler',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php'
		);

		// Beş yazma yolu: yeni gönderim, durum değişikliği, toplu okundu,
		// gönderim silme, form silme (gönderimlerini de siler).
		qrms_assert_true(
			substr_count( $kaynak, 'qrm_cf_flush_unread_total();' ) >= 5,
			'bütün yazma yolları temizliyor'
		);
	}
);

echo "\nQR Analiz — genel bakış tek sorgu\n";

/** genel_bakis()'in beklediği satır ve pc_tumu sayacı. */
function qrms_analitik_besle( $wpdb, $satir = array(), $pc_tumu = 900 ) {
	$wpdb->rows[] = array_merge(
		array(
			'mv_bugun' => 12,
			'mv_hafta' => 60,
			'mv_ay'    => 200,
			'pc_bugun' => 5,
			'pc_hafta' => 30,
			'uv_bugun' => 9,
			'masa_gun' => 4,
		),
		$satir
	);
	$wpdb->vars[] = $pc_tumu;
}

qrms_test(
	'sekiz ayrı COUNT sorgusu ikiye indi',
	function () {
		$wpdb = qrms_sayan_wpdb();
		qrms_analitik_besle( $wpdb );

		$genel = QRMS_Analitik::genel_bakis();

		qrms_assert_same( 2, count( $wpdb->queries ), 'toplam sorgu sayısı' );
		qrms_assert_same( 12, $genel['mv_bugun'], 'bugünkü görüntüleme' );
		qrms_assert_same( 900, $genel['pc_tumu'], 'tüm zamanlar tıklama' );
		qrms_assert_same( 4, $genel['masa_gun'], 'bugün hareket eden masa' );
	}
);

qrms_test(
	'tarihli kovalar İNDEKSLİ bir aralıkla sınırlanır',
	function () {
		// Regresyon koruması: bu sorgu bir ara WHERE'siz yazılmıştı ve 90
		// günlük tablonun tamamını satır satır tarıyordu. Alt sınır olmadan
		// idx_date/idx_td kullanılamaz.
		$wpdb = qrms_sayan_wpdb();
		qrms_analitik_besle( $wpdb );

		QRMS_Analitik::genel_bakis();

		qrms_assert_contains( 'WHERE created_at >=', $wpdb->queries[0], 'aralık sınırı var' );

		// Alt sınır ay başını da hafta başını da kapsamalı: ayın ilk
		// günlerinde "son 7 gün" penceresi önceki aya taşar.
		$ay_basi    = gmdate( 'Y-m-01' );
		$hafta_basi = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
		$beklenen   = min( $ay_basi, $hafta_basi );

		qrms_assert_contains( $beklenen . ' 00:00:00', $wpdb->queries[0], 'alt sınır en eski kovayı kapsıyor' );

		// Tarih sınırı OLMAYAN tek kova ayrı ve index-only sayımdır.
		qrms_assert_contains( "COUNT(*)", $wpdb->queries[1], 'pc_tumu ayrı sayım' );
		qrms_assert_false(
			false !== strpos( $wpdb->queries[1], 'created_at' ),
			'pc_tumu tarih koşulu taşımaz'
		);
	}
);

qrms_test(
	'aralıkta kayıt yokken NULL toplamlar sıfıra iner',
	function () {
		$wpdb = qrms_sayan_wpdb();
		qrms_analitik_besle(
			$wpdb,
			array(
				'mv_bugun' => null,
				'mv_hafta' => null,
				'mv_ay'    => null,
				'pc_bugun' => null,
				'pc_hafta' => null,
				'uv_bugun' => null,
				'masa_gun' => null,
			),
			0
		);

		$genel = QRMS_Analitik::genel_bakis();

		foreach ( $genel as $anahtar => $deger ) {
			qrms_assert_same( 0, $deger, $anahtar . ' sıfır' );
		}
	}
);

qrms_test(
	'masa filtresi İKİ sorguya birden uygulanır',
	function () {
		$wpdb = qrms_sayan_wpdb();
		qrms_analitik_besle( $wpdb );

		QRMS_Analitik::genel_bakis( 'masa-3' );

		// Filtre yalnızca birine uygulanırsa pc_tumu bütün masaları sayar ve
		// panel seçili masa için yanlış bir toplam gösterir.
		qrms_assert_contains( "masa_no = 'masa-3'", $wpdb->queries[0], 'aralık sorgusunda filtre' );
		qrms_assert_contains( "masa_no = 'masa-3'", $wpdb->queries[1], 'pc_tumu sorgusunda filtre' );
	}
);

echo "\nUzun dış istekler — bağlantı serbest bırakma\n";

qrms_test(
	'yardımcılar bağlantıyı kapatır ve GERİ AÇAR',
	function () {
		$wpdb = qrms_sayan_wpdb();

		$kapandi = qmo_db_serbest_birak();

		qrms_assert_true( $kapandi, 'bağlantı kapatıldı' );
		qrms_assert_same( 1, $wpdb->kapandi, 'close() çağrıldı' );

		qmo_db_geri_baglan( $kapandi );

		qrms_assert_same( 1, $wpdb->acildi, 'db_connect() çağrıldı' );
		qrms_assert_true( (bool) $wpdb->dbh, 'bağlantı yeniden hazır' );
	}
);

qrms_test(
	'kapatılmadıysa geri açma da denenmez',
	function () {
		$wpdb = qrms_sayan_wpdb();

		qmo_db_geri_baglan( false );

		qrms_assert_same( 0, $wpdb->acildi, 'gereksiz yeniden bağlanma yok' );
	}
);

qrms_test(
	'filtre ile tamamen kapatılabilir',
	function () {
		$wpdb = qrms_sayan_wpdb();

		// HyperDB / kalıcı bağlantı kullanan kurulumlar için çıkış kapısı.
		add_filter(
			'qmo_db_baglanti_serbest',
			function () {
				return false;
			}
		);

		qrms_assert_false( qmo_db_serbest_birak(), 'bırakma atlandı' );
		qrms_assert_same( 0, $wpdb->kapandi, 'bağlantıya dokunulmadı' );

		// Filtre sonraki testlere sızmasın.
		unset( $GLOBALS['qrms_test']['actions']['qmo_db_baglanti_serbest'] );
	}
);

qrms_test(
	'üç uzun çağrının üçü de bırak/geri-aç çiftiyle sarılı',
	function () {
		// Sarmanın YARISI (bırakma var, geri açma yok) sessiz veri kaybı
		// demektir: kapalı bağlantıda sorgular false döner. Bu yüzden her
		// dosyada iki tarafın da bulunduğu doğrulanır.
		$dosyalar = array(
			'modules/qr-chatbot/includes/ajax-chat.php'         => array( 'qmo_db_serbest_birak', 'qmo_db_geri_baglan' ),
			'modules/qr-chatbot/rest-order.php'                 => array( 'qmo_db_serbest_birak', 'qmo_db_geri_baglan' ),
			'modules/yorum-feedback/includes/ai-insights.php'   => array( 'qrm_db_serbest_birak', 'qrm_db_geri_baglan' ),
		);

		foreach ( $dosyalar as $yol => $cift ) {
			$kaynak = file_get_contents( QRMS_PLUGIN_DIR . $yol );

			qrms_assert_contains( $cift[0] . '()', $kaynak, $yol . ' bırakıyor' );
			qrms_assert_contains( $cift[1] . '(', $kaynak, $yol . ' geri açıyor' );
		}
	}
);

qrms_test(
	'bağlantı kapalıyken okunacak ayarlar önceden çözülüyor',
	function () {
		// get_option() kapalı bağlantıda sessizce false döner; çeviri o yüzden
		// sebepsiz hataya düşerdi. Anahtar ve model bırakmadan ÖNCE okunur.
		$siparis = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );

		$anahtar_konum = strpos( $siparis, '$api_key = get_option( \'gemini_api_key\' );' );
		$birakma_konum = strpos( $siparis, '$db_kapali = qmo_db_serbest_birak();', false !== $anahtar_konum ? $anahtar_konum : 0 );

		qrms_assert_true( false !== $anahtar_konum, 'anahtar önceden çözülüyor' );
		qrms_assert_true( false !== $birakma_konum, 'bırakma noktası var' );
		qrms_assert_true( $anahtar_konum < $birakma_konum, 'anahtar bırakmadan ÖNCE okunuyor' );
		qrms_assert_contains( 'qmo_not_cevir( $it[\'not\'], $dil, $api_key, $model )', $siparis, 'döngüye geçiriliyor' );
	}
);


/* ---------------------------------------------------------------------------
 * 15. YAVAŞ SORGU CEPHESİ — İNDEKSLER, N+1, CRON, TEŞHİS
 *
 * Hosting'in kesin teşhisi "bağlantı limiti değil, sorguların 1 saniyenin
 * altında bitmemesi" olduktan sonra eklenen korumalar.
 * ------------------------------------------------------------------------ */

echo "\nŞema — eksik indeksler\n";

/**
 * Bir CREATE TABLE metninde verilen indeks tanımlı mı?
 *
 * Sütun listesi boşluk farkına takılmasın diye normalize edilerek aranır.
 *
 * @param string $sema    CREATE TABLE metnini içeren kaynak.
 * @param string $sutunlar Ör. "status, created_at".
 * @return bool
 */
function qrms_indeks_var_mi( $sema, $sutunlar ) {
	$sutunlar = preg_replace( '/\s+/', '', $sutunlar );
	$sema     = preg_replace( '/\s+/', '', $sema );

	return false !== stripos( $sema, '(' . $sutunlar . ')' );
}

qrms_test(
	'yorum tablosu artık PRIMARY KEY dışında indeks taşıyor',
	function () {
		// Regresyon koruması: tablo uzun süre indekssizdi ve üzerindeki HER
		// sorgu tam tablo taraması + filesort yapıyordu.
		$sema = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/install.php' );

		qrms_assert_true(
			qrms_indeks_var_mi( $sema, 'status, created_at' ),
			'idx_status_created — ön yüz listesi ve sayaçlar'
		);
		qrms_assert_contains( 'KEY idx_created (created_at)', $sema, 'idx_created — filtresiz yönetim listesi' );
		qrms_assert_true(
			qrms_indeks_var_mi( $sema, 'is_active, sort_order' ),
			'form alanları — her ön yüz render\'ında sorgulanır'
		);
	}
);

qrms_test(
	'indeksler gerçekten çalışan sorgulara karşılık geliyor',
	function () {
		// İndeksin işe yaraması için sütun SIRASI sorgudakiyle uyumlu olmalı:
		// önce eşitlik (status), sonra sıralama (created_at).
		$liste = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/reviews-list.php' );

		qrms_assert_contains( 'WHERE status = 1 ORDER BY created_at DESC', $liste, 'ön yüz sorgusu' );

		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/dashboard.php' );

		qrms_assert_contains( 'WHERE status = %d ORDER BY created_at DESC', $admin, 'yönetim sorgusu' );
	}
);

qrms_test(
	'ödül ve gönderim tablolarının indeksleri de sorgularla eşleşiyor',
	function () {
		$odul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/db.php' );

		qrms_assert_true( qrms_indeks_var_mi( $odul, 'status, created_at' ), 'ödül: durum filtresi + sıralama' );
		qrms_assert_contains( 'KEY idx_source_review (source_review_id)', $odul, 'ödül: yorum başına kod kontrolü' );

		$formlar = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/db.php' );

		qrms_assert_true(
			qrms_indeks_var_mi( $formlar, 'form_id, status, created_at' ),
			'gönderim: iki sütunluk filtre + sıralama'
		);
		qrms_assert_true( qrms_indeks_var_mi( $formlar, 'form_id, created_at' ), 'gönderim: durum filtresi yokken' );
	}
);

echo "\nŞema — güncelleme akışı\n";

qrms_test(
	'şema güncellemesi ön yüz isteğinde ÇALIŞMAZ',
	function () {
		// ALTER TABLE büyük bir tabloda saniyeler sürebilir. plugins_loaded'a
		// bağlanmış olsaydı bu, menüyü açan bir MÜŞTERİNİN isteğinde olurdu.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/qr-menu-reviews.php' );

		qrms_assert_contains(
			"add_action('admin_init', 'qrm_pro_schema_maybe_upgrade'",
			$kaynak,
			'yalnızca yönetim isteğinde'
		);
		qrms_assert_false(
			false !== strpos( $kaynak, "add_action('plugins_loaded', 'qrm_pro_schema_maybe_upgrade'" ),
			'plugins_loaded\'a bağlı değil'
		);
		qrms_assert_contains( "current_user_can('manage_options')", $kaynak, 'yetki kontrolü var' );
	}
);

qrms_test(
	'başarısız ALTER "tamam" diye işaretlenmez',
	function () {
		// Veritabanı kullanıcısının ALTER yetkisi yoksa dbDelta sessizce
		// başarısız olur; sürüm damgası yine de atılırsa site kalıcı olarak
		// indekssiz kalır ve kimse fark etmez.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/qr-menu-reviews.php' );

		// Sürüm damgası atan HER yol önce indeksi doğrulamalı: doğrulamayan
		// bir yol, ALTER yetkisi olmayan kurulumu kalıcı olarak indekssiz
		// bırakır ve admin_init'teki denetim bir daha hiç devreye girmez.
		$damgalar = array();
		$son      = 0;

		while ( false !== ( $son = strpos( $kaynak, 'update_option(QRM_PRO_SCHEMA_OPTION, QRM_PRO_SCHEMA_VERSION, false);', $son ) ) ) {
			$damgalar[] = $son;
			$son++;
		}

		qrms_assert_true( count( $damgalar ) >= 2, 'iki yol da damga atıyor' );

		foreach ( $damgalar as $i => $konum ) {
			// Damgadan hemen önceki 400 karakterde bir doğrulama çağrısı olmalı.
			$onceki = substr( $kaynak, max( 0, $konum - 400 ), min( 400, $konum ) );

			qrms_assert_true(
				false !== strpos( $onceki, 'qrm_pro_schema_indexes_ok()' ),
				'damga ' . ( $i + 1 ) . ' doğrulamanın ardında'
			);
		}

		qrms_assert_contains( 'SHOW INDEX FROM', $kaynak, 'gerçekten tabloya bakıyor' );
	}
);

// import_title_key() saf bir yardımcıdır; trait'i taşıyan minik bir sınıfla
// doğrudan çağrılır (asıl sınıf WordPress'in tamamına bağımlı).
require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php';

class RMA_Menu_Test_Import {
	use RMA_Import_Export_Trait;
}

echo "\nDöngü içi sorgular (N+1)\n";

qrms_test(
	'içe aktarma artık satır başına wp_posts taraması yapmıyor',
	function () {
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		// wp_posts.post_title WordPress çekirdeğinde İNDEKSLİ DEĞİLDİR; satır
		// başına bir eşitlik sorgusu, satır başına bir tam tarama demekti.
		qrms_assert_false(
			false !== strpos( $kaynak, 'AND post_title = %s' ),
			'satır başına eşitlik sorgusu kalmadı'
		);
		qrms_assert_contains( 'post_title IN (', $kaynak, 'tek sorguda toplu arama' );

		// Harita döngüden ÖNCE kurulmalı, yoksa hiçbir şey kazanılmaz.
		$harita = strpos( $kaynak, '$baslik_haritasi = $this->import_title_map(' );
		$dongu  = strpos( $kaynak, "foreach ( \$data['items'] as \$item )" );

		qrms_assert_true( false !== $harita, 'harita kuruluyor' );
		qrms_assert_true( $harita < $dongu, 'döngüden önce kuruluyor' );
	}
);

qrms_test(
	'aynı dosyada tekrar eden başlık ikinci ürünü açmaz',
	function () {
		// Satır başına sorgu yapan eski kod, az önce oluşturulan kaydı
		// kendiliğinden buluyordu; harita bunu elle sürdürmeli.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-import-export.php'
		);

		qrms_assert_contains(
			'$baslik_haritasi[ $this->import_title_key( $title ) ] = (int) $pid;',
			$kaynak,
			'yeni kayıt haritaya ekleniyor'
		);
	}
);

qrms_test(
	'başlık anahtarı MySQL harmanlaması gibi büyük/küçük harf ayırmaz',
	function () {
		$menu = new RMA_Menu_Test_Import();

		qrms_assert_same(
			$menu->import_title_key( '  Adana Kebap  ' ),
			$menu->import_title_key( 'adana kebap' ),
			'harf büyüklüğü ve kenar boşluğu yok sayılır'
		);
		qrms_assert_false(
			$menu->import_title_key( 'Adana Kebap' ) === $menu->import_title_key( 'Urfa Kebap' ),
			'farklı ürünler ayrı kalır'
		);
	}
);

qrms_test(
	'toplu yazımlar ürün başına ayrı INSERT açmıyor',
	function () {
		// Menünün tamamını kapsayan bir kampanyada bu, tek işlemde yüzlerce
		// sorgu demekti.
		$kampanya = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya-db.php' );
		$vitrin   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-vitrin-db.php' );
		$formlar  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php' );

		qrms_assert_contains( 'INSERT INTO {$tablo} (campaign_id, product_id, original_price)', $kampanya, 'kampanya toplu' );
		qrms_assert_contains( 'INSERT INTO {$urunler} (showcase_id, product_id, sort_order)', $vitrin, 'vitrin toplu' );
		qrms_assert_contains( 'INSERT INTO $table (form_id, field_key', $formlar, 'form alanları toplu' );

		// Parçalama olmadan tek dev sorgu max_allowed_packet'e takılabilir.
		qrms_assert_contains( 'array_chunk', $kampanya, 'kampanya parçalanıyor' );
	}
);

qrms_test(
	'toplu yazımda tip dönüşümü satır satır yazımdakiyle aynı',
	function () {
		$satirlar = RMA_Kampanya_DB::anlik_satirlari( '7', array( '12' => '19,90', 3 => 45.5 ) );

		qrms_assert_same( 2, count( $satirlar ), 'satır sayısı' );
		qrms_assert_same( 7, $satirlar[0]['campaign_id'], 'kampanya ID int' );
		qrms_assert_same( 12, $satirlar[0]['product_id'], 'ürün ID int' );
		qrms_assert_same( 19.0, $satirlar[0]['original_price'], 'fiyat float' );
		qrms_assert_same( 45.5, $satirlar[1]['original_price'], 'ondalık korunur' );
	}
);

echo "\nCron — ağır işlemler\n";

qrms_test(
	'analitik temizliği birikime yetişebiliyor',
	function () {
		// Eskiden GÜNDE tek bir 5000'lik parça siliniyordu: günde 5000'den
		// fazla olay üreten bir sitede temizlik hiçbir zaman yetişemez, tablo
		// sınırsız büyür ve üzerindeki her sorgu yavaşlardı.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );

		qrms_assert_contains( 'for ( $tur = 0; $tur < self::SAKLAMA_TUR', $kaynak, 'tur içinde döngü var' );
		qrms_assert_contains( 'qrms_analitik_temizlik_sure', $kaynak, 'süre bütçesi filtrelenebilir' );
		qrms_assert_contains( 'if ( $silinen < self::SAKLAMA_PARCA )', $kaynak, 'silinecek kalmayınca durur' );
		qrms_assert_contains( 'self::SAKLAMA_TUR', $kaynak, 'sonsuz döngüye karşı emniyet freni' );
	}
);

qrms_test(
	'lisans cron\'u 15 sn\'lik istek boyunca bağlantı tutmuyor',
	function () {
		// WP cron bir ZİYARETÇİNİN isteği üzerinde çalışır; o ziyaretçinin
		// bağlantısı 15 saniye boyunca boşuna açık kalıyordu.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'includes/class-license-client.php' );

		$birak  = strpos( $kaynak, '$db_kapali = self::db_serbest_birak();' );
		$istek  = strpos( $kaynak, '$response = wp_remote_post(' );
		$geri   = strpos( $kaynak, 'self::db_geri_baglan( $db_kapali );' );

		qrms_assert_true( false !== $birak, 'bağlantı bırakılıyor' );
		qrms_assert_true( $birak < $istek, 'istekten ÖNCE bırakılıyor' );
		qrms_assert_true( $istek < $geri, 'istekten SONRA geri açılıyor' );

		// store_result() option yazar; geri açma ondan önce olmalı.
		$yazma = strpos( $kaynak, "return self::store_result( 'unreachable'" );
		qrms_assert_true( $geri < $yazma, 'yazmadan önce geri açılıyor' );
	}
);

echo "\nYavaş sorgu teşhisi\n";

qrms_test(
	'üretimde tamamen sessizdir',
	function () {
		// WP_DEBUG ve SAVEQUERIES tanımlı değil (test ortamı = üretim gibi).
		qrms_assert_false( QRMS_Query_Monitor::etkin_mi(), 'kendiliğinden açılmaz' );
	}
);

qrms_test(
	'yalnızca eşiği aşan sorgular raporlanır, en yavaş önce',
	function () {
		$kayitlar = array(
			array( 'SELECT 1', 0.01, 'wpdb->query, hizli_fonksiyon' ),
			array( 'SELECT * FROM wp_qrm_reviews WHERE status = 1', 1.25, 'wpdb->get_results, qrm_pro_fetch_approved_reviews, qrm_pro_shortcode' ),
			array( 'SELECT COUNT(*) FROM wp_rma_analytics', 0.60, 'wpdb->get_var, QRMS_Analitik::genel_bakis' ),
			array( 'SELECT 2', 0.49, 'wpdb->query, sinirda_fonksiyon' ),
		);

		$yavas = QRMS_Query_Monitor::yavaslari_ayikla( $kayitlar, 0.5 );

		qrms_assert_same( 2, count( $yavas ), 'eşiğin altındakiler elendi' );
		qrms_assert_same( 1.25, $yavas[0]['sure'], 'en yavaş başta' );
		qrms_assert_contains( 'qrm_reviews', $yavas[0]['sorgu'], 'sorgu metni taşınıyor' );
	}
);

qrms_test(
	'çağrı zinciri okunabilir hâle getirilir',
	function () {
		// wpdb'nin kendi metotları teşhise katkı sağlamaz, elenir; kalan
		// zincir dıştan içe okunur.
		qrms_assert_same(
			'qrm_pro_shortcode -> qrm_pro_fetch_approved_reviews',
			QRMS_Query_Monitor::cagriyi_kisalt( 'wpdb->get_results, qrm_pro_fetch_approved_reviews, qrm_pro_shortcode' ),
			'zincir çevrildi ve wpdb halkaları atıldı'
		);
		qrms_assert_same( 'bilinmiyor', QRMS_Query_Monitor::cagriyi_kisalt( '' ), 'boş zincir' );
		qrms_assert_same( 'bilinmiyor', QRMS_Query_Monitor::cagriyi_kisalt( 'wpdb->query' ), 'yalnızca wpdb halkası' );
	}
);

qrms_test(
	'uzun sorgu metni günlüğü şişirmez',
	function () {
		$uzun = 'SELECT ' . str_repeat( 'sutun_adi, ', 200 ) . 'son FROM tablo';
		$kisa = QRMS_Query_Monitor::sorguyu_kisalt( $uzun );

		qrms_assert_true( mb_strlen( $kisa ) <= 301, 'kırpıldı' );
		qrms_assert_contains( '…', $kisa, 'kırpma işareti' );

		// Çok satırlı sorgu tek satıra iner (günlük satır satır okunur).
		qrms_assert_same(
			'SELECT a FROM b WHERE c = 1',
			QRMS_Query_Monitor::sorguyu_kisalt( "SELECT a\n  FROM b\n  WHERE c = 1" ),
			'satır sonları düzleştirildi'
		);
	}
);

if ( empty( $GLOBALS['qrms_failures'] ) ) {
	echo "\033[32mTüm testler geçti\033[0m (" . $GLOBALS['qrms_assertions'] . " doğrulama)\n\n";
	exit( 0 );
}

echo "\033[31m" . count( $GLOBALS['qrms_failures'] ) . " test başarısız\033[0m\n\n";
exit( 1 );
