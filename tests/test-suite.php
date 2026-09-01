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
	$GLOBALS['qrms_test']['priorities'] = array();

	// Önbellek temizleme izleri: hangi eylemler tetiklendi, hangi gruplar
	// boşaltıldı, arka uç grup bazlı temizliği destekliyor mu.
	$GLOBALS['qrms_test']['fired_actions']  = array();
	$GLOBALS['qrms_test']['cache_flush']    = array();
	$GLOBALS['qrms_test']['cache_supports'] = array();

	// Kısa kod defteri statik bir durumdur ve testler arasında taşınır: bir
	// modülün init'ini çağıran test, sonraki testin menüsüne satır ekletirdi.
	QRMS_Shortcodes::reset();

	if ( class_exists( 'QRMS_Analitik' ) && method_exists( 'QRMS_Analitik', 'genel_bakis_onbellegini_temizle' ) ) {
		QRMS_Analitik::genel_bakis_onbellegini_temizle();
	}

	// add_shortcode() taklidinin defteri de aynı nedenle sıfırlanır; aksi
	// hâlde bir testte kaydedilen kısa kod sonraki testte "kurulu" görünür.
	$GLOBALS['qrms_test']['shortcodes'] = array();
	$GLOBALS['qrms_test']['json']       = null;
	$GLOBALS['qrms_test']['is_admin']   = false;

	$GLOBALS['qrms_test']['menus']      = array();
	$GLOBALS['qrms_test']['submenus']   = array();
	$GLOBALS['qrms_test']['removed']    = array();
	$GLOBALS['qrms_test']['redirects']  = array();
	$GLOBALS['qrms_test']['http']       = null;
	$GLOBALS['qrms_test']['http_calls'] = array();
	$GLOBALS['qrms_test']['can']        = true;
	$GLOBALS['qrms_test']['logged_in']  = false;
	$GLOBALS['qrms_test']['styles']     = array();
	$GLOBALS['qrms_test']['scripts']    = array();
	$GLOBALS['qrms_test']['inline_styles'] = array();
	$GLOBALS['qrms_test']['localized']     = array();
	$GLOBALS['qrms_test']['settings']       = array();
	$GLOBALS['qrms_test']['settings_fields'] = array();

	$GLOBALS['menu']                    = array();
	$GLOBALS['submenu']                 = array();
	$_POST                              = array();
	$_GET                               = array();
	$_COOKIE                            = array();

	// [qmo_sepet] istek-içi tek basım bayrağı testler arasında taşınmasın.
	if ( function_exists( 'qmo_sepet_istekte_basildi' ) ) {
		qmo_sepet_istekte_basildi( false );
	}
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
		qrms_assert_contains( 'rma_hub_today_views_', $php, 'görüntülenme transient anahtarı' );
		qrms_assert_contains( "event_type = %s", $php, 'tek COUNT sorgusu' );
		qrms_assert_contains( "'menu_view'", $php, 'menü görüntüleme olayı' );
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

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-oturum.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php';

echo "\nQMO Sepet — masa kısıtı ve modal seçicileri\n";

qrms_test(
	'module.php init shortcode-sepet.php\'yi require eder',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/module.php' );

		qrms_assert_contains(
			"require_once __DIR__ . '/includes/shortcode-sepet.php'",
			$php,
			'init zincirinde require var'
		);
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' ),
			'dosya yolu gerçek'
		);
	}
);

qrms_test(
	'sepet JS/CSS kayıtlı handle ile enqueue edilir',
	function () {
		$kayit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );
		$kisa  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );

		qrms_assert_contains( "wp_register_style( 'qmo-sepet'", $kayit, 'CSS kaydı' );
		qrms_assert_contains( "wp_register_script( 'qmo-sepet'", $kayit, 'JS kaydı' );
		qrms_assert_contains( 'css/sepet.css', $kayit, 'CSS yolu' );
		qrms_assert_contains( 'js/sepet.js', $kayit, 'JS yolu' );
		qrms_assert_contains( "qmo_asset_enqueue( 'qmo-sepet' )", $kisa, 'kısa kod render\'da enqueue' );
		qrms_assert_contains( 'ajax-sepet-analitik.php', $kayit, 'sepet analitik AJAX ucu yüklenir' );
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-sepet-analitik.php' ),
			'analitik AJAX dosyası durur'
		);
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' ),
			'sepet.js durur'
		);
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/sepet.css' ),
			'sepet.css durur'
		);
	}
);

qrms_test(
	'sepet analitik ucu nonce, oturum masası ve hız sınırı kullanır',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-sepet-analitik.php' );

		qrms_assert_contains( 'qmo_nonce_dogrula()', $php, 'nonce' );
		qrms_assert_contains( 'qmo_oturum()', $php, 'masa oturumdan' );
		qrms_assert_contains( "qmo_hiz_siniri( 'sepet_olay'", $php, 'hız sınırı' );
		qrms_assert_contains( "class_exists( 'QRMS_Analitik' )", $php, 'analitik yoksa no-op' );
		qrms_assert_contains( 'qmo_analitik_urun_alani', $php, 'yayın ürünü doğrulanır' );
		qrms_assert_contains( 'qmo_analitik_yaz', $php, 'kaydet köprüsü' );
		qrms_assert_false( false !== strpos( $php, "\$_POST['masa" ), 'istemci masasına güvenilmez' );
	}
);

qrms_test(
	'sepet JS dış kapsayıcıyı qrms-detail-* ile arar, içerik class\'larına dokunmaz',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( "body.closest( '.qrms-detail-box, .rma-modal-box' )", $js, 'görsel kutusu yeni + eski dış class' );
		qrms_assert_contains( "kutu.querySelector( '.rma-modal-img' )", $js, 'görsel class\'ı AJAX içeriğinde durur' );
		qrms_assert_contains( "'.qrms-detail-overlay, .qrms-detail-box, .rma-modal-overlay, .rma-modal-box, .rma-modal-body'", $js, 'modalAcikMi yeni dış + duran iç class' );
		qrms_assert_contains( "'.rma-modal-body:not([data-qmo])'", $js, 'enjeksiyon hedefi hâlâ rma-modal-body' );
		qrms_assert_contains( "'.rma-modal-title'", $js, 'başlık class\'ı durur' );
		qrms_assert_contains( "'.rma-modal-price'", $js, 'fiyat class\'ı durur' );
		qrms_assert_contains( "'.rma-price-new, .qmo-kombin-new-price'", $js, 'yalnızca güncel fiyat span\'i' );
		qrms_assert_contains( 'fiyatMetni', $js, 'kampanyalı fiyatta eski+yeni birleşmez' );
		qrms_assert_contains( "'.rma-card, .qrms-vitrin-card, .qmo-slider-product'", $js, 'vitrin/slider kartı da modal yakalar' );
		qrms_assert_contains( 'qmoSepet.endpoint', $js, 'sipariş qmoSepet.endpoint üzerinden gider' );
		qrms_assert_contains( 'qmo_sepet_olay', $js, 'sepet analitik ucu' );
		qrms_assert_contains( 'analitikKuyrukla', $js, 'sepet olayları kuyruklanır' );
		qrms_assert_contains( 'ANALITIK_PENCERE', $js, 'debounce penceresi' );
		qrms_assert_contains( 'body.getAttribute( \'data-id\' )', $js, 'ürün kimliği modal data-id' );
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );
		qrms_assert_contains( 'qrservis/v1/order', $php, 'REST adresi shortcode\'da üretilir' );
		qrms_assert_contains( "'analitik'", $php, 'analitik bayrağı localize edilir' );
		qrms_assert_contains( 'QMO_NONCE_ACTION', $php, 'sepet analitik nonce\'u sipariş/AJAX deseni' );
	}
);

qrms_test(
	'sepet kampanyalı/kombin fiyatta yalnızca güncel tutarı okur',
	function () {
		$js     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		$kamp   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-kampanya.php' );
		$ajax   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );
		$kombin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/admin-kombin-meta.php' );

		qrms_assert_contains( 'class="rma-price-old"', $kamp, 'üstü çizili eski fiyat span\'i' );
		qrms_assert_contains( "'rma-price-new '", $kamp, 'güncel kampanya fiyatı span\'i' );
		qrms_assert_contains( 'class="rma-modal-price"', $ajax, 'modal fiyat kapsayıcısı' );
		qrms_assert_contains( 'qmo-kombin-old-price', $kombin, 'kombin eski fiyat' );
		qrms_assert_contains( 'qmo-kombin-new-price', $kombin, 'kombin yeni fiyat' );
		qrms_assert_contains( 'fiyatMetni( fiyatEl )', $js, 'parse kapsayıcının güncel span\'inden' );
		qrms_assert_false(
			false !== strpos( $js, 'fiyatEl ? fiyatEl.textContent' ),
			'kapsayıcının tüm metni (eski+yeni) parse edilmez'
		);
	}
);

qrms_test(
	'sepet TL yazımı kuruşu korur, tam sayıda ondalığı gizler',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( 'function fiyatYazi', $js, 'TL biçimleyici' );
		qrms_assert_contains( 'RMA_Kampanya_DB::bicimle()', $js, 'ürün kartı kuralıyla aynı' );
		qrms_assert_contains( "fiyatYazi( x.fiyat * x.adet )", $js, 'satır fiyatı' );
		qrms_assert_contains( 'barTot.textContent = \'₺\' + fiyatYazi( t )', $js, 'çubuk toplamı' );
		qrms_assert_contains( 'tot.textContent = \'₺\' + fiyatYazi( t )', $js, 'çekmece toplamı' );
		qrms_assert_contains( "',00' === metin.slice( -3 )", $js, 'tam sayıda ,00 gizlenir' );
		qrms_assert_contains( 'parseFloat( t )', $js, 'fiyat parseFloat' );
		qrms_assert_false( false !== strpos( $js, 'toFixed( 0 )' ), 'toFixed(0) boşluklu yok' );
		qrms_assert_false( false !== strpos( $js, 'toFixed(0)' ), 'toFixed(0) yok' );
	}
);

qrms_test(
	'AJAX ürün detayı hâlâ rma-modal-body ve rma-modal-img basar',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-ajax.php' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-detail-modal.js' );

		qrms_assert_contains( 'class="rma-modal-body"', $php, 'iç HTML kökünde rma-modal-body' );
		qrms_assert_contains( 'data-id="%d"', $php, 'ürün kimliği modal gövdede' );
		qrms_assert_contains( 'class="rma-modal-img"', $php, 'img class\'ı rma-modal-img' );
		qrms_assert_contains( "inner.innerHTML = html", $js, 'içerik qrms-detail-inner\'a enjekte edilir' );
		qrms_assert_contains( "overlay.className = 'qrms-detail-overlay'", $js, 'dış overlay yeni class' );
		qrms_assert_contains( "'<div class=\"qrms-detail-box\">'", $js, 'dış kutu yeni class' );
	}
);

qrms_test(
	'masa oturumu yokken ve yönetici değilken sepet boş döner',
	function () {
		$GLOBALS['qrms_test']['can']       = false;
		$GLOBALS['qrms_test']['logged_in'] = false;
		unset( $_COOKIE[ QMO_Oturum::COOKIE ] );

		qrms_assert_false( qmo_sepet_izinli_mi(), 'izin yok' );
		qrms_assert_same( '', qmo_sepet_shortcode(), 'çıktı boş' );
	}
);

qrms_test(
	'yönetici masa parametresi olmadan da sepeti görür',
	function () {
		$GLOBALS['qrms_test']['can']       = true;
		$GLOBALS['qrms_test']['logged_in'] = true;
		unset( $_COOKIE[ QMO_Oturum::COOKIE ] );

		qrms_assert_true( qmo_sepet_izinli_mi(), 'admin muaf' );
	}
);

qrms_test(
	'geçerli masa oturumu olan müşteri sepeti görür',
	function () {
		$GLOBALS['qrms_test']['can']       = false;
		$GLOBALS['qrms_test']['logged_in'] = false;
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-sepet-icin-yeterince-uzun';

		$token = QMO_Oturum::token_uret( 'masa-31' );
		$_COOKIE[ QMO_Oturum::COOKIE ] = $token;

		qrms_assert_true( qmo_sepet_izinli_mi(), 'oturum geçerli' );
		qrms_assert_true( false !== qmo_oturum(), 'qmo_oturum HMAC çerezini okur' );
		qrms_assert_same( 'masa-31', qmo_oturum()['masa'], 'masa slug\'ı korunur' );
	}
);

qrms_test(
	'sepet anahtarı varsayılan kapalıdır (opt-in)',
	function () {
		qrms_assert_false( qmo_sepet_aktif_mi(), 'option yokken kapalı' );
		qrms_assert_same( '<menu/>', qmo_sepet_menuye_ekle( '<menu/>' ), 'kapalıyken menü çıktısı değişmez' );
	}
);

qrms_test(
	'anahtar açık + masa oturumu yokken menü altına sepet basılmaz',
	function () {
		$GLOBALS['qrms_test']['can']       = false;
		$GLOBALS['qrms_test']['logged_in'] = false;
		unset( $_COOKIE[ QMO_Oturum::COOKIE ] );
		update_option( 'qmo_sepet_aktif', 1 );

		qrms_assert_true( qmo_sepet_aktif_mi(), 'anahtar açık' );
		qrms_assert_false( qmo_sepet_izinli_mi(), 'oturum yok' );
		qrms_assert_same( '<menu/>', qmo_sepet_menuye_ekle( '<menu/>' ), 'izin yokken enjeksiyon boş ekler' );
	}
);

qrms_test(
	'anahtar açık + masa oturumu varken menü altına sepet eklenir',
	function () {
		$GLOBALS['qrms_test']['can']       = false;
		$GLOBALS['qrms_test']['logged_in'] = false;
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-anahtari-sepet-icin-yeterince-uzun';
		update_option( 'qmo_sepet_aktif', 1 );

		$token = QMO_Oturum::token_uret( 'masa-12' );
		$_COOKIE[ QMO_Oturum::COOKIE ] = $token;

		$html = qmo_sepet_menuye_ekle( '<div class="rma-wrap"></div>' );

		qrms_assert_contains( '<div class="rma-wrap"></div>', $html, 'menü durur' );
		qrms_assert_contains( 'id="qmo-sepet-root"', $html, 'sepet menünün altında' );
		qrms_assert_true(
			strpos( $html, 'id="qmo-sepet-root"' ) > strpos( $html, 'rma-wrap' ),
			'sepet menüden sonra gelir'
		);
	}
);

qrms_test(
	'aynı istekte sepet HTML\'i yalnızca bir kez basılır',
	function () {
		$GLOBALS['qrms_test']['can']       = true;
		$GLOBALS['qrms_test']['logged_in'] = true;

		$ilk = qmo_sepet_shortcode();
		$iki = qmo_sepet_shortcode();
		$uc  = qmo_sepet_menuye_ekle( '[elle]' );

		qrms_assert_contains( 'id="qmo-sepet-root"', $ilk, 'ilk çağrı basar' );
		qrms_assert_same( 1, substr_count( $ilk, 'id="qmo-sepet-root"' ), 'kök tekildir' );
		qrms_assert_same( '', $iki, 'ikinci kısa kod boş' );
		qrms_assert_same( '[elle]', $uc, 'otomatik enjeksiyon ikinci kopyayı eklemez' );
	}
);

qrms_test(
	'anahtar kapalıyken elle [qmo_sepet] hâlâ render edilir',
	function () {
		$GLOBALS['qrms_test']['can']       = true;
		$GLOBALS['qrms_test']['logged_in'] = true;
		update_option( 'qmo_sepet_aktif', 0 );

		qrms_assert_false( qmo_sepet_aktif_mi(), 'anahtar kapalı' );
		qrms_assert_same( '<menu/>', qmo_sepet_menuye_ekle( '<menu/>' ), 'otomatik ekleme yok' );
		qrms_assert_contains( 'id="qmo-sepet-root"', qmo_sepet_shortcode(), 'elle kısa kod çalışır' );
	}
);

qrms_test(
	'Diğer Ayarlar sayfasında sepet anahtarı ve kaydetme ucu vardır',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );
		$boot  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		$on    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		$kisa  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );
		$css   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );

		qrms_assert_contains( 'Sepet ile Sipariş', $sayfa, 'bölüm başlığı' );
		qrms_assert_contains( 'Sepet ile siparişi etkinleştir', $sayfa, 'anahtar etiketi' );
		qrms_assert_contains( "id=\"rma-sepet-siparis\"", $sayfa, 'bölüm çapası' );
		qrms_assert_contains( "href=\"#rma-sepet-siparis\"", $sayfa, 'nav bağlantısı' );
		qrms_assert_contains( 'handle_sepet_ayar_save', $sayfa, 'kaydetme metodu' );
		qrms_assert_contains( "check_admin_referer( 'qmo_sepet_ayar_kaydet' )", $sayfa, 'nonce' );
		qrms_assert_contains( 'QRMS_Admin::CAPABILITY', $sayfa, 'yetki kontrolü' );
		qrms_assert_contains( "update_option( 'qmo_sepet_aktif'", $sayfa, 'option kaydı' );
		qrms_assert_contains( "admin_post_qmo_sepet_ayar_kaydet", $boot, 'admin-post ucu' );
		qrms_assert_contains( "function_exists( 'qmo_sepet_menuye_ekle' )", $on, 'menü render enjeksiyonu' );
		qrms_assert_contains( 'qmo_sepet_menuye_ekle', $on, 'enjeksiyon çağrısı' );
		qrms_assert_contains( 'add_shortcode( \'qmo_sepet\'', $kisa, 'elle kısa kod durur' );
		qrms_assert_contains( 'qmo_sepet_istekte_basildi', $kisa, 'çift-render bayrağı' );
		qrms_assert_contains( '.rma-admin .rma-switch', $css, 'ayar sayfası anahtar stili' );
	}
);

/* ---------------------------------------------------------------------------
 * 8d. QR Chatbot — kart tabanlı hub ve gizli alt sayfalar
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/module.php';

echo "\nQR Chatbot hub\n";

qrms_test(
	'alt sayfalar tek kaynakta, hub grupları ve slug\'lar tanımlı',
	function () {
		$pages = qmo_chatbot_sayfalar();

		qrms_assert_same(
			array(
				'qrms-chatbot-bot-identity',
				'qrms-chatbot-appearance',
				'qrms-chatbot-quick-replies',
				'qrms-chatbot-visibility',
				'qrms-chatbot-gemini',
				'qrms-chatbot-ai-behavior',
				'qrms-chatbot-firebase',
				'qrms-chatbot-ana-site',
				'qrms-chatbot-history',
				'qrms-chatbot-unanswered',
			),
			array_keys( $pages ),
			'sayfa listesi'
		);

		foreach ( $pages as $slug => $page ) {
			foreach ( array( 'title', 'render', 'desc', 'icon', 'group' ) as $key ) {
				qrms_assert_true( ! empty( $page[ $key ] ), $slug . ' -> ' . $key . ' dolu' );
			}
			qrms_assert_same( 0, strpos( $page['icon'], 'dashicons-' ), $slug . ' ikonu dashicon' );
			qrms_assert_true( is_callable( $page['render'] ), $slug . ' callback çağrılabilir' );
		}

		qrms_assert_same( 'Bot', $pages['qrms-chatbot-bot-identity']['group'], 'Bot grubu' );
		qrms_assert_same( 'Bot', $pages['qrms-chatbot-appearance']['group'], 'Görünüm Bot grubunda' );
		qrms_assert_same( 'Bot', $pages['qrms-chatbot-quick-replies']['group'], 'Hazır sorular Bot grubunda' );
		qrms_assert_same( 'Bot', $pages['qrms-chatbot-visibility']['group'], 'Görünürlük Bot grubunda' );
		qrms_assert_same( 'Yapay Zeka', $pages['qrms-chatbot-gemini']['group'], 'Gemini grubu' );
		qrms_assert_same( 'Yapay Zeka', $pages['qrms-chatbot-ai-behavior']['group'], 'Davranış grubu' );
		qrms_assert_same( 'Entegrasyon', $pages['qrms-chatbot-firebase']['group'], 'Firebase grubu' );
		qrms_assert_same( 'Entegrasyon', $pages['qrms-chatbot-ana-site']['group'], 'Ana site grubu' );
		qrms_assert_same( 'Yönetim', $pages['qrms-chatbot-history']['group'], 'Geçmiş Yönetim grubunda' );
		qrms_assert_same( 'Yönetim', $pages['qrms-chatbot-unanswered']['group'], 'Cevaplanamayan Yönetim grubunda' );
	}
);

qrms_test(
	'hub kart ızgarası form içermez, bölüm başlıkları ve kartlar basılır',
	function () {
		ob_start();
		qmo_chatbot_ayar_sayfasi();
		$html = ob_get_clean();

		qrms_assert_contains( 'class="rma-hub"', $html, 'Restoran Menü kapsülü' );
		qrms_assert_contains( 'QR Chatbot', $html, 'hub başlığı' );
		qrms_assert_contains( 'Gemini destekli masa asistanı', $html, 'açıklama' );
		qrms_assert_contains( '[gemini_chatbot]', $html, 'kısa kod' );
		qrms_assert_contains( 'qrms-hub-group-title', $html, 'bölüm başlığı' );
		qrms_assert_contains( 'qrms-hub-group-title">Bot', $html, 'Bot bölümü' );
		qrms_assert_contains( 'Yapay Zeka', $html, 'Yapay Zeka bölümü' );
		qrms_assert_contains( 'Entegrasyon', $html, 'Entegrasyon bölümü' );
		qrms_assert_contains( 'page=qrms-chatbot-bot-identity', $html, 'Bot Kimliği kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-appearance', $html, 'Görünüm kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-quick-replies', $html, 'Hazır Sorular kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-visibility', $html, 'Görünürlük kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-gemini', $html, 'Gemini kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-ai-behavior', $html, 'Davranış kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-firebase', $html, 'Firebase kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-ana-site', $html, 'Ana site kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-history', $html, 'Sohbet Geçmişi kartı' );
		qrms_assert_contains( 'page=qrms-chatbot-unanswered', $html, 'Cevaplanamayan kartı' );
		qrms_assert_contains( 'Sohbet Asistanı', $html, 'ana anahtar' );
		qrms_assert_contains( 'qmo-cb-hub-switch', $html, 'AJAX anahtar' );
		qrms_assert_contains( 'Yönetim', $html, 'Yönetim bölümü' );
		qrms_assert_contains( '✗ Henüz yapılandırılmadı', $html, 'Firebase uyarı rozeti' );
		qrms_assert_false( false !== strpos( $html, '<form' ), 'hub form basmaz' );
		qrms_assert_false( false !== strpos( $html, 'nav-tab' ), 'eski sekmeler yok' );
	}
);

qrms_test(
	'eski ?tab= yer imleri ilgili alt sayfaya yönlenir',
	function () {
		$_GET['tab'] = 'gorunum';
		try {
			qmo_chatbot_ayar_sayfasi();
			qrms_assert_true( false, 'yönlendirme beklenirdi' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_contains( 'page=qrms-chatbot-appearance', $e->getMessage(), 'görünüm sayfası' );
		}

		$_GET['tab'] = 'yapayzeka';
		try {
			qmo_chatbot_ayar_sayfasi();
			qrms_assert_true( false, 'yönlendirme beklenirdi' );
		} catch ( QRMS_Test_Redirect $e ) {
			qrms_assert_contains( 'page=qrms-chatbot-ai-behavior', $e->getMessage(), 'davranış sayfası' );
		}
	}
);

qrms_test(
	'modül aktifken alt sayfalar gizli kayıtlıdır, option adları durur',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'Chatbot Asistan', QRMS_Admin::get_module_page_slug( 'qr-chatbot' ) ),
		);

		qrms_module_qr_chatbot_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		qrms_assert_same( array_keys( qmo_chatbot_sayfalar() ), $sluglar, 'kaydedilen sayfalar' );
		qrms_assert_true( QRMS_Admin::is_module_subpage( 'qrms-chatbot-bot-identity' ), 'kayıt defterinde' );
		qrms_assert_same( QRMS_Admin::MENU_SLUG, $GLOBALS['qrms_test']['submenus'][0]['parent'], 'üst menü' );

		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' );
		foreach ( array(
			'gemini_api_key',
			'qmo_gemini_model',
			'gemini_bot_name',
			'gemini_welcome_text',
			'gemini_placeholder_text',
			'gemini_show_toggle_text',
			'gemini_bot_icon',
			'gemini_icon_size',
			'gemini_border_radius',
			'gemini_system_prompt',
			'gemini_menu_json_data',
			'qmo_branch_id',
			'qmo_firebase_sa',
			'qmo_ana_site',
			'QMO_FIREBASE_SA_JSON',
		) as $alan ) {
			qrms_assert_contains( $alan, $php, $alan . ' durur' );
		}

		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/module.php' );
		qrms_assert_contains( 'modules/restoran-menu/assets/css/hub.css', $modul, 'hub.css kuyruğa alınır' );
		qrms_assert_contains( "array( 'qrms-admin' )", $modul, 'ortak admin.css sonrası yüklenir' );
	}
);

qrms_test(
	'modül lisansta aktif değilken chatbot alt sayfası kaydedilmez',
	function () {
		qrms_module_qr_chatbot_admin_menu();
		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

qrms_test(
	'Bot Kimliği sayfasında geri bağlantısı, nonce ve Kaydet vardır',
	function () {
		ob_start();
		qmo_chatbot_sayfa_bot_kimligi();
		$html = ob_get_clean();

		qrms_assert_contains( 'qrms-back-link', $html, 'geri bağlantısı' );
		qrms_assert_contains( 'QR Chatbot', $html, 'geri metni' );
		qrms_assert_contains( 'name="gemini_bot_name"', $html, 'bot adı alanı' );
		qrms_assert_contains( 'name="gemini_welcome_text"', $html, 'karşılama' );
		qrms_assert_contains( 'name="gemini_placeholder_text"', $html, 'placeholder' );
		qrms_assert_contains( 'name="gemini_show_toggle_text"', $html, 'toggle' );
		qrms_assert_contains( 'name="gemini_bot_icon"', $html, 'ikon' );
		qrms_assert_contains( 'qmo_chatbot_nonce', $html, 'nonce' );
		qrms_assert_contains( 'Kaydet', $html, 'Kaydet' );
		qrms_assert_contains( "submit_button( 'Kaydet', 'primary', 'qmo_chatbot_kaydet' )", file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' ), 'Kaydet düğmesi adı' );
		qrms_assert_false( false !== strpos( $html, 'name="gemini_api_key"' ), 'API anahtarı bu sayfada değil' );
	}
);

qrms_test(
	'Firebase sayfasında güvenlik açıklaması durur, ana site kutusu yoktur',
	function () {
		ob_start();
		qmo_chatbot_sayfa_firebase();
		$html = ob_get_clean();

		qrms_assert_contains( 'name="qmo_branch_id"', $html, 'şube kimliği' );
		qrms_assert_contains( 'name="qmo_firebase_sa"', $html, 'service account' );
		qrms_assert_contains( 'QMO_FIREBASE_SA_JSON', $html, 'wp-config önerisi' );
		qrms_assert_contains( '✗ Henüz yapılandırılmadı', $html, 'uyarı rozeti' );
		qrms_assert_contains( 'action="options.php"', $html, 'options.php' );
		qrms_assert_true( in_array( 'qmo_firebase_grup', $GLOBALS['qrms_test']['settings_fields'], true ), 'settings_fields grubu' );
		qrms_assert_false( false !== strpos( $html, 'Bu site ana site mi?' ), 'ana site kutusu bu sayfada değil' );
	}
);

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/color-defaults.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-ayarlar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-chatbot.php';
require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php';

qrms_test(
	'üç ana renkten diğerleri türetilir ve yazı zemin üzerinde okunur',
	function () {
		$renkler = qmo_chatbot_renkleri_turetilsin( '#8a2be2', '#f8fafc', '#333333' );

		foreach ( array( 'gemini_header_bg_color', 'gemini_user_msg_color', 'gemini_bot_msg_color', 'gemini_send_btn_bg_color', 'gemini_border_color' ) as $alan ) {
			qrms_assert_true( isset( $renkler[ $alan ] ), $alan . ' türetilir' );
			qrms_assert_true( (bool) sanitize_hex_color( $renkler[ $alan ] ), $alan . ' geçerli renk' );
		}

		qrms_assert_same( '#8a2be2', $renkler['gemini_main_color'], 'ana renk korunur' );
		qrms_assert_same( '#f8fafc', $renkler['gemini_chat_bg_color'], 'zemin korunur' );
		$fark = abs( qmo_chatbot_parlaklik( $renkler['gemini_text_color'] ) - qmo_chatbot_parlaklik( $renkler['gemini_chat_bg_color'] ) );
		qrms_assert_true( $fark >= 0.35, 'yazı zemin üzerinde okunur' );
	}
);

qrms_test(
	'hub anahtarı AJAX ucu yetki ve nonce ister',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-admin.php' );
		qrms_assert_contains( "wp_ajax_qmo_chatbot_toggle", $php, 'toggle eylemi' );
		qrms_assert_contains( "check_ajax_referer( 'qmo_chatbot_toggle'", $php, 'toggle nonce' );
		qrms_assert_contains( "current_user_can( 'manage_options' )", $php, 'yetki' );
		qrms_assert_contains( 'QMO_CHATBOT_OPT_AKTIF', $php, 'aktif option' );
	}
);

qrms_test(
	'sohbet tabloları dbDelta ile indexli kurulur',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-db.php' );
		qrms_assert_contains( 'qmo_chatbot_mesajlar', $php, 'mesaj tablosu' );
		qrms_assert_contains( 'qmo_chatbot_bilinmeyen', $php, 'bilinmeyen tablosu' );
		qrms_assert_contains( 'dbDelta', $php, 'dbDelta' );
		qrms_assert_contains( 'KEY idx_created', $php, 'tarih index' );
		qrms_assert_contains( 'KEY idx_masa_created', $php, 'masa index' );
		qrms_assert_contains( 'KEY idx_oturum', $php, 'oturum index' );
		qrms_assert_contains( 'UNIQUE KEY idx_soru_norm', $php, 'soru norm unique' );
		qrms_assert_contains( 'KEY idx_resolved_tekrar', $php, 'tekrar index' );
	}
);

qrms_test(
	'asistan kapalıysa ön yüz varlıkları yüklenmez',
	function () {
		update_option( 'qmo_chatbot_aktif', 'no' );
		qrms_assert_false( qmo_chatbot_onyuz_yuklensin_mi(), 'yükleme kapalı' );
		qrms_assert_same( '', qmo_chatbot_shortcode(), 'kısa kod boş' );

		$varlik = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php' );
		qrms_assert_contains( 'qmo_chatbot_onyuz_yuklensin_mi', $varlik, 'içerik tarayıcı atlar' );
		qrms_assert_contains( "'qmo-chatbot' === \$handle", $varlik, 'enqueue da atlar' );
	}
);

qrms_test(
	'Görünüm ve Bot Kimliği Türkçe etiket kullanır, eski name durur',
	function () {
		$kimlik  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' );
		$gorunum = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/sayfa-gorunum.php' );

		qrms_assert_contains( 'Kutu içi ipucu metni', $kimlik, 'ipucu etiketi' );
		qrms_assert_contains( 'Açma butonu yazısı', $kimlik, 'açma butonu' );
		qrms_assert_contains( 'Köşe yumuşaklığı', $gorunum, 'köşe' );
		qrms_assert_contains( 'name="gemini_icon_size"', $gorunum, 'eski boyut alanı' );
		qrms_assert_contains( 'name="gemini_border_radius"', $gorunum, 'eski köşe alanı' );
		qrms_assert_contains( "'gemini_header_bg_color'", $gorunum, 'gelişmiş renk name' );
		qrms_assert_contains( 'data-color-key', $gorunum, 'renk alan name döngüsü' );
		qrms_assert_false( false !== strpos( $gorunum, 'Toggle' ), 'Toggle yok' );
		qrms_assert_false( false !== strpos( $gorunum, 'border radius' ), 'border radius yok' );
		qrms_assert_contains( 'Hazır Şablonlar', $gorunum, 'şablonlar' );
		qrms_assert_contains( 'qmo_renk_sablonlari', $gorunum, 'mevcut şablon kaynağı' );
		$sablon = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/color-defaults.php' );
		qrms_assert_contains( 'Royal Violet & Gold', $sablon, 'royal' );
		qrms_assert_contains( 'Emerald Noir', $sablon, 'emerald' );
		qrms_assert_contains( 'Rose Blush', $sablon, 'rose' );
		qrms_assert_contains( 'Dark Mode', $sablon, 'dark' );
		qrms_assert_contains( 'Gelişmiş renk ayarları', $gorunum, 'gelişmiş' );
		qrms_assert_contains( 'qmo_chatbot_welcome_btn', $gorunum, 'karşılama butonu alanı' );
		qrms_assert_contains( "'qmo_chatbot_welcome_btn'", file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/class-ayarlar.php' ), 'karşılama varsayılanı' );
	}
);

/* ---------------------------------------------------------------------------
 * 9. QR Analiz — hub + kategori alt sayfaları + eski adresin yönlendirmesi
 * ------------------------------------------------------------------------ */

// Dosyalar dosya kapsamında yalnızca fonksiyon ve sabit tanımlar; stub
// ortamında yan etkisiz yüklenir.
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik-filtre.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/module.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/filtre-cubugu.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/genel-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/urunler-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/masalar-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/sepet-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/etkilesim-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/acilis-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/sistem-sayfasi.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php';

echo "\nQR Analiz sayfaları\n";

qrms_test(
	'modül satırı hub ekranıdır; yedi kategori kart olur, klasik görünüm kalktı',
	function () {
		// Sayfa listesi TEK KAYNAK: kayıt da kartlar da buradan beslenir.
		$sayfalar = qrms_module_qr_analiz_sayfalar();

		qrms_assert_same(
			array(
				'qrms-an-genel',
				'qrms-an-urunler',
				'qrms-an-masalar',
				'qrms-an-sepet',
				'qrms-an-etkilesim',
				'qrms-an-acilis',
				'qrms-an-sistem',
			),
			array_keys( $sayfalar ),
			'kategori slug\'ları'
		);

		foreach ( $sayfalar as $slug => $sayfa ) {
			qrms_assert_true( is_callable( $sayfa['render'] ), $slug . ' render edilebilir' );
			qrms_assert_true( '' !== $sayfa['desc'], $slug . ' açıklaması' );
			// İkonlar dashicons setinden gelir — emoji değil.
			qrms_assert_true( 0 === strpos( $sayfa['icon'], 'dashicons-' ), $slug . ' dashicon' );
		}

		// Eski tek-sayfa yapısı kapandı: dosyası yok, kartı yok.
		qrms_assert_false(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-analiz/analitik-sayfasi.php' ),
			'klasik panel dosyası silindi'
		);

		// Kart sayısı lisansa bağlıdır; bu test tümü aktifken bakar (süzme
		// kendi testinde doğrulanır).
		update_option( 'qrms_active_modules', array( 'qr-analiz', 'qr-chatbot', 'qr-acilis-ekrani' ) );

		$kartlar = qrms_module_qr_analiz_hub_kartlari();
		qrms_assert_same( 7, count( $kartlar ), 'yalnızca kategoriler' );

		foreach ( $kartlar as $kart ) {
			qrms_assert_false(
				false !== strpos( $kart['url'], QRMS_ANALITIK_KLASIK_SAYFA ),
				'klasik görünüm kartı kalktı'
			);
		}

		// Bütün kategoriler doldu: rozet kalmaz.
		$rozetli = array();

		foreach ( $kartlar as $kart ) {
			if ( '' !== $kart['badge'] ) {
				$rozetli[] = $kart['title'];
			}
		}

		qrms_assert_same( 0, count( $rozetli ), 'Yakında rozeti kalmadı' );

		// Ayar ekranı dosyası artık güvenlik modülünün altındadır.
		qrms_assert_false( defined( 'QRMS_ANALIZ_AYAR_SAYFA' ), 'ayar slug sabiti taşındı' );
		qrms_assert_true(
			file_exists( QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/firebase-ayarlari-sayfasi.php' ),
			'yeni konumda var'
		);
	}
);

qrms_test(
	'lisansta pasif modüle bağlı kategori hiç basılmaz',
	function () {
		// Sepet & Sipariş chatbot'a, Açılış Ekranı açılış modülüne bağlıdır:
		// kullanıcıya satın almadığı bir şeyin boş ekranı gösterilmez.
		update_option( 'qrms_active_modules', array( 'qr-analiz', 'restoran-menu' ) );

		$gecerli = qrms_module_qr_analiz_gecerli_sayfalar();

		qrms_assert_false( isset( $gecerli['qrms-an-sepet'] ), 'chatbot pasif: sepet yok' );
		qrms_assert_false( isset( $gecerli['qrms-an-acilis'] ), 'açılış pasif: kategori yok' );
		qrms_assert_false( isset( $gecerli['qrms-an-etkilesim'] ), 'etkileşim bağlı modüller pasif: kategori yok' );
		qrms_assert_true( isset( $gecerli['qrms-an-genel'] ), 'çekirdek kategoriler durur' );
		qrms_assert_true( isset( $gecerli['qrms-an-masalar'] ), 'masalar modüle bağlı değil' );

		// Chatbot açılınca sepet ve (OR bağlı) etkileşim geri gelir.
		update_option( 'qrms_active_modules', array( 'qr-analiz', 'qr-chatbot' ) );

		$gecerli = qrms_module_qr_analiz_gecerli_sayfalar();

		qrms_assert_true( isset( $gecerli['qrms-an-sepet'] ), 'chatbot aktif: sepet var' );
		qrms_assert_true( isset( $gecerli['qrms-an-etkilesim'] ), 'chatbot aktif: etkileşim var' );

		update_option( 'qrms_active_modules', array() );
	}
);

qrms_test(
	'chatbot pasifken sepet sayfası yine kayıtlıdır ama hub kartı yoktur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-analiz' ) );
		QRMS_Analitik_Filtre::sifirla();

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		qrms_assert_true( in_array( 'qrms-an-sepet', $sluglar, true ), 'doğrudan URL çalışsın diye kayıtlı' );

		$kart_url = array();
		foreach ( qrms_module_qr_analiz_hub_kartlari() as $kart ) {
			$kart_url[] = $kart['url'];
		}

		$sepet_kart = false;
		foreach ( $kart_url as $url ) {
			if ( false !== strpos( $url, 'page=qrms-an-sepet' ) ) {
				$sepet_kart = true;
			}
		}

		qrms_assert_false( $sepet_kart, 'hub kartı basılmaz' );

		ob_start();
		qrms_analitik_sayfa_sepet();
		$html = ob_get_clean();

		qrms_assert_contains( 'Chatbot Asistan bu lisansta kapalı', $html, 'anlamlı mesaj' );
		qrms_assert_false( false !== strpos( $html, 'id="qrms-an-cards"' ), 'boş tablo yok' );
		qrms_assert_false( false !== strpos( $html, 'qrms-an-table' ), 'tablo iskeleti yok' );

		update_option( 'qrms_active_modules', array() );
	}
);

