<?php
/**
 * QR Çeviri (mobil) testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

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

