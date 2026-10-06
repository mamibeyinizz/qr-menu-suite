<?php
/**
 * QR Restoran Menü — dar ekran (1-2-9) CSS sözleşmeleri.
 *
 * Gerçek tarayıcı ölçümü tests/fixtures/rma-mobile-viewport.html ile
 * Chrome headless üzerinden yapılır. Bu dosya kök neden kurallarının
 * kaynakta durduğunu doğrular.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nQR Restoran Menü — mobil viewport CSS\n";

qrms_test(
	'dar ekranda filtre ızgarası 2 sütuna iner; 360px kuralı boş bırakılmaz',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-frontend.css' );
		qrms_assert_contains(
			'@media (max-width: 360px) { .rma-filter-cards { grid-template-columns: repeat(2, 1fr); } }',
			$css,
			'360px altında 2 sütun'
		);
		qrms_assert_false(
			(bool) preg_match(
				'/@media \(max-width: 360px\) \{ \.rma-filter-cards \{ grid-template-columns: repeat\(3, 1fr\); \} \}/',
				$css
			),
			'eski 3 sütun kuralı durmaz'
		);
	}
);

qrms_test(
	'320px araç çubuğunda Filtrele etiketi gizlenir, aria-label kalır',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-frontend.php' );
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-frontend.css' );
		qrms_assert_contains( 'class="rma-filter-trigger-label"', $php, 'etiket sarmalayıcı' );
		qrms_assert_contains( 'aria-label="', $php, 'aria-label durur' );
		qrms_assert_contains( '@media (max-width: 359px)', $css, '320/360 kırılımı' );
		qrms_assert_contains( '.rma-filter-trigger-label { display: none; }', $css, 'etiket gizlenir' );
	}
);

qrms_test(
	'modal kapatma ve fiyat/başlık dar ekranda taşmaz, dokunma alanı 44px',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/assets/css/rma-frontend.css' );
		qrms_assert_contains( '.rma-modal-close { position:absolute; top:8px; right:8px; width:44px; height:44px;', $css, 'kapat 44px' );
		qrms_assert_contains( '.rma-modal-title', $css, 'başlık kuralı' );
		qrms_assert_contains( 'overflow-wrap: anywhere', $css, 'uzun ad sarar' );
		qrms_assert_contains( 'flex-wrap:wrap', $css, 'kampanya fiyatı sarar' );
		qrms_assert_contains( '.rma-porsiyon-sec {', $css, 'porsiyon kuralı' );
		qrms_assert_contains( 'min-height: 44px', $css, 'porsiyon dokunma' );
		qrms_assert_contains( 'max-width: 100%', $css, 'porsiyon taşmaz' );
		qrms_assert_contains( '.rma-ekstra-ad { color: var(--rma-text, #f5f0e8); flex: 1; font-size: .85rem; min-width: 0; overflow-wrap: anywhere; }', $css, 'ekstra ad sarar' );
	}
);

qrms_test(
	'sepet satırı dar ekranda sarar; +/- 44px; uzun ad kırılmaz',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/sepet.css' );
		qrms_assert_contains( 'flex-wrap: wrap;', $css, 'sepet satırı sarar' );
		qrms_assert_contains( 'flex: 1 1 8rem;', $css, 'ürün adı esner' );
		qrms_assert_contains( 'overflow-wrap: anywhere;', $css, 'uzun ad sarar' );
		qrms_assert_contains( "width: 44px;\n\theight: 44px;", $css, '+/- dokunma' );
		qrms_assert_contains( '.qmo-md-row', $css, 'modal sepet satırı' );
		qrms_assert_contains( 'max-width: calc(100% - 24px)', $css, 'alt bar viewport içinde' );
	}
);

qrms_test(
	'chatbot alanı iOS zoom tetiklemez (16px)',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/css/chatbot.css' );
		qrms_assert_contains( 'font-size: 16px;', $css, 'girdi 16px' );
		qrms_assert_contains( 'min-width: 0;', $css, 'girdi daralır' );
	}
);

qrms_test(
	'mobil viewport fixture dosyası mevcut',
	function () {
		$yol = QRMS_PLUGIN_DIR . 'tests/fixtures/rma-mobile-viewport.html';
		qrms_assert_true( is_file( $yol ), 'fixture var' );
		$html = file_get_contents( $yol );
		qrms_assert_contains( 'rma-wrap', $html, 'menü sarmalayıcı' );
		qrms_assert_contains( 'qmo-it-ad', $html, 'sepet satırı' );
		qrms_assert_contains( 'rma-modal-overlay', $html, 'ürün modalı' );
	}
);