qrms_test(
	'kategori sayfaları menüye satır EKLEMEZ; alt sayfa olarak kaydolur',
	function () {
		QRMS_Analitik_Filtre::sifirla();

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		foreach ( array_keys( qrms_module_qr_analiz_gecerli_sayfalar() ) as $slug ) {
			qrms_assert_true( in_array( $slug, $sluglar, true ), $slug . ' kayıtlı' );
			qrms_assert_true( QRMS_Admin::is_module_subpage( $slug ), $slug . ' alt sayfa defterinde' );
		}

		// Eski adresler yönlendirme olarak kayıtlı kalır (ekran değil).
		qrms_assert_true( in_array( QRMS_ANALITIK_KLASIK_SAYFA, $sluglar, true ), 'klasik slug yönlendirir' );
		qrms_assert_true( in_array( QRMS_ANALITIK_SAYFA, $sluglar, true ), 'eski panel slug\'ı yönlendirir' );

		// Hepsi menüden düşer: beyaz listede yalnızca modül satırı vardır.
		$gizlenen = QRMS_Admin::collect_hidden_rows(
			$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ],
			QRMS_Admin::get_menu_row_slugs()
		);

		foreach ( array_keys( qrms_module_qr_analiz_gecerli_sayfalar() ) as $slug ) {
			qrms_assert_true( in_array( $slug, $gizlenen, true ), $slug . ' menüden düşer' );
		}
	}
);

qrms_test(
	'paylaşılan filtre her bağlantıya yapışır, varsayılanda adres temiz kalır',
	function () {
		// Dokunulmamış filtre: adreslerde gereksiz arg yok.
		QRMS_Analitik_Filtre::ayarla( array() );
		qrms_assert_same( 'bugun', QRMS_Analitik_Filtre::donem(), 'varsayılan dönem' );
		qrms_assert_same( array(), QRMS_Analitik_Filtre::args(), 'temiz adres' );

		// Seçim yapıldığında hub kartları da geri bağlantısı da taşır.
		QRMS_Analitik_Filtre::ayarla(
			array(
				'donem' => 'hafta',
				'masa'  => 'Masa 3',
			)
		);

		qrms_assert_same(
			array(
				'donem' => 'hafta',
				'masa'  => 'masa-3',
			),
			QRMS_Analitik_Filtre::args(),
			'taşınan filtre'
		);

		$url = QRMS_Analitik_Filtre::url( 'qrms-an-sepet' );
		qrms_assert_contains( 'page=qrms-an-sepet', $url, 'sayfa' );
		qrms_assert_contains( 'donem=hafta', $url, 'dönem taşındı' );
		qrms_assert_contains( 'masa=masa-3', $url, 'masa taşındı' );

		foreach ( qrms_module_qr_analiz_hub_kartlari() as $kart ) {
			qrms_assert_contains( 'donem=hafta', $kart['url'], 'kart dönemi taşır' );
			qrms_assert_contains( 'masa=masa-3', $kart['url'], 'kart masayı taşır' );
		}

		// Geri bağlantısı hub'a döner ama seçimi kaybetmez.
		$geri = qrms_module_qr_analiz_geri_url( QRMS_Admin::get_module_page_url( 'qr-analiz' ), 'qr-analiz' );
		qrms_assert_contains( 'page=' . QRMS_Admin::get_module_page_slug( 'qr-analiz' ), $geri, 'hub adresi' );
		qrms_assert_contains( 'donem=hafta', $geri, 'geri bağlantısı dönemi taşır' );

		// Başka modülün alt sayfası etkilenmez.
		qrms_assert_same(
			QRMS_Admin::get_module_page_url( 'qr-galeri' ),
			qrms_module_qr_analiz_geri_url( QRMS_Admin::get_module_page_url( 'qr-galeri' ), 'qr-galeri' ),
			'yabancı modül dokunulmaz'
		);

		QRMS_Analitik_Filtre::sifirla();
	}
);

qrms_test(
	'filtre bağlamı bozuk değerleri güvenli varsayılana indirger',
	function () {
		// Tanınmayan dönem.
		$b = QRMS_Analitik_Filtre::coz( array( 'donem' => 'yil' ) );
		qrms_assert_same( 'bugun', $b['donem'], 'tanınmayan dönem' );

		// "ozel" ama tarih eksik: yarım aralık tüm tabloyu taratırdı.
		$b = QRMS_Analitik_Filtre::coz(
			array(
				'donem' => 'ozel',
				'bas'   => '2026-01-01',
			)
		);
		qrms_assert_same( 'bugun', $b['donem'], 'yarım aralık reddedilir' );

		// Takvimde olmayan gün.
		$b = QRMS_Analitik_Filtre::coz(
			array(
				'donem' => 'ozel',
				'bas'   => '2026-02-31',
				'bit'   => '2026-03-01',
			)
		);
		qrms_assert_same( 'bugun', $b['donem'], 'geçersiz tarih' );

		// Ters aralık takas edilir.
		$b = QRMS_Analitik_Filtre::coz(
			array(
				'donem' => 'ozel',
				'bas'   => '2026-03-10',
				'bit'   => '2026-03-01',
			)
		);
		qrms_assert_same( 'ozel', $b['donem'], 'özel aralık' );
		qrms_assert_same( '2026-03-01', $b['bas'], 'başlangıç takas edildi' );
		qrms_assert_same( '2026-03-10', $b['bit'], 'bitiş takas edildi' );

		// Dönem özel değilse tarihler taşınmaz.
		$b = QRMS_Analitik_Filtre::coz(
			array(
				'donem' => 'ay',
				'bas'   => '2026-03-01',
				'bit'   => '2026-03-10',
			)
		);
		qrms_assert_same( '', $b['bas'], 'artık tarih taşınmaz' );

		// Dizi gelirse (?masa[]=x) çökme değil, boş filtre.
		$b = QRMS_Analitik_Filtre::coz( array( 'masa' => array( 'x' ) ) );
		qrms_assert_same( '', $b['masa'], 'dizi değer yok sayılır' );
	}
);

qrms_test(
	'panelin eski adresi kayıtlı kalır ve modül satırına yönlendirir',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'QR Analiz', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$eski = array_values(
			array_filter(
				$GLOBALS['qrms_test']['submenus'],
				function ( $item ) {
					return QRMS_ANALITIK_SAYFA === $item['slug'];
				}
			)
		);

		qrms_assert_same( 1, count( $eski ), 'eski adres bir kez kayıtlı' );

		// Üst menü '' — satır sol menüde hiç görünmez, yalnızca adres çalışır.
		qrms_assert_same( '', $eski[0]['parent'], 'gizli sayfa' );

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

echo "\nFaz 9 — eski slug sayacı\n";

qrms_test(
	'eski slug vuruşu option\'a yazılır; boş slug yok sayılır',
	function () {
		QRMS_Helpers::legacy_slug_hit( 'rma_settings' );
		QRMS_Helpers::legacy_slug_hit( 'rma_settings' );
		QRMS_Helpers::legacy_slug_hit( 'qrms-analiz-panel' );
		QRMS_Helpers::legacy_slug_hit( '' );
		QRMS_Helpers::legacy_slug_hit( 'Not A Key!!' );

		$hits = QRMS_Helpers::legacy_slug_hits();

		qrms_assert_same( 2, $hits['rma_settings']['count'], 'aynı slug artar' );
		qrms_assert_same( 1, $hits['qrms-analiz-panel']['count'], 'ikinci slug ayrı' );
		qrms_assert_false( isset( $hits[''] ), 'boş slug yazılmaz' );
		qrms_assert_true( isset( $hits['notakey'] ), 'sanitize_key uygulanır' );
		qrms_assert_true( isset( $hits['rma_settings']['first'] ), 'ilk vuruş damgası' );
		qrms_assert_true( isset( $hits['rma_settings']['last'] ), 'son vuruş damgası' );
		qrms_assert_same(
			$hits['rma_settings']['first'],
			$hits['rma_settings']['last'],
			'aynı saniye içinde first=last'
		);
	}
);

qrms_test(
	'analiz eski adresi yönlendirirken sayacı artırır',
	function () {
		$_GET['page'] = QRMS_ANALITIK_SAYFA;

		try {
			qrms_module_qr_analiz_eski_adresi_yonlendir();
			qrms_assert_true( false, 'yönlendirme bekleniyordu' );
		} catch ( QRMS_Test_Redirect $e ) {
			$hits = QRMS_Helpers::legacy_slug_hits();
			qrms_assert_same( 1, $hits[ QRMS_ANALITIK_SAYFA ]['count'], 'vuruş kaydı' );
		}
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
	'eski tek-sayfa yapısı kapandı; araçlar "Veri & Sistem"e taşındı',
	function () {
		$sistem = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sistem-sayfasi.php' );

		// Taşınan araçların hepsi burada.
		qrms_assert_contains( 'id="qrms-an-clear"', $sistem, 'verileri sil' );
		qrms_assert_contains( 'id="qrms-an-refresh"', $sistem, 'yenile' );
		qrms_assert_contains( 'id="qrms-an-confirm"', $sistem, 'onay modalı' );
		qrms_assert_contains( 'qrms_analitik_teshis_listesi()', $sistem, 'teşhis' );
		qrms_assert_contains( "'kategori' => 'ham'", $sistem, 'ham CSV' );

		// Teşhis İKİ yerde ama TEK kaynaktan: hub kısa uyarı, sistem tam liste.
		$hub = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php' );

		qrms_assert_contains( 'QRMS_Analitik::teshis()', $hub, 'hub aynı kaynaktan' );
		qrms_assert_contains( 'QRMS_Analitik::teshis()', $sistem, 'sistem aynı kaynaktan' );

		// Silme akışı AYNEN taşındı: aynı uç, aynı nonce.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-sistem.js' );

		qrms_assert_contains( 'qrms_analitik_temizle', $js, 'silme ucu değişmedi' );
		qrms_assert_contains( 'confirmTable', $js, 'masa kapsamlı silme metni' );

		// Eski betik ORTADA kalmadı ama SİLİNMEDİ: Faz 9 temizlik turunda
		// topluca ele alınacak, o yüzden dosya başında işaretli.
		$eski = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik.js' );

		qrms_assert_contains( 'KULLANILMIYOR', $eski, 'ölü betik işaretli' );

		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/module.php' );

		qrms_assert_false( false !== strpos( $modul, 'assets/js/analitik.js' ), 'artık kuyruğa girmiyor' );
	}
);

echo "\nQR Analiz — Ürünler kategorisi\n";

qrms_test(
	'en az tıklananlar CPT\'den başlar: hiç tıklanmamış ürün de listelenir',
	function () {
		// İki kaynak birleşir: yayındaki ürünler + tıklama sayaçları.
		// Sayacı olmayan ürün listeden DÜŞMEMELİ; aranan tam da odur.
		$GLOBALS['qrms_test']['posts'] = array(
			(object) array(
				'ID'         => 11,
				'post_title' => 'Mercimek Çorbası',
			),
			(object) array(
				'ID'         => 12,
				'post_title' => 'Ayran',
			),
			(object) array(
				'ID'         => 13,
				'post_title' => 'Künefe',
			),
		);
		$GLOBALS['qrms_test']['post_meta'] = array( 13 => array( '_rma_tukendi' => '1' ) );
		$GLOBALS['qrms_test']['terms']     = array( 11 => 'Çorbalar' );

		qrms_analitik_onbellek_sifirla();

		$sonuc = qrms_analitik_en_az_tiklananlar(
			array(
				11 => array(
					'toplam' => 9,
					'tekil'  => 4,
					'son'    => '2026-03-10 12:00:00',
				),
			)
		);

		qrms_assert_same( 3, $sonuc['toplam'], 'üç ürün de listede' );
		qrms_assert_same( 2, $sonuc['hic'], 'iki ürün hiç tıklanmadı' );
		qrms_assert_same( 1, $sonuc['tukendi'], 'bir ürün tükendi' );

		// Artan sıralama; eşitlikte ada göre (sayfalama kaymasın).
		qrms_assert_same( 'Ayran', $sonuc['satirlar'][0]['ad'], 'en az tıklanan önce' );
		qrms_assert_same( 'Künefe', $sonuc['satirlar'][1]['ad'], 'eşitlikte ada göre' );
		qrms_assert_same( 'Mercimek Çorbası', $sonuc['satirlar'][2]['ad'], 'en çok tıklanan sonda' );

		// Tükendi ürün "ölü ürün" sanılmasın diye işaretlenir.
		qrms_assert_true( $sonuc['satirlar'][1]['tukendi'], 'tükendi bayrağı' );
		qrms_assert_false( $sonuc['satirlar'][0]['tukendi'], 'stokta olan işaretsiz' );

		// Sayaçlar ürüne doğru eşleşir.
		qrms_assert_same( 9, $sonuc['satirlar'][2]['toplam'], 'tıklama sayısı' );
		qrms_assert_same( 'Çorbalar', $sonuc['satirlar'][2]['kategori'], 'kategori adı' );
	}
);

qrms_test(
	'ürün listesi N+1 sorgu üretmez: tek get_posts, tek GROUP BY',
	function () {
		$GLOBALS['qrms_test']['posts'] = array();

		for ( $i = 1; $i <= 40; $i++ ) {
			$GLOBALS['qrms_test']['posts'][] = (object) array(
				'ID'         => $i,
				'post_title' => 'Ürün ' . $i,
			);
		}

		$GLOBALS['qrms_test']['post_meta'] = array();
		$GLOBALS['qrms_test']['terms']     = array();
		$GLOBALS['qrms_test']['get_posts_calls'] = 0;

		qrms_analitik_onbellek_sifirla();

		$wpdb = qrms_sayan_wpdb();
		$wpdb->results[] = array();  // urun_tiklama_sayaclari
		$wpdb->results[] = array();  // kategori_dagilimi
		$wpdb->vars[]    = 0;        // kategorisiz sayımı
		$wpdb->results[] = array();  // olay_sayaclari detay
		$wpdb->results[] = array();  // urun_siralamasi

		$veri = qrms_analitik_urun_verisi(
			array(
				'bas' => '2026-03-01 00:00:00',
				'bit' => '2026-03-31 23:59:59',
				'gun' => 31,
			),
			'',
			1,
			25
		);

		// Ürün sayısı 40 olmasına rağmen sorgu sayısı SABİT kalır: bir
		// get_posts + beş analitik sorgusu (sayaçlar, dağılım, kategorisiz
		// sayımı, detay açılışı, en çok tıklananlar).
		qrms_assert_same( 1, $GLOBALS['qrms_test']['get_posts_calls'], 'tek ürün sorgusu' );
		qrms_assert_same( 5, count( $wpdb->queries ), 'sabit sayıda analitik sorgusu' );

		// Sayfalama: 40 üründen ilk 25'i.
		qrms_assert_same( 25, count( $veri['enaz'] ), 'sayfa boyu' );
		qrms_assert_same( 2, $veri['enazOzet']['sayfalar'], 'iki sayfa' );
		qrms_assert_same( 40, $veri['enazOzet']['toplam'], 'toplam ürün' );

		// İkinci sayfa kalanı verir.
		qrms_analitik_onbellek_sifirla();
		$wpdb = qrms_sayan_wpdb();
		$wpdb->results[] = array();
		$wpdb->results[] = array();
		$wpdb->vars[]    = 0;
		$wpdb->results[] = array();
		$wpdb->results[] = array();

		$veri = qrms_analitik_urun_verisi(
			array(
				'bas' => '2026-03-01 00:00:00',
				'bit' => '2026-03-31 23:59:59',
				'gun' => 31,
			),
			'',
			2,
			25
		);

		qrms_assert_same( 15, count( $veri['enaz'] ), 'ikinci sayfa' );
	}
);

echo "\nQR Analiz — Veri & Sistem\n";

qrms_test(
	'saklama süresi kaydedilir; filtre en sonda kalır',
	function () {
		delete_option( QRMS_Analitik::SAKLAMA_OPT );

		// Ayar yokken sabit varsayılan geçerlidir.
		qrms_assert_same( 90, QRMS_Analitik::saklama_ayari(), 'varsayılan' );
		qrms_assert_same( 90, QRMS_Analitik::saklama_gun(), 'geçerli süre' );
		qrms_assert_false( QRMS_Analitik::saklama_kilitli_mi(), 'filtre yok' );

		// Kaydedilen değer geçerli olur.
		QRMS_Analitik::saklama_kaydet( 30 );
		qrms_assert_same( 30, QRMS_Analitik::saklama_gun(), 'kaydedilen süre' );

		// Alt sınır: 0 dışında 7 günün altına inilmez (panelin "son 30 gün"
		// görünümleri boşalmasın).
		QRMS_Analitik::saklama_kaydet( 3 );
		qrms_assert_same( 7, QRMS_Analitik::saklama_gun(), 'alt sınır' );

		// 0 temizliği kapatır.
		QRMS_Analitik::saklama_kaydet( 0 );
		qrms_assert_same( 0, QRMS_Analitik::saklama_gun(), 'sınırsız saklama' );
		qrms_assert_same( 0, QRMS_Analitik::eski_kayitlari_sil(), 'temizlik çalışmaz' );

		delete_option( QRMS_Analitik::SAKLAMA_OPT );
	}
);

qrms_test(
	'kodla sabitlenmiş saklama süresi ekranda görünür kılınır',
	function () {
		delete_option( QRMS_Analitik::SAKLAMA_OPT );
		QRMS_Analitik::saklama_kaydet( 30 );

		// Bir mu-plugin süreyi sabitlemişse ekrandan kaydedilen değer
		// geçersizdir; kullanıcı bunu bilmeli.
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 120;
			}
		);

		qrms_assert_same( 30, QRMS_Analitik::saklama_ayari(), 'kayıtlı değer korunur' );
		qrms_assert_same( 120, QRMS_Analitik::saklama_gun(), 'filtre kazanır' );
		qrms_assert_true( QRMS_Analitik::saklama_kilitli_mi(), 'ekran uyarır' );

		$GLOBALS['qrms_test']['actions']['qrms_analitik_saklama_gun'] = array();
		delete_option( QRMS_Analitik::SAKLAMA_OPT );
	}
);

qrms_test(
	'tablo istatistikleri transient ile önbelleklenir',
	function () {
		delete_transient( 'qrms_analitik_tablo_istat' );

		$wpdb = qrms_sayan_wpdb();
		$wpdb->vars[]    = 'wp_rma_analytics'; // tablo_var_mi
		$wpdb->rows[]    = array(
			'satir' => 1200,
			'ilk'   => '2026-01-05 10:00:00',
		);
		$wpdb->vars[]    = 3145728; // DATA_LENGTH + INDEX_LENGTH

		$istat = QRMS_Analitik::tablo_istatistikleri();

		qrms_assert_same( 1200, $istat['satir'], 'satır sayısı' );
		qrms_assert_same( '2026-01-05 10:00:00', $istat['ilk'], 'en eski kayıt' );
		qrms_assert_same( 3145728, $istat['boyut'], 'disk boyutu' );

		$sorgu_sayisi = count( $wpdb->queries );

		// İkinci çağrı hiç sorgu açmaz: COUNT(*) ve information_schema her
		// sayfa açılışında çalıştırılacak şeyler değildir.
		QRMS_Analitik::tablo_istatistikleri();
		qrms_assert_same( $sorgu_sayisi, count( $wpdb->queries ), 'ikinci çağrı önbellekten' );

		// Kayıt silindiğinde önbellek düşer, yoksa ekran bayat sayı gösterirdi.
		QRMS_Analitik::istatistik_onbellegini_temizle();
		qrms_assert_false( get_transient( 'qrms_analitik_tablo_istat' ), 'önbellek düştü' );

		qrms_assert_contains( 'MB', qrms_analitik_boyut_metni( 3145728 ), 'okunabilir boyut' );
		qrms_assert_same( '—', qrms_analitik_boyut_metni( 0 ), 'boş tablo' );
	}
);

qrms_test(
	'ham CSV akış hâlinde yazar: id ilerlemeli, tavanlı',
	function () {
		// Bellek: bütün tabloyu diziye almak yerine dilim dilim çekilir.
		// Sayfalama OFFSET ile değil id > son_id ile yapılır (OFFSET büyüdükçe
		// MySQL atlanan satırları da okur).
		$sinif = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );

		qrms_assert_contains( 'id > %d', $sinif, 'id ilerlemeli sayfalama' );
		// OFFSET yalnızca gerekçe yorumunda geçer, SORGUDA değil.
		qrms_assert_false( false !== strpos( $sinif, 'LIMIT %d OFFSET' ), 'OFFSET ile sayfalama yok' );
		qrms_assert_contains( 'const CSV_TAVAN', $sinif, 'satır tavanı' );
		qrms_assert_contains( 'const CSV_PARCA', $sinif, 'dilim boyu' );

		// Tavana dayanılırsa kullanıcı bunu dosyanın içinde görür; sessizce
		// kesmek eksik veriyi tam sanmaktan kötüdür.
		qrms_assert_contains( 'UYARI: Dosya', $sinif, 'kesme uyarısı' );
	}
);

echo "\nQR Analiz — Masalar kategorisi\n";

qrms_test(
	'hiç okutulmamış masa listeden DÜŞMEZ, 0 ile görünür',
	function () {
		// Kayıt kaynağı qr-masa; sayaç kaynağı analitik tablosu. Sayacı
		// olmayan masa listede kalmalı — asıl aranan satır odur.
		$masalar = array(
			array(
				'slug' => 'bahce-1',
				'ad'   => 'Bahçe 1',
				'grup' => 'bahce',
			),
			array(
				'slug' => 'bahce-2',
				'ad'   => 'Bahçe 2',
				'grup' => 'bahce',
			),
			array(
				'slug' => 'salon-1',
				'ad'   => 'Salon 1',
				'grup' => 'salon',
			),
		);

		$sayaclar = array(
			'bahce-1' => array(
				'mv'  => 40,
				'pc'  => 10,
				'uv'  => 22,
				'son' => '2026-03-10 12:00:00',
			),
			'salon-1' => array(
				'mv'  => 5,
				'pc'  => 1,
				'uv'  => 3,
				'son' => '2026-03-09 20:00:00',
			),
		);

		$karne = qrms_analitik_masa_karnesi( $masalar, $sayaclar );

		qrms_assert_same( 3, count( $karne['satirlar'] ), 'üç masa da listede' );
		qrms_assert_same( 3, $karne['ozet']['kayitli'], 'kayıtlı masa sayısı' );
		qrms_assert_same( 1, $karne['ozet']['sessiz'], 'hiç okutulmayan bir masa' );

		// Hareketi çok olan üstte, sessiz masa altta ama LİSTEDE.
		qrms_assert_same( 'bahce-1', $karne['satirlar'][0]['masa'], 'en hareketli üstte' );
		qrms_assert_same( 'bahce-2', $karne['satirlar'][2]['masa'], 'sessiz masa listede' );
		qrms_assert_same( 0, $karne['satirlar'][2]['mv'], 'sıfır okutma' );
	}
);

qrms_test(
	'silinmiş masanın kaydı "kayıtlı değil" olarak durur, masasız erişim ayrıdır',
	function () {
		$masalar = array(
			array(
				'slug' => 'salon-1',
				'ad'   => 'Salon 1',
				'grup' => 'salon',
			),
		);

		$sayaclar = array(
			'salon-1' => array(
				'mv'  => 10,
				'pc'  => 2,
				'uv'  => 6,
				'son' => '2026-03-10 10:00:00',
			),
			// Artık qr-masa'da olmayan bir masa: geçmişi kaybolmamalı.
			'eski-masa' => array(
				'mv'  => 30,
				'pc'  => 8,
				'uv'  => 15,
				'son' => '2026-02-01 10:00:00',
			),
			// QR okutmadan gelen hareketler.
			'' => array(
				'mv'  => 7,
				'pc'  => 1,
				'uv'  => 4,
				'son' => '2026-03-11 09:00:00',
			),
		);

		$karne   = qrms_analitik_masa_karnesi( $masalar, $sayaclar );
		$durumlar = array();

		foreach ( $karne['satirlar'] as $satir ) {
			$durumlar[ $satir['masa'] ] = $satir['durum'];
		}

		qrms_assert_same( 'kayitli', $durumlar['salon-1'], 'kayıtlı masa' );
		qrms_assert_same( 'kayitsiz', $durumlar['eski-masa'], 'silinmiş masa listede kalır' );
		qrms_assert_same( 'masasiz', $durumlar[''], 'masasız erişim ayrı' );
		qrms_assert_same( 1, $karne['ozet']['kayitsiz'], 'kayıtsız sayacı' );

		// Masasız satır bir masa değildir: sıralamaya karışmaz, en sonda durur.
		$son = $karne['satirlar'][ count( $karne['satirlar'] ) - 1 ];
		qrms_assert_same( 'masasiz', $son['durum'], 'masasız en sonda' );

		// Gruplar YALNIZCA kayıtlı masalardan üretilir.
		qrms_assert_same( 1, count( $karne['gruplar'] ), 'tek grup' );
		qrms_assert_same( 'salon', $karne['gruplar'][0]['grup'], 'grup adı' );
		qrms_assert_same( 10, $karne['gruplar'][0]['mv'], 'grup toplamı' );
	}
);

qrms_test(
	'gruplar toplulaştırılır ve sessiz masaları ayrıca sayar',
	function () {
		$masalar = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$masalar[] = array(
				'slug' => 'bahce-' . $i,
				'ad'   => 'Bahçe ' . $i,
				'grup' => 'bahce',
			);
		}

		$masalar[] = array(
			'slug' => 'salon-1',
			'ad'   => 'Salon 1',
			'grup' => 'salon',
		);

		$karne = qrms_analitik_masa_karnesi(
			$masalar,
			array(
				'bahce-1' => array(
					'mv'  => 60,
					'pc'  => 20,
					'uv'  => 30,
					'son' => '',
				),
				'bahce-2' => array(
					'mv'  => 60,
					'pc'  => 20,
					'uv'  => 30,
					'son' => '',
				),
				'salon-1' => array(
					'mv'  => 300,
					'pc'  => 40,
					'uv'  => 120,
					'son' => '',
				),
			)
		);

		// Sıralama toplam harekete göre: salon (340) > bahçe (160).
		qrms_assert_same( 'salon', $karne['gruplar'][0]['grup'], 'en hareketli grup' );
		qrms_assert_same( 300, $karne['gruplar'][0]['mv'], 'salon okutma' );
		qrms_assert_same( 120, $karne['gruplar'][1]['mv'], 'bahçe okutma toplamı' );
		qrms_assert_same( 3, $karne['gruplar'][1]['masa'], 'bahçede üç masa' );
		qrms_assert_same( 1, $karne['gruplar'][1]['sessiz'], 'biri hiç okutulmamış' );
	}
);

qrms_test(
	'uzun masa slug\'ı analitikteki kırpılmış anahtarla eşleşir',
	function () {
		// masa_no varchar(64); qrm_tables.table_slug varchar(100). Kırpma
		// hesaba katılmazsa uzun adlı masa "hiç okutulmadı" görünürdü.
		$uzun    = str_repeat( 'a', 70 );
		$kirpik  = substr( $uzun, 0, QRMS_Analitik::MASA_UZUNLUK );

		qrms_assert_same( 64, strlen( $kirpik ), 'anahtar 64 karaktere iner' );
		qrms_assert_same( $kirpik, qrms_analitik_masa_anahtari( $uzun ), 'anahtar kırpılır' );

		$karne = qrms_analitik_masa_karnesi(
			array(
				array(
					'slug' => $uzun,
					'ad'   => 'Uzun Masa',
					'grup' => 'uzun',
				),
			),
			array(
				$kirpik => array(
					'mv'  => 12,
					'pc'  => 3,
					'uv'  => 7,
					'son' => '2026-03-10 12:00:00',
				),
			)
		);

		qrms_assert_same( 1, count( $karne['satirlar'] ), 'tek satır (kopya yok)' );
		qrms_assert_same( 12, $karne['satirlar'][0]['mv'], 'sayaç eşleşti' );
		qrms_assert_same( 0, $karne['ozet']['kayitsiz'], 'kayıtsız satır üretilmedi' );
	}
);

qrms_test(
	'masa sayaçları tek GROUP BY ile indeksli aralıktan gelir',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'masa_no' => 'bahce-1',
				'mv'      => 10,
				'pc'      => 2,
				'uv'      => 6,
				'son'     => '2026-03-10 12:00:00',
			),
		);

		$sayac = QRMS_Analitik::masa_sayaclari( '2026-03-01 00:00:00', '2026-03-31 23:59:59', 'bahce-1' );

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );
		qrms_assert_same( 10, $sayac['bahce-1']['mv'], 'sayaç masa_no ile anahtarlanır' );

		// Aralık iki uçtan sınırlı + masa filtresi: idx_masa_td kullanılabilir.
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'kapalı aralık' );
		qrms_assert_contains( "masa_no = 'bahce-1'", $wpdb->queries[0], 'masa filtresi' );
		qrms_assert_contains( 'GROUP BY masa_no', $wpdb->queries[0], 'tek gruplama' );
	}
);

qrms_test(
	'garson çağırma / hesap isteme bölümü Masalar ekranında BASILMAZ',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/masalar-sayfasi.php' );

		// Faz 6 olayları yazıyor; Masalar raporu henüz bağlamaz. Boş bir
		// tablo veri yokluğunu hata gibi gösterirdi; bölüm hiç basılmaz
		// ama yeri yorumla işaretlidir.
		qrms_assert_contains( 'Garson çağırma / hesap isteme sayaçları', $sayfa, 'yer işareti' );
		qrms_assert_contains( 'waiter_call', $sayfa, 'olay adı yazılı' );
		qrms_assert_contains( 'bill_request', $sayfa, 'olay adı yazılı' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-masalar.js' );

		qrms_assert_false( false !== strpos( $js, 'waiter' ), 'ekranda bölüm yok' );
		qrms_assert_false( false !== strpos( $js, 'bill' ), 'ekranda bölüm yok' );
	}
);

echo "\nQR Analiz — Sepet & Sipariş kategorisi\n";

qrms_test(
	'Faz 6 yazım stratejisi: debounce toplu gönderim, sipariş kalem başına tek satır',
	function () {
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		$sip = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );

		qrms_assert_contains( 'ANALITIK_PENCERE = 3000', $js, '3 sn debounce' );
		qrms_assert_contains( 'istemci debounce + toplu gönderim seçildi', $js, 'strateji debounce, oturum sonu değil' );
		qrms_assert_contains( 'Adet kadar satır YAZILMAZ', $sip, 'sipariş kalem başına' );
		qrms_assert_contains( 'Her kalem (satır) için bir olay', $sip, 'adet çoğaltılmaz' );
	}
);

qrms_test(
	'terk oturum bazlıdır: cart_add var, order_sent yok',
	function () {
		// İki oturum: biri terk, biri dönüşmüş. Olay sayısı 3+1 olsa da
		// terk 1 oturumdur — olay bazlı saymak yanıltırdı.
		$gruplar = array(
			array(
				'ip_hash'    => 'aaa',
				'masa_no'    => 'masa-1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 10,
				'item_name'  => 'Burger',
				'category_name' => 'Ana',
				'adet'       => 3,
			),
			array(
				'ip_hash'    => 'bbb',
				'masa_no'    => 'masa-2',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 10,
				'item_name'  => 'Burger',
				'category_name' => 'Ana',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'bbb',
				'masa_no'    => 'masa-2',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_sent',
				'item_id'    => 10,
				'item_name'  => 'Burger',
				'category_name' => 'Ana',
				'adet'       => 1,
			),
		);

		$sonuc = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_assert_same( 4, $sonuc['ozet']['cart_add'], 'olay sayısı (3+1), porsiyon değil' );
		qrms_assert_same( 1, $sonuc['ozet']['cart_add_urun'], 'tekil ürün' );
		qrms_assert_same( 1, $sonuc['ozet']['order_sent'], 'dönüşen oturum' );
		qrms_assert_same( 1, $sonuc['ozet']['terk'], 'terk oturum' );
		qrms_assert_same( 2, $sonuc['ozet']['oturum_add'], 'sepeti olan oturum' );
		qrms_assert_same( 50, $sonuc['ozet']['terk_oran'], 'terk oranı %50' );
		qrms_assert_false( $sonuc['bos'], 'veri var' );

		// Burger: terk oturumunda dönüşmedi, dönüşen oturumda dönüştü.
		qrms_assert_same( 1, count( $sonuc['terk_urun'] ), 'yalnızca dönüşmeyen oturumdaki ürün' );
		qrms_assert_same( 10, $sonuc['terk_urun'][0]['id'], 'burger' );
		qrms_assert_same( 1, $sonuc['terk_urun'][0]['terk'], 'bir oturum terk' );
		qrms_assert_same( 3, $sonuc['terk_urun'][0]['ekleme'], 'terk oturumundaki ekleme olayı' );
	}
);

qrms_test(
	'siparişe dönüşen kalem terk tablosuna girmez; aynı oturumda kalan girer',
	function () {
		// Burger + ayran eklendi, yalnızca ayran sipariş edildi: burger
		// fiyat direnci tablosundadır, oturum ise TERK DEĞİLDİR (order_sent var).
		$gruplar = array(
			array(
				'ip_hash'    => 'aaa',
				'masa_no'    => 'masa-1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 10,
				'item_name'  => 'Burger',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'aaa',
				'masa_no'    => 'masa-1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 11,
				'item_name'  => 'Ayran',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'aaa',
				'masa_no'    => 'masa-1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_sent',
				'item_id'    => 11,
				'item_name'  => 'Ayran',
				'adet'       => 1,
			),
		);

		$sonuc = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_assert_same( 0, $sonuc['ozet']['terk'], 'oturum sipariş verdi' );
		qrms_assert_same( 1, $sonuc['ozet']['order_sent'], 'gönderildi' );
		qrms_assert_same( 1, count( $sonuc['terk_urun'] ), 'burger dönüşmedi' );
		qrms_assert_same( 'Burger', $sonuc['terk_urun'][0]['ad'], 'dönüşmeyen ürün' );
	}
);

qrms_test(
	'sepetten çıkarma oranı küresel ekleme/çıkarma sayısından hesaplanır',
	function () {
		$gruplar = array(
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_add',
				'item_id'    => 7,
				'item_name'  => 'Künefe',
				'adet'       => 4,
			),
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'cart_remove',
				'item_id'    => 7,
				'item_name'  => 'Künefe',
				'adet'       => 2,
			),
			array(
				'ip_hash'    => 'b',
				'masa_no'    => 'm2',
				'pencere'    => '2026-03-10 14',
				'event_type' => 'cart_remove',
				'item_id'    => 8,
				'item_name'  => 'Çay',
				'adet'       => 1,
			),
		);

		$sonuc = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_assert_same( 2, count( $sonuc['cikarilan'] ), 'iki ürün çıkarıldı' );
		qrms_assert_same( 'Künefe', $sonuc['cikarilan'][0]['ad'], 'daha çok çıkan üstte' );
		qrms_assert_same( 2, $sonuc['cikarilan'][0]['cikarma'], 'çıkarma' );
		qrms_assert_same( 4, $sonuc['cikarilan'][0]['ekleme'], 'ekleme' );
		qrms_assert_same( 2.0, $sonuc['cikarilan'][0]['oran'], '4/2=2' );
		qrms_assert_same( 'Çay', $sonuc['cikarilan'][1]['ad'], 'ikinci' );
		qrms_assert_same( 0, $sonuc['cikarilan'][1]['ekleme'], 'hiç eklenmeden çıkarılmış olabilir' );
		qrms_assert_same( 0.0, $sonuc['cikarilan'][1]['oran'], '0/1=0' );
	}
);

qrms_test(
	'engellenen sipariş ürüne toplanır; hatalar sıfırsa dağılım boştur',
	function () {
		$gruplar = array(
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_blocked',
				'item_id'    => 3,
				'item_name'  => 'Izgara',
				'adet'       => 2,
			),
			array(
				'ip_hash'    => 'b',
				'masa_no'    => 'm2',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_blocked',
				'item_id'    => 3,
				'item_name'  => 'Izgara',
				'adet'       => 1,
			),
		);

		$sonuc = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );

		qrms_assert_same( 3, $sonuc['ozet']['blocked'], 'kaçırılan sipariş olayı' );
		qrms_assert_same( 1, count( $sonuc['engellenen'] ), 'tek ürün' );
		qrms_assert_same( 3, $sonuc['engellenen'][0]['siparis'], 'ürüne toplanır' );
		qrms_assert_same( 0, $sonuc['ozet']['failed'], 'hata yok' );
		qrms_assert_same( array(), $sonuc['hatalar'], 'dağılım basılmaz' );
	}
);

qrms_test(
	'sipariş hataları oturum bazlı zaman kovasına düşer; çok kalemli sipariş tek sayılır',
	function () {
		$gruplar = array(
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_failed',
				'item_id'    => 1,
				'item_name'  => 'A',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'a',
				'masa_no'    => 'm1',
				'pencere'    => '2026-03-10 12',
				'event_type' => 'order_failed',
				'item_id'    => 2,
				'item_name'  => 'B',
				'adet'       => 1,
			),
			array(
				'ip_hash'    => 'c',
				'masa_no'    => 'm2',
				'pencere'    => '2026-03-11 08',
				'event_type' => 'order_failed',
				'item_id'    => 1,
				'item_name'  => 'A',
				'adet'       => 1,
			),
		);

		$gun = qrms_analitik_sepet_hesapla( $gruplar, 1, 0 );
		qrms_assert_same( 2, $gun['ozet']['failed'], 'iki oturum' );
		qrms_assert_same( 2, count( $gun['hatalar'] ), 'iki pencere (tek gün kırılımı)' );

		$ay = qrms_analitik_sepet_hesapla( $gruplar, 7, 0 );
		qrms_assert_same( 2, count( $ay['hatalar'] ), 'güne indirgenir' );
		qrms_assert_same( '2026-03-10', $ay['hatalar'][0]['label'], 'ilk gün' );
		qrms_assert_same( 1, $ay['hatalar'][0]['sayi'], 'o gün bir oturum' );
	}
);

