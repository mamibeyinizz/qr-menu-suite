<?php
/**
 * QR Menu Suite — stub tabanlı mantık testleri.
 *
 * Test koşucusu tests/test-suite.php içindedir; bu dosya stub ve assert yardımcılarını yükler.
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
	$GLOBALS['qrms_test']['post_meta']  = array();
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
	if ( function_exists( 'qmo_chatbot_istekte_basildi' ) ) {
		qmo_chatbot_istekte_basildi( false );
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

require_once __DIR__ . '/test-helpers.php';
