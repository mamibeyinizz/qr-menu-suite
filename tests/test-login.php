<?php
/**
 * Özel giriş sistemi testleri.
 *
 * @package QR_Menu_Suite
 */

echo "\nGiriş URL'si\n";

require_once QRMS_PLUGIN_DIR . 'includes/class-qrms-login.php';

qrms_test(
	'yasaklı slug reddedilir',
	function () {
		$err = QRMS_Login::slug_dogrula( 'wp-admin' );
		qrms_assert_true( is_wp_error( $err ), 'wp-admin yasak' );
		$err2 = QRMS_Login::slug_dogrula( 'admin' );
		qrms_assert_true( is_wp_error( $err2 ), 'admin yasak' );
	}
);

qrms_test(
	'geçerli slug temizlenir',
	function () {
		update_option( QRMS_Login::OPT_SLUG, 'QRM-Test', false );
		qrms_assert_same( 'qrm-test', QRMS_Login::slug(), 'sanitize_title' );
		update_option( QRMS_Login::OPT_SLUG, 'qrm', false );
	}
);

qrms_test(
	'login_url filtresi yeni adresi üretir',
	function () {
		if ( defined( 'QRMS_LOGIN_DISABLE' ) && QRMS_LOGIN_DISABLE ) {
			return;
		}
		QRMS_Login::init();
		$url = apply_filters( 'login_url', 'http://example.test/wp-login.php', '', false );
		qrms_assert_contains( '/qrm', $url, 'özel slug' );
		qrms_assert_false( false !== strpos( $url, 'wp-login.php' ), 'wp-login yok' );
	}
);

qrms_test(
	'QRMS_LOGIN_DISABLE tanımlıyken init hook bağlamaz',
	function () {
		if ( ! defined( 'QRMS_LOGIN_DISABLE' ) ) {
			define( 'QRMS_LOGIN_DISABLE', true );
		}
		$onceki = did_action( 'plugins_loaded' );
		QRMS_Login::init();
		qrms_assert_true( QRMS_Login::devre_disimi(), 'devre dışı' );
	}
);