qrms_test(
	'boş girdi "toplanmaya yeni başlandı" durumudur, hata değil',
	function () {
		$sonuc = qrms_analitik_sepet_hesapla( array(), 1, 0 );

		qrms_assert_true( $sonuc['bos'], 'boş bayrağı' );
		qrms_assert_same( 0, $sonuc['ozet']['cart_add'], 'sıfır' );
		qrms_assert_same( array(), $sonuc['terk_urun'], 'tablo boş' );
	}
);

qrms_test(
	'sepet grupları tek GROUP BY, indeksli aralık, istek içi önbellekli',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'ip_hash'       => 'x',
				'masa_no'       => 'masa-1',
				'pencere'       => '2026-03-10 12',
				'event_type'    => 'cart_add',
				'item_id'       => 10,
				'item_name'     => 'Burger',
				'category_name' => 'Ana',
				'adet'          => 1,
				'ilk'           => '2026-03-10 12:01:00',
				'son'           => '2026-03-10 12:01:00',
			),
		);

		QRMS_Analitik::sepet_onbellegini_temizle();
		qrms_analitik_onbellek_sifirla();

		$grup = QRMS_Analitik::sepet_olay_gruplari( '2026-03-10 00:00:00', '2026-03-10 23:59:59', 'masa-1' );

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'idx_td/idx_date aralığı' );
		qrms_assert_contains( "event_type IN ('cart_add','cart_remove','order_sent','order_failed','order_blocked')", $wpdb->queries[0], 'beş olay tipi' );
		qrms_assert_contains( 'GROUP BY ip_hash, masa_no', $wpdb->queries[0], 'oturum gruplaması SQL\'de' );
		qrms_assert_contains( "masa_no = 'masa-1'", $wpdb->queries[0], 'masa filtresi idx_masa_td' );
		qrms_assert_false( false !== strpos( $wpdb->queries[0], 'LIMIT %d OFFSET' ), 'OFFSET yok' );

		// İkinci çağrı aynı istekte sorgu açmaz.
		QRMS_Analitik::sepet_olay_gruplari( '2026-03-10 00:00:00', '2026-03-10 23:59:59', 'masa-1' );
		qrms_assert_same( 1, count( $wpdb->queries ), 'istek içi önbellek' );

		qrms_analitik_onbellek_sifirla();
		$veri = qrms_analitik_sepet_verisi(
			array(
				'bas' => '2026-03-10 00:00:00',
				'bit' => '2026-03-10 23:59:59',
				'gun' => 1,
			),
			'masa-1'
		);
		qrms_assert_same( 1, $veri['ozet']['cart_add'], 'hesaplama gruplardan' );
		qrms_assert_same( 1, count( $wpdb->queries ), 'veri fonksiyonu yeni sorgu açmaz' );

		qrms_assert_same( 1, count( $grup ), 'grup satırı' );
	}
);

qrms_test(
	'sepet CSV\'si sistem ham/ürün/masa indirmesinden ayrıdır',
	function () {
		$sinif = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sepet-sayfasi.php' );
		$sistem = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sistem-sayfasi.php' );

		qrms_assert_contains( "if ( 'sepet' === \$kategori )", $sinif, 'ayrı kategori anahtarı' );
		qrms_assert_contains( 'csv_sepet', $sinif, 'sepet CSV üreticisi' );
		qrms_assert_contains( "'kategori' => 'sepet'", $sayfa, 'sayfa kendi indirmesini ister' );
		qrms_assert_false( false !== strpos( $sistem, "'kategori' => 'sepet'" ), 'sistem sayfası sepet CSV üretmez' );
		qrms_assert_contains( 'qr-analitik-sepet-', $sinif, 'dosya adı çakışmaz' );
	}
);

qrms_test(
	'sepet sayfası paylaşılan filtreyi kullanır ve Ürünüm Yok bağlantısı filtreyi taşır',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/sepet-sayfasi.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-sepet.js' );
		$hub   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php' );

		qrms_assert_contains( "qrms_analitik_filtre_cubugu( 'qrms-an-sepet' )", $sayfa, 'paylaşılan filtre' );
		qrms_assert_contains( 'qrms-rm-urunum-yok', $sayfa, 'Ürünüm Yok bağlantısı' );
		qrms_assert_contains( 'QRMS_Analitik_Filtre::args()', $sayfa, 'filtre taşınır' );
		qrms_assert_contains( 'qrms-analiz-ayarlar', $sayfa, 'Firebase ayar slug yedek' );
		qrms_assert_contains( 'qrms_analitik_sepet', $js, 'AJAX ucu' );
		qrms_assert_contains( 'hataPanel.hidden', $js, 'sıfır hatada bölüm basılmaz' );
		qrms_assert_contains( 'justStarted', $js, 'yeni başladı boş durumu' );
		qrms_assert_contains( "'hazir'  => true", $hub, 'sepet kartı yakında değil' );
		qrms_assert_false(
			false !== strpos( $hub, 'function qrms_analitik_sayfa_sepet' ),
			'placeholder fonksiyon hub\'dan kalktı'
		);
	}
);

qrms_test(
	'etkileşim hesaplaması saf fonksiyondur: ödül dönüşümü sıfıra bölünmez',
	function () {
		$bos = qrms_analitik_etkilesim_hesapla( array() );

		qrms_assert_true( $bos['bos'], 'boş girdi' );
		qrms_assert_same( 0, $bos['ozet']['reward_oran'], 'üretilen yokken oran 0' );

		$sonuc = qrms_analitik_etkilesim_hesapla(
			array(
				array(
					'event_type' => 'chatbot_message',
					'item_name'  => '',
					'adet'       => 4,
				),
				array(
					'event_type' => 'form_submit',
					'item_name'  => 'Rezervasyon',
					'adet'       => 3,
				),
				array(
					'event_type' => 'form_submit',
					'item_name'  => 'İletişim',
					'adet'       => 1,
				),
				array(
					'event_type' => 'reward_issued',
					'item_name'  => '',
					'adet'       => 10,
				),
				array(
					'event_type' => 'reward_redeemed',
					'item_name'  => '',
					'adet'       => 4,
				),
				array(
					'event_type' => 'lang_switch',
					'item_name'  => 'en',
					'adet'       => 6,
				),
				array(
					'event_type' => 'lang_switch',
					'item_name'  => 'ar',
					'adet'       => 2,
				),
				array(
					'event_type' => 'gallery_view',
					'item_name'  => '',
					'adet'       => 5,
				),
			)
		);

		qrms_assert_false( $sonuc['bos'], 'dolu girdi' );
		qrms_assert_same( 4, $sonuc['ozet']['chatbot'], 'chatbot' );
		qrms_assert_same( 4, $sonuc['ozet']['form'], 'form toplam' );
		qrms_assert_same( 40, $sonuc['ozet']['reward_oran'], '4/10 dönüşüm' );
		qrms_assert_same( 'Rezervasyon', $sonuc['formlar'][0]['ad'], 'form sırası adet' );
		qrms_assert_same( 8, $sonuc['ozet']['lang'], 'dil toplam' );
		qrms_assert_same( 'en', $sonuc['diller'][0]['kod'], 'en önde' );
		qrms_assert_same( 75, $sonuc['diller'][0]['pay'], 'en payı' );
		qrms_assert_same( 5, $sonuc['ozet']['gallery'], 'galeri' );
	}
);

qrms_test(
	'etkileşim CSV\'si ayrı kategori anahtarı kullanır',
	function () {
		$sinif = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/etkilesim-sayfasi.php' );

		qrms_assert_contains( "if ( 'etkilesim' === \$kategori )", $sinif, 'ayrı kategori anahtarı' );
		qrms_assert_contains( 'csv_etkilesim', $sinif, 'etkileşim CSV üreticisi' );
		qrms_assert_contains( "'kategori' => 'etkilesim'", $sayfa, 'sayfa kendi indirmesini ister' );
		qrms_assert_contains( 'qr-analitik-etkilesim-', $sinif, 'dosya adı çakışmaz' );
	}
);

qrms_test(
	'etkileşim sayfası paylaşılan filtreyi kullanır ve bağlı olmayan bölümü basmaz',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/etkilesim-sayfasi.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-etkilesim.js' );
		$hub   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php' );

		qrms_assert_contains( "qrms_analitik_filtre_cubugu( 'qrms-an-etkilesim' )", $sayfa, 'paylaşılan filtre' );
		qrms_assert_contains( 'qrms_analitik_etkilesim', $js, 'AJAX ucu' );
		qrms_assert_contains( 'justStarted', $js, 'yeni başladı boş durumu' );
		qrms_assert_contains( "'hazir'    => true", $hub, 'etkileşim kartı yakında değil' );
		qrms_assert_false(
			false !== strpos( $hub, 'function qrms_analitik_sayfa_etkilesim' ),
			'placeholder fonksiyon hub\'dan kalktı'
		);
		qrms_assert_contains( 'moduller', $hub, 'OR lisans süzmesi' );
	}
);

qrms_test(
	'etkileşim bağlı modüller pasifken sayfa yine kayıtlıdır ama hub kartı yoktur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-analiz' ) );
		QRMS_Analitik_Filtre::sifirla();

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		qrms_assert_true( in_array( 'qrms-an-etkilesim', $sluglar, true ), 'doğrudan URL çalışsın diye kayıtlı' );

		$etk_kart = false;
		foreach ( qrms_module_qr_analiz_hub_kartlari() as $kart ) {
			if ( false !== strpos( $kart['url'], 'page=qrms-an-etkilesim' ) ) {
				$etk_kart = true;
			}
		}

		qrms_assert_false( $etk_kart, 'hub kartı basılmaz' );

		ob_start();
		qrms_analitik_sayfa_etkilesim();
		$html = ob_get_clean();

		qrms_assert_contains( 'Bu kategori bu lisansta kapalı', $html, 'anlamlı mesaj' );
		qrms_assert_false( false !== strpos( $html, 'id="qrms-an-etk-chatbot-cards"' ), 'boş tablo yok' );

		update_option( 'qrms_active_modules', array() );
	}
);

qrms_test(
	'açılış hesaplaması saf fonksiyondur: splash_view sıfırken oran 0',
	function () {
		$bos = qrms_analitik_acilis_hesapla( array() );

		qrms_assert_true( $bos['bos'], 'boş girdi' );
		qrms_assert_same( 0, $bos['ozet']['menu_oran'], 'gösterim yokken menü oranı 0' );
		qrms_assert_same( 0, $bos['ozet']['atla_oran'], 'gösterim yokken atlanma 0' );

		$sifir_payda = qrms_analitik_acilis_hesapla(
			array(
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'menu',
					'adet'       => 3,
				),
			)
		);

		qrms_assert_same( 0, $sifir_payda['ozet']['view'], 'gösterim yok' );
		qrms_assert_same( 3, $sifir_payda['ozet']['menu'], 'eylem sayılır' );
		qrms_assert_same( 0, $sifir_payda['ozet']['menu_oran'], 'payda sıfır: bölme yok' );

		$sonuc = qrms_analitik_acilis_hesapla(
			array(
				array(
					'event_type' => 'splash_view',
					'item_name'  => '',
					'adet'       => 10,
				),
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'menu',
					'adet'       => 4,
				),
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'atla',
					'adet'       => 2,
				),
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'wifi',
					'adet'       => 3,
				),
				array(
					'event_type' => 'splash_action',
					'item_name'  => 'sosyal',
					'adet'       => 1,
				),
			)
		);

		qrms_assert_false( $sonuc['bos'], 'dolu girdi' );
		qrms_assert_same( 10, $sonuc['ozet']['view'], 'gösterim' );
		qrms_assert_same( 40, $sonuc['ozet']['menu_oran'], '4/10 menü' );
		qrms_assert_same( 20, $sonuc['ozet']['atla_oran'], '2/10 atla' );
		qrms_assert_same( 'wifi', $sonuc['butonlar'][0]['kod'], 'wifi ilk buton' );
		qrms_assert_same( 3, $sonuc['butonlar'][0]['adet'], 'wifi adet' );
		qrms_assert_same( 30, $sonuc['butonlar'][0]['pay'], 'wifi payı gösterime' );
	}
);

qrms_test(
	'açılış CSV\'si ayrı kategori anahtarı kullanır',
	function () {
		$sinif = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/acilis-sayfasi.php' );

		qrms_assert_contains( "if ( 'acilis' === \$kategori )", $sinif, 'ayrı kategori anahtarı' );
		qrms_assert_contains( 'csv_acilis', $sinif, 'açılış CSV üreticisi' );
		qrms_assert_contains( "'kategori' => 'acilis'", $sayfa, 'sayfa kendi indirmesini ister' );
		qrms_assert_contains( 'qr-analitik-acilis-', $sinif, 'dosya adı çakışmaz' );
	}
);

qrms_test(
	'açılış sayfası paylaşılan filtreyi kullanır ve sıfıra bölmez',
	function () {
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/acilis-sayfasi.php' );
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-acilis.js' );
		$hub   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/hub-sayfasi.php' );

		qrms_assert_contains( "qrms_analitik_filtre_cubugu( 'qrms-an-acilis' )", $sayfa, 'paylaşılan filtre' );
		qrms_assert_contains( 'qrms_analitik_acilis', $js, 'AJAX ucu' );
		qrms_assert_contains( 'justStarted', $js, 'yeni başladı boş durumu' );
		qrms_assert_contains( "'hazir'  => true", $hub, 'açılış kartı yakında değil' );
		qrms_assert_false(
			false !== strpos( $hub, 'function qrms_analitik_sayfa_acilis' ),
			'placeholder fonksiyon hub\'dan kalktı'
		);
		qrms_assert_contains( '$ozet[\'view\'] > 0', $sayfa, 'payda sıfır koruması' );
	}
);

qrms_test(
	'açılış modülü pasifken sayfa yine kayıtlıdır ama hub kartı yoktur',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-analiz' ) );
		QRMS_Analitik_Filtre::sifirla();

		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'İstatistikler', QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ),
		);

		qrms_module_qr_analiz_admin_menu();

		$sluglar = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$GLOBALS['qrms_test']['submenus']
		);

		qrms_assert_true( in_array( 'qrms-an-acilis', $sluglar, true ), 'doğrudan URL çalışsın diye kayıtlı' );

		$acilis_kart = false;
		foreach ( qrms_module_qr_analiz_hub_kartlari() as $kart ) {
			if ( false !== strpos( $kart['url'], 'page=qrms-an-acilis' ) ) {
				$acilis_kart = true;
			}
		}

		qrms_assert_false( $acilis_kart, 'hub kartı basılmaz' );

		ob_start();
		qrms_analitik_sayfa_acilis();
		$html = ob_get_clean();

		qrms_assert_contains( 'Bu kategori bu lisansta kapalı', $html, 'anlamlı mesaj' );
		qrms_assert_false( false !== strpos( $html, 'id="qrms-an-acilis-cards"' ), 'boş tablo yok' );

		update_option( 'qrms_active_modules', array() );
	}
);

qrms_test(
	'detay modalı açılışı ayrı olaydır ve tıklama sıfırken oran 0',
	function () {
		$frontend = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-frontend.js' );
		$modal    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/rma-detail-modal.js' );
		$urun     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/urunler-sayfasi.php' );
		$js       = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-urunler.js' );

		qrms_assert_contains( "yaz('item_detail_open'", $frontend, 'ana menü modalı' );
		qrms_assert_contains( 'rmaAnalitikDetay(id)', $frontend, 'gösterimde yazılır' );
		qrms_assert_contains( "yaz('item_detail_open'", $modal, 'vitrin/slider modalı' );
		qrms_assert_false( false !== strpos( $urun, 'bilinçli olarak YOKTUR' ), 'Faz 3 yorumu kalktı' );
		qrms_assert_contains( 'qrms-an-detay-cards', $urun, 'ürünler bölümü' );
		qrms_assert_contains( 'justStartedDetail', $js, 'yeni başladı boş durumu' );

		$bos = qrms_analitik_urun_detay_hesapla( array() );
		qrms_assert_true( $bos['bos'], 'açılış yok' );
		qrms_assert_same( 0, $bos['oran'], 'payda sıfır' );

		$oran = qrms_analitik_urun_detay_hesapla(
			array(
				array(
					'event_type' => 'product_click',
					'item_name'  => 'A',
					'adet'       => 4,
				),
				array(
					'event_type' => 'item_detail_open',
					'item_name'  => 'A',
					'adet'       => 6,
				),
			)
		);

		qrms_assert_same( 4, $oran['click'], 'tıklama' );
		qrms_assert_same( 6, $oran['open'], 'açılış' );
		qrms_assert_same( 150, $oran['oran'], 'önbellek yüzünden %100 üzeri' );
		qrms_assert_false( $oran['bos'], 'dolu' );
	}
);

qrms_test(
	'kategori dağılımı boş adları listeye KARIŞTIRMAZ, ayrı sayar',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'category_name' => 'Tatlılar',
				'toplam'        => 12,
				'urun_sayisi'   => 3,
				'tekil'         => 8,
			),
		);
		$wpdb->vars[] = 5;

		$sonuc = QRMS_Analitik::kategori_dagilimi( '2026-03-01 00:00:00', '2026-03-31 23:59:59' );

		qrms_assert_same( 1, count( $sonuc['satirlar'] ), 'yalnızca gerçek kategoriler' );
		qrms_assert_same( 'Tatlılar', $sonuc['satirlar'][0]['kategori'], 'kategori adı' );
		qrms_assert_same( 5, $sonuc['kategorisiz'], 'boş adlar ayrı sayılır' );

		// category_name yalnızca product_click olayında dolar; sorgu da
		// yalnızca ona bakar ve boş adları dışarıda bırakır.
		qrms_assert_contains( "event_type='product_click'", $wpdb->queries[0], 'yalnızca tıklama olayı' );
		qrms_assert_contains( "category_name <> ''", $wpdb->queries[0], 'boş adlar dışarıda' );

		// Aralık iki uçtan sınırlı: idx_td aralık taraması olarak kullanılır.
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'kapalı aralık' );
	}
);

qrms_test(
	'yeniden adlandırılmış kategori "eski ad" olarak işaretlenir',
	function () {
		$GLOBALS['qrms_test']['posts']      = array();
		$GLOBALS['qrms_test']['post_meta']  = array();
		$GLOBALS['qrms_test']['terms']      = array();
		// Taksonomide yalnızca "Tatlılar" var; "Tatlı" eski addır.
		$GLOBALS['qrms_test']['term_names'] = array( 'Tatlılar', 'Çorbalar' );

		qrms_analitik_onbellek_sifirla();

		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(); // sayaçlar
		$wpdb->results[] = array(
			array(
				'category_name' => 'Tatlılar',
				'toplam'        => 12,
				'urun_sayisi'   => 3,
				'tekil'         => 8,
			),
			array(
				'category_name' => 'Tatlı',
				'toplam'        => 4,
				'urun_sayisi'   => 1,
				'tekil'         => 3,
			),
		);
		$wpdb->vars[]    = 0;
		$wpdb->results[] = array(); // en çok tıklananlar

		$veri = qrms_analitik_urun_verisi(
			array(
				'bas' => '2026-03-01 00:00:00',
				'bit' => '2026-03-31 23:59:59',
				'gun' => 31,
			)
		);

		qrms_assert_false( $veri['kategoriler'][0]['eski_ad'], 'mevcut ad işaretlenmez' );
		qrms_assert_true( $veri['kategoriler'][1]['eski_ad'], 'artık olmayan ad işaretlenir' );

		// Sayı DÜZELTİLMEZ: iki satır da olduğu gibi kalır, yalnızca
		// etiketlenir (hangi eski adın hangi yeni ada karşılık geldiğini
		// söyleyen bir kayıt yok).
		qrms_assert_same( 2, count( $veri['kategoriler'] ), 'satırlar birleştirilmez' );
		qrms_assert_same( 4, $veri['kategoriler'][1]['toplam'], 'eski adın sayısı korunur' );
	}
);

qrms_test(
	'ortak JS yardımcıları TEK dosyada durur, iki ekranda kopyalanmaz',
	function () {
		$ortak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-ortak.js' );
		$eski  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik.js' );
		$genel = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-genel.js' );

		// Fetch sarmalayıcısı, tablo iskeleti, grafik çizimi ve filtre çubuğu:
		// hepsi ortakta tanımlı, diğer iki dosyada YENİDEN tanımlı değil.
		foreach ( array( 'function post(', 'function tabloIskelet(', 'function grafikHtml(', 'function filtreKur(' ) as $fn ) {
			qrms_assert_contains( $fn, $ortak, $fn . ' ortakta' );
			qrms_assert_false( false !== strpos( $genel, $fn ), $fn . ' Genel Bakış\'ta kopyalanmadı' );
		}

		foreach ( array( 'function tabloIskelet(', 'function grafikHtml(', 'function esc(' ) as $fn ) {
			qrms_assert_false( false !== strpos( $eski, $fn ), $fn . ' klasik panelde kopyalanmadı' );
		}

		// İki ekran da ortağı kullanır ve yoksa sessizce durur.
		qrms_assert_contains( 'window.qrmsAnOrtak', $ortak, 'ortak ad alanı' );
		qrms_assert_contains( 'window.qrmsAnOrtak', $eski, 'klasik panel ortağı kullanır' );
		qrms_assert_contains( 'window.qrmsAnOrtak', $genel, 'Genel Bakış ortağı kullanır' );
	}
);

qrms_test(
	'Genel Bakış aynı kırılımı iki kez istemez',
	function () {
		// Aralık ve masa sayfa ömrü boyunca sabittir (değişmeleri sayfayı
		// yeniler); yalnızca kırılım değişir, o da önbelleğe alınır.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-genel.js' );

		qrms_assert_contains( 'state.onbellek[ state.kirilim ]', $js, 'kırılım önbelleği' );
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
 * (6c bölümünün QRMS_Test_Wpdb'si ayrıdır; bu sınıf
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
 * 8z-3. Yorum gönderimi — puan aralığı ve yazma sonucu
 *
 * qrm_pro_handle_review_submission hem AJAX hem klasik POST akışının ortak
 * yoludur; burada GERÇEK spam/cooldown yardımcıları (security.php) yüklenir ki
 * doğrulama sırası da testin kapsamında kalsın.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/security.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-review.php';

// Bu bölümün taklidi yalnızca insert() karşılar; sonraki bölümlere sızmaması
// için o ana kadarki $wpdb saklanır ve bölüm sonunda geri takılır.
$qrms_gonderim_onceki_wpdb = $GLOBALS['wpdb'];

/**
 * insert() çağrılarını kaydeden $wpdb taklidi.
 *
 * $sonuc = false yapılarak yazma hatası (tablo yok, bağlantı düştü, sütun
 * taşması) taklit edilir; gerçek $wpdb->insert de bu durumda false döner.
 */
class QRMS_Yorum_Insert_Wpdb {
	public $prefix    = 'wp_';
	public $inserts   = array();
	public $insert_id = 0;
	public $sonuc     = 1;

	public function insert( $table, $data, $format = null ) {
		$this->inserts[] = array(
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		);

		if ( false === $this->sonuc ) {
			return false;
		}

		$this->insert_id = 101;

		return 1;
	}

	public function son_insert() {
		return end( $this->inserts ) ?: array();
	}

	/**
	 * Yorum satırının insert'i (analitik kaydı sonra gelebilir).
	 *
	 * @return array
	 */
	public function yorum_insert() {
		foreach ( $this->inserts as $insert ) {
			if ( isset( $insert['data']['rating_1'] ) ) {
				return $insert;
			}
		}

		return $this->son_insert();
	}
}

/**
 * Yorum gönderimi testleri için taze bir $wpdb takar.
 *
 * @param mixed $sonuc insert() dönüş değeri (false = yazma hatası).
 * @return QRMS_Yorum_Insert_Wpdb
 */
function qrms_gonderim_wpdb( $sonuc = 1 ) {
	$db        = new QRMS_Yorum_Insert_Wpdb();
	$db->sonuc = $sonuc;

	$GLOBALS['wpdb'] = $db;

	return $db;
}

/**
 * Gönderim ayarları (beş kriter de açık, ödül/Google kapalı).
 *
 * @param array $ek Üzerine yazılacak ayarlar.
 * @return array
 */
function qrms_gonderim_ayarlari( $ek = array() ) {
	$ayarlar = array(
		'auto_approve_rating'       => 0,
		'google_review_enabled'     => 0,
		'google_review_url'         => '',
		'google_review_threshold'   => 3.5,
		'qrm_spam_cooldown_minutes' => 10,
	);

	for ( $i = 1; $i <= 5; $i++ ) {
		$ayarlar[ 'crit_' . $i . '_active' ] = 1;
	}

	return array_merge( $ayarlar, $ek );
}

/**
 * Spam korumasını geçen geçerli bir $_POST hazırlar.
 *
 * Zaman tuzağı en az 3 saniye beklemeyi şart koştuğu için imzalı damga geçmişe
 * tarihlenir; captcha cevabı da aynı imza şemasıyla üretilir.
 *
 * @param array $puanlar rating_N => değer.
 * @param array $ek      Ek POST alanları.
 * @return void
 */
function qrms_gonderim_postu( $puanlar, $ek = array() ) {
	$damga = time() - 10;

	$_POST = array(
		'qrm_ts'           => $damga . '.' . hash_hmac( 'sha256', $damga . '|qrm_ts', wp_salt( 'auth' ) ),
		'qrm_captcha'      => 7,
		'qrm_captcha_hash' => hash_hmac( 'sha256', '7', wp_salt( 'nonce' ) ),
	);

	foreach ( $puanlar as $kriter => $puan ) {
		$_POST[ 'rating_' . $kriter ] = $puan;
	}

	foreach ( $ek as $anahtar => $deger ) {
		$_POST[ $anahtar ] = $deger;
	}

	// Cooldown yalnızca yetkisiz ziyaretçilere uygulanır; testler gerçek
	// müşteri akışını izlesin diye yetki kapatılır.
	$GLOBALS['qrms_test']['can'] = false;
}

echo "\nYorum gönderimi — puan aralığı ve yazma sonucu\n";

qrms_test(
	'5 üstü puan sunucuda 0\'a düşer ve ortalamaya katılmaz',
	function () {
		// Form 1-5 gönderir; istek elle hazırlandığında aralık dışı değer
		// eskiden olduğu gibi kaydediliyor, ortalamayı 5'in üstüne çıkarıyordu.
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 4, 2 => 99 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );
		$veri  = $db->yorum_insert();

		qrms_assert_true( $sonuc['success'], 'gönderim kabul edilir' );
		qrms_assert_same( 0, $veri['data']['rating_2'], 'aralık dışı puan sıfırlanır' );
		qrms_assert_same( 4, $veri['data']['rating_1'], 'geçerli puan korunur' );
		qrms_assert_same( 4.0, $sonuc['avg'], 'ortalama yalnızca geçerli puandan hesaplanır' );
	}
);

qrms_test(
	'negatif puan da 0\'a düşer',
	function () {
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 5, 3 => -4 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );
		$veri  = $db->yorum_insert();

		qrms_assert_same( 0, $veri['data']['rating_3'], 'negatif puan sıfırlanır' );
		qrms_assert_same( 5.0, $sonuc['avg'], 'ortalama negatiften etkilenmez' );
	}
);

qrms_test(
	'aralık dışı puan TEK kriterse gönderim reddedilir',
	function () {
		// Sıfıra düşen puan "puanlanmamış" sayılır: ortalama 0 kalır ve
		// kayıt hiç açılmaz.
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 12 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_false( $sonuc['success'], 'reddedilir' );
		qrms_assert_same( 0, count( $db->inserts ), 'kayıt açılmaz' );
	}
);

qrms_test(
	'5 sınır değeri geçerli sayılır',
	function () {
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 5 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_true( $sonuc['success'], 'üst sınır kabul edilir' );
		qrms_assert_same( 5, $db->yorum_insert()['data']['rating_1'], '5 korunur' );
	}
);

qrms_test(
	'insert sütun formatlarıyla çağrılır, sıra veriyle örtüşür',
	function () {
		$db = qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 4 ), array( 'is_anonymous' => '1', 'table_no' => 'A12' ) );

		qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		$veri = $db->yorum_insert();

		qrms_assert_true( is_array( $veri['format'] ), 'format dizisi verilir' );
		qrms_assert_same(
			count( $veri['data'] ),
			count( $veri['format'] ),
			'her sütun için bir format'
		);
		qrms_assert_same(
			array( '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
			$veri['format'],
			'formatlar sütun sırasına göre'
		);
		qrms_assert_same(
			array(
				'rating',
				'rating_1',
				'rating_2',
				'rating_3',
				'rating_4',
				'rating_5',
				'comment',
				'customer_name',
				'customer_phone',
				'table_no',
				'is_anonymous',
				'status',
				'form_source',
			),
			array_keys( $veri['data'] ),
			'sütun sırası formatla aynı'
		);
	}
);

qrms_test(
	'yazma başarısız olursa success:false döner',
	function () {
		// Eskiden insert()'in dönüşü okunmuyordu: kayıt açılmasa bile
		// kullanıcıya "alındı" deniyordu.
		qrms_gonderim_wpdb( false );
		qrms_gonderim_postu( array( 1 => 4 ) );

		$sonuc = qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_false( $sonuc['success'], 'başarısızlık bildirilir' );
		qrms_assert_contains( 'kaydedilemedi', $sonuc['message'], 'kullanıcıya tekrar deneme mesajı' );
	}
);

qrms_test(
	'yazma başarısız olursa cooldown penceresi BAŞLAMAZ',
	function () {
		// Aksi hâlde kaydı oluşmayan müşteri, dakikalarca tekrar deneyemezdi.
		qrms_gonderim_wpdb( false );
		qrms_gonderim_postu( array( 1 => 4 ), array( 'customer_phone' => '0555 111 22 33' ) );

		qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		foreach ( qrm_pro_cooldown_keys( array( 'phone' => '05551112233' ) ) as $anahtar ) {
			qrms_assert_false( get_transient( $anahtar ), 'cooldown işaretlenmedi: ' . $anahtar );
		}

		// Kısıt gerçekten çalışıyor olsun diye kontrol: başarılı gönderim işaretler.
		qrms_gonderim_wpdb();
		qrms_gonderim_postu( array( 1 => 4 ), array( 'customer_phone' => '0555 111 22 33' ) );
		qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_true(
			(bool) get_transient( qrm_pro_cooldown_keys( array( 'phone' => '05551112233' ) )[0] ),
			'başarılı gönderim cooldown başlatır'
		);
	}
);

qrms_test(
	'yazma başarısız olursa istatistik önbelleği boşuna geçersizlenmez',
	function () {
		set_transient( QRM_PRO_STATS_TRANSIENT, array( 'total' => 7 ), 60 );

		qrms_gonderim_wpdb( false );
		qrms_gonderim_postu( array( 1 => 4 ) );

		qrm_pro_handle_review_submission( qrms_gonderim_ayarlari() );

		qrms_assert_same(
			array( 'total' => 7 ),
			get_transient( QRM_PRO_STATS_TRANSIENT ),
			'önbellek yerinde kalır'
		);
	}
);

qrms_test(
	'başarılı yazmada review_id insert_id\'den gelir ve akış sürer',
	function () {
		$db = qrms_gonderim_wpdb();
		set_transient( QRM_PRO_STATS_TRANSIENT, array( 'total' => 7 ), 60 );
		qrms_gonderim_postu( array( 1 => 5 ) );

		$ayarlar = qrms_gonderim_ayarlari(
			array(
				'auto_approve_rating'   => 4,
				'google_review_enabled' => 1,
				'google_review_url'     => 'https://example.test/review',
				'qrm_reward_enabled'    => 1,
			)
		);

		$sonuc = qrm_pro_handle_review_submission( $ayarlar );

		qrms_assert_true( $sonuc['success'], 'başarı' );
		qrms_assert_same( 1, $sonuc['status'], 'eşiği geçen yorum yayınlanır' );
		qrms_assert_same( 101, $sonuc['review_id'], 'kimlik insert_id\'den' );
		qrms_assert_true( $sonuc['show_reward'], 'ödül popup\'ı açılır' );
		qrms_assert_false( get_transient( QRM_PRO_STATS_TRANSIENT ), 'önbellek geçersizlendi' );
	}
);

qrms_test(
	'AJAX ucu ile klasik POST akışı aynı fonksiyonu kullanır',
	function () {
		// Tek kod yolu: doğrulama düzeltmeleri iki akışta da geçerli olsun.
		$kaynak = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-review.php'
		);

		qrms_assert_same(
			1,
			substr_count( $kaynak, 'function qrm_pro_handle_review_submission' ),
			'tek işleyici tanımı'
		);
		qrms_assert_same(
			1,
			substr_count( $kaynak, '$wpdb->insert(' ),
			'tek yazma noktası'
		);
	}
);
$GLOBALS['wpdb'] = $qrms_gonderim_onceki_wpdb;
unset( $qrms_gonderim_onceki_wpdb );

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

qrms_test(
	'tip bazlı saklama sepeti kısaltır, siparişi uzatır, tanımsız varsayılanı kullanır',
	function () {
		delete_option( QRMS_Analitik::SAKLAMA_OPT );

		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'cart_add' ), 'sepet ekleme kısa' );
		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'cart_remove' ), 'sepet çıkarma kısa' );
		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'splash_view' ), 'açılış gösterimi kısa' );
		qrms_assert_same( 30, QRMS_Analitik::saklama_gun_tip( 'chatbot_message' ), 'chatbot orta' );
		qrms_assert_same( 30, QRMS_Analitik::saklama_gun_tip( 'splash_action' ), 'açılış eylemi orta' );
		qrms_assert_same( 180, QRMS_Analitik::saklama_gun_tip( 'review_submit' ), 'yorum uzun' );
		qrms_assert_same( 365, QRMS_Analitik::saklama_gun_tip( 'reward_issued' ), 'ödül uzun' );
		qrms_assert_same( 365, QRMS_Analitik::saklama_gun_tip( 'order_sent' ), 'sipariş uzun' );
		qrms_assert_same( 365, QRMS_Analitik::saklama_gun_tip( 'order_blocked' ), 'engel uzun' );
		qrms_assert_same( 90, QRMS_Analitik::saklama_gun_tip( 'menu_view' ), 'tanımsız varsayılan' );
		qrms_assert_same( 90, QRMS_Analitik::saklama_gun_tip( 'waiter_call' ), 'çağrı varsayılan' );

		add_filter(
			'qrms_analitik_saklama_gun_tip',
			function ( $harita ) {
				$harita['cart_add'] = 3;
				return $harita;
			}
		);

		qrms_assert_same( 3, QRMS_Analitik::saklama_gun_tip( 'cart_add' ), 'filtre tip istisnasını ezer' );
		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'cart_remove' ), 'diğer istisna durur' );

		$GLOBALS['qrms_test']['actions']['qrms_analitik_saklama_gun_tip'] = array();
		delete_option( QRMS_Analitik::SAKLAMA_OPT );
	}
);

qrms_test(
	'global temizlik kapalıyken tip istisnası da silmez',
	function () {
		add_filter(
			'qrms_analitik_saklama_gun',
			function () {
				return 0;
			}
		);

		qrms_assert_same( 0, QRMS_Analitik::saklama_gun_tip( 'cart_add' ), 'sepet de saklanır' );
		qrms_assert_same( 0, QRMS_Analitik::eski_kayitlari_sil(), 'hiçbir tip silinmez' );
	}
);

qrms_test(
	'sepet ve sipariş olay adları varchar(30) sınırının altında',
	function () {
		foreach ( QRMS_Analitik::OLAY_TIPLERI as $tip ) {
			qrms_assert_true( strlen( $tip ) <= 30, $tip . ' uzunluğu' );
		}
	}
);

qrms_test(
	'kaydet dışarıdan çağrılabilir ve tek yazım yoludur',
	function () {
		$yansima = new ReflectionMethod( 'QRMS_Analitik', 'kaydet' );
		qrms_assert_true( $yansima->isPublic(), 'kaydet public' );

		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		qrms_assert_same( 1, substr_count( $kaynak, '$wpdb->insert(' ), 'tek INSERT' );

		$yardimci = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' );
		qrms_assert_contains( 'function qmo_analitik_yaz', $yardimci, 'sessiz yazım köprüsü' );
		qrms_assert_contains( "class_exists( 'QRMS_Analitik' )", $yardimci, 'lisans/modül yoksa no-op' );
		qrms_assert_contains( 'QRMS_Analitik::kaydet', $yardimci, 'köprü kaydet kullanır' );
	}
);

echo "\nQR Analiz — Faz 8 olay yazımı\n";

qrms_test(
	'Faz 8 olay tipleri varchar(30) altında ve saklama haritasında',
	function () {
		$beklenen = array(
			'chatbot_message',
			'lang_switch',
			'splash_view',
			'splash_action',
			'gallery_view',
			'reward_issued',
			'reward_redeemed',
			'review_submit',
			'form_submit',
			'item_detail_open',
		);

		foreach ( $beklenen as $tip ) {
			qrms_assert_true( in_array( $tip, QRMS_Analitik::OLAY_TIPLERI, true ), $tip . ' listede' );
			qrms_assert_true( strlen( $tip ) <= 30, $tip . ' uzunluğu' );
		}

		qrms_assert_same( 14, QRMS_Analitik::saklama_gun_tip( 'splash_view' ), 'splash_view en kısa yeni tip' );
	}
);

qrms_test(
	'chatbot_message hız sınırından sonra yazılır, mesaj içeriği gitmez',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/ajax-chat.php' );

		$zorla = strpos( $php, 'qmo_chat_zorla' );
		$yaz   = strpos( $php, 'chatbot_message' );
		$bos   = strpos( $php, "'' === \$message" );

		qrms_assert_true( false !== $zorla && false !== $yaz && false !== $bos, 'noktalar var' );
		qrms_assert_true( $zorla < $yaz, 'yazım oturum/limitten sonra' );
		qrms_assert_true( $bos < $yaz, 'boş mesaj yazılmaz' );
		qrms_assert_contains( "'masa_no'", $php, 'masa doldurulur' );
		qrms_assert_false( false !== strpos( $php, "'item_name'     => \$message" ), 'mesaj içeriği yazılmaz' );
		qrms_assert_false( false !== strpos( $php, "'item_name' => \$message" ), 'mesaj içeriği yazılmaz (kısa)' );
	}
);

