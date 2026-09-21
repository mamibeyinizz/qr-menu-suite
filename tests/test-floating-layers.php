<?php
/**
 * Mobil tam ekran paneller açıkken floating UI gizleme ve hamburger z-index.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nFloating katmanlar — hamburger / filtre paneli UX\n";

/**
 * @param string $css      floating-layer-states.css içeriği.
 * @param string $state    ':has(...)' parçası (ör. '.hfb-mobile-panel.is-open').
 * @param string $selector Hedef seçici (body:has öncesi).
 */
function qrms_floating_layer_assert_hidden( $css, $state, $selector ) {
	$needle = 'body:has(' . $state . ') ' . $selector;
	qrms_assert_contains( $needle, $css, $state . ' → ' . $selector );
}

qrms_test(
	'hamburger state: FAB, overlay, sepet, call/warn, toast gizlenir',
	function () {
		$css   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets/css/floating-layer-states.css' );
		$state = '.hfb-mobile-panel.is-open';

		qrms_floating_layer_assert_hidden( $css, $state, '.gemini-chat-toggle-btn' );
		qrms_floating_layer_assert_hidden( $css, $state, '.gemini-chat-overlay.gemini-acik' );
		qrms_floating_layer_assert_hidden( $css, $state, '.qmo-bar' );
		qrms_floating_layer_assert_hidden( $css, $state, '.qmo-ov' );
		qrms_floating_layer_assert_hidden( $css, $state, '.qmo-dr' );
		qrms_floating_layer_assert_hidden( $css, $state, '.hfb-footer__call-wrap:has(.qmo-cagri-bar)' );
		qrms_floating_layer_assert_hidden( $css, $state, '.hfb-footer__call-wrap:has(.hfb-footer__call--warn)' );
		qrms_floating_layer_assert_hidden( $css, $state, '.qmo-toast' );
	}
);

qrms_test(
	'filter state: FAB, overlay, sepet, call/warn, toast gizlenir',
	function () {
		$css   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets/css/floating-layer-states.css' );
		$state = '.rma-panel-sheet.open';

		qrms_floating_layer_assert_hidden( $css, $state, '.gemini-chat-toggle-btn' );
		qrms_floating_layer_assert_hidden( $css, $state, '.gemini-chat-overlay.gemini-acik' );
		qrms_floating_layer_assert_hidden( $css, $state, '.qmo-bar' );
		qrms_floating_layer_assert_hidden( $css, $state, '.qmo-ov' );
		qrms_floating_layer_assert_hidden( $css, $state, '.qmo-dr' );
		qrms_floating_layer_assert_hidden( $css, $state, '.hfb-footer__call-wrap:has(.qmo-cagri-bar)' );
		qrms_floating_layer_assert_hidden( $css, $state, '.hfb-footer__call-wrap:has(.hfb-footer__call--warn)' );
		qrms_floating_layer_assert_hidden( $css, $state, '.qmo-toast' );
	}
);

qrms_test(
	'.qmo-send visibility leak: sepet.css visible !important, panel state ile nötralize',
	function () {
		$sepet   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/sepet.css' );
		$layers  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets/css/floating-layer-states.css' );
		$markup  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );

		qrms_assert_contains( '#qmo-sepet-root .qmo-send', $sepet, 'sepet gönder butonu visible override' );
		qrms_assert_contains( 'visibility: visible !important', $sepet, 'child visible !important kaynağı' );
		qrms_assert_contains( 'class="qmo-dr"', $markup, '.qmo-send .qmo-dr içinde' );
		qrms_assert_contains( 'class="qmo-send"', $markup, 'gönder butonu çekmecede' );

		qrms_floating_layer_assert_hidden( $layers, '.hfb-mobile-panel.is-open', '#qmo-sepet-root .qmo-dr .qmo-send' );
		qrms_floating_layer_assert_hidden( $layers, '.rma-panel-sheet.open', '#qmo-sepet-root .qmo-dr .qmo-send' );
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
		$assets  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php' );
		$hfb     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		$rma     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		$chatbot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/chatbot.php' );
		$buttons = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/buttons.css' );

		qrms_assert_contains( "'qmo-floating-layers'", $assets, 'ortak kayıt' );
		qrms_assert_contains( 'floating-layer-states.css', $assets, 'dosya yolu' );
		qrms_assert_contains( "array( 'qmo-floating-layers' )", $hfb, 'HFB bağımlılığı' );
		qrms_assert_contains( "[ 'qmo-floating-layers' ]", $rma, 'RMA bağımlılığı' );
		qrms_assert_contains( "array( 'qmo-floating-layers' )", $chatbot, 'sepet bağımlılığı' );

		qrms_assert_false( (bool) preg_match( '/position:\s*(fixed|sticky)/', $buttons ), 'qmo-buttons fixed/sticky kullanmaz' );
		qrms_assert_false( (bool) preg_match( '/z-index\s*:/', $buttons ), 'qmo-buttons z-index kullanmaz' );
		qrms_assert_false(
			(bool) preg_match( "/wp_register_style\(\s*'qmo-buttons'[^)]*'qmo-floating-layers'/", $chatbot ),
			'qmo-buttons floating-layers bağımlılığı yok (gerek yok)'
		);
	}
);
