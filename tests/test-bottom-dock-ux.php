<?php
/**
 * QR menü alt dock UX — chatbot FAB, sepet dock, Garson/Hesap hiyerarşisi.
 *
 * Kapsam: kapalı chatbot yalnızca ikon, sepet birincil dock, Garson/Hesap
 * ikincil görsel ağırlık, içerik kaydırma payı token'larla hesaplanır.
 * Davranış (AJAX action, sepet click, chatbot toggle) değişmez.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nAlt dock UX (chatbot FAB / sepet / Garson-Hesap)\n";

qrms_test(
	'kapalı chatbot FAB: görünür asistan adı yok, aria-label çeviri köprüsünden, ikon aria-hidden',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-chatbot.php' );

		qrms_assert_contains(
			"qmo_ceviri_chat( __( 'Menü asistanını aç', 'qrms' ) )",
			$php,
			'FAB aria-label çeviri köprüsü'
		);
		qrms_assert_contains( 'aria-hidden="true"', $php, 'ikon sarmalayıcı dekoratif' );
		qrms_assert_false(
			(bool) preg_match( '/gemini-chat-toggle-btn[\s\S]{0,800}if\s*\(\s*\$metin_goster\s*\)/', $php ),
			'kapalı FAB üzerinde asistan adı basılmaz'
		);
		qrms_assert_false( false !== strpos( $php, 'gemini-toggle-label' ), 'ön yüz toggle etiketi yok' );

		foreach ( array( 'AI', 'Chatbot', 'Menü Asistanı', 'AI Garson', 'Yardım' ) as $yasak ) {
			qrms_assert_false(
				(bool) preg_match( '/gemini-chat-toggle-btn[\s\S]{0,400}' . preg_quote( $yasak, '/' ) . '/', $php ),
				'FAB görünür metninde ' . $yasak . ' yok'
			);
		}
	}
);

qrms_test(
	'chatbot.css: FAB 48–52px, metin gizli, sepet dock üstünde, reduced-motion durur',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/chatbot.css' );

		qrms_assert_contains( 'width: 50px;', $css, 'FAB görsel genişlik' );
		qrms_assert_contains( 'height: 50px;', $css, 'FAB görsel yükseklik' );
		qrms_assert_contains( 'min-width: 44px;', $css, 'dokunma hedefi eni' );
		qrms_assert_contains( 'min-height: 44px;', $css, 'dokunma hedefi boyu' );
		qrms_assert_contains( 'max-width: 52px;', $css, 'FAB tavanı' );
		qrms_assert_contains( 'max-height: 52px;', $css, 'FAB tavanı boy' );
		qrms_assert_contains( 'body:not(.wp-admin) .gemini-chat-toggle-btn .gemini-toggle-label', $css, 'ön yüzde etiket gizlenir' );
		qrms_assert_contains( 'html:has(#qmo-bar.qmo-on)', $css, 'sepet çubuğu görünürken FAB konumu' );
		qrms_assert_contains( 'html:has(#qmo-dr.qmo-on) .gemini-chat-toggle-btn', $css, 'çekmece açıkken FAB gizlenir' );
		qrms_assert_contains( 'html:has(#qmo-ov.qmo-on) .gemini-chat-toggle-btn', $css, 'overlay açıkken FAB gizlenir' );
		qrms_assert_contains( 'display: none !important', $css, 'çekmecede FAB display none' );
		qrms_assert_contains( '--gm-fab-bottom', $css, 'FAB alt token' );
		qrms_assert_contains( '--hfb-svc-row-h', $css, 'servis satırı FAB hesabına girer' );
		qrms_assert_contains( 'env(safe-area-inset-bottom, 0px)', $css, 'iOS safe-area' );
		qrms_assert_contains( '@media (prefers-reduced-motion: reduce)', $css, 'reduced motion durur' );
	}
);

qrms_test(
	'sepet dock birincil: mevcut #qmo-bar / çekmece korunur, chevron sunum katmanı',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/sepet.css' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( 'id="qmo-bar"', $php, 'mevcut bar id' );
		qrms_assert_contains( 'id="qmo-dr"', $php, 'mevcut çekmece' );
		qrms_assert_contains( "qmo_ceviri_cart( __( 'Sepeti aç', 'qrms' ) )", $php, 'sepet aria-label' );
		qrms_assert_contains( 'class="qmo-bar-go"', $php, 'chevron sunum' );
		qrms_assert_contains( 'id="qmo-bar-tot"', $php, 'toplam id korunur (JS textContent)' );
		qrms_assert_contains( '--qmo-bar-h', $css, 'dock yükseklik token' );
		qrms_assert_contains( '--qmo-bottom-ui-height', $css, 'ortak alt UI token' );
		qrms_assert_contains( 'var(--hfb-call-bar-h, 0px)', $css, 'eski çağrı bar yüksekliği 0 yedeği (dock alta oturur)' );
		qrms_assert_contains( 'var(--hfb-svc-row-h, 0px)', $css, 'servis satırı kaydırma payına girer' );
		qrms_assert_contains( "bar.addEventListener( 'click', ac )", $js, 'mevcut tıklama durur' );
		qrms_assert_same( 1, substr_count( $js, "bar.addEventListener( 'click', ac )" ), 'tek bar click dinleyicisi' );
	}
);

qrms_test(
	'Garson/Hesap ikincil: AJAX action adları ve data-qmo-cagri sözleşmesi durur',
	function () {
		$hfb  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );
		$css  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css' );
		$ajax = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-waiter-bill.php' );
		$js   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/buttons.js' );

		qrms_assert_contains( 'data-qmo-cagri="garson"', $hfb, 'garson sözleşmesi' );
		qrms_assert_contains( 'data-qmo-cagri="hesap"', $hfb, 'hesap sözleşmesi' );
		qrms_assert_contains( 'hfb-icon--call', $hfb, 'mevcut SVG ikon' );
		qrms_assert_contains( 'aria-label="\' . esc_attr( $garson )', $hfb, 'garson aria-label' );
		qrms_assert_contains( 'color: var(--hfb-muted, #8f8a82)', $css, 'ikincil muted renk' );
		qrms_assert_contains( 'background: transparent', $css, 'servis satırı zeminsiz' );
		qrms_assert_contains( 'width: max-content', $css, 'full-width bar değil' );
		qrms_assert_contains( 'pointer-events: none', $css, 'wrap tıklanabilir dock değil' );
		qrms_assert_contains( 'font-size: 14px', $css, 'ikincil 14px metin' );
		qrms_assert_contains( '--hfb-call-bar-h: 0px', $css, '48px bar yüksekliği layout\'a eklenmez' );
		qrms_assert_contains( '--hfb-svc-row-h: 44px', $css, 'dokunma satırı 44px' );
		qrms_assert_contains( "add_action( 'wp_ajax_garson_cagir'", $ajax, 'garson_cagir action' );
		qrms_assert_contains( "add_action( 'wp_ajax_hesap_iste'", $ajax, 'hesap_iste action' );
		qrms_assert_contains( "add_action( 'wp_ajax_qrservis_call'", $ajax, 'qrservis_call action' );
		qrms_assert_contains( '[data-qmo-cagri]', $js, 'mevcut tıklama seçicisi' );
		qrms_assert_contains( 'dataset.qmoButtonsInit', $js, 'çift dinleyici kilidi durur' );
	}
);

qrms_test(
	'menü kaydırma payı kör 150px değil, mevcut token + safe-area',
	function () {
		$rma = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-frontend.css' );
		$hfb = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/frontend.css' );

		qrms_assert_contains(
			'padding-bottom: calc(var(--qmo-bottom-ui-height, 0px) + var(--qmo-bottom-safe-space, 0px));',
			$rma,
			'rma-wrap token payı'
		);
		qrms_assert_false( false !== strpos( $rma, 'padding-bottom: 150px' ), 'kör 150px yok' );
		qrms_assert_contains( '--hfb-call-bar-h: 0px', $hfb, 'eski 48px bar token\'ı layout\'a eklenmez' );
		qrms_assert_contains( '--hfb-svc-row-h: 44px', $hfb, 'servis dokunma satırı token' );
		qrms_assert_contains( '--qmo-bottom-ui-height', $hfb, 'ortak yükseklik token HFB\'de' );
		qrms_assert_contains( 'env(safe-area-inset-bottom, 0px)', $hfb, 'safe-area HFB\'de' );
	}
);

qrms_test(
	'public API / shortcode / REST uçları bu revizyonda değişmedi',
	function () {
		$bot = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-chatbot.php' );
		$sep = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );
		$btn = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-buttons.php' );
		$ord = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );

		qrms_assert_contains( "add_shortcode( 'gemini_chatbot'", $bot, 'chatbot shortcode' );
		qrms_assert_contains( "add_shortcode( 'qmo_sepet'", $sep, 'sepet shortcode' );
		qrms_assert_contains( "add_shortcode( 'garson_butonu'", $btn, 'garson shortcode' );
		qrms_assert_contains( "add_shortcode( 'hesap_iste_butonu'", $btn, 'hesap shortcode' );
		qrms_assert_contains( "add_shortcode( 'ikili_buton'", $btn, 'ikili shortcode' );
		qrms_assert_contains( "add_shortcode( 'qr_garson_hesap'", $btn, 'alias shortcode' );
		qrms_assert_contains( 'qrservis/v1/order', $ord, 'sipariş REST' );
	}
);

qrms_test(
	'sepet çekmecesi açıkken chatbot gizlenir: mevcut #qmo-dr.qmo-on state, aria-hidden, odak yok',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/chatbot.css' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/chatbot.js' );
		$sep = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-chatbot.php' );

		qrms_assert_contains( "ov.classList.add( 'qmo-on' )", $sep, 'çekmece açılınca overlay qmo-on' );
		qrms_assert_contains( "dr.classList.add( 'qmo-on' )", $sep, 'çekmece açılınca drawer qmo-on' );
		qrms_assert_contains( "ov.classList.remove( 'qmo-on' )", $sep, 'kapanınca overlay class kalkar' );
		qrms_assert_contains( "dr.classList.remove( 'qmo-on' )", $sep, 'kapanınca drawer class kalkar' );

		qrms_assert_contains( 'function sepetCekmeceAcikMi()', $js, 'mevcut çekmece state okunur' );
		qrms_assert_contains( "dr.classList.contains( 'qmo-on' )", $js, 'qmo-dr.qmo-on kullanılır' );
		qrms_assert_contains( "setAttribute( 'aria-hidden', 'true' )", $js, 'çekmece açıkken aria-hidden' );
		qrms_assert_contains( "removeAttribute( 'aria-hidden' )", $js, 'çekmece kapanınca aria-hidden kalkar' );
		qrms_assert_contains( "setAttribute( 'tabindex', '-1' )", $js, 'çekmece açıkken odak dışı' );
		qrms_assert_contains( "setAttribute( 'tabindex', '0' )", $js, 'kapanınca tabindex geri gelir' );
		qrms_assert_contains( 'chatbotFabA11ySepet', $js, 'a11y senkron fonksiyonu' );
		qrms_assert_contains( 'MutationObserver', $js, 'çekmece class değişimini izler' );
		qrms_assert_contains( 'if ( sepetCekmeceAcikMi() )', $js, 'çekmece açıkken FAB açılmaz' );

		qrms_assert_contains(
			"qmo_ceviri_chat( __( 'Menü asistanını aç', 'qrms' ) )",
			$php,
			'aria-label Menü asistanını aç korunur'
		);
		qrms_assert_false( false !== strpos( $js, "aria-label', 'Menü asistanını aç" ), 'JS aria-label üzerine yazmaz' );

		qrms_assert_contains( 'html:has(#qmo-dr.qmo-on) .gemini-teaser', $css, 'teaser da gizlenir' );
		qrms_assert_false( (bool) preg_match( '/#qmo-dr\.qmo-on[^{]{0,200}opacity:\s*0/', $css ), 'opacity ile yarı gizleme yok' );
		qrms_assert_false( (bool) preg_match( '/#qmo-dr\.qmo-on[^{]{0,200}visibility:\s*hidden/', $css ), 'visibility hidden değil, display none' );
	}
);