qrms_test(
	'dil değiştirici ve splash/galeri beacon üzerinden yazar',
	function () {
		$ceviri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-ceviri/assets/js/ceviri.js' );
		$splash = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );
		$galeri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-galeri/includes/trait-assets.php' );
		$beacon = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/assets/js/analitik-onyuz.js' );
		$sinif  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );

		qrms_assert_contains( "yaz( 'lang_switch'", $ceviri, 'dil seçici hedef dili yazar' );
		qrms_assert_contains( "analitikYaz('splash_view')", $splash, 'gösterim yazılır' );
		qrms_assert_contains( "splashEylem('menu')", $splash, 'menü eylemi' );
		qrms_assert_contains( "splashEylem('atla')", $splash, 'atlanma eylemi' );
		qrms_assert_contains( "splashEylem('wifi')", $splash, 'wifi eylemi' );
		qrms_assert_contains( "yaz('gallery_view')", $galeri, 'galeri açılışı' );
		qrms_assert_contains( 'qrms_analitik_onyuz', $beacon, 'beacon action' );
		qrms_assert_contains( 'keepalive: true', $beacon, 'navigasyonu kesmez' );
		qrms_assert_contains( 'function masa_onyuz', $sinif, 'masa POST\'tan okunmaz' );
		qrms_assert_contains( 'NONCE_ONYUZ', $sinif, 'ön yüz nonce' );
		qrms_assert_false( false !== strpos( $beacon, 'masa' ), 'istemci masa göndermez' );
	}
);

qrms_test(
	'ödül ve form olayları içerik yazmaz, honeypot sayılmaz',
	function () {
		$odul  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/functions.php' );
		$yorum = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-review.php' );
		$form  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-custom-form.php' );

		qrms_assert_contains( "'event_type' => 'reward_issued'", $odul, 'kod üretimi' );
		qrms_assert_contains( "'event_type' => 'reward_redeemed'", $odul, 'kod kullanımı' );
		qrms_assert_contains( "qmo_analitik_yaz(['event_type' => 'review_submit'])", $yorum, 'yorum olayı' );
		qrms_assert_contains( "'event_type' => 'form_submit'", $form, 'form olayı' );
		qrms_assert_contains( "'item_name'  => isset(\$form->title)", $form, 'form adı' );

		$yorum_yaz = strpos( $yorum, 'review_submit' );
		$yorum_ins = strpos( $yorum, '$wpdb->insert' );
		$honeypot  = strpos( $yorum, 'qrm_website' );

		qrms_assert_true( false !== $yorum_yaz && false !== $yorum_ins && false !== $honeypot, 'yorum noktaları' );
		qrms_assert_true( $honeypot < $yorum_ins, 'honeypot insertten önce çıkar' );
		qrms_assert_true( $yorum_ins < $yorum_yaz, 'yazım kayıttan sonra' );
		qrms_assert_false( false !== strpos( $yorum, '$comment' ) && false !== strpos( substr( $yorum, $yorum_yaz, 400 ), '$comment' ), 'yorum metni analitiğe gitmez' );
		qrms_assert_false( false !== strpos( $form, "validated['data']" ) && false !== strpos( substr( $form, strpos( $form, 'form_submit' ), 350 ), "validated['data']" ), 'form yanıtı analitiğe gitmez' );
	}
);

qrms_test(
	'ön yüz olay kuralları izin listesi dışını reddeder',
	function () {
		$kurallar = QRMS_Analitik::onyuz_olay_kurallari();

		qrms_assert_true( isset( $kurallar['lang_switch']['item_name'] ), 'dil listesi' );
		qrms_assert_true( in_array( 'tr', $kurallar['lang_switch']['item_name'], true ), 'tr' );
		qrms_assert_true( in_array( 'en', $kurallar['lang_switch']['item_name'], true ), 'en' );
		qrms_assert_true( in_array( 'menu', $kurallar['splash_action']['item_name'], true ), 'menu' );
		qrms_assert_true( in_array( 'atla', $kurallar['splash_action']['item_name'], true ), 'atla' );
		qrms_assert_true( in_array( 'wifi', $kurallar['splash_action']['item_name'], true ), 'wifi' );
		qrms_assert_same( 'qr-acilis-ekrani', $kurallar['splash_view']['modul'], 'splash modüle bağlı' );
		qrms_assert_same( 'qr-galeri', $kurallar['gallery_view']['modul'], 'galeri modüle bağlı' );
		qrms_assert_same( 'qr-ceviri', $kurallar['lang_switch']['modul'], 'dil modüle bağlı' );
	}
);

qrms_test(
	'olay_sayaclari idx_td aralık taraması kullanır, şemaya sütun eklemez',
	function () {
		$wpdb            = qrms_sayan_wpdb();
		$wpdb->results[] = array(
			array(
				'event_type' => 'chatbot_message',
				'item_name'  => '',
				'adet'       => 4,
			),
		);

		$sonuc = QRMS_Analitik::olay_sayaclari(
			array( 'chatbot_message', 'review_submit' ),
			'2026-03-01 00:00:00',
			'2026-03-31 23:59:59'
		);

		qrms_assert_same( 1, count( $sonuc ), 'satır' );
		qrms_assert_same( 4, $sonuc[0]['adet'], 'adet' );
		qrms_assert_contains( 'event_type IN', $wpdb->queries[0], 'IN listesi' );
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'idx_td aralığı' );
		qrms_assert_contains( 'GROUP BY event_type, item_name', $wpdb->queries[0], 'kırılım' );

		$sema = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-analiz/class-qrms-analitik.php' );
		qrms_assert_contains( "const DB_SURUM = '1.1'", $sema, 'şema sütun eklemedi' );
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

/**
 * Yorum sayaçlarının taklidi — hub ve rozet testleri için.
 *
 * qrm_pro_review_stats() istek içi memo'yu ($GLOBALS['qrm_pro_stats_memo'])
 * olduğu gibi döndürdüğü için ekranlar veritabanına hiç gitmeden beslenebilir.
 *
 * @param array $args Ezilecek alanlar.
 * @return array
 */
function qrms_sahte_yorum_stats( $args = array() ) {
	$stats = array_merge(
		array(
			'table_ok'        => true,
			'total'           => 40,
			'approved'        => 36,
			'pending'         => 4,
			'avg'             => 3.9,
			'google_eligible' => 20,
			'threshold'       => 3.5,
			'crit'            => array( 1 => 4.0, 2 => 4.0, 3 => 4.0, 4 => 4.0, 5 => 4.0 ),
		),
		$args
	);

	$stats['sentiment'] = qrm_pro_empty_sentiment_stats( qrm_pro_sentiment_threshold() );

	return $stats;
}

/**
 * Bir özet kutusunun sınıf özniteliği (etiketinden bulunur).
 *
 * @param string $html  Hub çıktısı.
 * @param string $label Kutunun etiketi.
 * @return string Bulunamazsa boş string.
 */
function qrms_yf_stat_class( $html, $label ) {
	$desen = '/<a class="([^"]+)"[^>]*>\s*<div class="qrms-hub-stat-label">'
		. preg_quote( $label, '/' ) . '</u';

	return preg_match( $desen, $html, $m ) ? $m[1] : '';
}

/**
 * Hub ekranını verilen sayaçlarla basar ve HTML'ini döndürür.
 *
 * @param array|null $stats qrms_sahte_yorum_stats() argümanları.
 * @return string
 */
function qrms_yf_hub_html( $stats = array() ) {
	$GLOBALS['qrm_pro_stats_memo'] = qrms_sahte_yorum_stats( $stats );

	ob_start();
	qrm_pro_admin_hub();

	return ob_get_clean();
}

echo "\nYorum & Feedback sayfaları\n";

qrms_test(
	'altı ekranın hepsi kayıt defterinde ve her birinin callback\'i var',
	function () {
		$pages = qrm_pro_admin_pages();

		// "Detaylı İçgörüler" (qrms-yf-icgoruler) kaldırıldı; kalan altı ekran
		// hub'daki grup sırasıyla durur.
		qrms_assert_same(
			array(
				'qrms-yf-yorumlar',
				'qrms-yf-form-alanlari',
				'qrms-yf-iletisim',
				'qrms-yf-formlar',
				'qrms-yf-ayarlar',
				'qrms-yf-odul',
			),
			array_keys( $pages ),
			'sayfa listesi'
		);

		foreach ( $pages as $slug => $page ) {
			foreach ( array( 'title', 'menu_title', 'render', 'desc', 'icon', 'group' ) as $key ) {
				qrms_assert_true( ! empty( $page[ $key ] ), $slug . ' -> ' . $key . ' dolu' );
			}

			qrms_assert_true(
				array_key_exists( $page['group'], qrm_pro_admin_page_groups() ),
				$slug . ' -> tanımlı bir gruba ait'
			);
		}

		qrms_assert_false(
			array_key_exists( 'qrms-yf-icgoruler', $pages ),
			'kaldırılan İçgörüler ekranı kayıtlı değil'
		);
		qrms_assert_false( function_exists( 'qrm_pro_admin_insights' ), 'callback de kalmadı' );
		qrms_assert_false( function_exists( 'qrm_ai_generate_summary' ), 'Gemini özeti de kalmadı' );
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
			// Kaldırılan İçgörüler ekranının iki adresi de yorum listesine düşer.
			array( 'qrm-pro-main', array( 'tab' => 'insights' ), 'qrms-yf-yorumlar', '' ),
			array( 'qrm-pro-insights', array(), 'qrms-yf-yorumlar', '' ),
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
	'rozet modülün TEK satırında toplanır — bekleyen yorum dahil',
	function () {
		// Alt satırlar kalktığı için bekleyen iş yalnızca "Yorum & Feedback"
		// satırında görünebilir. qrm_pro_menu_badge_state() istek başına bir kez
		// hesaplanıp static'te tutulduğu için sayaçlar ilk çağrıdan ÖNCE
		// sabitlenir (yorum sayaçları burada memo üzerinden verilir).
		$GLOBALS['qrm_pro_stats_memo'] = qrms_sahte_yorum_stats( array( 'pending' => 3 ) );

		$label = qrms_module_yorum_feedback_menu_label( 'Yorum & Feedback', 'yorum-feedback' );

		qrms_assert_contains( 'Yorum & Feedback', $label, 'modül adı korunur' );
		qrms_assert_contains( 'update-count', $label, 'bekleyen iş rozeti' );

		// 3 onay bekleyen yorum + 2 okunmamış gönderim (qrm_cf_unread_total taklidi).
		qrms_assert_contains( '>5<', $label, 'iki sayaç toplanır' );
		qrms_assert_contains( 'onay bekleyen yorum', $label, 'rozetin sebebi başlıkta' );

		qrms_assert_same(
			'QR Masa',
			qrms_module_yorum_feedback_menu_label( 'QR Masa', 'qr-masa' ),
			'başka modülün etiketine dokunulmaz'
		);
	}
);

qrms_test(
	'hub altı ekranı da üç başlık altında basar',
	function () {
		$html = qrms_yf_hub_html();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'ortak kart ızgarası' );
		qrms_assert_contains( 'qrms-hub-stats', $html, 'özet kutuları' );

		foreach ( qrm_pro_admin_pages() as $slug => $page ) {
			qrms_assert_contains( 'page=' . $slug, $html, $slug . ' kartı' );
		}

		// Üç grup başlığı, kayıt defterindeki sırayla.
		$sira = array();
		foreach ( qrm_pro_admin_page_groups() as $baslik ) {
			$konum = strpos( $html, '>' . $baslik . '<' );
			qrms_assert_true( false !== $konum, $baslik . ' başlığı basılır' );
			$sira[] = $konum;
		}

		$sirali = $sira;
		sort( $sirali );
		qrms_assert_same( $sirali, $sira, 'başlık sırası: Yorumlar, Formlar, Ayarlar' );

		// Karışan iki ad netleştirildi.
		qrms_assert_contains( 'İletişim Formu', $html, 'iletişim kartının tam adı' );
		qrms_assert_contains( 'Özel Formlar', $html, 'özel formlar kartının tam adı' );
		qrms_assert_false( false !== strpos( $html, 'İçgörüler' ), 'kaldırılan kart yok' );
	}
);

qrms_test(
	'dört özet kutusunun dördü de filtrelenmiş listeye gider',
	function () {
		$html = qrms_yf_hub_html( array( 'pending' => 4, 'total' => 40, 'approved' => 36 ) );

		// Kutular <a> olarak basılır: sayıyı gören, kayıtlara da tıklayarak gider.
		qrms_assert_same( 4, substr_count( $html, 'qrms-hub-stat-label' ), 'dört kutu' );
		qrms_assert_same( 4, substr_count( $html, '<a class="qrms-hub-stat' ), 'dördü de bağlantı' );

		qrms_assert_contains( 'page=qrms-yf-yorumlar&amp;durum=bekleyen', $html, 'onay bekleyen -> bekleyen filtresi' );
		qrms_assert_contains( 'page=qrms-yf-yorumlar&amp;durum=onayli', $html, 'genel ortalama -> yayındakiler' );
		qrms_assert_contains( 'page=qrms-yf-formlar&amp;tab=submissions', $html, 'okunmamış gönderim -> gönderiler' );

		// Bekleyen iş varken kutu vurgulanır.
		qrms_assert_contains(
			'qrms-hub-stat-alert',
			qrms_yf_stat_class( $html, 'Onay Bekleyen' ),
			'onay bekleyen kutusu vurgulanır'
		);
		qrms_assert_contains( '3.9 ★', $html, 'ortalama basılır' );
	}
);

qrms_test(
	'bekleyen yorum yokken kutu vurgulanmaz, puan yokken "—" yazmaz',
	function () {
		$html = qrms_yf_hub_html( array( 'pending' => 0, 'total' => 0, 'approved' => 0 ) );

		qrms_assert_false(
			false !== strpos( qrms_yf_stat_class( $html, 'Onay Bekleyen' ), 'qrms-hub-stat-alert' ),
			'vurgu yok'
		);

		// Boş ortalamanın "—" hâli hiçbir şey anlatmıyordu.
		qrms_assert_contains( 'Henüz puan yok', $html, 'boş ortalama açıklanır' );
		qrms_assert_false(
			false !== strpos( $html, '<span class="qrms-stat-value">—</span>' ),
			'kutuda tire kalmadı'
		);
	}
);

qrms_test(
	'hiç yorum yokken kartların üstünde kısa kod yönlendirmesi çıkar',
	function () {
		$bos = qrms_yf_hub_html( array( 'pending' => 0, 'total' => 0, 'approved' => 0 ) );

		qrms_assert_contains( 'qrms-hub-hint', $bos, 'yönlendirme basılır' );
		qrms_assert_contains( '[qr_menu_reviews]', $bos, 'kısa kod' );
		qrms_assert_contains( 'data-qrms-copy=', $bos, 'kopyalama butonu (ortak admin.js)' );
		qrms_assert_contains( 'QR kodunuzu', $bos, 'QR kodun paylaşılması gerektiği söylenir' );

		// Kartların ÜSTÜNDE: ilk kart ızgarasından önce gelir.
		qrms_assert_true(
			strpos( $bos, 'qrms-hub-hint' ) < strpos( $bos, 'qrms-hub-grid' ),
			'kartlardan önce'
		);

		// Tek yorum bile gelmişse yönlendirme kaybolur.
		$dolu = qrms_yf_hub_html( array( 'total' => 1, 'approved' => 1, 'pending' => 0 ) );

		qrms_assert_false( false !== strpos( $dolu, 'qrms-hub-hint' ), 'yorum gelince kaybolur' );
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
	'ayar ekranları gizli alt sayfa olarak kaydedilir, menüde satırları olmaz',
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
	'hub tüm ekranları kart olarak basar ve ikonları dashicon\'dur',
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
		qrms_assert_same( array( 'instagram' ), $opts['social_media_active'], 'sosyal hesap Ayarlar kaydında korunur' );
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
			'qrms-ae-sosyal',
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
	'iki dil de aynı HTML\'de taşınır; çeviri boşsa katalogdan tamamlanır',
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
		// Çevirisi girilmemiş metin i18n kataloğundaki karşılığına düşer;
		// katalogda da yoksa Türkçesi basılır.
		qrms_assert_contains( 'data-sp-en="Follow us"', $html, 'boş çeviri katalogdan tamamlanır' );
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
		qrms_assert_false( strpos( $html, 'name="text_en_btn5"' ), 'İngilizce alanı yok' );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'Menüye Git', $opts['button_texts']['btn1'], 'kayıtlı değer ezilmez' );
		qrms_assert_same( 'Wifi Şifresi', $opts['button_texts']['btn5'], 'eksik anahtar varsayılandan' );
		qrms_assert_same( 4, count( $opts['footer_icons'] ), 'sayısal liste büyümez' );
	}
);

qrms_test(
	'yönetim: İngilizce çeviri alanları kaldırıldı, düğme anahtarı durur',
	function () {
		update_option(
			'splash_screen_options',
			array(
				'texts_en' => array( 'btn1' => 'View Menu', 'divider' => 'Follow us' ),
			)
		);

		$html = qrms_ae_submit(
			'qrms-ae-butonlar',
			array(
				'lang_toggle'     => '1',
				'button_text_1'   => 'Menüye Git',
				'text_en_btn1'    => 'Should Not Save',
				'text_en_divider' => 'Should Not Save Either',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 1, $opts['lang_toggle'], 'düğme açıldı' );
		qrms_assert_same( 'View Menu', $opts['texts_en']['btn1'], 'eski çeviri ezilmez' );
		qrms_assert_same( 'Follow us', $opts['texts_en']['divider'], 'eski ayraç çevirisi durur' );
		qrms_assert_false( strpos( $html, 'name="text_en_btn1"' ), 'buton İngilizce alanı yok' );
		qrms_assert_false( strpos( $html, 'name="text_en_divider"' ), 'ayraç İngilizce alanı yok' );
		qrms_assert_false( strpos( $html, 'Buton yazısı (English)' ), 'İngilizce etiket yok' );
		qrms_assert_contains( 'name="lang_toggle"', $html, 'düğme anahtarı basılır' );
		qrms_assert_contains( 'name="button_text_1"', $html, 'Türkçe yazı alanı durur' );

		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'x' ) );

		qrms_assert_same( 1, get_option( 'splash_screen_options' )['lang_toggle'], 'başka sayfa dili kapatmaz' );
	}
);

qrms_test(
	'Ayarlar ve Sosyal Medya Bağlantısı ayrı sayfalardır',
	function () {
		$pages = qrms_ae()->admin_pages();

		qrms_assert_true( isset( $pages['qrms-ae-davranis'] ), 'Ayarlar slug durur' );
		qrms_assert_same( 'Ayarlar', $pages['qrms-ae-davranis']['title'], 'sekme adı Ayarlar' );
		qrms_assert_false( strpos( $pages['qrms-ae-davranis']['title'], 'Sosyal' ), 'Ayarlar adında Sosyal yok' );

		qrms_assert_true( isset( $pages['qrms-ae-sosyal'] ), 'sosyal sayfa kayıtlı' );
		qrms_assert_same( 'Sosyal Medya Bağlantısı', $pages['qrms-ae-sosyal']['title'], 'yeni sekme adı' );

		$ayarlar = qrms_ae_submit( 'qrms-ae-davranis', array() );
		$sosyal  = qrms_ae_submit( 'qrms-ae-sosyal', array() );

		qrms_assert_contains( 'name="dismiss_duration"', $ayarlar, 'süre Ayarlar\'da' );
		qrms_assert_contains( 'name="wifi_password"', $ayarlar, 'wifi Ayarlar\'da' );
		qrms_assert_false( strpos( $ayarlar, 'name="social_media_active[]"' ), 'sosyal kutu Ayarlar\'da yok' );

		qrms_assert_contains( 'name="social_media_active[]"', $sosyal, 'sosyal kutu yeni sayfada' );
		qrms_assert_false( strpos( $sosyal, 'name="wifi_password"' ), 'wifi sosyal sayfada yok' );
	}
);

qrms_test(
	'sosyal sayfayı kaydetmek wifi şifresini silmez, Ayarlar sosyal seçimi silmez',
	function () {
		update_option(
			'splash_screen_options',
			array(
				'wifi_password'       => 'misafir123',
				'social_media_active' => array( 'instagram' ),
				'social_media'        => array( 'instagram' => 'https://instagram.com/x' ),
			)
		);

		qrms_ae_submit( 'qrms-ae-sosyal', array( 'social_media_active' => array( 'facebook' ) ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'misafir123', $opts['wifi_password'], 'wifi durur' );
		qrms_assert_same( array( 'facebook' ), $opts['social_media_active'], 'sosyal sahibi sayfada yazılır' );

		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'yeni-sifre' ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 'yeni-sifre', $opts['wifi_password'], 'wifi Ayarlar\'da yazılır' );
		qrms_assert_same( array( 'facebook' ), $opts['social_media_active'], 'sosyal Ayarlar kaydında durur' );
	}
);

qrms_test(
	'bayrak boyutu CSS değişkenine basılır, buton padding anahtarı silinir',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;

		update_option(
			'splash_screen_options',
			array(
				'button_padding' => '14px 28px',
			)
		);

		qrms_ae_submit(
			'qrms-ae-gorunum',
			array(
				'ceviri_flag_size' => '40',
			)
		);

		ob_start();
		qrms_ae()->print_critical_head();
		$html = ob_get_clean();

		qrms_assert_contains( '--sp-flag-size: 40px', $html, 'bayrak boyutu CSS\'e basılır' );
		qrms_assert_false( isset( get_option( 'splash_screen_options' )['button_padding'] ), 'eski padding anahtarı silinir' );
		qrms_assert_false( strpos( $html, '--sp-cta-pad' ), 'CTA padding değişkeni basılmaz' );
	}
);

qrms_test(
	'tekrar göstermeme süresi 0 iken çerez yazılmaz, kontrol atlanır',
	function () {
		$js   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );
		$head = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/includes/frontend.php' );

		qrms_assert_contains( 'if (dismissMinutes > 0)', $js, 'çerez yalnızca süre > 0 iken yazılır' );
		qrms_assert_contains( 'else if (hasDismissCookie())', $js, '0 iken çerez kontrolü atlanır' );
		qrms_assert_contains( 'dismissMinutes === 0', $head, 'kritik head 0\'ı ayrı ele alır' );

		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'splash_screen_options', array( 'dismiss_duration' => 0 ) );

		ob_start();
		qrms_ae()->print_critical_head();
		$html = ob_get_clean();

		qrms_assert_contains( 'var dismissMinutes = 0;', $html, 'süre head betiğine basılır' );
		qrms_assert_contains( 'splash-loading', $html, '0 iken splash yine gösterilir' );
	}
);


/* ---------------------------------------------------------------------------
 * 15b. Açılış Ekranı — QR Çeviri bayrak seçici
 * ------------------------------------------------------------------------ */

echo "\nAçılış Ekranı (QR Çeviri seçici)\n";

/**
 * Testlerde QR Çeviri fonksiyonlarının ince bir taklidi.
 *
 * ceviri.php yüklenmez (aktivasyon hook'u ve tablo katmanı stub'larda yok);
 * splash yalnızca dil listesini ve aktif dil kodlarını sorduğu için bu yeter.
 *
 * @return void
 */
function qrms_ae_stub_ceviri() {
	if ( ! function_exists( 'qrmenu_get_langs' ) ) {
		/**
		 * @return array<string,array{name:string,flag:string}>
		 */
		function qrmenu_get_langs() {
			return array(
				'tr' => array( 'name' => 'Türkçe', 'flag' => 'TR' ),
				'en' => array( 'name' => 'English', 'flag' => 'EN' ),
				'de' => array( 'name' => 'Deutsch', 'flag' => 'DE' ),
			);
		}
	}
	if ( ! function_exists( 'rma_ceviri_aktif_diller' ) ) {
		/**
		 * @return string[]
		 */
		function rma_ceviri_aktif_diller() {
			$aktif = get_option( 'qrmenu_active_langs', array( 'tr', 'en', 'de' ) );
			return is_array( $aktif ) ? $aktif : array( 'tr', 'en', 'de' );
		}
	}
}

qrms_test(
	'seçici kapalıyken bayrak markup\'a hiç girmez',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 0,
				'ceviri_selector_langs' => array( 'tr', 'en' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_false( strpos( $html, 'splash-ceviri' ), 'seçici yok' );
	}
);

qrms_test(
	'QR Çeviri kapalıyken seçici açık olsa da basılmaz',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();

		qrms_assert_false( strpos( ob_get_clean(), 'splash-ceviri' ), 'modül yokken basılmaz' );
	}
);

qrms_test(
	'açık ama hiç dil seçilmemişse yine basılmaz',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array(),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();

		qrms_assert_false( strpos( ob_get_clean(), 'splash-ceviri' ), 'dil yokken basılmaz' );
	}
);

qrms_test(
	'açıkken yalnızca işaretli diller basılır, çıktı çerezden bağımsızdır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en', 'xx' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();
		$cerezsiz = ob_get_clean();

		$_COOKIE['rma_lang'] = 'en';
		$_GET['lang']        = 'en';

		ob_start();
		qrms_ae()->handle_frontend();
		$cerezli = ob_get_clean();

		unset( $_COOKIE['rma_lang'], $_GET['lang'] );

		qrms_assert_same( $cerezsiz, $cerezli, 'çıktı her ziyaretçide aynı' );
		qrms_assert_contains( 'splash-ceviri', $cerezsiz, 'seçici basılır' );
		qrms_assert_contains( 'data-lang="tr"', $cerezsiz, 'Türkçe seçenek' );
		qrms_assert_contains( 'data-lang="en"', $cerezsiz, 'İngilizce seçenek' );
		qrms_assert_false( strpos( $cerezsiz, 'data-lang="xx"' ), 'QR Çeviri dışı kod düşer' );
		qrms_assert_false( strpos( $cerezsiz, 'data-lang="de"' ), 'işaretsiz dil basılmaz' );
		qrms_assert_contains( 'data-cookie="rma_lang"', $cerezsiz, 'QR Çeviri çerezi' );
	}
);

qrms_test(
	'QR Çeviri bir dili kapatırsa splash seçeneği de düşer',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option( 'qrmenu_active_langs', array( 'tr', 'en' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en', 'de' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'data-lang="en"', $html, 'açık dil kalır' );
		qrms_assert_false( strpos( $html, 'data-lang="de"' ), 'QR Çeviri\'de kapalı dil düşer' );

		update_option( 'qrmenu_active_langs', array( 'tr', 'en', 'de' ) );
	}
);

qrms_test(
	'önizlemede seçici çalışır; JS dinleyiciyi elemente bağlar',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->render_splash_preview();
		$html = ob_get_clean();

		qrms_assert_contains( 'splash-ceviri', $html, 'seçici önizlemede de var' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );

		qrms_assert_contains( 'if (!root) return noopCeviri()', $js, 'element yoksa dinleyici yok' );
		qrms_assert_contains( 'qrmenuTranslate', $js, 'QR Çeviri fonksiyonuna bağlanır' );
		qrms_assert_contains( 'rma_dil', $js, 'sessionStorage anahtarı QR Çeviri ile aynı' );
	}
);

qrms_test(
	'yönetim: Dil Seçici Görünüm sayfasındadır, başka sayfa kapatmaz',
	function () {
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		qrms_ae_stub_ceviri();

		$html = qrms_ae_submit(
			'qrms-ae-gorunum',
			array(
				'ceviri_selector'       => '1',
				'ceviri_selector_langs' => array( 'tr', 'en', 'de', 'xx' ),
				'bg_color'              => '#101010',
			)
		);

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 1, $opts['ceviri_selector'], 'seçici açıldı' );
		qrms_assert_same( array( 'tr', 'en', 'de' ), $opts['ceviri_selector_langs'], 'geçerli diller kalır, xx düşer' );
		qrms_assert_contains( 'name="ceviri_selector"', $html, 'anahtar basılır' );
		qrms_assert_contains( 'name="ceviri_selector_langs[]"', $html, 'dil listesi basılır' );

		qrms_ae_submit( 'qrms-ae-davranis', array( 'wifi_password' => 'x' ) );

		$opts = get_option( 'splash_screen_options' );

		qrms_assert_same( 1, $opts['ceviri_selector'], 'başka sayfa seçiciyi kapatmaz' );
		qrms_assert_same( array( 'tr', 'en', 'de' ), $opts['ceviri_selector_langs'], 'dil listesi korunur' );

		qrms_ae_submit( 'qrms-ae-gorunum', array( 'bg_color' => '#202020' ) );

		qrms_assert_same( 0, get_option( 'splash_screen_options' )['ceviri_selector'], 'sahibi sayfa kutuyu kapatır' );
	}
);

qrms_test(
	'bayrak seçici açıkken splash metinleri tüm dillerde taşınır',
	function () {
		$GLOBALS['qrms_test']['is_front_page'] = true;
		update_option( 'qrms_active_modules', array( 'qr-acilis-ekrani', 'qr-ceviri' ) );
		update_option(
			'splash_screen_options',
			array(
				'ceviri_selector'       => 1,
				'ceviri_selector_langs' => array( 'tr', 'en', 'de' ),
				'button_texts'          => array( 'btn1' => 'Menüye Git', 'btn2' => 'İletişim' ),
				'button_links'          => array( 'btn1' => 'https://restoran.test/menu', 'btn2' => 'tel:+900' ),
				'divider_text'          => 'Bizi takip edin',
				'social_media_active'   => array( 'instagram' ),
				'social_media'          => array( 'instagram' => 'https://instagram.com/x' ),
			)
		);

		qrms_ae_stub_ceviri();

		ob_start();
		qrms_ae()->handle_frontend();
		$html = ob_get_clean();

		qrms_assert_contains( 'data-sp-tr="Menüye Git"', $html, 'Türkçe CTA' );
		qrms_assert_contains( 'data-sp-en="View Menu"', $html, 'İngilizce CTA' );
		qrms_assert_contains( 'data-sp-de="Zum Menü"', $html, 'Almanca CTA' );
		qrms_assert_contains( 'data-sp-de="Kontakt"', $html, 'Almanca rozet' );
		qrms_assert_contains( 'data-sp-de="Folgen Sie uns"', $html, 'Almanca ayraç' );
		qrms_assert_contains( 'data-sp-lang-select-de="Sprache wählen (%s)"', $html, 'Almanca bayrak etiketi' );
		qrms_assert_contains( '>Menüye Git</a>', $html, 'sunucu yine Türkçeyi basar' );
	}
);

