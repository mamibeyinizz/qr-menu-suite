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
	$GLOBALS['qrms_test']['menus']      = array();
	$GLOBALS['qrms_test']['submenus']   = array();
	$GLOBALS['qrms_test']['removed']    = array();
	$GLOBALS['qrms_test']['redirects']  = array();
	$GLOBALS['qrms_test']['http']       = null;
	$GLOBALS['qrms_test']['http_calls'] = array();
	$GLOBALS['qrms_test']['can']        = true;
	$GLOBALS['qrms_test']['styles']     = array();
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
	'sol menü flyout CSS\'i plugin ekranı dışında da yüklenir',
	function () {
		$_GET = array();

		QRMS_Admin::enqueue_admin_menu_css();
		QRMS_Admin::enqueue_assets();

		$handles = array_map(
			function ( $style ) {
				return $style['handle'];
			},
			$GLOBALS['qrms_test']['styles']
		);

		qrms_assert_true( in_array( 'qrms-admin-menu', $handles, true ), 'flyout stili' );
		qrms_assert_false( in_array( 'qrms-admin', $handles, true ), 'ekran stili yüklenmemeli' );
	}
);

qrms_test(
	'admin CSS native #adminmenu flyout gizlemesini ezmez',
	function () {
		$files = array(
			QRMS_PLUGIN_DIR . 'assets/css/admin.css',
			QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css',
			QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets/css/admin.css',
		);

		foreach ( $files as $file ) {
			$css = file_get_contents( $file );
			qrms_assert_true( is_string( $css ) && '' !== $css, basename( $file ) . ' okunmalı' );
			$css = preg_replace( '#/\*.*?\*/#s', '', $css );
			qrms_assert_false(
				(bool) preg_match( '/#adminmenu|\.wp-submenu|\.wp-has-submenu/', $css ),
				basename( $file ) . ' sol menüyü hedeflememeli'
			);
		}

		$menu_css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/admin-menu.css' );
		qrms_assert_contains( 'wp-not-current-submenu', $menu_css, 'yalnızca açık olmayan menü' );
		qrms_assert_contains( 'top: -1000em', $menu_css, 'WordPress gizleme noktası' );
		$menu_css = preg_replace( '#/\*.*?\*/#s', '', $menu_css );
		qrms_assert_false(
			(bool) preg_match( '/wp-has-current-submenu/', $menu_css ),
			'açık sayfanın alt menüsü gizlenmemeli'
		);
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
function qrm_pro_get_settings() {
	return array( 'qrm_reward_enabled' => 1 );
}

function qrm_reward_is_active( $settings ) {
	return false;
}

function qrm_cf_unread_total() {
	return 2;
}

function qrm_pro_review_stats() {
	return array(
		'table_ok' => true,
		'pending'  => 3,
		'total'    => 12,
		'approved' => 9,
		'avg'      => 4.25,
	);
}

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

if ( empty( $GLOBALS['qrms_failures'] ) ) {
	echo "\033[32mTüm testler geçti\033[0m (" . $GLOBALS['qrms_assertions'] . " doğrulama)\n\n";
	exit( 0 );
}

echo "\033[31m" . count( $GLOBALS['qrms_failures'] ) . " test başarısız\033[0m\n\n";
exit( 1 );
