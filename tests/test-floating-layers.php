<?php
/**
 * Mobil tam ekran paneller açıkken floating UI gizleme ve hamburger z-index.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nFloating katmanlar — hamburger / filtre paneli UX\n";

qrms_test(
	'floating-layer-states.css hamburger ve filtre :has() kurallarını içerir',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets/css/floating-layer-states.css' );

		qrms_assert_contains( 'body:has(.hfb-mobile-panel.is-open)', $css, 'hamburger durumu' );
		qrms_assert_contains( 'body:has(.rma-panel-sheet.open)', $css, 'filtre panel durumu' );
		qrms_assert_contains( '.gemini-chat-toggle-btn', $css, 'sohbet FAB' );
		qrms_assert_contains( '.gemini-teaser', $css, 'sohbet teaser' );
		qrms_assert_contains( '.qmo-bar', $css, 'sepet çubuğu' );
		qrms_assert_contains( '.qmo-cagri-bar', $css, 'garson/hesap çubuğu' );
		qrms_assert_contains( 'visibility: hidden !important', $css, 'görünürlük kapatma' );
		qrms_assert_contains( 'pointer-events: none !important', $css, 'tıklama kapatma' );
		qrms_assert_contains( '.gemini-chat-overlay.gemini-acik', $css, 'filtre açıkken açık sohbet katmanı' );
	}
);

qrms_test(
	'hamburger paneli sepet/sohbet z-index üstünde',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css' );
		preg_match( '/\.hfb-mobile-panel\s*\{[^}]*z-index:\s*(\d+)/s', $css, $m );
		qrms_assert_true( ! empty( $m[1] ), 'hamburger z-index tanımlı' );
		qrms_assert_true( (int) $m[1] > 2147482800, 'sohbet katmanının üstünde' );
	}
);

qrms_test(
	'varlık kaydı qmo-floating-layers handle ile bağlanır',
	function () {
		$assets = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php' );
		$hfb    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		$rma    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		$chat   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );

		qrms_assert_contains( "'qmo-floating-layers'", $assets, 'ortak kayıt' );
		qrms_assert_contains( 'floating-layer-states.css', $assets, 'dosya yolu' );
		qrms_assert_contains( "array( 'qmo-floating-layers' )", $hfb, 'HFB bağımlılığı' );
		qrms_assert_contains( "[ 'qmo-floating-layers' ]", $rma, 'RMA bağımlılığı' );
		qrms_assert_contains( "array( 'qmo-floating-layers' )", $chat, 'sepet bağımlılığı' );
	}
);