qrms_test(
	'bayrak seçici ile dil çevirisi istemcide uygulanır',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-acilis-ekrani/assets/js/splash.js' );

		qrms_assert_contains( 'applySplashLang(overlay, lang)', $js, 'seçimde metin güncellenir' );
		qrms_assert_contains( 'data-sp-lang-select-', $js, 'bayrak etiketi şablonu okunur' );
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

		qrms_assert_contains( 'var(--qrms-cs-today, #c9a84c)', $css, 'vurgu rengi geri düşüş' );
		qrms_assert_contains( 'var(--qrms-cs-divider, rgba(255, 255, 255, 0.14))', $css, 'ayraç rengi geri düşüş' );
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

qrms_test(
	'ön yüzde kısa kod Elementor boxed kapsayıcıyı 100vw ile ezer',
	function () {
		$html = qrms_cs_shortcode( array() );

		qrms_assert_contains( 'qrms-cs--full', $html, 'full width sınıfı' );
		qrms_assert_contains( 'qrms-cs-inner', $html, 'içerik ortalanır' );
		qrms_assert_contains( 'qrms-cs-card', $html, 'kart durur' );
		qrms_assert_contains( 'qrms-cs-today-tag', $html, 'Bugün etiketi' );

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/assets/css/frontend.css' );

		qrms_assert_contains( 'width: 100vw', $css, 'viewport genişliği' );
		qrms_assert_contains( 'calc(50% - 50vw)', $css, 'kırılım hesabı' );
		qrms_assert_contains( 'overflow-x: clip', $css, 'yatay kaydırma yok' );
		qrms_assert_contains( '.elementor-widget:has(.qrms-cs--full)', $css, 'Elementor widget padding ezmesi' );
		qrms_assert_contains( '.e-con:has(.qrms-cs--full)', $css, 'Elementor container overflow ezmesi' );
		qrms_assert_contains( 'flex-wrap: nowrap', $css, 'gün ve saat tek satır' );
		qrms_assert_contains( 'max-width: 1100px', $css, 'içerik ortada kaplanır' );
	}
);

qrms_test(
	'fullwidth=0 dar sütunda kırılımı kapatır, kart aynı kalır',
	function () {
		$html = qrms_cs_shortcode( array( 'fullwidth' => '0' ) );

		qrms_assert_false( false !== strpos( $html, 'qrms-cs--full' ), 'sınıf yok' );
		qrms_assert_contains( 'class="qrms-cs"', $html, 'sarmalayıcı durur' );
		qrms_assert_contains( 'qrms-cs-card', $html, 'kart durur' );
		qrms_assert_contains( 'qrms-cs-inner', $html, 'iç sarmalayıcı durur' );
	}
);

qrms_test(
	'yönetim önizlemesi full-bleed kırılımı almaz',
	function () {
		$GLOBALS['qrms_test']['is_admin'] = true;

		$html = qrms_cs_shortcode( array() );

		qrms_assert_false( false !== strpos( $html, 'qrms-cs--full' ), 'admin kırılmaz' );
		qrms_assert_contains( 'qrms-cs-card', $html, 'önizleme kartı' );
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
		qrms_assert_contains( 'order_blocked', $siparis, 'engel analitiği' );
		qrms_assert_contains( 'order_sent', $siparis, 'başarılı sipariş olayı' );
		qrms_assert_contains( 'order_failed', $siparis, 'başarısız sipariş olayı' );
		qrms_assert_contains( 'qmo_analitik_siparis_yaz', $siparis, 'sipariş analitik yazımı' );

		$cagri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-waiter-bill.php' );
		$hiz   = strpos( $cagri, 'qmo_hiz_siniri' );
		$yaz   = strpos( $cagri, 'qmo_analitik_yaz' );
		$birak = strpos( $cagri, 'qmo_db_serbest_birak' );
		qrms_assert_true( false !== $hiz && false !== $yaz && false !== $birak, 'çağrı analitik noktaları var' );
		qrms_assert_true( $hiz < $yaz, 'analitik hız sınırından sonra' );
		qrms_assert_true( $yaz < $birak, 'analitik bağlantı bırakılmadan önce' );
		qrms_assert_contains( 'waiter_call', $cagri, 'garson olayı' );
		qrms_assert_contains( 'bill_request', $cagri, 'hesap olayı' );

		$menu = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		qrms_assert_contains( "add_filter( 'qmo_siparis_onay_oncesi'", $menu, 'menü bağlar' );

		$json = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/admin/admin-sayfa.php' );
		qrms_assert_contains( "'tukendi'", $json, 'menü JSON alanı' );

		qrms_assert_same( null, RMA_Tukendi::siparis_engeli( array() ), 'boş sipariş' );
		qrms_assert_same( 'önceki', RMA_Tukendi::siparis_filtresi( 'önceki', array() ), 'önceki engel korunur' );
		qrms_assert_true( method_exists( 'RMA_Tukendi', 'siparis_engeli_detay' ), 'yapısal engel ayrıntısı' );
		qrms_assert_true( method_exists( 'RMA_Tukendi', 'ad_tukendi_urun' ), 'engelleyen ürün kimliği' );
	}
);

echo "\nÜrünüm Yok — elle kapatılanlar listesi\n";

qrms_test(
	'eksik özet elle kapatılan ürün id\'lerini de tutar, ikinci sorgu yok',
	function () {
		$stok = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/urunum-yok/class-stock.php' );

		qrms_assert_contains( "'elle_ids'   => []", $stok, 'elle_ids kovası' );
		qrms_assert_contains( "\$ozet['elle_ids'][] = (int) \$id", $stok, 'elle id aynı döngüde eklenir' );
		qrms_assert_contains( "\$ozet['elle']++", $stok, 'elle sayacı durur' );

		// Tek get_posts: hem malzeme kırılımı hem elle id listesi aynı taramadan.
		qrms_assert_same( 1, substr_count( $stok, "function qmo_urunum_yok_eksik_ozet" ), 'tek özet fonksiyonu' );
	}
);

qrms_test(
	'Ürünüm Yok sayfası elle kapatılanları malzeme listesinin üstünde basar',
	function () {
		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/urunum-yok/trait-admin.php' );

		qrms_assert_contains( 'render_urunum_yok_elle_liste', $admin, 'elle liste metodu' );
		qrms_assert_contains( 'Elle Kapatılan Ürünler', $admin, 'bölüm başlığı' );
		qrms_assert_contains( 'Elle kapatılan ürün yok.', $admin, 'boş durum mesajı' );
		qrms_assert_contains( 'Tekrar Aktif Et', $admin, 'geri alma butonu' );
		qrms_assert_contains( 'qmo_urunum_yok_eksik_ozet', $admin, 'aynı özet kaynağı' );
		qrms_assert_contains( "\$ozet['elle_ids']", $admin, 'id listesi özettendir' );
		qrms_assert_contains( 'qmo_uy_aktiflestir', $admin, 'mevcut aktifleştirme ucu' );
		qrms_assert_contains( 'qmo_uy_reactivate_', $admin, 'mevcut nonce' );
		qrms_assert_contains( '$limit = 50', $admin, 'sayfalama limiti' );
		qrms_assert_contains( "edit.php?post_type=rma_menu_item", $admin, 'Ürünlerim devam linki' );
		qrms_assert_contains( 'widefat striped', $admin, 'malzeme listesiyle aynı tablo' );
		qrms_assert_contains( 'tbody tr:hover', file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' ), 'satır hover' );

		$elle  = strpos( $admin, 'function render_urunum_yok_elle_liste' );
		$malz  = strpos( $admin, 'function render_urunum_yok_aktif_liste' );
		$cagri_elle = strpos( $admin, '$this->render_urunum_yok_elle_liste();' );
		$cagri_malz = strpos( $admin, '$this->render_urunum_yok_aktif_liste();' );

		qrms_assert_true( false !== $elle && false !== $malz, 'iki render metodu da var' );
		qrms_assert_true( $cagri_elle < $cagri_malz, 'elle liste malzeme listesinin üstünde çağrılır' );
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
			'positive_total'    => 28,
			'positive_approved' => 24,
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

		// Sekme sayaçları (olumlu/olumsuz) da AYNI taramadan gelir; sekmeye
		// tıklamak ekstra bir COUNT sorgusu açmamalı.
		qrms_assert_contains( 'AS positive_total', $sql, 'olumlu sayacı aynı sorguda' );
		qrms_assert_contains( 'AS positive_approved', $sql, 'olumlu/yayında sayacı aynı sorguda' );

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

echo "\nYönetimdeki yorum listesi — üç sekme\n";

qrms_test(
	'olumlu/olumsuz eşiği TEK yerden gelir ve filtreyle değişir',
	function () {
		qrms_assert_same( 3.0, qrm_pro_sentiment_threshold(), 'varsayılan eşik' );
		qrms_assert_same( 3.0, (float) QRM_PRO_SENTIMENT_THRESHOLD, 'sabit ile aynı' );

		add_filter(
			'qrm_pro_sentiment_threshold',
			function () {
				return 4.0;
			}
		);

		qrms_assert_same( 4.0, qrm_pro_sentiment_threshold(), 'filtre geçerli' );

		// Aralık dışı bir değer sorguya sızmamalı.
		unset( $GLOBALS['qrms_test']['actions']['qrm_pro_sentiment_threshold'] );
		add_filter(
			'qrm_pro_sentiment_threshold',
			function () {
				return 99;
			}
		);

		qrms_assert_same( 5.0, qrm_pro_sentiment_threshold(), '0-5 aralığına sıkışır' );

		unset( $GLOBALS['qrms_test']['actions']['qrm_pro_sentiment_threshold'] );
	}
);

qrms_test(
	'bilinmeyen sekme değeri sorguya sızmaz',
	function () {
		qrms_assert_same( '', qrm_pro_admin_review_tab( '' ), 'boş -> tümü' );
		qrms_assert_same( 'olumlu', qrm_pro_admin_review_tab( 'olumlu' ), 'olumlu' );
		qrms_assert_same( 'olumsuz', qrm_pro_admin_review_tab( 'olumsuz' ), 'olumsuz' );
		qrms_assert_same( '', qrm_pro_admin_review_tab( 'notr' ), 'bilinmeyen -> tümü' );
		qrms_assert_same( '', qrm_pro_admin_review_tab( '1 OR 1=1' ), 'enjeksiyon denemesi -> tümü' );
		qrms_assert_same( '', qrm_pro_admin_review_tab( array( 'olumlu' ) ), 'dizi -> tümü' );

		// Nötr kategori yok: her yorum iki sekmeden birine düşer.
		qrms_assert_same(
			array( '', 'olumlu', 'olumsuz' ),
			array_keys( qrm_pro_admin_review_tabs() ),
			'üç sekme'
		);
	}
);

qrms_test(
	'sekme filtresi SQL tarafında uygulanır',
	function () {
		// Asıl mesele bu: tablonun tamamı PHP'ye çekilip orada elenirse, çok
		// yorumlu bir sitede sayfa açılmaz olur.
		$wpdb = qrms_sayan_wpdb();

		qrm_pro_admin_fetch_reviews( '', 25, 1, 'olumlu', 3.0 );
		qrms_assert_contains( 'WHERE rating >= 3', $wpdb->queries[0], 'olumlu koşulu SQL\'de' );
		qrms_assert_contains( 'LIMIT 25 OFFSET 0', $wpdb->queries[0], 'sayfalama duruyor' );

		qrm_pro_admin_fetch_reviews( '', 25, 2, 'olumsuz', 3.0 );
		qrms_assert_contains( 'WHERE rating < 3', $wpdb->queries[1], 'olumsuz koşulu SQL\'de' );
		qrms_assert_contains( 'LIMIT 25 OFFSET 25', $wpdb->queries[1], 'ikinci sayfa' );

		// Sekme ve durum filtresi birlikte de tek sorguda birleşir.
		qrm_pro_admin_fetch_reviews( 'bekleyen', 10, 1, 'olumsuz', 3.0 );
		qrms_assert_contains( 'WHERE status = 0 AND rating < 3', $wpdb->queries[2], 'iki filtre birlikte' );

		// Eşik filtreden geliyorsa da sorguya o değer girer.
		qrm_pro_admin_fetch_reviews( '', 10, 1, 'olumlu', 4.5 );
		qrms_assert_contains( 'rating >= 4.5', $wpdb->queries[3], 'eşik değeri yerine kondu' );
	}
);

qrms_test(
	'sekme sayaçları ve sayfalama toplamı EK SORGU açmaz',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();

		$stats = qrm_pro_review_stats( true );

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );

		// 40 kayıt, 28'i olumlu -> 12'si olumsuz; nötr kova yok.
		qrms_assert_same( 28, $stats['sentiment']['olumlu']['total'], 'olumlu toplam' );
		qrms_assert_same( 12, $stats['sentiment']['olumsuz']['total'], 'olumsuz toplam' );
		qrms_assert_same(
			$stats['total'],
			$stats['sentiment']['olumlu']['total'] + $stats['sentiment']['olumsuz']['total'],
			'iki sekme toplamı = tüm yorumlar'
		);

		// 30 yayında, 24'ü olumlu -> olumsuz yayında 6, olumsuz bekleyen 6.
		qrms_assert_same( 24, $stats['sentiment']['olumlu']['approved'], 'olumlu yayında' );
		qrms_assert_same( 4, $stats['sentiment']['olumlu']['pending'], 'olumlu bekleyen' );
		qrms_assert_same( 6, $stats['sentiment']['olumsuz']['approved'], 'olumsuz yayında' );
		qrms_assert_same( 6, $stats['sentiment']['olumsuz']['pending'], 'olumsuz bekleyen' );

		// Sayfalama toplamı sekme + durum kombinasyonunda da aynı diziden okunur.
		qrms_assert_same( 28, qrm_pro_admin_reviews_total( '', $stats, 'olumlu' ), 'olumlu / tümü' );
		qrms_assert_same( 6, qrm_pro_admin_reviews_total( 'bekleyen', $stats, 'olumsuz' ), 'olumsuz / bekleyen' );
		qrms_assert_same( 40, qrm_pro_admin_reviews_total( '', $stats ), 'sekmesiz davranış korunur' );

		qrms_assert_same( 1, count( $wpdb->queries ), 'sayaçlar için ek sorgu yok' );
	}
);

qrms_test(
	'olumlu/olumsuz eşiği değişince saklanan sayaçlar kabul edilmez',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = qrms_sahte_stat_satiri();
		$wpdb->rows[] = qrms_sahte_stat_satiri( array( 'positive_total' => 9 ) );

		qrms_assert_same( 28, qrm_pro_review_stats( true )['sentiment']['olumlu']['total'], 'eşik 3.0' );

		unset( $GLOBALS['qrm_pro_stats_memo'] );
		add_filter(
			'qrm_pro_sentiment_threshold',
			function () {
				return 4.0;
			}
		);

		qrms_assert_same( 9, qrm_pro_review_stats()['sentiment']['olumlu']['total'], 'eşik 4.0 ile yeniden sorulur' );

		unset( $GLOBALS['qrms_test']['actions']['qrm_pro_sentiment_threshold'] );
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
	'keyfi aralık özeti TEK indeksli sorguya iner',
	function () {
		$wpdb = qrms_sayan_wpdb();
		$wpdb->rows[] = array(
			'mv'          => 10,
			'pc'          => 4,
			'uv'          => 7,
			'masa_sayisi' => 3,
			'mv_onceki'   => 8,
			'pc_onceki'   => 2,
			'uv_onceki'   => 6,
		);

		$ozet = QRMS_Analitik::aralik_ozeti(
			'2026-03-10 00:00:00',
			'2026-03-16 23:59:59',
			'2026-03-03 00:00:00'
		);

		qrms_assert_same( 1, count( $wpdb->queries ), 'tek sorgu' );
		qrms_assert_same( 10, $ozet['mv'], 'görüntüleme' );
		qrms_assert_same( 8, $ozet['mv_onceki'], 'önceki pencere' );

		// Pencere İKİ UÇTAN sınırlı olmalı: aksi hâlde idx_date bir aralık
		// taraması olarak kullanılamaz ve tablo baştan sona taranır.
		qrms_assert_contains( 'WHERE created_at BETWEEN', $wpdb->queries[0], 'kapalı aralık' );
		qrms_assert_contains( '2026-03-03 00:00:00', $wpdb->queries[0], 'alt sınır önceki pencereden' );
		qrms_assert_contains( '2026-03-16 23:59:59', $wpdb->queries[0], 'üst sınır' );

		// Şimdiki/önceki ayrımı WHERE'de değil SUM/CASE içinde yapılır.
		qrms_assert_contains( "SUM(event_type='menu_view'     AND created_at >=", $wpdb->queries[0], 'kova koşulu' );
	}
);

qrms_test(
	'aralık özetinde kayıt yokken NULL toplamlar sıfıra iner',
	function () {
		$wpdb         = qrms_sayan_wpdb();
		$wpdb->rows[] = array(
			'mv'          => null,
			'pc'          => null,
			'uv'          => null,
			'masa_sayisi' => null,
			'mv_onceki'   => null,
			'pc_onceki'   => null,
			'uv_onceki'   => null,
		);

		foreach ( QRMS_Analitik::aralik_ozeti( 'a', 'b', 'c' ) as $anahtar => $deger ) {
			qrms_assert_same( 0, $deger, $anahtar . ' sıfır' );
		}
	}
);

qrms_test(
	'grafik keyfi aralıkta da sıfır doldurur ve indeksli kalır',
	function () {
		$wpdb = qrms_sayan_wpdb();
		// Yalnızca bir günde veri var; kalan günler sıfırla dolmalı.
		$wpdb->results[] = array(
			array(
				'k'  => '2026-03-11',
				'mv' => 5,
				'pc' => 2,
				'uv' => 3,
			),
		);

		$satirlar = QRMS_Analitik::grafik_araligi( 'daily', '2026-03-10 00:00:00', '2026-03-12 23:59:59' );

		qrms_assert_same( 3, count( $satirlar ), 'üç günün üçü de var' );
		qrms_assert_same( 0, $satirlar[0]['mv'], 'sessiz gün sıfırla dolar' );
		qrms_assert_same( 5, $satirlar[1]['mv'], 'veri olan gün' );
		qrms_assert_contains( 'created_at BETWEEN', $wpdb->queries[0], 'kapalı aralık' );
	}
);

qrms_test(
	'kırılım aralıkla uyumsuzsa en yakın anlamlıya düşer',
	function () {
		// Tek gün: yalnızca saatlik anlamlı (günlük tek çubuk olurdu).
		qrms_assert_same( array( 'hourly' ), QRMS_Analitik_Filtre::kirilimlar( 1 ), 'tek gün' );

		// Bir hafta: günlük; haftalık iki tam hafta istemeden anlamsız.
		qrms_assert_same( array( 'daily' ), QRMS_Analitik_Filtre::kirilimlar( 7 ), 'yedi gün' );

		// İki hafta ve üstü: haftalık da açılır.
		qrms_assert_same( array( 'daily', 'weekly' ), QRMS_Analitik_Filtre::kirilimlar( 14 ), 'iki hafta' );

		// İki ay ve üstü: aylık da açılır.
		qrms_assert_same( array( 'daily', 'weekly', 'monthly' ), QRMS_Analitik_Filtre::kirilimlar( 60 ), 'iki ay' );

		// Çok uzun aralıkta günlük düşer (yüzlerce çubuk okunmaz).
		qrms_assert_same( array( 'weekly', 'monthly' ), QRMS_Analitik_Filtre::kirilimlar( 200 ), 'uzun aralık' );

		// "Bugün" + aylık istenirse saatliğe düşülür, hata verilmez.
		qrms_assert_same(
			'hourly',
			QRMS_Analitik_Filtre::kirilim(
				array(
					'donem'   => 'bugun',
					'kirilim' => 'monthly',
				)
			),
			'geçersiz kırılım düzeltilir'
		);

		// Geçerli olan aynen kalır.
		qrms_assert_same(
			'weekly',
			QRMS_Analitik_Filtre::kirilim(
				array(
					'donem'   => 'ozel',
					'bas'     => '2026-01-01',
					'bit'     => '2026-03-01',
					'kirilim' => 'weekly',
				)
			),
			'geçerli kırılım korunur'
		);
	}
);

qrms_test(
	'filtre aralığı ve karşılaştırma penceresi eşit uzunluktadır',
	function () {
		$bugun = QRMS_Analitik_Filtre::aralik( array( 'donem' => 'bugun' ) );
		qrms_assert_same( 1, $bugun['gun'], 'bugün tek gün' );
		qrms_assert_contains( '00:00:00', $bugun['bas'], 'günün başı' );
		qrms_assert_contains( '23:59:59', $bugun['bit'], 'günün sonu' );

		$hafta = QRMS_Analitik_Filtre::aralik( array( 'donem' => 'hafta' ) );
		qrms_assert_same( 7, $hafta['gun'], 'son 7 gün' );

		$ozel = array(
			'donem' => 'ozel',
			'bas'   => '2026-03-10',
			'bit'   => '2026-03-12',
		);

		qrms_assert_same( 3, QRMS_Analitik_Filtre::aralik( $ozel )['gun'], 'özel aralık gün sayısı' );

		// Karşılaştırma penceresi aralığın hemen öncesinde ve aynı uzunlukta:
		// 10–12 Mart'ın öncesi 7–9 Mart'tır.
		qrms_assert_same( '2026-03-07 00:00:00', QRMS_Analitik_Filtre::onceki_baslangic( $ozel ), 'önceki pencere' );
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
	'uzun çağrıların hepsi bırak/geri-aç çiftiyle sarılı',
	function () {
		// Sarmanın YARISI (bırakma var, geri açma yok) sessiz veri kaybı
		// demektir: kapalı bağlantıda sorgular false döner. Bu yüzden her
		// dosyada iki tarafın da bulunduğu doğrulanır.
		$dosyalar = array(
			'modules/qr-chatbot/includes/ajax-chat.php'         => array( 'qmo_db_serbest_birak', 'qmo_db_geri_baglan' ),
			'modules/qr-chatbot/rest-order.php'                 => array( 'qmo_db_serbest_birak', 'qmo_db_geri_baglan' ),
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

		// Yönetim sorgusu artık dinamik kurulur (sekme + durum filtresi); sütun
		// sırası üretilen SQL üzerinden doğrulanır.
		$wpdb = qrms_sayan_wpdb();

		qrm_pro_admin_fetch_reviews( 'bekleyen', 10, 1, '', 3.0 );

		qrms_assert_contains( 'WHERE status = 0 ORDER BY created_at DESC', $wpdb->queries[0], 'yönetim sorgusu' );
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

		qrms_assert_contains( 'while ( $tur < self::SAKLAMA_TUR', $kaynak, 'tur içinde döngü var' );
		qrms_assert_contains( 'qrms_analitik_temizlik_sure', $kaynak, 'süre bütçesi filtrelenebilir' );
		qrms_assert_contains( 'if ( $silinen < self::SAKLAMA_PARCA )', $kaynak, 'silinecek kalmayınca durur' );
		qrms_assert_contains( 'self::SAKLAMA_TUR', $kaynak, 'sonsuz döngüye karşı emniyet freni' );
		qrms_assert_contains( 'WHERE event_type = %s AND created_at < %s', $kaynak, 'silme idx_td kullanır' );
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

/* ---------------------------------------------------------------------------
 * 15. Header Footer Builder (header-footer-builder)
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/class-header-footer-builder.php';

echo "\nHeader Footer Builder\n";

/**
 * Modül örneği (hook kaydı yapmadan).
 *
 * @return QRMS_Header_Footer_Builder
 */
function qrms_hfb() {
	return new QRMS_Header_Footer_Builder();
}

/**
 * Çeviri modülünün dil seçici kısa kodunu taklit eder.
 *
 * @return void
 */
function qrms_hfb_fake_lang_shortcode() {
	add_shortcode(
		'qrmenu_flags_only',
		function () {
			return '<div class="qrmenu-lang-dropdown qrmenu-flags-only-mod">TR-BAYRAK</div>';
		}
	);
}

qrms_test(
	'modül loader sözleşmesine uyar: slug, dosya ve init fonksiyonu',
	function () {
		qrms_assert_true(
			in_array( 'header-footer-builder', QRMS_Helpers::MODULE_SLUGS, true ),
			'slug bilinen modüller arasında'
		);
		qrms_assert_true( QRMS_Module_Loader::module_file_exists( 'header-footer-builder' ), 'module.php diskte' );
		qrms_assert_same(
			'qrms_module_header_footer_builder_init',
			QRMS_Module_Loader::get_init_function( 'header-footer-builder' ),
			'init fonksiyon adı'
		);

		update_option( 'qrms_active_modules', array( 'header-footer-builder' ) );

		qrms_assert_same(
			array( 'header-footer-builder' ),
			QRMS_Module_Loader::load_modules(),
			'aktifken yüklenir'
		);
	}
);

qrms_test(
	'tek sabit tasarım basılır: QR marka, iki satır, mobil panel',
	function () {
		$hfb    = qrms_hfb();
		$header = $hfb->render_header( $hfb->get_header_options() );
		$footer = $hfb->render_footer( $hfb->get_footer_options() );

		qrms_assert_contains( 'hfb-header-wrap', $header, 'header sarmalayıcı' );
		qrms_assert_contains( 'hfb-brand__mark', $header, 'QR kod ikonu' );
		qrms_assert_contains( 'QR MENU', $header, 'marka üst satırı' );
		qrms_assert_contains( 'OFFİCİAL', $header, 'marka alt satırı' );
		qrms_assert_contains( 'hfb-header__toggle', $header, 'hamburger düğmesi' );
		qrms_assert_contains( 'hfb-mobile-panel', $header, 'mobil panel' );
		qrms_assert_contains( 'hfb-footer-wrap', $footer, 'footer sarmalayıcı' );
	}
);

qrms_test(
	'eski varyant sınıfları basılmaz; tasarım anahtarları artık varsayılanlarla gelir',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_true( ! array_key_exists( 'variant', $opts ), 'varyant ayarı yok' );
		qrms_assert_true( ! array_key_exists( 'mobile_panel_gradient_start', $opts ), 'eski gradient yok' );
		qrms_assert_same( '#0a0a0c', $opts['bg_color'], 'varsayılan zemin' );
		qrms_assert_same( '#c9a84c', $opts['icon_color'], 'varsayılan ikon rengi' );
		qrms_assert_same( 160, (int) $opts['logo_width_desktop'], 'varsayılan logo genişliği' );

		qrms_assert_true(
			! array_key_exists( 'variant', $hfb->get_footer_options() ),
			'footer varyantı yok'
		);

		$header = $hfb->render_header( $opts );
		foreach ( array( 'minimal-sticky', 'glass-bento', 'kinetic-bold', 'menulux' ) as $eski ) {
			qrms_assert_true(
				false === strpos( $header, 'hfb-header--' . $eski ),
				$eski . ' varyant sınıfı basılmıyor'
			);
		}

		qrms_assert_contains( '--hfb-header-bg:#0a0a0c', $header, 'CSS değişkeni basılır' );
	}
);

qrms_test(
	'eski kurulumun varyant anahtarları budanır, geçerli renk korunur',
	function () {
		update_option(
			'hfb_header_options',
			array(
				'variant'                     => 'menulux',
				'bg_color'                    => '#ffffff',
				'mobile_panel_gradient_start' => '#e91e8c',
				'logo_width'                  => 240,
				'menu_id'                     => 7,
			)
		);

		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_true( ! array_key_exists( 'variant', $opts ), 'variant budandı' );
		qrms_assert_true( ! array_key_exists( 'mobile_panel_gradient_start', $opts ), 'gradient budandı' );
		qrms_assert_true( ! array_key_exists( 'logo_width', $opts ), 'eski tekil logo_width budandı' );
		qrms_assert_same( '#ffffff', $opts['bg_color'], 'geçerli zemin rengi taşındı' );
		qrms_assert_same( 7, (int) $opts['menu_id'], 'korunan ayar taşındı' );
		qrms_assert_same( 'QR MENU', $opts['brand_line1'], 'yeni alan varsayılana düştü' );

		$hfb->save_settings( array( 'hfb_header_menu_id' => '7' ) );
		qrms_assert_true(
			! array_key_exists( 'variant', get_option( 'hfb_header_options' ) ),
			'kayıtta da yok'
		);
		qrms_assert_same( '#ffffff', get_option( 'hfb_header_options' )['bg_color'], 'renk kayıtta durur' );
	}
);

qrms_test(
	'sticky kapatılabilir; tasarımın kalanı değişmez',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_contains( 'hfb-header--sticky', $hfb->render_header( $opts ), 'varsayılan sticky' );

		$opts['sticky'] = 0;
		$html           = $hfb->render_header( $opts );

		qrms_assert_true( false === strpos( $html, 'hfb-header--sticky' ), 'kapalıyken sınıf yok' );
		qrms_assert_contains( 'hfb-brand__mark', $html, 'marka yerinde' );
	}
);

qrms_test(
	'sosyal ikonlar yalnızca URL girilmiş platformlar için basılır',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_same( array( 'facebook', 'x', 'youtube' ), $opts['social_media_active'], 'varsayılan üçlü' );
		qrms_assert_true(
			false === strpos( $hfb->render_header( $opts ), 'hfb-social__link' ),
			'URL yokken ikon basılmaz'
		);

		$opts['social_media'] = array(
			'facebook' => 'https://facebook.com/qrmenu',
			'youtube'  => 'https://youtube.com/@qrmenu',
		);

		$html = $hfb->render_header( $opts );

		qrms_assert_contains( 'hfb-social__link--facebook', $html, 'facebook ikonu' );
		qrms_assert_contains( 'hfb-social__link--youtube', $html, 'youtube ikonu' );
		qrms_assert_true( false === strpos( $html, 'hfb-social__link--x"' ), 'URL girilmemiş X basılmaz' );
	}
);

qrms_test(
	'dil seçici: çeviri modülü yokken sessizce çıkmaz',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		qrms_assert_same( 1, (int) $opts['lang_show'], 'toggle varsayılan açık' );
		qrms_assert_true( ! $hfb->lang_switcher_available(), 'kısa kod kayıtlı değil' );
		qrms_assert_same( '', $hfb->render_lang_switcher( $opts ), 'çıktı boş' );
		qrms_assert_true(
			false === strpos( $hfb->render_header( $opts ), 'hfb-lang' ),
			'header\'da dil kabı yok'
		);
	}
);

qrms_test(
	'dil seçici açıkken hem masaüstü hem mobil panelde görünür',
	function () {
		qrms_hfb_fake_lang_shortcode();

		$hfb       = qrms_hfb();
		$opts      = $hfb->get_header_options();
		$hamburger = $hfb->get_hamburger_options();

		// Panelde bayrak, dinamik blok modelinde "Dil Seçici" bloğu
		// eklendiğinde görünür; header sağ ucu lang_show'a bağlıdır.
		$hamburger['blocks'][] = array(
			'id'      => 'blk_lang',
			'type'    => 'lang',
			'enabled' => true,
			'align'   => 'center',
		);

		qrms_assert_true( $hfb->lang_switcher_available(), 'kısa kod bulundu' );

		$html = $hfb->render_header( $opts, $hamburger );

		qrms_assert_same( 2, substr_count( $html, 'TR-BAYRAK' ), 'header sağ ucu + mobil panel' );
		qrms_assert_contains( 'hfb-header__actions', $html, 'sağ blok' );
		qrms_assert_contains( 'hfb-mobile-panel__block--lang', $html, 'mobil paneldeki dil bloğu' );
	}
);

qrms_test(
	'dil toggle kapalıyken bayrak hiçbir yerde görünmez',
	function () {
		qrms_hfb_fake_lang_shortcode();

		$hfb               = qrms_hfb();
		$opts              = $hfb->get_header_options();
		$opts['lang_show'] = 0;

		$html = $hfb->render_header( $opts );

		qrms_assert_true( false === strpos( $html, 'TR-BAYRAK' ), 'masaüstünde yok' );
		qrms_assert_true( false === strpos( $html, 'hfb-mobile-panel__block--lang' ), 'mobilde lang bloğu yok' );
	}
);

qrms_test(
	'toggle, kayıtta ve önizlemede aynı biçimde çözülür',
	function () {
		$hfb = qrms_hfb();

		$acik = $hfb->sanitize_header_input(
			array( 'hfb_lang_show' => '1' ),
			$hfb->get_header_options()
		);
		qrms_assert_same( 1, $acik['lang_show'], 'işaretliyken 1' );

		// Onay kutusu işaretsizken tarayıcı alanı hiç göndermez.
		$kapali = $hfb->sanitize_header_input( array(), $hfb->get_header_options() );
		qrms_assert_same( 0, $kapali['lang_show'], 'işaretsizken 0' );
		qrms_assert_same( 0, $kapali['sticky'], 'sticky de aynı kuralla' );
	}
);

qrms_test(
	'önizleme ile kayıt aynı temizleyiciden geçer, çıktı birebir aynı',
	function () {
		$hfb   = qrms_hfb();
		$girdi = array(
			'hfb_header_brand_line1'               => '  Deneme Marka  ',
			'hfb_header_brand_line2'               => 'ALT SATIR',
			'hfb_header_sticky'                    => '1',
			'hfb_lang_show'                        => '1',
			'hfb_header_social_media_active'       => array( 'facebook' ),
			'hfb_header_social_media_url_facebook' => 'https://facebook.com/deneme',
			'hfb_footer_copyright'                 => '© 2026 Deneme',
			'hfb_footer_email'                     => 'bilgi@deneme.test',
			'hfb_hamburger_block_order'            => 'blk_1,blk_2,blk_3,blk_4',
			'hfb_hamburger_blocks'                 => array(
				'blk_1' => array(
					'type'    => 'logo',
					'enabled' => '1',
					'align'   => 'center',
				),
				'blk_2' => array(
					'type'    => 'menu',
					'enabled' => '1',
					'align'   => 'center',
				),
				'blk_3' => array(
					'type'    => 'social',
					'enabled' => '1',
					'align'   => 'center',
				),
				'blk_4' => array(
					'type'    => 'text',
					'enabled' => '0',
					'align'   => 'center',
					'content' => '',
				),
			),
		);

		$header_in    = $hfb->sanitize_header_input( $girdi, $hfb->get_header_options() );
		$hamburger_in = $hfb->sanitize_hamburger_input( $girdi, $hfb->get_hamburger_options() );
		$onizleme     = $hfb->render_header( $header_in, $hamburger_in );

		$hfb->save_settings( $girdi );
		$kayitli = $hfb->render_header( $hfb->get_header_options(), $hfb->get_hamburger_options() );

		qrms_assert_same( $onizleme, $kayitli, 'önizleme ve kayıt aynı HTML' );
		qrms_assert_contains( 'Deneme Marka', $kayitli, 'marka kaydedildi' );
		qrms_assert_contains( 'hfb-social__link--facebook', $kayitli, 'sosyal bağlantı kaydedildi' );

		$footer = $hfb->render_footer( $hfb->get_footer_options() );
		qrms_assert_contains( '© 2026 Deneme', $footer, 'telif kaydedildi' );
		qrms_assert_contains( 'bilgi@deneme.test', $footer, 'e-posta kaydedildi' );
	}
);

qrms_test(
	'aynı istekte ikinci kez render edilmez (Elementor çift çıktı freni)',
	function () {
		$hfb = qrms_hfb();

		qrms_assert_true( $hfb->should_render( 'header' ), 'ilk çağrı serbest' );
		$hfb->mark_rendered( 'header' );
		qrms_assert_true( ! $hfb->should_render( 'header' ), 'ikinci çağrı engellenir' );
		qrms_assert_true( $hfb->should_render( 'footer' ), 'footer ayrı sayılır' );

		// Elementor yüklü değilken uyumluluk kontrolleri sessizce false döner.
		qrms_assert_true( ! $hfb->elementor_loaded(), 'Elementor yok' );
		qrms_assert_true( ! $hfb->elementor_is_edit_mode(), 'editör modu değil' );
		qrms_assert_true( ! $hfb->theme_location_has_template( 'header' ), 'Theme Builder şablonu yok' );
	}
);

qrms_test(
	'AJAX önizleme uç noktası header ve footer\'ı birlikte döndürür',
	function () {
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_header_brand_line1' => 'Önizleme Marka',
				'hfb_header_menu_id'     => '7',
				'hfb_footer_copyright'   => '© 2026 Önizleme',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Önizleme Marka', $yanit['data']['header'], 'header taze veriyle döndü' );
		qrms_assert_contains( '© 2026 Önizleme', $yanit['data']['footer'], 'footer taze veriyle döndü' );

		// Önizleme hiçbir şeyi kaydetmez.
		qrms_assert_same( 'QR MENU', $hfb->get_header_options()['brand_line1'], 'depo değişmedi' );
	}
);

qrms_test(
	'aynı menü iki kez basılır ama id\'ler çakışmaz',
	function () {
		$hfb             = qrms_hfb();
		$h               = $hfb->get_header_options();
		$h['menu_id']    = 7;
		$f               = $hfb->get_footer_options();
		$f['menu_id']    = 7;

		$html = $hfb->render_header( $h ) . $hfb->render_footer( $f );

		preg_match_all( '/\bid="([^"]+)"/', $html, $eslesme );
		$idler = $eslesme[1];

		qrms_assert_same( count( $idler ), count( array_unique( $idler ) ), 'tekrar eden id yok' );
		qrms_assert_contains( 'hfb-h-menu-item-101', $html, 'masaüstü menüsü kendi id alanında' );
		qrms_assert_contains( 'hfb-m-menu-item-101', $html, 'mobil panel kendi id alanında' );
		qrms_assert_contains( 'hfb-f-menu-item-101', $html, 'footer kendi id alanında' );
	}
);

qrms_test(
	'kısa kod kaydı rehbere düşer',
	function () {
		update_option( 'qrms_active_modules', array( 'header-footer-builder' ) );
		qrms_hfb()->register_hooks();

		$gruplar = QRMS_Shortcodes::all();
		qrms_assert_true( isset( $gruplar['header-footer-builder'] ), 'modül kayıtlı' );
		$kodlar = $gruplar['header-footer-builder'];
		qrms_assert_same( 2, count( $kodlar ), 'iki kısa kod' );
		qrms_assert_same( 'hfb_header', $kodlar[0]['tag'], 'header tag' );
		qrms_assert_same( 'hfb_footer', $kodlar[1]['tag'], 'footer tag' );
	}
);

qrms_test(
	'logo boyutu aralığa sıkışır; otomatik yükseklik 0 yazar',
	function () {
		$hfb = qrms_hfb();

		$temiz = $hfb->sanitize_header_input(
			array(
				'hfb_header_logo_width_desktop'       => '999',
				'hfb_header_logo_width_mobile'        => '40',
				'hfb_header_logo_height_auto_desktop' => '1',
				'hfb_header_logo_height_auto_tablet'  => '1',
				'hfb_header_logo_height_auto_mobile'  => '',
				'hfb_header_logo_height_mobile'       => '80',
				'hfb_header_sticky'                   => '1',
				'hfb_lang_show'                       => '1',
			),
			$hfb->get_header_options()
		);

		qrms_assert_same( 320, (int) $temiz['logo_width_desktop'], 'üst sınır' );
		qrms_assert_same( 80, (int) $temiz['logo_width_mobile'], 'alt sınır' );
		qrms_assert_same( 1, (int) $temiz['logo_height_auto_desktop'], 'otomatik açık' );
		qrms_assert_same( 0, (int) $temiz['logo_height_desktop'], 'otomatikte yükseklik 0' );
		qrms_assert_same( 0, (int) $temiz['logo_height_auto_mobile'], 'otomatik kapalı' );
		qrms_assert_same( 80, (int) $temiz['logo_height_mobile'], 'sabit yükseklik' );
	}
);

qrms_test(
	'geçersiz renk varsayılana düşer; geçerli hex korunur',
	function () {
		$hfb = qrms_hfb();
		$cur = $hfb->get_header_options();

		$kotu = $hfb->sanitize_header_input(
			array(
				'hfb_header_bg_color'   => 'red',
				'hfb_header_icon_color' => '#abc',
				'hfb_header_sticky'     => '1',
				'hfb_lang_show'         => '1',
			),
			$cur
		);

		qrms_assert_same( '#0a0a0c', $kotu['bg_color'], 'geçersiz renk reddedildi' );
		qrms_assert_same( '#abc', $kotu['icon_color'], '3 haneli hex kabul' );
	}
);

qrms_test(
	'sticky blur sınıfı yalnızca sticky açıkken basılır',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		$opts['sticky']      = 1;
		$opts['sticky_blur'] = 1;
		qrms_assert_contains( 'hfb-header--sticky-blur', $hfb->render_header( $opts ), 'blur sınıfı' );

		$opts['sticky'] = 0;
		qrms_assert_true(
			false === strpos( $hfb->render_header( $opts ), 'hfb-header--sticky-blur' ),
			'sticky kapalıyken blur yok'
		);
	}
);

qrms_test(
	'hamburger blok sırası ve görünürlük panele yansır',
	function () {
		$hfb       = qrms_hfb();
		$header    = $hfb->get_header_options();
		$hamburger = $hfb->get_hamburger_options();

		$header['social_media']        = array( 'instagram' => 'https://instagram.com/x' );
		$header['social_media_active'] = array( 'instagram' );
		$header['cta_phone']           = '0850 000 00 00';

		// Dinamik blok modeli: sıra dizinin kendi sırasıdır, görünürlük
		// blok başına `enabled` alanıdır.
		$hamburger['blocks'] = array(
			array(
				'id'      => 'blk_1',
				'type'    => 'text',
				'enabled' => true,
				'align'   => 'center',
				'content' => '<p>Açık büfe</p>',
			),
			array(
				'id'      => 'blk_2',
				'type'    => 'social',
				'enabled' => true,
				'align'   => 'center',
			),
			array(
				'id'      => 'blk_3',
				'type'    => 'logo',
				'enabled' => true,
				'align'   => 'center',
			),
			array(
				'id'      => 'blk_4',
				'type'    => 'menu',
				'enabled' => false,
				'align'   => 'center',
			),
			array(
				'id'          => 'blk_5',
				'type'        => 'button',
				'enabled'     => true,
				'align'       => 'center',
				'label'       => 'Rezervasyon',
				'url'         => 'https://ornek.test/rezervasyon',
				'bg_color'    => '#c9a84c',
				'text_color'  => '#0a0a0c',
				'shape'       => 'pill',
				'font'        => 'Playfair Display',
				'font_size'   => 15,
				'font_weight' => 600,
			),
		);

		$html = $hfb->render_header( $header, $hamburger );

		qrms_assert_contains( 'hfb-mobile-panel__block--text', $html, 'metin bloğu' );
		qrms_assert_contains( 'Açık büfe', $html, 'metin içeriği' );
		qrms_assert_contains( 'hfb-mobile-panel__block--social', $html, 'sosyal blok' );
		qrms_assert_contains( 'hfb-mobile-panel__block--logo', $html, 'logo blok' );
		qrms_assert_true( false === strpos( $html, 'hfb-mobile-panel__block--menu' ), 'kapalı menü yok' );
		qrms_assert_contains( 'hfb-cta', $html, 'telefon CTA blok sırasının dışında altta' );
		qrms_assert_contains( 'hfb-mobile-panel__btn', $html, 'buton bloğu' );
		qrms_assert_contains( 'Rezervasyon', $html, 'buton metni' );

		$text_pos   = strpos( $html, 'hfb-mobile-panel__block--text' );
		$social_pos = strpos( $html, 'hfb-mobile-panel__block--social' );
		$logo_pos   = strpos( $html, 'hfb-mobile-panel__block--logo' );
		qrms_assert_true( $text_pos < $social_pos && $social_pos < $logo_pos, 'blok sırası text-social-logo' );
	}
);

qrms_test(
	'sanitize_hamburger_input blok sırasını, hizayı, fontu ve metni temizler',
	function () {
		$hfb = qrms_hfb();

		$temiz = $hfb->sanitize_hamburger_input(
			array(
				'hfb_hamburger_block_order'         => 'blk_4,hack,blk_1,blk_1,blk_2',
				'hfb_hamburger_blocks'              => array(
					'blk_1' => array(
						'type'    => 'logo',
						'enabled' => '1',
						'align'   => 'left',
					),
					'blk_2' => array(
						'type'    => 'menu',
						'enabled' => '0',
						'align'   => 'center',
					),
					'blk_3' => array(
						'type'    => 'social',
						'enabled' => '1',
						'align'   => 'center',
					),
					'blk_4' => array(
						'type'    => 'text',
						'enabled' => '1',
						'align'   => 'justify',
						'content' => '<p>Merhaba</p><script>x</script>',
					),
					'blk_5' => array(
						'type'        => 'button',
						'enabled'     => '1',
						'align'       => 'center',
						'label'       => '  Rezervasyon  ',
						'url'         => 'https://ornek.test/rezervasyon',
						'bg_color'    => 'red',
						'shape'       => 'hexagon',
						'font'        => 'Comic Sans',
						'font_size'   => '99',
						'font_weight' => '550',
					),
					// Bilinmeyen tip hiç listeye girmez.
					'blk_6' => array(
						'type'    => 'hack',
						'enabled' => '1',
					),
				),
				'hfb_hamburger_font_family'         => 'Comic Sans',
				'hfb_hamburger_font_size'           => '99',
				'hfb_hamburger_font_weight'         => '550',
				'hfb_hamburger_font_align'          => 'justify',
				'hfb_hamburger_close_icon_color'    => '#ff00aa',
				'hfb_hamburger_panel_bg_color'      => '#111111',
			),
			$hfb->get_hamburger_options()
		);

		$types   = wp_list_pluck( $temiz['blocks'], 'type' );
		$by_type = array();
		foreach ( $temiz['blocks'] as $block ) {
			$by_type[ $block['type'] ] = $block;
		}

		qrms_assert_same( array( 'text', 'logo', 'menu', 'social', 'button' ), $types, 'sıra + eksik tamamlandı; bilinmeyen tip elendi' );
		qrms_assert_same( 1, (int) $by_type['logo']['enabled'], 'logo açık' );
		qrms_assert_same( 0, (int) $by_type['menu']['enabled'], 'menü kapalı (kutu yok)' );
		qrms_assert_same( 'left', $by_type['logo']['align'], 'geçerli blok hizası korunur' );
		qrms_assert_same( 'center', $by_type['text']['align'], 'geçersiz blok hizası varsayılan' );
		qrms_assert_same( 'Playfair Display', $temiz['font_family'], 'bilinmeyen font reddedildi' );
		qrms_assert_same( 32, (int) $temiz['font_size'], 'punto üst sınır' );
		qrms_assert_same( 500, (int) $temiz['font_weight'], 'geçersiz kalınlık varsayılan' );
		qrms_assert_same( 'center', $temiz['font_align'], 'geçersiz hiza varsayılan' );
		qrms_assert_same( '#ff00aa', $temiz['close_icon_color'], 'kapatma rengi' );
		// Zararlı etiket ayıklaması wp_kses_post'un işidir; testte taklit
		// edildiği için burada yalnızca metnin korunduğu doğrulanır.
		qrms_assert_contains( 'Merhaba', $by_type['text']['content'], 'metin durur' );
		qrms_assert_same( 'Rezervasyon', $by_type['button']['label'], 'buton metni' );
		qrms_assert_same( '#c9a84c', $by_type['button']['bg_color'], 'geçersiz buton rengi varsayılan' );
		qrms_assert_same( 'pill', $by_type['button']['shape'], 'geçersiz şekil varsayılan' );
		qrms_assert_same( 'Playfair Display', $by_type['button']['font'], 'bilinmeyen buton fontu reddedildi' );
		qrms_assert_same( 32, (int) $by_type['button']['font_size'], 'buton punto üst sınır' );
	}
);

qrms_test(
	'AJAX önizleme hamburger metnini de döndürür ve kaydetmez',
	function () {
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_header_brand_line1'        => 'Önizleme Marka',
				'hfb_hamburger_block_order'     => 'blk_4,blk_1,blk_2,blk_3',
				'hfb_hamburger_blocks'          => array(
					'blk_1' => array(
						'type'    => 'logo',
						'enabled' => '1',
						'align'   => 'center',
					),
					'blk_2' => array(
						'type'    => 'menu',
						'enabled' => '1',
						'align'   => 'center',
					),
					'blk_3' => array(
						'type'    => 'social',
						'enabled' => '1',
						'align'   => 'center',
					),
					'blk_4' => array(
						'type'    => 'text',
						'enabled' => '1',
						'align'   => 'center',
						'content' => 'Panel notu',
					),
				),
				'hfb_footer_copyright'          => '© 2026 Önizleme',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Panel notu', $yanit['data']['header'], 'hamburger metni önizlemede' );
		qrms_assert_contains( 'hfb-mobile-panel__block--text', $yanit['data']['header'], 'metin bloğu sınıfı' );

		$stored_text = '';
		foreach ( $hfb->get_hamburger_options()['blocks'] as $block ) {
			if ( 'text' === $block['type'] ) {
				$stored_text = isset( $block['content'] ) ? (string) $block['content'] : '';
			}
		}
		qrms_assert_same( '', $stored_text, 'depo değişmedi' );
	}
);


qrms_test(
	'AJAX önizleme düz anahtarlı blok alanlarını da çözer',
	function () {
		// Önizleme isteği blokları `hfb_hamburger_blocks[blk_1][alan]`
		// biçiminde DÜZ anahtarlarla gönderir; kayıt yolundaki iç içe
		// diziden ayrı bir çözümleme dalıdır (bkz.
		// extract_hamburger_blocks_from_input).
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_header_brand_line1'                   => 'Önizleme Marka',
				'hfb_hamburger_blocks[blk_1][type]'        => 'text',
				'hfb_hamburger_blocks[blk_1][enabled]'     => '1',
				'hfb_hamburger_blocks[blk_1][content]'     => 'Panel notu',
				'hfb_hamburger_blocks[blk_2][type]'        => 'logo',
				'hfb_hamburger_blocks[blk_2][enabled]'     => '1',
				'hfb_hamburger_blocks[blk_2][description]' => 'Lezzetin adresi',
				'hfb_hamburger_block_order'                => 'blk_1,blk_2',
				'hfb_footer_copyright'                     => '© 2026 Önizleme',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Önizleme Marka', $yanit['data']['header'], 'marka taze veriyle döndü' );
		qrms_assert_contains( 'Panel notu', $yanit['data']['header'], 'düz anahtarlı metin bloğu' );
		qrms_assert_contains( 'Lezzetin adresi', $yanit['data']['header'], 'logo altı açıklama önizlemede' );

		// Önizleme hiçbir şeyi kaydetmez.
		$kayitli = $hfb->get_hamburger_options();
		qrms_assert_same( 'blk_1', $kayitli['blocks'][0]['id'], 'depodaki ilk blok varsayılan' );
		qrms_assert_same( 'logo', $kayitli['blocks'][0]['type'], 'depo değişmedi' );
	}
);

qrms_test(
	'header yerleşim ayarları CSS değişkeni olarak basılır ve aralığa sıkışır',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_header_options();

		$varsayilan = $hfb->render_header( $opts );
		qrms_assert_contains( '--hfb-header-max-width:1200px', $varsayilan, 'varsayılan genişlik' );
		qrms_assert_contains( '--hfb-header-padding-x:20px', $varsayilan, 'masaüstü yan boşluk' );
		qrms_assert_contains( '--hfb-header-padding-y:12px', $varsayilan, 'masaüstü dikey boşluk' );
		qrms_assert_contains( '--hfb-header-padding-x-m:20px', $varsayilan, 'mobil yan boşluk' );
		qrms_assert_contains( '--hfb-header-padding-y-m:12px', $varsayilan, 'mobil dikey boşluk' );

		$temiz = $hfb->sanitize_header_input(
			array(
				'hfb_header_content_width'     => '4000',
				'hfb_header_padding_x_desktop' => '999',
				'hfb_header_padding_y_desktop' => '999',
				'hfb_header_padding_x_mobile'  => '999',
				'hfb_header_padding_y_mobile'  => '4',
				'hfb_header_sticky'            => '1',
				'hfb_lang_show'                => '1',
			),
			$opts
		);

		qrms_assert_same( 1600, (int) $temiz['content_width'], 'genişlik üst sınır' );
		qrms_assert_same( 80, (int) $temiz['padding_x_desktop'], 'masaüstü yan üst sınır' );
		qrms_assert_same( 40, (int) $temiz['padding_y_desktop'], 'masaüstü dikey üst sınır' );
		qrms_assert_same( 32, (int) $temiz['padding_x_mobile'], 'mobil yan üst sınır dar' );
		qrms_assert_same( 4, (int) $temiz['padding_y_mobile'], 'aralıktaki değer korunur' );
		qrms_assert_same( 0, (int) $temiz['content_full_width'], 'tam genişlik kapalı' );

		// Tam genişlik seçilince kural sabit piksel değil `none` görür.
		$tam = $hfb->sanitize_header_input(
			array(
				'hfb_header_content_full_width' => '1',
				'hfb_header_sticky'             => '1',
				'hfb_lang_show'                 => '1',
			),
			$opts
		);

		qrms_assert_same( 1, (int) $tam['content_full_width'], 'tam genişlik açık' );
		qrms_assert_contains( '--hfb-header-max-width:none', $hfb->render_header( $tam ), 'genişlik sınırı kalkar' );
	}
);

