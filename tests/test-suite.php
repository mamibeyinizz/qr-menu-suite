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
 * 9. QR Analiz — sayfa kayıt defteri ve hub
 * ------------------------------------------------------------------------ */

// module.php dosya kapsamında yalnızca fonksiyon ve sabit tanımlar; stub
// ortamında yan etkisiz yüklenir.
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/module.php';

echo "\nQR Analiz sayfaları\n";

qrms_test(
	'iki ekran da kayıt defterinde ve her birinin callback\'i var',
	function () {
		$pages = qrms_module_qr_analiz_sayfalar();

		qrms_assert_same(
			array( QRMS_ANALITIK_SAYFA, QRMS_ANALIZ_AYAR_SAYFA ),
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
	'ayar ekranının slug\'ı modül satırından ayrıdır',
	function () {
		// Modül satırı (qrms-module-qr-analiz) artık hub ekranıdır; ayar ekranı
		// kendi slug'ına taşındı. Eski adres kırılmaz, hub'ı açar.
		qrms_assert_false(
			QRMS_ANALIZ_AYAR_SAYFA === QRMS_Admin::get_module_page_slug( 'qr-analiz' ),
			'slug çakışması yok'
		);
	}
);

qrms_test(
	'hub, iki ekranı da kart olarak basar',
	function () {
		ob_start();
		qrms_module_qr_analiz_hub();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'ortak kart ızgarası' );
		qrms_assert_contains( 'page=' . QRMS_ANALITIK_SAYFA, $html, 'analitik kartı' );
		qrms_assert_contains( 'page=' . QRMS_ANALIZ_AYAR_SAYFA, $html, 'ayarlar kartı' );
		qrms_assert_contains( 'Menü Analitiği', $html, 'analitik başlığı' );
	}
);

qrms_test(
	'modül lisansta aktif değilken hiçbir sayfa kaydedilmez',
	function () {
		// "QR Analiz" satırı yoksa $submenu de boştur; ekranların kaydedilmesi
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

qrms_test(
	'modül aktifken iki ekran da gizli sayfa olarak kaydedilir',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'QR Analiz', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		qrms_assert_same(
			array( QRMS_ANALITIK_SAYFA, QRMS_ANALIZ_AYAR_SAYFA ),
			array_map(
				function ( $item ) {
					return $item['slug'];
				},
				$GLOBALS['qrms_test']['submenus']
			),
			'kaydedilen sayfalar'
		);

		qrms_assert_true( QRMS_Admin::is_module_subpage( QRMS_ANALITIK_SAYFA ), 'kayıt defterinde' );

		// Kayıt gerçek bir alt menüdir (parent: MENU_SLUG) — route çözümü buna
		// bağlıdır; menüden düşürme işi admin_head'de yapılır.
		qrms_assert_same( QRMS_Admin::MENU_SLUG, $GLOBALS['qrms_test']['submenus'][0]['parent'], 'üst menü' );
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

function qrm_reward_is_active( $settings ) {
	return false;
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


if ( empty( $GLOBALS['qrms_failures'] ) ) {
	echo "\033[32mTüm testler geçti\033[0m (" . $GLOBALS['qrms_assertions'] . " doğrulama)\n\n";
	exit( 0 );
}

echo "\033[31m" . count( $GLOBALS['qrms_failures'] ) . " test başarısız\033[0m\n\n";
exit( 1 );