qrms_test(
	'header__inner sabit ölçü yerine yerleşim değişkenlerini kullanır',
	function () {
		$css = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css'
		);

		qrms_assert_contains( 'max-width: var(--hfb-header-max-width, 1200px)', $css, 'genişlik değişkeni' );
		qrms_assert_contains( 'var(--hfb-header-padding-y, 0.75rem) var(--hfb-header-padding-x, 1.25rem)', $css, 'masaüstü boşluk değişkenleri' );
		qrms_assert_contains( '--hfb-header-padding-x-m', $css, 'mobil kırılımda ayrı set' );

		// Dil bayrağı: daire içeriğiyle birlikte kurulur, taşma kırpılır.
		qrms_assert_contains( '.hfb-header-wrap .hfb-lang .qrmenu-current-btn img', $css, 'bayrak görseli kuralı' );
		qrms_assert_contains( 'object-fit: cover', $css, 'bayrak oranı korunur' );

		// Mobil panel zenginleştirmesi.
		qrms_assert_contains( '.hfb-mobile-panel__desc', $css, 'logo altı açıklama stili' );
		qrms_assert_contains( '.hfb-mobile-panel__btn--full', $css, 'tam genişlik buton' );
	}
);

qrms_test(
	'panel logo bloğu açıklama, buton bloğu tam genişlik taşır',
	function () {
		$hfb       = qrms_hfb();
		$header    = $hfb->get_header_options();
		$hamburger = $hfb->get_hamburger_options();

		$hamburger['blocks'] = array(
			array(
				'id'          => 'blk_1',
				'type'        => 'logo',
				'enabled'     => true,
				'align'       => 'center',
				'description' => 'Lezzetin adresi',
			),
			array(
				'id'         => 'blk_2',
				'type'       => 'button',
				'enabled'    => true,
				'align'      => 'center',
				'label'      => 'Rezervasyon Yap',
				'url'        => 'https://rezervasyon.test',
				'shape'      => 'pill',
				'full_width' => 1,
			),
		);

		$html = $hfb->render_header( $header, $hamburger );

		qrms_assert_contains( 'hfb-mobile-panel__desc', $html, 'açıklama kabı' );
		qrms_assert_contains( 'Lezzetin adresi', $html, 'açıklama metni' );
		qrms_assert_contains( 'hfb-mobile-panel__btn--full', $html, 'tam genişlik sınıfı' );
		qrms_assert_contains( 'Rezervasyon Yap', $html, 'buton metni' );

		// Tam genişlik kapalıyken sınıf hiç basılmaz.
		$hamburger['blocks'][1]['full_width'] = 0;
		qrms_assert_true(
			false === strpos( $hfb->render_header( $header, $hamburger ), 'hfb-mobile-panel__btn--full' ),
			'kapalıyken sınıf yok'
		);
	}
);

qrms_test(
	'Görünüm adımı öncesi kayıtlar görüntü değişmeden taşınır',
	function () {
		$hfb = qrms_hfb();

		// Adım eklenmeden ÖNCEKİ kayıt: Görünüm anahtarları yok, header'ın
		// ikon rengi ve mobil logo ölçüsü özelleştirilmiş.
		update_option(
			'hfb_header_options',
			array(
				'icon_color'              => '#ff0066',
				'logo_width_mobile'       => 210,
				'logo_height_mobile'      => 70,
				'logo_height_auto_mobile' => 0,
			)
		);
		update_option(
			'hfb_hamburger_options',
			array(
				'panel_bg_color' => '#111111',
				'font_color'     => '#dddddd',
			)
		);

		$opts = $hfb->get_hamburger_options();

		// Renkler eski kaynaklarından devralınır.
		qrms_assert_same( '#dddddd', $opts['menu_link_color'], 'satır metni panel yazı renginden' );
		qrms_assert_same( '#ff0066', $opts['menu_hover_color'], 'hover header ikon renginden' );
		qrms_assert_same( '#ff0066', $opts['menu_divider_color'], 'ayraç header ikon renginden' );
		qrms_assert_same( '#ff0066', $opts['menu_arrow_color'], 'ok header ikon renginden' );
		qrms_assert_same( '#ff0066', $opts['social_border_color'], 'sosyal çerçeve header ikon renginden' );
		qrms_assert_same( '#ff0066', $opts['social_icon_color'], 'sosyal glyph header ikon renginden' );
		qrms_assert_same( '', $opts['social_bg_color'], 'sosyal zemin şeffaf kalır' );

		// Panel logosu header'ın MOBİL ölçüsünü devralır (tek set).
		qrms_assert_same( 210, (int) $opts['logo_width'], 'panel logo genişliği devralındı' );
		qrms_assert_same( 70, (int) $opts['logo_height'], 'sabit yükseklik devralındı' );
		qrms_assert_same( 0, (int) $opts['logo_height_auto'], 'otomatik oran kapalı devralındı' );

		// Mevcut kaydedilmiş veri korunur.
		qrms_assert_same( '#111111', $opts['panel_bg_color'], 'panel zemini korunur' );
		qrms_assert_same( '#dddddd', $opts['font_color'], 'yazı rengi korunur' );

		// Kaydedilmiş bir Görünüm değeri varsa geçiş onu EZMEZ.
		update_option(
			'hfb_hamburger_options',
			array(
				'font_color'        => '#dddddd',
				'menu_arrow_color'  => '#00ff00',
				'logo_width_mobile' => 88,
			)
		);

		$kayitli = $hfb->get_hamburger_options();

		qrms_assert_same( '#00ff00', $kayitli['menu_arrow_color'], 'kaydedilmiş ok rengi korunur' );
		qrms_assert_same( 88, (int) $kayitli['logo_width'], 'kaydedilmiş panel logo korunur' );
	}
);

qrms_test(
	'hamburger masaüstü/mobil ayrımı tek sete birleşir, veri kaybolmaz',
	function () {
		$hfb = qrms_hfb();

		// Ayrım kaldırılmadan ÖNCEKİ kayıt: iki ayrı set.
		update_option(
			'hfb_hamburger_options',
			array(
				'font_size_desktop'         => 30,
				'font_size_mobile'          => 21,
				'font_weight_desktop'       => 700,
				'font_weight_mobile'        => 400,
				'font_align_desktop'        => 'left',
				'font_align_mobile'         => 'right',
				'logo_width_desktop'        => 300,
				'logo_width_mobile'         => 95,
				'logo_height_desktop'       => 180,
				'logo_height_mobile'        => 60,
				'logo_height_auto_desktop'  => 0,
				'logo_height_auto_mobile'   => 0,
			)
		);

		$opts = $hfb->get_hamburger_options();

		// Panel yalnızca mobilde açılır: gerçekte görünen MOBİL değerdir,
		// tek sete o taşınır.
		qrms_assert_same( 21, (int) $opts['font_size'], 'punto mobil değerden' );
		qrms_assert_same( 400, (int) $opts['font_weight'], 'kalınlık mobil değerden' );
		qrms_assert_same( 'right', $opts['font_align'], 'hizalama mobil değerden' );
		qrms_assert_same( 95, (int) $opts['logo_width'], 'logo genişliği mobil değerden' );
		qrms_assert_same( 60, (int) $opts['logo_height'], 'logo yüksekliği mobil değerden' );
		qrms_assert_same( 0, (int) $opts['logo_height_auto'], 'otomatik oran mobil değerden' );

		// Kırılım anahtarları artık şemada yok: merge_options() budar.
		foreach ( array( 'font_size_desktop', 'font_size_mobile', 'logo_width_desktop', 'logo_width_mobile' ) as $eski ) {
			qrms_assert_true( ! array_key_exists( $eski, $opts ), $eski . ' anahtarı kalmaz' );
		}

		// Mobil anahtar hiç yoksa masaüstü değeri kurtarılır — veri kaybı yok.
		update_option(
			'hfb_hamburger_options',
			array(
				'font_size_desktop'  => 26,
				'logo_width_desktop' => 175,
			)
		);

		$yalniz_masaustu = $hfb->get_hamburger_options();

		qrms_assert_same( 26, (int) $yalniz_masaustu['font_size'], 'mobil yoksa masaüstü puntosu taşınır' );
		qrms_assert_same( 175, (int) $yalniz_masaustu['logo_width'], 'mobil yoksa masaüstü logosu taşınır' );
	}
);

qrms_test(
	'hamburger sekmesinde masaüstü/mobil alanı yok, panel logosu 50px\'e inebilir',
	function () {
		$hfb = qrms_hfb();
		$GLOBALS['qrms_test']['can'] = true;

		ob_start();
		$hfb->render_admin_page();
		$html = ob_get_clean();

		// Tek set alan adları basılır.
		foreach ( array( 'hfb_hamburger_font_size', 'hfb_hamburger_font_weight', 'hfb_hamburger_font_align', 'hfb_hamburger_logo_width', 'hfb_hamburger_logo_height', 'hfb_hamburger_logo_height_auto' ) as $alan ) {
			qrms_assert_contains( 'name="' . $alan . '"', $html, $alan . ' alanı' );
		}

		// Kırılıma bölünmüş hamburger alanları TAMAMEN kalkar.
		foreach ( array( 'hfb_hamburger_font_size_desktop', 'hfb_hamburger_font_size_mobile', 'hfb_hamburger_font_weight_desktop', 'hfb_hamburger_font_align_desktop', 'hfb_hamburger_logo_width_desktop', 'hfb_hamburger_logo_width_mobile', 'hfb_hamburger_logo_height_auto_desktop' ) as $eski ) {
			qrms_assert_true(
				false === strpos( $html, 'name="' . $eski . '"' ),
				$eski . ' arayüzden çıkar'
			);
		}

		// Header sekmesinin kırılım ayrımına DOKUNULMAZ.
		qrms_assert_contains( 'name="hfb_header_logo_width_desktop"', $html, 'header masaüstü logosu durur' );
		qrms_assert_contains( 'name="hfb_header_logo_width_tablet"', $html, 'header tablet logosu durur' );
		qrms_assert_contains( 'name="hfb_header_logo_width_mobile"', $html, 'header mobil logosu durur' );

		// Panel logo kaydırıcısının alt sınırı 50px (header'ınki 80px kalır).
		qrms_assert_contains(
			'id="hfb_hamburger_logo_width"',
			$html,
			'panel logo kaydırıcısı'
		);
		$parca = substr( $html, (int) strpos( $html, 'id="hfb_hamburger_logo_width"' ) - 200, 400 );
		qrms_assert_contains( 'min="50"', $parca, 'panel logo alt sınırı 50px' );
	}
);

qrms_test(
	'AJAX önizleme Görünüm adımının alanlarını da yansıtır ve kaydetmez',
	function () {
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_hamburger_panel_bg_image'      => '7',
				'hfb_hamburger_panel_bg_opacity'    => '35',
				'hfb_hamburger_logo_width'          => '95',
				'hfb_hamburger_menu_link_color'     => '#eeeeee',
				'hfb_hamburger_menu_hover_color'    => '#00cc88',
				'hfb_hamburger_menu_divider_color'  => '#334455',
				'hfb_hamburger_menu_arrow_color'    => '#ff2200',
				'hfb_hamburger_social_border_color' => '#445566',
				'hfb_hamburger_social_bg_color'     => '#0b0b0f',
				'hfb_hamburger_social_icon_color'   => '#ffcc00',
				'hfb_hamburger_btn_bg_color'        => '#123123',
				'hfb_hamburger_btn_shape'           => 'rounded',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];
		$html  = $yanit['data']['header'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );

		// Kaydetmeden, formdaki her Görünüm alanı önizlemeye iner.
		qrms_assert_contains( 'hfb-mobile-panel__bg', $html, 'arka plan katmanı' );
		qrms_assert_contains( '--hfb-panel-bg-opacity:0.35', $html, 'opaklık' );
		qrms_assert_contains( '--hfb-panel-logo-w:95px', $html, 'panel logo ölçüsü (tek set)' );
		qrms_assert_true(
			false === strpos( $html, '--hfb-panel-logo-w-m' ),
			'kırılıma özel ikinci logo değişkeni basılmaz'
		);
		qrms_assert_contains( '--hfb-panel-menu-color:#eeeeee', $html, 'satır metin rengi' );
		qrms_assert_contains( '--hfb-panel-menu-hover:#00cc88', $html, 'satır hover rengi' );
		qrms_assert_contains( '--hfb-panel-menu-divider:#334455', $html, 'ayraç rengi' );
		qrms_assert_contains( '--hfb-panel-menu-arrow:#ff2200', $html, 'ok rengi' );
		qrms_assert_contains( '--hfb-panel-social-border:#445566', $html, 'sosyal çerçeve' );
		qrms_assert_contains( '--hfb-panel-social-bg:#0b0b0f', $html, 'sosyal zemin' );
		qrms_assert_contains( '--hfb-panel-social-icon:#ffcc00', $html, 'sosyal glyph' );
		qrms_assert_contains( '--hfb-panel-btn-bg:#123123', $html, 'buton zemini' );
		qrms_assert_contains( '--hfb-panel-btn-radius:10px', $html, 'yuvarlatılmış buton' );

		// Önizleme depoya yazmaz.
		$kayitli = $hfb->get_hamburger_options();
		qrms_assert_same( 0, (int) $kayitli['panel_bg_image'], 'görsel kaydedilmedi' );
		qrms_assert_same( '#c9a84c', $kayitli['menu_arrow_color'], 'renk kaydedilmedi' );

		$_POST = array();
	}
);

qrms_test(
	'Görünüm adımı tek formda; alanlar ve Açılış adımına referans basılır',
	function () {
		$hfb = qrms_hfb();
		ob_start();
		$hfb->render_admin_page();
		$html = (string) ob_get_clean();

		// Tek form / tek Kaydet düğmesi korunur.
		qrms_assert_same( 1, substr_count( $html, 'id="hfb-settings-form"' ), 'tek form' );
		qrms_assert_same( 1, substr_count( $html, 'name="hfb_save"' ), 'tek Kaydet düğmesi' );
		qrms_assert_contains( 'name="hfb_nonce"', $html, 'nonce alanı' );

		// Görünüm adımının alanları.
		foreach (
			array(
				'hfb_hamburger_panel_bg_image',
				'hfb_hamburger_panel_bg_opacity',
				'hfb_hamburger_logo_width',
				'hfb_hamburger_logo_height_auto',
				'hfb_hamburger_menu_link_color',
				'hfb_hamburger_menu_hover_color',
				'hfb_hamburger_menu_divider_color',
				'hfb_hamburger_menu_arrow_color',
				'hfb_hamburger_social_border_color',
				'hfb_hamburger_social_bg_color',
				'hfb_hamburger_social_icon_color',
				'hfb_hamburger_btn_bg_color',
				'hfb_hamburger_btn_text_color',
				'hfb_hamburger_btn_shape',
				'hfb_hamburger_btn_font_family',
				'hfb_hamburger_btn_font_size',
				'hfb_hamburger_btn_font_weight',
			) as $alan
		) {
			qrms_assert_contains( 'name="' . $alan . '"', $html, $alan . ' alanı' );
		}

		// Panel arka plan RENGİ ve kapatma ikonu Açılış adımında kalır;
		// Görünüm adımında tekrar oluşturulmaz.
		qrms_assert_same(
			1,
			substr_count( $html, 'name="hfb_hamburger_panel_bg_color"' ),
			'panel arka plan rengi tek yerde (Açılış adımı)'
		);
		qrms_assert_same(
			1,
			substr_count( $html, 'name="hfb_hamburger_close_icon_color"' ),
			'kapatma ikonu rengi tek yerde (Açılış adımı)'
		);
		qrms_assert_contains( '1. Açılış adımında ayarlanır', $html, 'Açılış adımına referans' );
	}
);

qrms_test(
	'sanitize_hamburger_input Görünüm adımının alanlarını temizler',
	function () {
		$hfb = qrms_hfb();

		$temiz = $hfb->sanitize_hamburger_input(
			array(
				// Arka plan görseli + opaklık.
				'hfb_hamburger_panel_bg_image'         => '42abc',
				'hfb_hamburger_panel_bg_opacity'       => '250',
				// Panel içi logo — header logosundan bağımsız, tek set.
				'hfb_hamburger_logo_width'             => '900',
				'hfb_hamburger_logo_height'            => '90',
				// Liste satırı renkleri.
				'hfb_hamburger_menu_link_color'        => '#AABBCC',
				'hfb_hamburger_menu_hover_color'       => 'javascript:alert(1)',
				'hfb_hamburger_menu_divider_color'     => '#123456',
				'hfb_hamburger_menu_arrow_color'       => '#654321',
				// Sosyal ikon renkleri; zemin boş = şeffaf.
				'hfb_hamburger_social_border_color'    => '#0f0f0f',
				'hfb_hamburger_social_bg_color'        => '   ',
				'hfb_hamburger_social_icon_color'      => '#ff8800',
				// Panel geneli buton varsayılanları.
				'hfb_hamburger_btn_bg_color'           => '#010203',
				'hfb_hamburger_btn_shape'              => 'hexagon',
				'hfb_hamburger_btn_font_family'        => 'Comic Sans',
				'hfb_hamburger_btn_font_size'          => '99',
				'hfb_hamburger_btn_font_weight'        => '550',
			),
			$hfb->get_hamburger_options()
		);

		qrms_assert_same( 42, (int) $temiz['panel_bg_image'], 'ek kimliği absint' );
		qrms_assert_same( 100, (int) $temiz['panel_bg_opacity'], 'opaklık üst sınıra sıkışır' );

		qrms_assert_same( 320, (int) $temiz['logo_width'], 'panel logo genişliği üst sınıra sıkışır' );
		qrms_assert_same( 0, (int) $temiz['logo_height_auto'], 'kutu işaretsizken otomatik oran kapalı' );
		qrms_assert_same( 90, (int) $temiz['logo_height'], 'sabit yükseklik korunur' );

		// Panel logosunun alt sınırı header'ın 80px'i değil 50px'tir.
		$dar = $hfb->sanitize_hamburger_input(
			array( 'hfb_hamburger_logo_width' => '55' ),
			$hfb->get_hamburger_options()
		);
		qrms_assert_same( 55, (int) $dar['logo_width'], '50–80 arası değer kabul edilir' );

		$cok_dar = $hfb->sanitize_hamburger_input(
			array( 'hfb_hamburger_logo_width' => '10' ),
			$hfb->get_hamburger_options()
		);
		qrms_assert_same( 50, (int) $cok_dar['logo_width'], 'panel logo genişliği 50px alt sınırına sıkışır' );

		$otomatik = $hfb->sanitize_hamburger_input(
			array( 'hfb_hamburger_logo_height_auto' => '1' ),
			$hfb->get_hamburger_options()
		);
		qrms_assert_same( 1, (int) $otomatik['logo_height_auto'], 'otomatik oran açık' );
		qrms_assert_same( 0, (int) $otomatik['logo_height'], 'otomatik oranda yükseklik sıfırlanır' );

		qrms_assert_same( '#AABBCC', $temiz['menu_link_color'], 'geçerli hex olduğu gibi korunur' );
		qrms_assert_same( '#c9a84c', $temiz['menu_hover_color'], 'geçersiz renk varsayılana düşer' );
		qrms_assert_same( '#123456', $temiz['menu_divider_color'], 'ayraç rengi' );
		qrms_assert_same( '#654321', $temiz['menu_arrow_color'], 'ok rengi' );

		qrms_assert_same( '#0f0f0f', $temiz['social_border_color'], 'sosyal çerçeve rengi' );
		qrms_assert_same( '', $temiz['social_bg_color'], 'boş bırakılan zemin şeffaf kalır' );
		qrms_assert_same( '#ff8800', $temiz['social_icon_color'], 'sosyal glyph rengi' );

		qrms_assert_same( '#010203', $temiz['btn_bg_color'], 'buton zemini' );
		qrms_assert_same( 'pill', $temiz['btn_shape'], 'bilinmeyen şekil varsayılana düşer' );
		qrms_assert_same( 'Playfair Display', $temiz['btn_font_family'], 'bilinmeyen font reddedildi' );
		qrms_assert_same( 32, (int) $temiz['btn_font_size'], 'punto üst sınır' );
		qrms_assert_same( 600, (int) $temiz['btn_font_weight'], 'geçersiz kalınlık varsayılan' );

		// Yazı adımı ve bloklar bu adımdan etkilenmez.
		qrms_assert_same( '#f5f0e8', $temiz['font_color'], 'yazı rengi korunur' );
		qrms_assert_true( count( $temiz['blocks'] ) > 0, 'blok listesi korunur' );
	}
);

qrms_test(
	'Görünüm ayarları panel CSS değişkeni olarak basılır',
	function () {
		$hfb       = qrms_hfb();
		$opts      = $hfb->get_header_options();
		$hamburger = $hfb->get_hamburger_options();

		// Varsayılan hâl: görsel yok, sosyal zemin şeffaf.
		$varsayilan = $hfb->render_header( $opts, $hamburger );

		qrms_assert_contains( '--hfb-panel-logo-w:120px', $varsayilan, 'panel logo genişliği' );
		qrms_assert_contains( '--hfb-panel-logo-h:auto', $varsayilan, 'otomatik oran' );
		qrms_assert_contains( '--hfb-panel-menu-color:#f5f0e8', $varsayilan, 'satır metin rengi' );
		qrms_assert_contains( '--hfb-panel-menu-divider:#c9a84c', $varsayilan, 'ayraç rengi' );
		qrms_assert_contains( '--hfb-panel-btn-radius:999px', $varsayilan, 'buton şekli yarıçapa çevrilir' );
		qrms_assert_true(
			false === strpos( $varsayilan, '--hfb-panel-social-bg' ),
			'şeffaf zemin için değişken hiç basılmaz'
		);
		qrms_assert_true(
			false === strpos( $varsayilan, 'hfb-mobile-panel__bg' ),
			'görsel yokken arka plan katmanı basılmaz'
		);

		$hamburger['panel_bg_image']      = 42;
		$hamburger['panel_bg_opacity']    = 40;
		$hamburger['social_bg_color']     = '#101014';
		$hamburger['menu_arrow_color']    = '#ff0000';
		$hamburger['logo_width']          = 200;
		$hamburger['btn_shape']           = 'square';

		$ozel = $hfb->render_header( $opts, $hamburger );

		qrms_assert_contains( 'hfb-mobile-panel__bg', $ozel, 'arka plan katmanı basılır' );
		qrms_assert_contains( '--hfb-panel-bg-image:url(https://restoran.test', $ozel, 'görsel adresi' );
		qrms_assert_contains( '--hfb-panel-bg-opacity:0.4', $ozel, 'opaklık 0–1 aralığına çevrilir' );
		qrms_assert_contains( '--hfb-panel-social-bg:#101014', $ozel, 'sosyal zemin rengi' );
		qrms_assert_contains( '--hfb-panel-menu-arrow:#ff0000', $ozel, 'ok rengi' );
		qrms_assert_contains( '--hfb-panel-logo-w:200px', $ozel, 'panel logo ölçüsü (tek set)' );
		qrms_assert_contains( '--hfb-panel-btn-radius:0', $ozel, 'köşeli buton' );
	}
);

qrms_test(
	'buton bloğu kendi ayarını taşımıyorsa panel varsayılanını kullanır',
	function () {
		$hfb = qrms_hfb();

		// Panel varsayılanları değiştirilir; blok yalnızca etiket taşır.
		update_option(
			'hfb_hamburger_options',
			array(
				'btn_bg_color'   => '#00ff00',
				'btn_text_color' => '#111111',
				'btn_shape'      => 'square',
				'btn_font_size'  => 22,
				'blocks'         => array(
					array(
						'id'      => 'blk_1',
						'type'    => 'button',
						'enabled' => true,
						'align'   => 'center',
						'label'   => 'Rezervasyon',
					),
				),
			)
		);

		$hamburger = $hfb->get_hamburger_options();
		$html      = $hfb->render_header( $hfb->get_header_options(), $hamburger );

		qrms_assert_contains( 'background-color:#00ff00', $html, 'panel varsayılanı butona iner' );
		qrms_assert_contains( 'color:#111111', $html, 'panel yazı rengi' );
		qrms_assert_contains( 'font-size:22px', $html, 'panel punto varsayılanı' );
		qrms_assert_contains( 'hfb-mobile-panel__btn--square', $html, 'panel şekil varsayılanı' );

		// Blok kendi rengini taşıyorsa panel varsayılanını ezer.
		$hamburger['blocks'][0]['bg_color'] = '#0000ff';
		$ezilmis = $hfb->render_header( $hfb->get_header_options(), $hamburger );

		qrms_assert_contains( 'background-color:#0000ff', $ezilmis, 'blok kendi rengini kullanır' );
	}
);

qrms_test(
	'ayar sayfası sekmeleri ve adım başlıklarını basar',
	function () {
		$hfb = qrms_hfb();
		ob_start();
		$hfb->render_admin_page();
		$html = (string) ob_get_clean();

		qrms_assert_contains( 'data-hfb-tab="header"', $html, 'Header sekmesi' );
		qrms_assert_contains( 'data-hfb-tab="hamburger"', $html, 'Hamburger Menü sekmesi' );
		qrms_assert_contains( '1. Logo Boyutu', $html, 'header adım 1' );
		qrms_assert_contains( '2. Header Görünümü', $html, 'header adım 2' );
		qrms_assert_contains( '3. İkon ve Buton Renkleri', $html, 'header adım 3' );
		qrms_assert_contains( '4. Yerleşim / Boşluklar', $html, 'header adım 4' );
		qrms_assert_contains( 'hfb_header_content_width', $html, 'içerik genişliği kaydırıcısı' );
		qrms_assert_contains( 'hfb_header_padding_x_mobile', $html, 'mobil yan boşluk kaydırıcısı' );
		qrms_assert_contains( '1. Açılış Davranışı', $html, 'hamburger adım 1' );
		qrms_assert_contains( '2. İçerik Blokları ve Sıralama', $html, 'hamburger adım 2' );
		qrms_assert_contains( '3. Panel Görünümü', $html, 'hamburger adım 3' );
		qrms_assert_contains( '4. Yazı Tipi ve Renk', $html, 'hamburger adım 4' );
		qrms_assert_contains( '1. Logo ve Slogan', $html, 'footer adım 1' );
		qrms_assert_contains( '2. Hızlı Menü', $html, 'footer adım 2' );
		qrms_assert_contains( '3. Çalışma Saatleri', $html, 'footer adım 3' );
		qrms_assert_contains( '4. İletişim Bilgileri', $html, 'footer adım 4' );
		qrms_assert_contains( '5. Garson / Hesap Butonu', $html, 'footer adım 5' );
		qrms_assert_true( false === strpos( $html, '3. Çalışma Saatleri ve İletişim' ), 'eski birleşik saatler+iletişim başlığı yok' );
		qrms_assert_true( false === strpos( $html, '4. Garson / Hesap Butonu' ), 'çağrı artık 5. adım' );
		qrms_assert_contains( 'Başlık yazı rengi', $html, 'başlık rengi etiketi ayrışmış' );
		qrms_assert_contains( 'Link yazı rengi', $html, 'link rengi etiketi ayrışmış' );
		qrms_assert_contains( 'Gün/saat yazı rengi', $html, 'saat satır rengi etiketi ayrışmış' );
		qrms_assert_contains( 'İletişim satır yazı rengi', $html, 'iletişim satır rengi etiketi ayrışmış' );
		qrms_assert_contains( 'id="hfb-steps-footer"', $html, 'footer adım şeridi' );
		qrms_assert_contains( 'Adım 1/5: Logo ve Slogan', $html, 'footer ilerleme 5 adım' );

		if ( preg_match( '/id="hfb-panel-footer"(.*?)<div class="hfb-tab-panel/s', $html, $footer_panel ) ) {
			$footer_html = $footer_panel[1];

			qrms_assert_contains( 'data-step="5"', $footer_html, 'çağrı data-step=5' );

			if ( preg_match( '/data-step="3"[^>]*>(.*?)<div class="qrms-card hfb-step" data-step="4"/s', $footer_html, $saatler ) ) {
				qrms_assert_contains( 'hfb_footer_hours_title', $saatler[1], 'saatler adımında saat başlığı' );
				qrms_assert_true( false === strpos( $saatler[1], 'hfb_footer_contact_title' ), 'saatler adımında iletişim yok' );
				qrms_assert_true( false === strpos( $saatler[1], 'hfb_footer_address' ), 'saatler adımında adres yok' );
			} else {
				qrms_assert_true( false, 'footer adım 3 kartı bulunamadı' );
			}

			if ( preg_match( '/data-step="4"[^>]*>(.*?)<div class="qrms-card hfb-step" data-step="5"/s', $footer_html, $iletisim ) ) {
				qrms_assert_contains( 'hfb_footer_contact_title', $iletisim[1], 'iletişim adımında iletişim başlığı' );
				qrms_assert_contains( 'hfb_footer_address', $iletisim[1], 'iletişim adımında adres' );
				qrms_assert_true( false === strpos( $iletisim[1], 'hfb_footer_hours_title' ), 'iletişim adımında saat başlığı yok' );
			} else {
				qrms_assert_true( false, 'footer adım 4 kartı bulunamadı' );
			}

			qrms_assert_contains( 'hfb_footer_call_enabled', $footer_html, 'çağrı alanı footer panelinde' );
			qrms_assert_contains( 'hfb_footer_call_garson_label', $footer_html, 'garson metni duruyor' );
		} else {
			qrms_assert_true( false, 'footer paneli bulunamadı' );
		}
		qrms_assert_contains( 'hfb-color-picker', $html, 'renk seçici' );
		qrms_assert_contains( 'hfb-block-sortable', $html, 'sürükle-bırak liste' );
		qrms_assert_contains( 'Masaüstü Önizleme', $html, 'masaüstü önizleme düğmesi' );
		qrms_assert_contains( 'Önizlemede Aç', $html, 'hamburger panel önizleme' );
		qrms_assert_true( false === strpos( $html, 'Tasarım sabittir' ), 'eski sabit tasarım metni yok' );
	}
);

qrms_test(
	'footer adım sihirbazı DOM kart sayısını kullanır, sabit 4 yoktur',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/js/admin.js' );

		qrms_assert_contains( 'var toplam = $steps.length;', $js, 'toplam DOM\'daki kart sayısı' );
		qrms_assert_contains( 'function sinirla(adimNo)', $js, 'adım sınır kontrolü' );
		qrms_assert_contains( '$next.toggle(mevcut < toplam);', $js, 'son adımda Devam Et gizlenir' );
		qrms_assert_true( false === strpos( $js, 'var toplam = 4' ), 'sabit 4 adım yok' );
		qrms_assert_true( false === strpos( $js, "'4/4'" ), 'sabit 4/4 metni yok' );
	}
);

qrms_test(
	'footer dört sütun basar: marka, menü, saatler, iletişim',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_footer_options();
		$opts['description'] = 'Lezzetin adresi.';
		$opts['menu_id']     = 7;
		$opts['phone']       = '0850 000 00 00';
		$opts['email']       = 'info@ornek.test';
		$opts['address']     = "Atatürk Cad.\nNo: 12";

		$html = $hfb->render_footer( $opts );

		qrms_assert_contains( 'hfb-footer__col--brand', $html, 'marka sütunu' );
		qrms_assert_contains( 'Lezzetin adresi.', $html, 'açıklama' );
		qrms_assert_contains( 'hfb-footer__col--links', $html, 'hızlı menü sütunu' );
		qrms_assert_contains( 'Hızlı Menü', $html, 'varsayılan menü başlığı' );
		qrms_assert_contains( 'hfb-footer__col--hours', $html, 'saat sütunu (modül yüklü)' );
		qrms_assert_contains( 'Çalışma Saatlerimiz', $html, 'saat başlığı' );
		qrms_assert_contains( 'hfb-footer__hours-day', $html, 'gün adı' );
		qrms_assert_contains( 'hfb-footer__col--contact', $html, 'iletişim sütunu' );
		qrms_assert_contains( 'İletişim', $html, 'iletişim başlığı' );
		qrms_assert_contains( 'hfb-icon--contact', $html, 'iletişim ikonu' );
		qrms_assert_contains( 'Atatürk Cad.', $html, 'adres' );
		qrms_assert_contains( '--hfb-footer-brand-align:left', $html, 'CSS değişkeni' );
		qrms_assert_true( false === strpos( $html, 'data-qmo-cagri' ), 'çağrı kapalıyken buton yok' );
	}
);

qrms_test(
	'sanitize_footer_input yeni alanları temizler, mevcutları korur',
	function () {
		$hfb = qrms_hfb();
		$cur = $hfb->get_footer_options();
		$cur['brand_line1'] = 'Kayıtlı Marka';
		$cur['phone']       = '0212 111 22 33';

		$temiz = $hfb->sanitize_footer_input(
			array(
				'hfb_footer_brand_line1'             => '  Yeni Marka  ',
				'hfb_footer_address'                 => "Cadde 1\n<script>x</script>",
				'hfb_footer_links_title'             => 'Hızlı Menü',
				'hfb_footer_hours_title'             => 'Çalışma Saatlerimiz',
				'hfb_footer_contact_title'           => 'İletişim',
				'hfb_footer_phone'                   => '0212 111 22 33',
				'hfb_footer_email'                   => 'info@ornek.test',
				'hfb_footer_brand_align'             => 'center',
				'hfb_footer_links_align'             => 'justify',
				'hfb_footer_brand_font_family'       => 'Comic Sans',
				'hfb_footer_brand_font_size_desktop' => '99',
				'hfb_footer_brand_font_color'        => '#abcdef',
				'hfb_footer_logo_width_desktop'      => '999',
				'hfb_footer_call_enabled'            => '1',
				'hfb_footer_call_garson_label'       => '  Garson  ',
				'hfb_footer_btn_bg_color'            => 'red',
				'hfb_footer_btn_shape'               => 'hexagon',
				'hfb_footer_btn_font_size'           => '12',
			),
			$cur
		);

		qrms_assert_same( 'Yeni Marka', $temiz['brand_line1'], 'marka temizlendi' );
		qrms_assert_same( '0212 111 22 33', $temiz['phone'], 'telefon durur' );
		qrms_assert_contains( 'Cadde 1', $temiz['address'], 'adres durur' );
		qrms_assert_true( false === strpos( $temiz['address'], '<script>' ), 'script yok' );
		qrms_assert_same( 'info@ornek.test', $temiz['email'], 'e-posta durur' );
		qrms_assert_same( 'center', $temiz['brand_align'], 'hizalama' );
		qrms_assert_same( 'left', $temiz['links_align'], 'geçersiz hiza varsayılan' );
		qrms_assert_same( 'Playfair Display', $temiz['brand_font_family'], 'bilinmeyen font reddedildi' );
		qrms_assert_same( 32, (int) $temiz['brand_font_size_desktop'], 'punto üst sınır' );
		qrms_assert_same( '#abcdef', $temiz['brand_font_color'], 'renk' );
		qrms_assert_same( 320, (int) $temiz['logo_width_desktop'], 'logo genişlik üst sınır' );
		qrms_assert_same( 1, (int) $temiz['call_enabled'], 'çağrı açık' );
		qrms_assert_same( 'Garson', $temiz['call_garson_label'], 'buton metni' );
		qrms_assert_same( '#c9a84c', $temiz['btn_bg_color'], 'geçersiz buton rengi varsayılan' );
		qrms_assert_same( 'pill', $temiz['btn_shape'], 'geçersiz şekil varsayılan' );
		qrms_assert_same( 12, (int) $temiz['btn_font_size'], 'buton punto' );
		qrms_assert_same( 'QR MENU', $hfb->get_footer_options()['brand_line1'], 'depo değişmedi' );
	}
);

qrms_test(
	'garson butonları oturum yokken uyarı basar, önizlemede stilli görünür',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_footer_options();
		$opts['call_enabled']      = 1;
		$opts['call_garson_label'] = 'Garson Çağır';
		$opts['call_hesap_label']  = 'Hesap İste';

		$html = $hfb->render_footer( $opts );
		qrms_assert_contains( 'Lütfen QR kodunu okutarak masanızdan erişin', $html, 'oturumsuz uyarı' );
		qrms_assert_true( false === strpos( $html, 'data-qmo-cagri' ), 'sahte çağrı yok' );

		$GLOBALS['qrms_test']['is_admin'] = true;
		$onizleme = $hfb->render_footer( $opts );
		qrms_assert_contains( 'hfb-footer__call-btn', $onizleme, 'önizlemede buton' );
		qrms_assert_contains( 'Garson Çağır', $onizleme, 'garson metni' );
		qrms_assert_contains( 'Hesap İste', $onizleme, 'hesap metni' );
		qrms_assert_true( false === strpos( $onizleme, 'data-qmo-cagri' ), 'önizlemede AJAX bağlanmaz' );
		qrms_assert_contains( 'hfb-icon--call', $onizleme, 'önizlemede zil/fiş ikonu' );
		qrms_assert_contains( 'hfb-footer__cq', $onizleme, 'sütun kabı çağrı çubuğunun dışında' );
	}
);

qrms_test(
	'footer çağrı buton stili admin altı alanı CSS değişkenine basılır',
	function () {
		$hfb  = qrms_hfb();
		$opts = $hfb->get_footer_options();
		$opts['call_enabled']    = 1;
		$opts['btn_bg_color']    = '#112233';
		$opts['btn_text_color']  = '#fefefe';
		$opts['btn_shape']       = 'rounded';
		$opts['btn_font_family'] = 'Inter';
		$opts['btn_font_size']   = 18;
		$opts['btn_font_weight'] = 700;

		$html = $hfb->render_footer( $opts );

		qrms_assert_contains( '--hfb-btn-bg:#112233', $html, 'zemin rengi değişkeni' );
		qrms_assert_contains( '--hfb-btn-color:#fefefe', $html, 'yazı rengi değişkeni' );
		qrms_assert_contains( '--hfb-btn-radius:10px', $html, 'yuvarlatılmış şekil' );
		qrms_assert_contains( '--hfb-btn-font:', $html, 'font yığını basılır' );
		qrms_assert_contains( 'Inter', $html, 'Inter font yığını' );
		qrms_assert_contains( '--hfb-btn-size:18px', $html, 'punto' );
		qrms_assert_contains( '--hfb-btn-weight:700', $html, 'kalınlık' );
		qrms_assert_contains( 'hfb-footer__call--warn', $html, 'oturumsuz uyarı sınıfı (sticky seçici)' );
		qrms_assert_true( false === strpos( $html, 'data-qmo-cagri' ), 'oturumsuzken AJAX yok' );

		$GLOBALS['qrms_test']['is_admin'] = true;
		$onizleme = $hfb->render_footer( $opts );
		qrms_assert_contains( '--hfb-btn-bg:#112233', $onizleme, 'önizlemede zemin' );
		qrms_assert_contains( '--hfb-btn-radius:10px', $onizleme, 'önizlemede şekil' );
		qrms_assert_contains( 'hfb-icon--call', $onizleme, 'önizlemede ikon' );
		qrms_assert_true( false === strpos( $onizleme, 'data-qmo-cagri' ), 'önizlemede AJAX yok' );
	}
);

qrms_test(
	'AJAX önizleme footer buton stil alanlarını döndürür',
	function () {
		$hfb = qrms_hfb();
		$GLOBALS['qrms_test']['is_admin'] = true;

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_footer_call_enabled'     => '1',
				'hfb_footer_call_garson_label' => 'Garson',
				'hfb_footer_btn_bg_color'     => '#445566',
				'hfb_footer_btn_text_color'   => '#aabbcc',
				'hfb_footer_btn_shape'        => 'square',
				'hfb_footer_btn_font_family'  => 'Poppins',
				'hfb_footer_btn_font_size'    => '20',
				'hfb_footer_btn_font_weight'  => '500',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		$footer = $yanit['data']['footer'];
		qrms_assert_contains( '--hfb-btn-bg:#445566', $footer, 'önizleme zemin' );
		qrms_assert_contains( '--hfb-btn-color:#aabbcc', $footer, 'önizleme yazı' );
		qrms_assert_contains( '--hfb-btn-radius:0', $footer, 'önizleme köşeli' );
		qrms_assert_contains( 'Poppins', $footer, 'önizleme font' );
		qrms_assert_contains( '--hfb-btn-size:20px', $footer, 'önizleme punto' );
		qrms_assert_contains( '--hfb-btn-weight:500', $footer, 'önizleme kalınlık' );
		qrms_assert_contains( 'hfb-footer__call-btn', $footer, 'önizlemede buton (admin)' );
	}
);

qrms_test(
	'footer çağrı butonu CSS admin değişkenlerini okur, sticky her viewport\'ta',
	function () {
		$css = file_get_contents(
			QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css'
		);

		qrms_assert_contains( 'background-color: var(--hfb-btn-bg, var(--hfb-gold))', $css, 'zemin değişkeni' );
		qrms_assert_contains( 'border: 1.5px solid var(--hfb-btn-bg, var(--hfb-gold))', $css, 'kenarlık accent' );
		qrms_assert_contains( 'border-radius: var(--hfb-btn-radius, 999px)', $css, 'şekil değişkeni, sabit 50px yok' );
		qrms_assert_contains( 'font-family: var(--hfb-btn-font, inherit)', $css, 'font değişkeni' );
		qrms_assert_contains( 'font-size: var(--hfb-btn-size, 14px)', $css, 'punto değişkeni' );
		qrms_assert_contains( 'font-weight: var(--hfb-btn-weight, 600)', $css, 'kalınlık değişkeni' );
		qrms_assert_contains( 'linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(0, 0, 0, 0.08))', $css, 'gradient overlay' );
		qrms_assert_contains( 'background-blend-mode: overlay', $css, 'blend overlay' );
		qrms_assert_contains( 'transform: translateY(-3px)', $css, 'masaüstü hover kalkış' );
		qrms_assert_contains( '@keyframes btn-spin', $css, 'spinner animasyonu' );
		qrms_assert_contains( '.hfb-footer__call-btn.is-disabled', $css, 'disabled durumu' );
		qrms_assert_contains( '.hfb-footer__call-btn.is-success', $css, 'success durumu' );
		// Çubuk artık masaüstü dâhil her genişlikte sabit: kurallar bir
		// kırılımın içinde değil, satır başında (girintisiz) global durur.
		qrms_assert_true( false === strpos( $css, '@media (max-width: 767px)' ), 'mobil-özel sticky kırılımı kalktı' );
		qrms_assert_contains( "\n.hfb-footer__call-wrap:has(.qmo-cagri-bar),", $css, 'butonlu wrap her viewport\'ta sticky' );
		qrms_assert_contains( "\n.hfb-footer__call-wrap:has(.hfb-footer__call--warn) {", $css, 'uyarı wrap her viewport\'ta sticky' );
		qrms_assert_contains( 'position: fixed', $css, 'ekrana sabit' );
		qrms_assert_contains( "\nbody:has(.hfb-footer__call-wrap .qmo-cagri-bar):not(.wp-admin),", $css, 'body boşluğu kırılımsız' );
		qrms_assert_contains( 'padding-bottom: calc(66px + env(safe-area-inset-bottom, 0px))', $css, 'body boşluğu' );
		qrms_assert_contains( "\n.wp-admin .hfb-footer__call-wrap:has(.qmo-cagri-bar),", $css, 'admin önizlemesi akışta kalır' );
		qrms_assert_contains( 'border-radius: 12px', $css, 'köşeli-yuvarlak buton' );
		qrms_assert_contains( 'flex: 1 1 0', $css, 'iki buton eşit genişlik' );
		qrms_assert_contains( 'transform: scale(0.97)', $css, ':active dokunma' );
		qrms_assert_contains( 'background: rgba(10, 10, 12, 0.82)', $css, 'sticky bar zemini' );
		qrms_assert_true( false === strpos( $css, 'border-radius: 50px' ), 'sabit 50px radius yok' );
		qrms_assert_true( false === strpos( $css, '#d4af37' ), 'hardcoded altın yok' );
		qrms_assert_contains( 'container-name: hfb-footer', $css, 'footer kap sorgusu durur' );
		qrms_assert_contains( '.hfb-footer__cq', $css, 'kap çağrı çubuğunun dışında' );

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );
		qrms_assert_contains( '[data-qmo-cagri]', $js, 'tıklama seçicisi durur' );
		qrms_assert_contains( "getAttribute( 'data-qmo-cagri' )", $js, 'tip okuma durur' );
		qrms_assert_contains( "'hesap' === tip", $js, 'hesap action durur' );

		$kayit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-settings-page.php' );
		qrms_assert_contains( "function_exists( 'qmo_tum_onbellek_temizle' )", $kayit, 'kaydet sonrası ortak önbellek temizliği' );
		qrms_assert_contains( 'qmo_tum_onbellek_temizle()', $kayit, 'qmo_tum_onbellek_temizle çağrılır' );

		$yardimci = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' );
		qrms_assert_contains( "function_exists( 'rocket_clean_domain' )", $yardimci, 'WP Rocket ortak yardımcıda' );
	}
);

qrms_test(
	'AJAX önizleme footer saat ve başlık alanlarını döndürür',
	function () {
		$hfb = qrms_hfb();

		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_footer_links_title'   => 'Hızlı Menü',
				'hfb_footer_hours_title'   => 'Çalışma Saatlerimiz',
				'hfb_footer_contact_title' => 'İletişim',
				'hfb_footer_address'       => 'Test Sokak 1',
				'hfb_footer_copyright'     => '© 2026 Önizleme',
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Hızlı Menü', $yanit['data']['footer'], 'menü başlığı' );
		qrms_assert_contains( 'Çalışma Saatlerimiz', $yanit['data']['footer'], 'saat başlığı' );
		qrms_assert_contains( 'Test Sokak 1', $yanit['data']['footer'], 'adres' );
		qrms_assert_contains( '© 2026 Önizleme', $yanit['data']['footer'], 'telif' );
	}
);


echo "\nKampanya Banner slider\n";

qrms_test(
	'banner modülü ürün vitrini slider\'ından bağımsızdır',
	function () {
		// İki slider ayrı dosyalarda, ayrı prefix'lerle durur: birinin
		// stili/betiği diğerinin seçicilerine dokunmaz.
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		foreach ( array( 'admin-cpt-banner.php', 'shortcode-banner-slider.php', 'frontend-banner-slider.css', 'frontend-banner-slider.js' ) as $dosya ) {
			qrms_assert_true( file_exists( $dizin . $dosya ), $dosya . ' var' );
		}

		$css = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		$js  = file_get_contents( $dizin . 'frontend-banner-slider.js' );

		qrms_assert_false( strpos( $css, '.qmo-slider-' ) !== false, 'banner css ürün slider seçicisine dokunmaz' );
		qrms_assert_false( strpos( $js, 'qmo-slider-' ) !== false, 'banner betiği ürün slider seçicisine dokunmaz' );

		// 16:9; slayt track'in iç genişliğinin tamamı (peek açıkken %88).
		qrms_assert_contains( 'aspect-ratio: 16 / 9', $css, 'banner oranı' );
		qrms_assert_contains( 'flex: 0 0 100%', $css, 'slayt track iç genişliğini kaplar' );

		// Autoplay + IntersectionObserver + hareket tercihi + swipe.
		qrms_assert_contains( 'IntersectionObserver', $js, 'viewport tetikli autoplay' );
		qrms_assert_contains( 'prefers-reduced-motion', $js, 'hareket tercihi' );
		qrms_assert_contains( 'touchend', $js, 'swipe' );

		// Bootstrap: yeni CPT ve kısa kod ana dosyadan başlatılır.
		$boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qmo-one-cikan-slider.php' );
		qrms_assert_contains( 'QMO_Banner_CPT::init()', $boot, 'CPT başlatılır' );
		qrms_assert_contains( 'QMO_Shortcode_Banner_Slider::init()', $boot, 'kısa kod başlatılır' );
	}
);

qrms_test(
	'banner kaydı nonce/yetki geçer, görselsizken sessizce basılmaz',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$cpt   = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$kod   = file_get_contents( $dizin . 'shortcode-banner-slider.php' );

		// Kaydetme güvenliği mevcut qmo_slide deseninin aynısı.
		qrms_assert_contains( 'wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD )', $cpt, 'nonce alanı' );
		qrms_assert_contains( 'wp_verify_nonce', $cpt, 'nonce doğrulaması' );
		qrms_assert_contains( 'current_user_can( \'edit_post\', $post_id )', $cpt, 'yetki kontrolü' );
		qrms_assert_contains( 'esc_url_raw', $cpt, 'bağlantı temizliği' );

		// Görseli olmayan banner atlanır; hiç kalmazsa kısa kod boş döner.
		qrms_assert_contains( 'if ( empty( $banners ) ) return \'\';', $kod, 'sessiz fallback' );

		// Boyut uyarıları (GÖREV 3).
		qrms_assert_contains( '1600x900px (16:9), JPG/WEBP, maksimum 300KB', $cpt, 'banner boyut notu' );

		$slide = file_get_contents( $dizin . 'admin-cpt-slide.php' );
		qrms_assert_contains( '1080x1080px (1:1 kare), JPG/WEBP, maksimum 200KB', $slide, 'ürün görseli boyut notu' );
	}
);

qrms_test(
	'sıra no kaydı save_post içinde sonsuz özyinelemeye girmez',
	function () {
		// REGRESYON: wp_update_post() `save_post_*` kancasını yeniden tetikler.
		// Kanca kaldırılmadan çağrılırsa save_meta -> wp_update_post -> save_meta
		// döngüsü bellek tükenmesiyle wp-admin/post.php üzerinde fatal error
		// verirdi (banner görseli kaydedilirken "ciddi bir sorun çıktı").
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		$beklenen = array(
			'admin-cpt-banner.php' => "remove_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save_meta' ] );",
			'admin-cpt-slide.php'  => "remove_action( 'save_post_qmo_slide', [ __CLASS__, 'save_meta' ] );",
		);

		foreach ( $beklenen as $dosya => $kaldirma ) {
			$kod = file_get_contents( $dizin . $dosya );

			$kaldirma_yeri = strpos( $kod, $kaldirma );
			$guncelleme    = strpos( $kod, 'wp_update_post( [' );
			// strrpos: aynı add_action satırı init() içinde de geçer, aranan
			// olan save_meta'daki geri ekleme dosyadaki son örnektir.
			$geri_ekleme   = strrpos( $kod, str_replace( 'remove_action', 'add_action', $kaldirma ) );

			qrms_assert_true( false !== $kaldirma_yeri, $dosya . ': kanca kaldırılıyor' );
			qrms_assert_true( false !== $geri_ekleme, $dosya . ': kanca geri ekleniyor' );

			// Sıra: kaldır -> güncelle -> geri ekle.
			qrms_assert_true( $kaldirma_yeri < $guncelleme, $dosya . ': kaldırma wp_update_post öncesinde' );
			qrms_assert_true( $guncelleme < $geri_ekleme, $dosya . ': geri ekleme wp_update_post sonrasında' );
		}
	}
);

qrms_test(
	'banner yönetimi kendi sayfasında, Fiyat Kampanyaları ve Menü Görünümü temiz',
	function () {
		// İSİMLENDİRME: "Kampanya" = banner görselleri, "Fiyat Kampanyası" =
		// toplu zam/indirim. İkisi ayrı ekranlardır ve ortak kodu yoktur;
		// ön yüzdeki [qmo_banner_slider] kısa kodu bu taşımadan etkilenmez.
		$dizin    = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$sayfa    = file_get_contents( $dizin . 'trait-admin-pages.php' );
		$kampanya = file_get_contents( $dizin . 'trait-kampanya-admin.php' );
		$banner   = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );

		// Fiyat Kampanyaları sayfasında banner'dan eser kalmadı.
		// (Dosyada yalnızca "buraya geri eklenmemeli" notu kalır; kod kalmadı.)
		qrms_assert_false( strpos( $kampanya, 'function render_banner_section' ) !== false, 'liste bölümü kampanya ekranından çıktı' );
		qrms_assert_false( strpos( $kampanya, '$this->render_banner_section();' ) !== false, 'kampanya listesi artık banner basmıyor' );
		qrms_assert_false( strpos( $kampanya, 'function render_banner_settings_page' ) !== false, 'ayar ekranı kampanya ekranından çıktı' );
		qrms_assert_false( strpos( $kampanya, 'function handle_banner_settings_save' ) !== false, 'kaydetme ucu kampanya ekranından çıktı' );
		qrms_assert_false( strpos( $kampanya, 'qmo_banner_slide' ) !== false, 'kampanya ekranı banner CPT\'sine bakmıyor' );
		qrms_assert_false( strpos( $kampanya, 'QMO_Banner_Slider_Settings' ) !== false, 'kampanya ekranı banner ayarına bakmıyor' );

		// Fiyat tarafının kendi içeriği bozulmadan duruyor.
		qrms_assert_contains( 'private function render_kampanya_list()', $kampanya, 'fiyat kampanyası listesi' );
		qrms_assert_contains( 'Kampanyalarım', $kampanya, 'geçmiş kampanya kartı' );
		qrms_assert_contains( '+ Yeni Kampanya', $kampanya, 'yeni kampanya butonu' );
		qrms_assert_contains( 'rma_kampanya_geri_al', $kampanya, 'geri alma ucu' );

		// Sihirbaz KENDİ sayfasında: get_subpages()'te bağımsız bir slug'ı var.
		qrms_assert_contains( "'qrms-rm-kampanya-banner' => [", $sayfa, 'sayfa kayıtlı' );
		qrms_assert_contains( "'render'     => 'render_kampanya_banner_page'", $sayfa, 'render metodu bağlı' );
		qrms_assert_contains( 'public function render_kampanya_banner_page()', $banner, 'sayfa render metodu tanımlı' );
		qrms_assert_contains( 'public function render_banner_wizard_section()', $banner, 'sihirbaz gövdesi' );

		// Hub'da kendi kartı var.
		qrms_assert_contains( "\$from_sub( \$this, 'qrms-rm-kampanya-banner' )", $sayfa, 'hub kartı' );

		// Menü Görünümü sayfasında banner'a dair HİÇBİR iz kalmadı.
		qrms_assert_false( strpos( $sayfa, 'render_banner_wizard_section' ) !== false, 'görünüm sayfası sihirbazı basmıyor' );
		qrms_assert_false( strpos( $sayfa, 'banner_anchor' ) !== false, 'görünüm sayfasında banner çapası yok' );

		// Sihirbaz adımları da yeni sayfaya bakıyor, Menü Görünümü'ne değil.
		qrms_assert_contains( "'qrms-rm-kampanya-banner',", $banner, 'adım adresleri kendi sayfasına' );
		qrms_assert_false( strpos( $banner, "'qrms-rm-gorunum'" ) !== false, 'sihirbaz görünüm sayfasına link vermiyor' );

		// Üç adım da tanımlı.
		foreach ( array( 'ozet', 'kampanyalar', 'olustur' ) as $adim ) {
			qrms_assert_contains( "'" . $adim . "'", $banner, $adim . ' adımı tanımlı' );
		}
		qrms_assert_contains( 'private function render_banner_adim_ozet()', $banner, '1. adım' );
		qrms_assert_contains( 'private function render_banner_adim_kampanyalar()', $banner, '2. adım' );
		qrms_assert_contains( 'private function render_banner_adim_olustur()', $banner, '3. adım' );

		// Liste olduğu gibi taşındı: kısa kod notu ve iki eylem butonu.
		qrms_assert_contains( '[qmo_banner_slider]', $banner, 'kısa kod açıklaması' );
		qrms_assert_contains( 'Yeni Kampanya Ekle', $banner, 'ekleme butonu' );
		qrms_assert_contains( 'Tüm Kampanyaları Yönet', $banner, 'yönetim butonu' );

		// Veri katmanı DEĞİŞMEDİ: CPT ve meta anahtarları sabit üzerinden.
		qrms_assert_contains( 'QMO_Banner_CPT::POST_TYPE', $banner, 'CPT slug\'ı sabitten' );
		qrms_assert_contains( 'QMO_Banner_CPT::META_IMAGE', $banner, 'görsel meta anahtarı sabitten' );
	}
);

qrms_test(
	'eski qrms-rm-banner-ayar adresi yeni konuma yönlendirir',
	function () {
		// Sayfa kaldırıldı ama slug silinmedi: kırık link/404 bırakılmaz.
		$sayfa = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		// Artık gerçek bir sayfa DEĞİL: get_subpages() kaydı düştü ve aynı
		// işlev iki slug'ta tutulmuyor.
		qrms_assert_false( strpos( $sayfa, "'render'     => 'render_banner_settings_page'" ) !== false, 'sayfa kaydı kaldırıldı' );
		qrms_assert_false( strpos( $sayfa, "'qrms-rm-banner-ayar' => [" ) !== false, 'eski slug artık sayfa değil' );

		// Ama eski slug hâlâ kayıtlı ve YENİ BAĞIMSIZ sayfaya yönlendiriliyor.
		qrms_assert_contains( "'qrms-rm-banner-ayar'      => [ 'qrms-rm-kampanya-banner'", $sayfa, 'eski slug yeni sayfaya yönlenir' );
		qrms_assert_contains( "[ 'banner_adim' => 'kampanyalar' ]", $sayfa, 'hedef 2. adım' );

		// Yönlendirme, tablodaki query arg'larını da taşır.
		qrms_assert_contains( '$this->admin_page_url( $target[0], $target[2] ?? [], $target[1] )', $sayfa, 'arg\'lar hedefe taşınır' );
		qrms_assert_contains( 'wp_safe_redirect(', $sayfa, 'güvenli yönlendirme' );
	}
);

qrms_test(
	'toplu kampanya görseli: canvas -> AJAX -> medya kütüphanesi + banner kaydı',
	function () {
		$banner = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-kampanya-banner-admin.php' );
		$js     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/banner-olustur.js' );
		$boot   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );

		// Uç kayıtlı ve nonce + yetki kontrolü mevcut kod tabanı desenine uyuyor.
		qrms_assert_contains( 'wp_ajax_qmo_banner_gorsel_olustur', $boot, 'AJAX ucu kayıtlı' );
		qrms_assert_contains( 'check_ajax_referer( $this->banner_olustur_nonce_action', $banner, 'nonce doğrulaması' );
		qrms_assert_contains( 'QRMS_Admin::CAPABILITY', $banner, 'yetki kontrolü' );

		// Data URI dört kademede doğrulanır; hiçbiri atlanmaz.
		qrms_assert_contains( "'data:image/png;base64,'", $banner, 'önek kontrolü' );
		qrms_assert_contains( 'base64_decode(', $banner, 'base64 çözümü' );
		qrms_assert_contains( '"\x89PNG\r\n\x1a\n"', $banner, 'PNG imza kontrolü' );
		qrms_assert_contains( 'getimagesize(', $banner, 'dosyaya yazıldıktan sonra doğrulama' );
		qrms_assert_contains( 'banner_uretim_max_byte()', $banner, 'boyut sınırı' );

		// Üretilen görsel CPT'nin BEKLEDİĞİ yere bağlanır (featured image değil,
		// _qmo_banner_gorsel_id meta'sı) ki listede ve ön yüzde görünsün.
		qrms_assert_contains( 'wp_insert_attachment(', $banner, 'medya kaydı' );
		qrms_assert_contains( 'wp_generate_attachment_metadata(', $banner, 'ek meta üretimi' );
		qrms_assert_contains( "update_post_meta( \$kayit_id, QMO_Banner_CPT::META_IMAGE", $banner, 'görsel banner kaydına bağlanır' );
		qrms_assert_contains( "'post_status' => 'publish'", $banner, 'kayıt yayına alınır' );

		// Oran seçenekleri QMO_Banner_Slider_Settings ile aynı kaynaktan gelir.
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::oranlar()', $banner, 'oran listesi tek kaynaktan' );

		// Şablonlar tek kaynakta; JS renkleri data-* üzerinden okur, sabit renk tutmaz.
		qrms_assert_contains( 'public static function banner_sablonlari()', $banner, 'şablon tanımı' );
		qrms_assert_contains( "getAttribute('data-bg-bas')", $js, 'JS rengi markup\'tan okur' );
		qrms_assert_contains( "toDataURL('image/png')", $js, 'canvas dışa aktarımı' );
	}
);

/* ---------------------------------------------------------------------------
 * Kampanya Banner — görünüm ayarları
 *
 * QMO_Banner_Slider_Settings, QMO_Slider_Settings ile aynı deseni izler ama
 * ayrı bir option'da (qmo_banner_slider_settings) ve kendi alan kümesiyle
 * durur: oran, geçiş biçimi, otomatik geçiş, oklar/noktalar ve başlık.
 * Option hiç yoksa eski görünüm korunur.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-banner-slider-settings.php';

qrms_test(
	'banner varsayılanları mevcut görünümü korur',
	function () {
		$v = QMO_Banner_Slider_Settings::varsayilanlar();

		qrms_assert_same( 1, $v['show_nav'], 'oklar açık' );
		qrms_assert_same( 1, $v['show_dots'], 'noktalar açık' );
		qrms_assert_same( 0, $v['show_title'], 'başlık kapalı — görsel tek başına basılırdı' );
		qrms_assert_same( 'slide', $v['gecis'], 'kaydırma geçişi' );
		qrms_assert_same( '16:9', $v['oran'], '16:9 oran' );
		qrms_assert_same( 4500, $v['autoplay'], 'kısa kodun eski varsayılanı' );
		qrms_assert_same( 'Playfair Display', $v['title_font'], 'Playfair' );
		qrms_assert_same( 32, $v['title_size'], 'masaüstü punto' );
		qrms_assert_same( 20, $v['title_size_mobile'], 'mobil punto' );
	}
);

qrms_test(
	'banner sanitize: oran, geçiş ve otomatik geçiş beyaz listeye çekilir',
	function () {
		$temiz = QMO_Banner_Slider_Settings::sanitize(
			array(
				'oran'     => '9:16',
				'gecis'    => 'zoom',
				'autoplay' => 999999,
			)
		);

		qrms_assert_same( '16:9', $temiz['oran'], 'bilinmeyen oran varsayılana düşer' );
		qrms_assert_same( 'slide', $temiz['gecis'], 'bilinmeyen geçiş varsayılana düşer' );
		qrms_assert_same( QMO_Banner_Slider_Settings::MAX_AUTOPLAY, $temiz['autoplay'], 'autoplay üst sınır' );

		$gecerli = QMO_Banner_Slider_Settings::sanitize(
			array(
				'oran'     => '21:9',
				'gecis'    => 'fade',
				'autoplay' => 6000,
			)
		);

		qrms_assert_same( '21:9', $gecerli['oran'], 'geçerli oran' );
		qrms_assert_same( 'fade', $gecerli['gecis'], 'geçerli geçiş' );
		qrms_assert_same( 6000, $gecerli['autoplay'], 'geçerli autoplay' );

		// 0 "kapalı" demektir: alt sınıra çekilmez.
		$kapali = QMO_Banner_Slider_Settings::sanitize( array( 'autoplay' => 0 ) );
		qrms_assert_same( 0, $kapali['autoplay'], 'otomatik geçiş kapatılabilir' );

		// 0'dan büyük ama çok küçük değer alt sınıra çekilir.
		$kucuk = QMO_Banner_Slider_Settings::sanitize( array( 'autoplay' => 200 ) );
		qrms_assert_same( QMO_Banner_Slider_Settings::MIN_AUTOPLAY, $kucuk['autoplay'], 'autoplay alt sınır' );
	}
);

qrms_test(
	'banner sanitize: checkbox, renk, font, punto ve hizalama temizlenir',
	function () {
		$kapali = QMO_Banner_Slider_Settings::sanitize( array() );
		qrms_assert_same( 0, $kapali['show_nav'], 'ok kapalı' );
		qrms_assert_same( 0, $kapali['show_dots'], 'nokta kapalı' );
		qrms_assert_same( 0, $kapali['show_title'], 'başlık kapalı' );

		$acik = QMO_Banner_Slider_Settings::sanitize(
			array(
				'show_nav'          => '1',
				'show_dots'         => 'on',
				'show_title'        => 1,
				'title_color'       => 'mavi',
				'title_font'        => 'Comic Sans',
				'title_size'        => 999,
				'title_size_mobile' => 1,
				'title_weight'      => 850,
				'title_align'       => 'justify',
			)
		);

		qrms_assert_same( 1, $acik['show_nav'], 'ok açık' );
		qrms_assert_same( 1, $acik['show_dots'], 'nokta açık' );
		qrms_assert_same( 1, $acik['show_title'], 'başlık açık' );
		qrms_assert_same( '#f5f0e8', $acik['title_color'], 'geçersiz renk varsayılana düşer' );
		qrms_assert_same( 'Playfair Display', $acik['title_font'], 'bilinmeyen font Playfair\'e düşer' );
		qrms_assert_same( QMO_Banner_Slider_Settings::MAX_TITLE_SIZE, $acik['title_size'], 'masaüstü üst sınır' );
		qrms_assert_same( QMO_Banner_Slider_Settings::MIN_TITLE_SIZE_MOBILE, $acik['title_size_mobile'], 'mobil alt sınır' );
		qrms_assert_same( 600, $acik['title_weight'], 'kalınlık varsayılana düşer' );
		qrms_assert_same( 'center', $acik['title_align'], 'hizalama varsayılana düşer' );
	}
);

qrms_test(
	'banner option yokken get() varsayılanları döner, css değişkenleri basılır',
	function () {
		$ayar = QMO_Banner_Slider_Settings::get();
		qrms_assert_same( 1, $ayar['show_nav'], 'kayıt yokken oklar açık' );
		qrms_assert_same( '16:9', $ayar['oran'], 'kayıt yokken 16:9' );

		$css = QMO_Banner_Slider_Settings::css_degiskenleri( $ayar );
		qrms_assert_contains( '--qmo-banner-oran:16 / 9', $css, 'oran değişkeni' );
		qrms_assert_contains( "--qmo-banner-title-font:'Playfair Display'", $css, 'font yığını' );
		qrms_assert_contains( '--qmo-banner-title-size:32px', $css, 'masaüstü punto' );
		qrms_assert_contains( '--qmo-banner-title-size-mobile:20px', $css, 'mobil punto' );

		$fade = QMO_Banner_Slider_Settings::css_degiskenleri(
			QMO_Banner_Slider_Settings::sanitize( array( 'oran' => '3:1' ) )
		);
		qrms_assert_contains( '--qmo-banner-oran:3 / 1', $fade, 'seçilen oran CSS\'e çevrilir' );
	}
);

qrms_test(
	'önerilen px canvas ve CSS oranıyla birebir eşleşir',
	function () {
		$onalti = QMO_Banner_Slider_Settings::onerilen_px( '16:9' );
		qrms_assert_same( 1600, $onalti[0], '16:9 genişlik' );
		qrms_assert_same( 900, $onalti[1], '16:9 yükseklik' );

		$uc = QMO_Banner_Slider_Settings::onerilen_px( '3:1' );
		qrms_assert_same( 1600, $uc[0], '3:1 genişlik' );
		qrms_assert_same( (int) round( 1600 / 3 ), $uc[1], '3:1 yükseklik' );

		$yirmi = QMO_Banner_Slider_Settings::onerilen_px( '21:9' );
		qrms_assert_same( 1600, $yirmi[0], '21:9 genişlik' );
		qrms_assert_same( (int) round( 1600 * 9 / 21 ), $yirmi[1], '21:9 yükseklik' );
	}
);

qrms_test(
	'banner kaydet() option\'a yazar, get() geri okur',
	function () {
		qrms_reset();

		QMO_Banner_Slider_Settings::kaydet(
			array(
				'show_nav'   => '0',
				'gecis'      => 'fade',
				'oran'       => '21:9',
				'show_title' => '1',
				'autoplay'   => '8000',
			)
		);

		$ayar = QMO_Banner_Slider_Settings::get();

		qrms_assert_same( 0, $ayar['show_nav'], 'ok kapatıldı' );
		qrms_assert_same( 'fade', $ayar['gecis'], 'solma geçişi' );
		qrms_assert_same( '21:9', $ayar['oran'], 'oran' );
		qrms_assert_same( 1, $ayar['show_title'], 'başlık açık' );
		qrms_assert_same( 8000, $ayar['autoplay'], 'autoplay' );

		qrms_reset();
	}
);

qrms_test(
	'banner ayarları kısa kod, css, js ve admin ekranına bağlanır',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		$kod   = file_get_contents( $dizin . 'shortcode-banner-slider.php' );
		$css   = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		$js    = file_get_contents( $dizin . 'frontend-banner-slider.js' );
		$admin = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$sayfa = file_get_contents( $dizin . 'trait-admin-pages.php' );
		$adminjs = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );

		// Kısa kod ayarı okur ve ok/nokta/başlık/geçiş çıktısına yansıtır.
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::get', $kod, 'kısa kod ayar okur' );
		qrms_assert_contains( '$show_nav', $kod, 'ok bloğu ayara bağlı' );
		qrms_assert_contains( 'data-qmo-banner-prev', $kod, 'önceki oku' );
		qrms_assert_contains( 'data-qmo-banner-next', $kod, 'sonraki oku' );
		qrms_assert_contains( 'qmo-banner-title', $kod, 'başlık öğesi' );
		qrms_assert_contains( 'data-gecis', $kod, 'geçiş biçimi betiğe taşınır' );

		// CSS: oran değişkeni, solma geçişi, ok ve başlık stilleri.
		foreach ( array( '--qmo-banner-oran', '--qmo-banner-title-font', '--qmo-banner-title-color', '--qmo-banner-title-size', '--qmo-banner-title-size-mobile', '--qmo-banner-title-weight', '--qmo-banner-title-align' ) as $degisken ) {
			qrms_assert_contains( $degisken, $css, $degisken . ' frontend' );
			qrms_assert_contains( $degisken, $adminjs, $degisken . ' önizleme' );
		}

		qrms_assert_contains( '.qmo-banner-root.is-fade .qmo-banner-slide', $css, 'solma geçişi' );
		qrms_assert_contains( '.qmo-banner-nav-btn', $css, 'ok stili' );

		// Betik: oklar ve solma mantığı.
		qrms_assert_contains( "data-qmo-banner-prev", $js, 'ok butonu bağlanır' );
		qrms_assert_contains( "getAttribute('data-gecis')", $js, 'geçiş biçimi okunur' );
		qrms_assert_contains( "classList.toggle('is-active'", $js, 'aktif slayt sınıfı' );

		// İki slider hâlâ birbirinden bağımsız.
		qrms_assert_false( strpos( $css, '.qmo-slider-' ) !== false, 'banner css ürün slider seçicisine dokunmaz' );
		qrms_assert_false( strpos( $js, 'qmo-slider-' ) !== false, 'banner betiği ürün slider seçicisine dokunmaz' );

		// Admin: kendi sayfası, kaydetme ucu ve nonce.
		// (Ayar formu sihirbazın 2. adımı; alanların hiçbiri düşmedi.)
		qrms_assert_contains( "'qrms-rm-kampanya-banner' => [", $sayfa, 'sayfa kayıtlı' );
		qrms_assert_contains( 'render_kampanya_banner_page', $sayfa, 'render metodu bağlı' );
		qrms_assert_contains( 'private function render_banner_ayar_formu()', $admin, 'ayar formu tanımlı' );
		qrms_assert_contains( 'public function handle_banner_settings_save()', $admin, 'kaydetme ucu' );
		qrms_assert_contains( 'check_admin_referer( $this->banner_nonce_action )', $admin, 'nonce' );
		qrms_assert_contains( 'initBannerPreview', $adminjs, 'canlı önizleme' );

		// Görünüm formundaki HİÇBİR alan taşımada düşmedi.
		foreach ( array( '[oran]', '[gecis]', '[show_nav]', '[show_dots]', '[autoplay]', '[show_title]', '[title_font]', '[title_color]', '[title_size]', '[title_size_mobile]', '[title_weight]', '[title_align]' ) as $alan ) {
			qrms_assert_contains( 'qmo_banner_slider_settings' . $alan, $admin, $alan . ' alanı korundu' );
		}
	}
);

qrms_test(
	'banner peek: komşu slaytların kenarı görünür, tek banner\'da kapalı',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';

		$css = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		$js  = file_get_contents( $dizin . 'frontend-banner-slider.js' );
		$kod = file_get_contents( $dizin . 'shortcode-banner-slider.php' );

		// Peek, track'e verilen yatay padding'le kurulur; slaytın
		// `flex: 0 0 100%` yüzdesi kendiliğinden daralır. Bunun çalışması
		// track'in border-box olmasına bağlıdır.
		qrms_assert_contains( '--qmo-banner-peek', $css, 'peek değişkeni' );
		qrms_assert_contains( 'padding-inline: var(--qmo-banner-peek)', $css, 'track yatay padding' );
		qrms_assert_contains( 'box-sizing: border-box', $css, 'track kutu modeli' );
		qrms_assert_contains( 'gap: var(--qmo-banner-gap)', $css, 'slaytlar arası boşluk' );
		qrms_assert_contains( 'border-radius: var(--qmo-banner-radius)', $css, 'yuvarlak köşe' );
		qrms_assert_contains( 'min-width: 0', $css, 'slayt içerik minine kilitlenmez' );
		qrms_assert_contains( 'flex: 0 0 auto', $css, 'peek slayt genişliği width:100% ile' );
		qrms_assert_false( strpos( $css, 'min-width: 100%' ) !== false, 'min-width:100% peek\'i yutardı' );

		// Peek yalnızca birden fazla banner varken açılır: tek banner'da
		// yanlarda gösterilecek komşu yok.
		qrms_assert_contains( "\$kok_sinif .= ' is-peek';", $kod, 'is-peek sınıfı' );
		qrms_assert_contains( 'if ( $count > 1 ) {', $kod, 'yalnızca 2+ banner' );
		qrms_assert_contains( 'filemtime( $css )', $kod, 'css sürümü dosya zamanı' );
		qrms_assert_contains( 'filemtime( $js )', $kod, 'js sürümü dosya zamanı' );

		// Solma modunda peek kapalı: slaytlar üst üste, komşu kenarı yok.
		foreach ( array( 'track', 'slide' ) as $parca ) {
			qrms_assert_contains(
				'.qmo-banner-root.is-peek:not(.is-fade) .qmo-banner-' . $parca,
				$css,
				$parca . ' peek kuralı fade dışında'
			);
		}

		// Transform artık yüzde değil piksel: slayt genişliği + gap
		// runtime'da ölçülür (gap cqi tabanlı clamp, sabit yüzdeyle
		// ifade edilemez), pencere boyutu değişince yeniden hesaplanır.
		qrms_assert_contains( 'function slideStep()', $js, 'adım ölçümü' );
		qrms_assert_contains( 'getBoundingClientRect().left', $js, 'gerçek konum okunur' );
		qrms_assert_contains( 'offsetLeft', $js, 'layout yokken yedek ölçüm' );
		qrms_assert_contains( 'requestAnimationFrame', $js, 'stil uygulandıktan sonra yeniden ölçülür' );
		qrms_assert_contains( "translateX(' + (-slideStep() * trackIndex) + 'px)", $js, 'px cinsinden transform' );
		qrms_assert_contains( "addEventListener('resize'", $js, 'yeniden boyutlandırma' );
		qrms_assert_false( strpos( $js, "(-100 * current) + '%'" ) !== false, 'eski yüzde hesabı kaldırıldı' );
	}
);

qrms_test(
	'banner görselleri tüm slaytlarda object-fit ile kırpılır',
	function () {
		$dizin = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$css   = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		$kod   = file_get_contents( $dizin . 'shortcode-banner-slider.php' );
		$cpt   = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$ayar  = file_get_contents( $dizin . 'class-banner-slider-settings.php' );

		// Tüm slayt görselleri: :first-child yok, object-fit her .qmo-banner-img'e.
		qrms_assert_contains( '.qmo-banner-img', $css, 'görsel seçici' );
		qrms_assert_contains( 'object-fit: cover', $css, 'object-fit cover' );
		qrms_assert_contains( 'object-position: center', $css, 'object-position center' );
		qrms_assert_false( strpos( $css, '.qmo-banner-slide:first-child' ) !== false, 'ilk slayta özel kırpma yok' );
		qrms_assert_contains( 'position: absolute', $css, 'görsel akıştan çıkar' );
		qrms_assert_contains( 'min-height: 0', $css, 'flex min-height kilitlenmez' );

		// width/height ipucu kayıtlı orana göre, döngüde sızmaz.
		qrms_assert_contains( 'QMO_Banner_Slider_Settings::onerilen_px', $kod, 'oranla eşleşen px' );
		qrms_assert_contains( 'function onerilen_px', $ayar, 'önerilen px tek kaynak' );

		// Boyut uyarısı oran-duyarlı; 16:9 varsayılanı dosyada durur.
		qrms_assert_contains( '1600x900px (16:9), JPG/WEBP, maksimum 300KB', $cpt, 'banner boyut notu' );
		qrms_assert_contains( 'function boyut_notu()', $cpt, 'dinamik boyut notu' );
	}
);

qrms_test(
	'kaydırma modunda sonsuz karusel klon tekniği, solma etkilenmez',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/frontend-banner-slider.js' );

		qrms_assert_contains( 'data-qmo-banner-clone', $js, 'klon işareti' );
		qrms_assert_contains( 'cloneSlide', $js, 'klon üretici' );
		qrms_assert_contains( 'insertBefore', $js, 'son slayt başa' );
		qrms_assert_contains( 'appendChild', $js, 'ilk slayt sona' );
		qrms_assert_contains( 'snapIfNeeded', $js, 'sınırda anlık sıçrama' );
		qrms_assert_contains( "var looping = !fade && !reducedMotion && realCount > 1", $js, 'solma ve reduced-motion klonlamaz' );
		qrms_assert_contains( 'transitionend', $js, 'geçiş bitince sıçra' );
	}
);

qrms_test(
	'admin kampanya listesi tüm kayıtları çeker ve sıra AJAX ile değişir',
	function () {
		$dizin  = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$cpt    = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$banner = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$js     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/js/admin-ui.js' );
		$boot   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		$kisa   = file_get_contents( $dizin . 'shortcode-banner-slider.php' );

		qrms_assert_contains( 'function get_admin_banners', $cpt, 'yönetim sorgusu' );
		qrms_assert_contains( "'posts_per_page'         => -1", $cpt, 'limit yok' );
		qrms_assert_contains( "'nopaging'               => true", $cpt, 'sayfalama kapalı' );
		qrms_assert_contains( "'draft'", $cpt, 'taslaklar da listelenir' );
		qrms_assert_contains( 'QMO_Banner_CPT::get_admin_banners()', $banner, 'liste admin sorgusunu kullanır' );

		qrms_assert_contains( 'data-yon="up"', $banner, 'yukarı ok' );
		qrms_assert_contains( 'data-yon="down"', $banner, 'aşağı ok' );
		qrms_assert_contains( 'initBannerOrder', $js, 'ok tıklaması bağlanır' );
		qrms_assert_contains( 'qmo_banner_sira_kaydet', $js, 'AJAX eylemi JS' );
		qrms_assert_contains( 'wp_ajax_qmo_banner_sira_kaydet', $boot, 'AJAX ucu kayıtlı' );
		qrms_assert_contains( 'check_ajax_referer( \'rma_admin_nonce\', \'security\' )', $cpt, 'nonce' );
		qrms_assert_contains( 'QRMS_Admin::CAPABILITY', $cpt, 'yetki' );

		// Ön yüz ve admin aynı sıra alanını kullanır.
		qrms_assert_contains( 'QMO_Banner_CPT::get_published_banners()', $kisa, 'ön yüz yayınlanmış + menu_order' );
		qrms_assert_contains( "'menu_order' => 'ASC'", $cpt, 'ortak sıra alanı' );
	}
);

qrms_test(
	'banner kaydetme ucu ve önbellek kancası kayıtlı',
	function () {
		$boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );
		qrms_assert_contains( 'admin_post_qmo_banner_ayar_kaydet', $boot, 'kaydetme ucu' );
		qrms_assert_contains( 'update_option_qmo_banner_slider_settings', $boot, 'önbellek kancası' );

		$slider_boot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qmo-one-cikan-slider.php' );
		qrms_assert_contains( 'class-banner-slider-settings.php', $slider_boot, 'ayar sınıfı yüklenir' );
	}
);


/* ---------------------------------------------------------------------------
 * Kampanya Banner — SUNUCU TARAFI KIRPMA
 *
 * Kırpma tutarsızlığının kökü, görsellerin yalnızca CSS object-fit ile
 * "kesilmesiydi": dosyalar farklı oranlarda kaldığı için her slayttan farklı
 * bir bölge kayboluyordu. Artık dosyanın kendisi hedef orana getiriliyor
 * (wp_get_image_editor), CSS yalnızca güvenlik ağı.
 * ------------------------------------------------------------------------ */

require_once QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-banner-kirpma.php';

qrms_test(
	'kırpma kutusu: kare, dikey ve geniş görseller aynı orana iner',
	function () {
		$hedef = QMO_Banner_Kirpma::oran_orani( '16:9' );

		// KARE (1000x1000) — dikeyde kesilir, yatay tam kalır.
		$kare = QMO_Banner_Kirpma::kirpma_kutusu( 1000, 1000, $hedef );
		qrms_assert_same( 0, $kare['x'], 'kare: yatayda kesilmez' );
		qrms_assert_same( 219, $kare['y'], 'kare: üstten ve alttan eşit pay' );
		qrms_assert_same( 1000, $kare['en'], 'kare: tam genişlik' );
		qrms_assert_same( 563, $kare['boy'], 'kare: 16:9 yüksekliği' );

		// DİKEY (800x1200) — yine dikeyde kesilir, kayıp daha büyüktür.
		$dikey = QMO_Banner_Kirpma::kirpma_kutusu( 800, 1200, $hedef );
		qrms_assert_same( 375, $dikey['y'], 'dikey: merkezden' );
		qrms_assert_same( 800, $dikey['en'], 'dikey: tam genişlik' );
		qrms_assert_same( 450, $dikey['boy'], 'dikey: 16:9 yüksekliği' );

		// ÇOK GENİŞ (3000x1000) — bu kez yatayda kesilir.
		$genis = QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef );
		qrms_assert_same( 611, $genis['x'], 'geniş: soldan ve sağdan eşit pay' );
		qrms_assert_same( 0, $genis['y'], 'geniş: dikeyde kesilmez' );
		qrms_assert_same( 1778, $genis['en'], 'geniş: 16:9 genişliği' );
		qrms_assert_same( 1000, $genis['boy'], 'geniş: tam yükseklik' );

		// ÜÇÜNÜN DE ÇIKTISI AYNI ORANDA: tutarsızlığın kalıcı çözümü bu.
		foreach ( array( $kare, $dikey, $genis ) as $kutu ) {
			qrms_assert_true(
				abs( ( $kutu['cikti_en'] / $kutu['cikti_boy'] ) / $hedef - 1 ) <= QMO_Banner_Kirpma::TOLERANS,
				'çıktı 16:9 toleransında'
			);
		}

		// Çıktı kaynaktan büyütülmez (upscale bulanıklık üretir).
		$kucuk = QMO_Banner_Kirpma::kirpma_kutusu( 400, 400, $hedef );
		qrms_assert_same( 400, $kucuk['cikti_en'], 'küçük görsel büyütülmez' );
		qrms_assert_same( 225, $kucuk['cikti_boy'], 'küçük görselin 16:9 yüksekliği' );

		// Uzun kenar önerilen 1600px'i aşmaz.
		qrms_assert_same( 1600, $genis['cikti_en'], 'çıktı 1600px ile sınırlı' );
		qrms_assert_same( 900, $genis['cikti_boy'], '1600x900' );
	}
);

qrms_test(
	'kırpma odağı kesilen kenarı kaydırır, beyaz liste dışına çıkmaz',
	function () {
		$hedef = QMO_Banner_Kirpma::oran_orani( '16:9' );
		$ucluk = QMO_Banner_Kirpma::oran_orani( '3:1' );

		// Yatay kesimde sol/sağ, dikey kesimde üst/alt anlamlıdır.
		qrms_assert_same( 0, QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef, 'sol' )['x'], 'sol kenar' );
		qrms_assert_same( 1222, QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef, 'sag' )['x'], 'sağ kenar' );
		qrms_assert_same( 0, QMO_Banner_Kirpma::kirpma_kutusu( 1000, 1000, $ucluk, 'ust' )['y'], 'üst kenar' );
		qrms_assert_same( 667, QMO_Banner_Kirpma::kirpma_kutusu( 1000, 1000, $ucluk, 'alt' )['y'], 'alt kenar' );

		// Bilinmeyen odak merkeze düşer; kutu merkezî kırpmanın aynısı olur.
		qrms_assert_same( 'merkez', QMO_Banner_Kirpma::odak( 'çapraz' ), 'bilinmeyen odak merkez' );
		qrms_assert_same( 'sag', QMO_Banner_Kirpma::odak( ' SAG ' ), 'boşluk ve büyük harf temizlenir' );
		qrms_assert_same(
			QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef, 'merkez' )['x'],
			QMO_Banner_Kirpma::kirpma_kutusu( 3000, 1000, $hedef, 'çapraz' )['x'],
			'geçersiz odak merkezî kırpma verir'
		);

		// object-position karşılıkları: yönetim önizlemesi ve henüz
		// kırpılmamış eski görseller bunu kullanır.
		qrms_assert_same( 'center center', QMO_Banner_Kirpma::odak_css( 'merkez' ), 'merkez css' );
		qrms_assert_same( 'center bottom', QMO_Banner_Kirpma::odak_css( 'alt' ), 'alt css' );
		qrms_assert_same( 'left center', QMO_Banner_Kirpma::odak_css( 'sol' ), 'sol css' );
	}
);

qrms_test(
	'zaten doğru orandaki görsel yeniden yazılmaz, boyut adı orana özeldir',
	function () {
		$hedef = QMO_Banner_Kirpma::oran_orani( '16:9' );

		qrms_assert_true( QMO_Banner_Kirpma::oran_uyuyor( 1600, 900, $hedef ), 'tam 16:9' );
		qrms_assert_true( QMO_Banner_Kirpma::oran_uyuyor( 1600, 901, $hedef ), '1px sapma toleransta — CSS yutar' );
		qrms_assert_false( QMO_Banner_Kirpma::oran_uyuyor( 1000, 1000, $hedef ), 'kare uymaz' );
		qrms_assert_false( QMO_Banner_Kirpma::oran_uyuyor( 800, 1200, $hedef ), 'dikey uymaz' );
		qrms_assert_false( QMO_Banner_Kirpma::oran_uyuyor( 0, 900, $hedef ), 'ölçüsüz görsel uymaz' );

		// Kırpılmış sürüm ORAN BAŞINA ayrı bir ek boyutta durur: 16:9'dan
		// 3:1'e geçilince eskisi silinmez, sadece kullanılmaz.
		qrms_assert_same( 'qmo-banner-16x9', QMO_Banner_Kirpma::boyut_adi( '16:9' ), '16:9 boyut adı' );
		qrms_assert_same( 'qmo-banner-3x1', QMO_Banner_Kirpma::boyut_adi( '3:1' ), '3:1 boyut adı' );
		qrms_assert_same( 'qmo-banner-16x9', QMO_Banner_Kirpma::boyut_adi( '9:16' ), 'bilinmeyen oran varsayılana düşer' );

		// oranlar() listesindeki her oran için ayrı bir ad üretilir.
		$adlar = array();
		foreach ( array_keys( QMO_Banner_Slider_Settings::oranlar() ) as $oran ) {
			$adlar[] = QMO_Banner_Kirpma::boyut_adi( $oran );
		}
		qrms_assert_same( count( $adlar ), count( array_unique( $adlar ) ), 'her oranın adı benzersiz' );
	}
);

qrms_test(
	'kırpma kaydetme akışına, ön yüze ve yönetim önizlemesine bağlı',
	function () {
		$dizin  = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$kirpma = file_get_contents( $dizin . 'class-banner-kirpma.php' );
		$cpt    = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$kod    = file_get_contents( $dizin . 'shortcode-banner-slider.php' );
		$banner = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$boot   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qmo-one-cikan-slider.php' );

		// GERÇEK kırpma WordPress'in görüntü düzenleyicisiyle yapılır;
		// doğrudan GD/Imagick çağrısı yok.
		qrms_assert_contains( 'wp_get_image_editor(', $kirpma, 'WP görüntü düzenleyici' );
		qrms_assert_contains( '$editor->crop(', $kirpma, 'sunucu tarafında kırpma' );
		qrms_assert_contains( '$editor->save(', $kirpma, 'kırpılmış dosya yazılır' );
		qrms_assert_false( strpos( $kirpma, 'imagecreatefrom' ) !== false, 'doğrudan GD çağrısı yok' );
		qrms_assert_false( strpos( $kirpma, 'new Imagick' ) !== false, 'doğrudan Imagick çağrısı yok' );

		// Orijinal korunur: sonuç ek boyut olarak metadata'ya yazılır.
		qrms_assert_contains( "\$meta['sizes'][ \$ad ]", $kirpma, 'ek boyut kaydı' );
		qrms_assert_contains( 'wp_update_attachment_metadata(', $kirpma, 'metadata güncellenir' );

		// Kaydetme akışı: görsel seçilince kırpma çalışır.
		qrms_assert_contains( 'QMO_Banner_Kirpma::banner_kirp( $post_id )', $cpt, 'kayıtta kırpılır' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::META_ODAK', $cpt, 'odak alanı kaydedilir' );

		// Ön yüz kırpılmış sürümü basar; kırpılmışta srcset basılmaz
		// (adaylar farklı oranda olurdu).
		qrms_assert_contains( 'QMO_Banner_Kirpma::gorsel(', $kod, 'ön yüz kırpılmışı okur' );
		qrms_assert_contains( "\$srcset = \$kirpildi ? '' :", $kod, 'kırpılmışta srcset yok' );

		// Yönetim önizlemesi ön yüzle AYNI dosyayı gösterir.
		qrms_assert_contains( 'QMO_Banner_Kirpma::gorsel(', $banner, 'önizleme kırpılmışı okur' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::gorsel(', $cpt, 'meta kutusu kırpılmışı okur' );

		// Sınıf bootstrap'a bağlı.
		qrms_assert_contains( 'class-banner-kirpma.php', $boot, 'kırpma sınıfı yüklenir' );

		// CSS güvenlik ağı olarak DURUR ama artık tek başına iş görmez.
		$css = file_get_contents( $dizin . 'frontend-banner-slider.css' );
		qrms_assert_contains( 'object-fit: cover', $css, 'güvenlik ağı yerinde' );
		qrms_assert_contains( 'GÜVENLİK AĞIDIR', $css, 'CSS\'in rolü belgelenmiş' );
	}
);

qrms_test(
	'eski görseller için yeniden kırpma: toplu düğme, satır uyarısı ve oran değişimi',
	function () {
		$dizin  = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/';
		$banner = file_get_contents( $dizin . 'trait-kampanya-banner-admin.php' );
		$cpt    = file_get_contents( $dizin . 'admin-cpt-banner.php' );
		$boot   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/qr-menu.php' );

		// admin_post ucu kayıtlı, nonce ve yetki kontrollü.
		qrms_assert_contains( 'admin_post_qmo_banner_kirp', $boot, 'yeniden kırpma ucu kayıtlı' );
		qrms_assert_contains( 'public function handle_banner_kirp()', $banner, 'işleyici tanımlı' );
		qrms_assert_contains( 'check_admin_referer( $this->banner_kirp_nonce_action )', $banner, 'nonce' );
		qrms_assert_contains( 'QRMS_Admin::CAPABILITY', $banner, 'yetki' );

		// İki kapsam: tek kayıt ve tümü.
		qrms_assert_contains( 'QMO_Banner_Kirpma::toplu_kirp()', $banner, 'toplu kırpma' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::banner_kirp( $banner_id )', $banner, 'tek kayıt kırpma' );
		qrms_assert_contains( 'Tüm görselleri yeniden kırp', $banner, 'toplu düğme' );
		qrms_assert_contains( 'Yeniden kırp', $banner, 'satır düğmesi' );

		// Satır eylemi bir <span> içinde durduğu için <form> değil nonce'lu
		// bağlantıdır (WordPress'in kendi satır eylemi deseni).
		qrms_assert_contains( 'wp_nonce_url(', $banner, 'bağlantı nonce\'lu' );
		qrms_assert_false( strpos( $banner, 'banner_kirp_formu' ) !== false, 'span içinde form yok' );

		// Satır başına durum rozeti: kullanıcı hangi görselin eski
		// olduğunu görmeden bırakılmaz.
		qrms_assert_contains( 'QMO_Banner_Kirpma::durum(', $banner, 'liste durumu okur' );
		qrms_assert_contains( 'rma-kb-kirpma-rozet', $banner, 'durum rozeti' );
		qrms_assert_contains( 'QMO_Banner_Kirpma::bekleyen_sayisi(', $banner, 'bekleyen sayısı' );

		// WordPress\'in kendi liste ekranı da uyarır.
		qrms_assert_contains( 'Güncel orana kırpılmadı', $cpt, 'CPT liste sütunu uyarısı' );

		// Oran sonradan değişirse kullanıcı bilgilendirilir.
		qrms_assert_contains( "'oran_degisti'", $banner, 'oran değişimi bildirimi' );
		qrms_assert_contains( 'yeniden kırpılması gerekiyor', $banner, 'bildirim metni' );

		// Durum rozetlerinin stili admin CSS\'inde tanımlı.
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/admin-ui.css' );
		qrms_assert_contains( '.rma-kb-kirpma-rozet', $css, 'rozet stili' );
		qrms_assert_contains( '.rma-kb-kirpma-uyari', $css, 'uyarı kutusu stili' );
	}
);


/* ---------------------------------------------------------------------------
 * 24. HFB — "Yeni Blok Ekle" listesi, canlı önizleme yükü, önbellek temizliği
 * ------------------------------------------------------------------------ */

echo "\n\033[1mHFB — açılır liste, önizleme yükü, önbellek\033[0m\n";

qrms_test(
	'"Yeni Blok Ekle" listesi sayfa akışını itmez, düğmenin üstüne binmez',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/admin.css' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/js/admin.js' );

		preg_match( '/\.hfb-wrap \.hfb-block-add \{(.*?)\}/s', $css, $sarmalayici );
		preg_match( '/\.hfb-wrap \.hfb-block-add__menu \{(.*?)\}/s', $css, $liste );

		qrms_assert_contains( 'position: relative', $sarmalayici[1], 'sarmalayıcı konumlandırma bağlamı kurar' );

		// Ofsetsiz bir mutlak kutu STATİK konumunda kalır — yani tetikleyen
		// düğmenin yerinde — ve onun üzerine biner. Ofset zorunludur.
		qrms_assert_contains( 'position: absolute', $liste[1], 'liste akıştan çıkar' );
		qrms_assert_contains( 'top: calc(100% + 6px)', $liste[1], 'düğmenin altına çapalanır' );
		qrms_assert_contains( 'left: 0', $liste[1], 'yatay çapa' );

		preg_match( '/z-index: (\d+)/', $liste[1], $z );
		qrms_assert_true( (int) $z[1] >= 100, 'altındaki gezinme şeridinin üstünde kalır' );

		// `[hidden]` tek özgüllük birimidir; `.hfb-wrap .hfb-block-add__menu`
		// üzerindeki display kuralı onu ezer ve liste KAPALIYKEN de görünürdü.
		qrms_assert_contains(
			'.hfb-wrap .hfb-block-add__menu[hidden] {',
			$css,
			'gizleme kuralı aynı özgüllükte tekrarlanır'
		);

		// Dışarı tıklama ve Escape kapatır; odak geri verilirken sayfa kaymaz.
		qrms_assert_contains( 'setBlockMenuOpen(false)', $js, 'kapatma yardımcısı' );
		qrms_assert_contains( "e.key === 'Escape'", $js, 'Escape kapatır' );
		qrms_assert_contains( 'preventScroll: true', $js, 'odak scroll konumunu bozmaz' );
	}
);

qrms_test(
	'önizleme yükü iç içe kurulur; blok alanları PHP tarafında bozulmadan çözülür',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/js/admin.js' );

		// Tek delege dinleyici formun tamamını kapsar: sınıf listesine
		// bakılmaz, sonradan eklenen blok alanları da kendiliğinden girer.
		qrms_assert_contains( "on('input change', '#hfb-settings-form'", $js, 'form kökünde delegasyon' );
		qrms_assert_contains( "is('input, select, textarea')", $js, 'her alan tipi kapsanır' );
		qrms_assert_true(
			false === strpos( $js, "'#hfb-settings-form .hfb-preview-trigger'" ),
			'sınıfa bağlı eski dinleyici kaldırıldı'
		);
		qrms_assert_contains( 'var DEBOUNCE_MS = 300;', $js, 'debounce korunur' );

		/*
		 * Asıl kök neden: düz anahtarla gönderilen blok alanları
		 * `data[hfb_hamburger_blocks[blk_1][enabled]]` hâline geliyor, PHP
		 * ise İLK kapanan paranteze göre ayrıştırıp anahtarı
		 * `hfb_hamburger_blocks[blk_1` diye bozuyordu. Aşağıdaki iki
		 * doğrulama, bozuk ve düzeltilmiş yükün sunucudaki karşılığını
		 * yan yana gösterir.
		 */
		parse_str( 'data%5Bhfb_hamburger_blocks%5Bblk_1%5D%5Benabled%5D%5D=1', $bozuk );
		qrms_assert_true(
			! isset( $bozuk['data']['hfb_hamburger_blocks'] ),
			'düz anahtar sunucuda bloklara hiç ulaşmaz'
		);

		parse_str( 'data%5Bhfb_hamburger_blocks%5D%5Bblk_1%5D%5Benabled%5D=1', $duzgun );
		qrms_assert_same(
			'1',
			$duzgun['data']['hfb_hamburger_blocks']['blk_1']['enabled'],
			'iç içe yük doğru çözülür'
		);

		qrms_assert_contains( 'function nameToPath(', $js, 'alan adı yola çevrilir' );
		qrms_assert_contains( 'function assignPath(', $js, 'yol boyunca yazılır' );
	}
);

qrms_test(
	'iç içe önizleme yükü blok değişikliklerini kaydetmeden yansıtır',
	function () {
		$hfb = qrms_hfb();

		// Tarayıcının artık gönderdiği şekil: hfb_hamburger_blocks bir
		// dizidir, düz "…[blk_1][enabled]" anahtarı değil.
		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_hamburger_block_order' => 'blk_9,blk_8',
				'hfb_hamburger_blocks'      => array(
					'blk_9' => array(
						'type'    => 'text',
						'enabled' => '1',
						'align'   => 'left',
						'content' => 'Kaydetmeden görünen not',
					),
					'blk_8' => array(
						'type'    => 'logo',
						'enabled' => '1',
						'align'   => 'center',
					),
				),
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Kaydetmeden görünen not', $yanit['data']['header'], 'yeni metin bloğu önizlemede' );

		// Kayıt DEĞİŞMEZ: önizleme salt okunurdur.
		$kayitli = $hfb->get_hamburger_options();
		foreach ( $kayitli['blocks'] as $block ) {
			qrms_assert_true(
				! isset( $block['content'] ) || false === strpos( (string) $block['content'], 'Kaydetmeden görünen not' ),
				'önizleme kaydetmez'
			);
		}
	}
);

qrms_test(
	'qmo_tum_onbellek_temizle nesne önbelleğini ve kurulu eklentileri temizler',
	function () {
		// Kurulu eklenti taklitleri: yalnızca function_exists() dallarının
		// çalıştığını göstermek için. Gerçekte kurulu değilse dal atlanır.
		if ( ! function_exists( 'rocket_clean_domain' ) ) {
			function rocket_clean_domain() {
				$GLOBALS['qrms_test']['eklenti_temizlik'][] = 'rocket';
			}
		}
		if ( ! function_exists( 'wp_cache_clear_cache' ) ) {
			function wp_cache_clear_cache() {
				$GLOBALS['qrms_test']['eklenti_temizlik'][] = 'super_cache';
			}
		}
		if ( ! function_exists( 'autoptimize_flush_pagecache' ) ) {
			function autoptimize_flush_pagecache() {
				$GLOBALS['qrms_test']['eklenti_temizlik'][] = 'autoptimize';
			}
		}

		$GLOBALS['qrms_test']['eklenti_temizlik'] = array();

		$temizlenen = qmo_tum_onbellek_temizle();

		qrms_assert_true( in_array( '*', $GLOBALS['qrms_test']['cache_flush'], true ), 'nesne önbelleği boşaltıldı' );
		qrms_assert_true( in_array( 'wp_rocket', $temizlenen, true ), 'WP Rocket temizlendi' );
		qrms_assert_true( in_array( 'wp_super_cache', $temizlenen, true ), 'WP Super Cache temizlendi' );
		qrms_assert_true( in_array( 'autoptimize', $temizlenen, true ), 'Autoptimize temizlendi' );
		qrms_assert_same(
			array( 'rocket', 'super_cache', 'autoptimize' ),
			$GLOBALS['qrms_test']['eklenti_temizlik'],
			'her eklenti bir kez çağrıldı'
		);

		// Kurulu OLMAYAN eklenti sessizce atlanır — ölümcül hata yok.
		qrms_assert_true( ! in_array( 'w3tc', $temizlenen, true ), 'W3TC kurulu değil, atlandı' );
		qrms_assert_true(
			in_array( 'qmo_onbellek_temizlendi', $GLOBALS['qrms_test']['fired_actions'], true ),
			'genişletme kancası tetiklenir'
		);

		// Arka uç grup bazlı temizliği destekliyorsa daha dar kapsam seçilir.
		$GLOBALS['qrms_test']['cache_flush']    = array();
		$GLOBALS['qrms_test']['cache_supports'] = array( 'flush_group' => true );

		$dar = qmo_tum_onbellek_temizle( 'qmo' );

		qrms_assert_true( in_array( 'qmo', $GLOBALS['qrms_test']['cache_flush'], true ), 'yalnızca grup boşaltıldı' );
		qrms_assert_true( in_array( 'wp_cache_flush_group:qmo', $dar, true ), 'grup temizliği raporlanır' );
		qrms_assert_true( ! in_array( '*', $GLOBALS['qrms_test']['cache_flush'], true ), 'genel flush yapılmaz' );

		// Mevcut masa yardımcısı DEĞİŞMEDEN durur.
		qrms_assert_true( function_exists( 'qmo_masa_cache_temizle' ), 'masa yardımcısı yerinde' );
	}
);

qrms_test(
	'HFB kaydı başarılı olduğunda önbellek kendiliğinden temizlenir',
	function () {
		$hfb = qrms_hfb();

		$GLOBALS['qrms_test']['cache_flush']   = array();
		$GLOBALS['qrms_test']['fired_actions'] = array();

		$hfb->save_settings( array( 'hfb_header_brand_line1' => 'Yeni Marka' ) );

		qrms_assert_same( 'Yeni Marka', get_option( 'hfb_header_options' )['brand_line1'], 'ayar kaydedildi' );
		qrms_assert_true(
			! empty( $GLOBALS['qrms_test']['cache_flush'] ),
			'kayıttan hemen sonra önbellek temizlenir'
		);
		qrms_assert_true(
			in_array( 'qmo_onbellek_temizlendi', $GLOBALS['qrms_test']['fired_actions'], true ),
			'ortak temizleyici çağrıldı'
		);

		// Nonce/yetki kontrolü kayıt akışında yerinde durur.
		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-admin.php' );
		qrms_assert_contains( "current_user_can( QRMS_Admin::CAPABILITY )", $admin, 'yetki kontrolü' );
		qrms_assert_contains( "check_admin_referer( 'hfb_save_settings', 'hfb_nonce' )", $admin, 'nonce kontrolü' );
	}
);

echo "\nİletişim formu — tam genişlik ve alan sütunu\n";

qrms_test(
	'alan sütun genişliği yalnızca full veya half kabul eder',
	function () {
		qrms_assert_same( 'full', qrm_pro_sanitize_column_width( 'full' ), 'full' );
		qrms_assert_same( 'half', qrm_pro_sanitize_column_width( 'half' ), 'half' );
		qrms_assert_same( 'full', qrm_pro_sanitize_column_width( '33' ), 'bilinmeyen değer tam genişlik' );
		qrms_assert_same( 'full', qrm_pro_sanitize_column_width( '' ), 'boş değer tam genişlik' );
	}
);

qrms_test(
	'kayıtsız alanda eski otomatik yarım genişlik korunur; kayıtlı değer baskın gelir',
	function () {
		qrms_assert_same(
			'half',
			qrm_pro_field_column_width( array( 'field_key' => 'customer_name' ), 'review' ),
			'eski yorum alanı: ad'
		);
		qrms_assert_same(
			'full',
			qrm_pro_field_column_width( array( 'field_key' => 'comment' ), 'review' ),
			'yorum metni tam genişlik'
		);
		qrms_assert_same(
			'full',
			qrm_pro_field_column_width(
				array( 'field_key' => 'customer_name', 'column_width' => 'full' ),
				'review'
			),
			'kayıtlı tam genişlik otomatik half\'i ezer'
		);
		qrms_assert_same(
			'half',
			qrm_pro_field_column_width( array( 'field_type' => 'email' ), 'custom' ),
			'eski özel form e-posta alanı'
		);
		qrms_assert_same(
			'full',
			qrm_pro_field_column_width(
				array( 'field_type' => 'email', 'column_width' => 'full' ),
				'custom'
			),
			'yeni e-posta alanı tekli kalabilir'
		);
	}
);

qrms_test(
	'iletişim kısa kodu fullbleed, yorum listesi boxed kalır',
	function () {
		$iletisim = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/shortcode-contact.php' );
		$yorum    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/shortcode-reviews.php' );
		$ozel     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );

		qrms_assert_contains( 'qrm-form-fullbleed', $iletisim, 'iletişim wrapper' );
		qrms_assert_contains( 'qrm-form-fullbleed', $ozel, 'özel form wrapper' );
		qrms_assert_false(
			false !== strpos( $yorum, 'qrm-form-fullbleed' ),
			'yorum listesi max-width 800px kutusunu korur'
		);
	}
);

qrms_test(
	'özel form alanı tipi otomatik half class eklemez',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );

		qrms_assert_false(
			false !== strpos( $kaynak, "in_array(\$type, ['text', 'email', 'tel', 'number', 'date']" ),
			'tipe göre otomatik ikili yok'
		);
		qrms_assert_contains( "qrm_pro_field_column_width(\$field, 'custom')", $kaynak, 'alan bazında genişlik' );
	}
);

qrms_test(
	'Elementor max-width CSS ile ezilir, yatay kaydırma clip ile kesilir',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/assets/css/frontend-form.css' );

		qrms_assert_contains( 'width: 100vw', $css, '100vw' );
		qrms_assert_contains( 'overflow-x: clip', $css, 'yatay kaydırma yok' );
		qrms_assert_contains( 'max-width: 100% !important', $css, 'Elementor max-width ezilir' );
		qrms_assert_contains( 'padding-left: 0 !important', $css, 'Elementor yatay padding ezilir' );
		qrms_assert_contains( '@media (max-width: 767px)', $css, 'mobil kırılım' );
		qrms_assert_contains( '.qrm-form-fullbleed .qrm-input-group.half', $css, 'ikili alan class\'ı' );
		qrms_assert_contains( 'qrms-contact-fullwidth', $css, 'native Elementor Form section class' );
		qrms_assert_contains( '.qrm-form-fullbleed .qrm-form-box', $css, 'kart kapsayıcıyı doldurur' );
		qrms_assert_false( false !== strpos( $css, 'border-radius: 0' ), 'kart köşesi ezilmez' );
	}
);

qrms_test(
	'form düzenleyici sütun genişliğini alan bazında kaydeder',
	function () {
		$builder = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/custom-form-builder.php' );
		$alanlar = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/form-builder.php' );
		$js      = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/assets/js/form-preview.js' );
		$sema    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/db.php' );
		$install = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/install.php' );

		qrms_assert_contains( "column_width: 'full'", $builder, 'yeni özel alan varsayılanı tekli' );
		qrms_assert_contains( 'data-edit="column_width"', $builder, 'özel form düzenleme paneli' );
		qrms_assert_contains( 'name="fields[<?php echo intval($f->id); ?>][column_width]"', $alanlar, 'yorum formu sütun seçimi' );
		qrms_assert_contains( 'select[name*="[column_width]"]', $js, 'önizleme seçimi okur' );
		qrms_assert_false( false !== strpos( $js, 'HALF_KEYS' ), 'sabit half anahtar listesi kalktı' );
		qrms_assert_contains( "column_width varchar(10) DEFAULT 'full' NOT NULL", $sema, 'özel form şeması' );
		qrms_assert_contains( "column_width varchar(10) DEFAULT 'full' NOT NULL", $install, 'yorum formu şeması' );
		qrms_assert_contains( 'qrm_pro_migrate_column_widths', $install, 'eski alanlar bir kez taşınır' );
	}
);

qrms_test(
	'ön yüz form CSS dosyası asset_version ile kuyruğa alınır',
	function () {
		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/module.php' );

		qrms_assert_contains( "add_action( 'wp_enqueue_scripts', 'qrms_module_yorum_feedback_frontend_form_assets' )", $modul, 'kanca' );
		qrms_assert_contains( 'asset_version( \'modules/yorum-feedback/assets/css/frontend-form.css\' )', $modul, 'cache bust' );
	}
);


if ( empty( $GLOBALS['qrms_failures'] ) ) {
	echo "\033[32mTüm testler geçti\033[0m (" . $GLOBALS['qrms_assertions'] . " doğrulama)\n\n";
	exit( 0 );
}

echo "\033[31m" . count( $GLOBALS['qrms_failures'] ) . " test başarısız\033[0m\n\n";
exit( 1 );
