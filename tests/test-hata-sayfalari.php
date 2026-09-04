<?php
/**
 * Markalı 404 / hata sayfası testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nMarkalı hata sayfaları\n";

qrms_test(
	'template_redirect kancası init ile kaydolur',
	function () {
		QRMS_Hata_Sayfalari::init();

		$kancalar = $GLOBALS['qrms_test']['actions'];

		qrms_assert_true( isset( $kancalar['template_redirect'] ), 'kanca var' );
		qrms_assert_true(
			in_array( array( 'QRMS_Hata_Sayfalari', 'template_redirect' ), $kancalar['template_redirect'], true ),
			'yakalayıcı kayıtlı'
		);
	}
);

qrms_test(
	'admin, ajax ve cron istekleri atlanır',
	function () {
		$GLOBALS['qrms_test']['is_404'] = true;

		$GLOBALS['qrms_test']['is_admin'] = true;
		QRMS_Hata_Sayfalari::template_redirect();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'admin atlanır' );

		$GLOBALS['qrms_test']['is_admin']   = false;
		$GLOBALS['qrms_test']['doing_ajax'] = true;
		QRMS_Hata_Sayfalari::template_redirect();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'ajax atlanır' );

		$GLOBALS['qrms_test']['doing_ajax'] = false;
		$GLOBALS['qrms_test']['doing_cron'] = true;
		QRMS_Hata_Sayfalari::template_redirect();
		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'cron atlanır' );
	}
);

qrms_test(
	'REST atlama deseni masa doğrulama ile aynıdır',
	function () {
		$php   = file_get_contents( QRMS_PLUGIN_DIR . 'includes/class-qrms-hata-sayfalari.php' );
		$kilit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/masa-dogrulama.php' );

		qrms_assert_contains( "defined( 'REST_REQUEST' ) && REST_REQUEST", $php, 'REST atlanır' );
		qrms_assert_contains( 'is_admin() || wp_doing_ajax() || wp_doing_cron()', $php, 'admin/ajax/cron' );
		qrms_assert_contains( "defined( 'REST_REQUEST' ) && REST_REQUEST", $kilit, 'kilit referansı' );
	}
);

qrms_test(
	'404 olmayan önyüz isteği belgesiz döner',
	function () {
		$GLOBALS['qrms_test']['is_404'] = false;

		QRMS_Hata_Sayfalari::template_redirect();

		qrms_assert_true( empty( $GLOBALS['qrms_test']['status_header'] ), 'durum basılmaz' );
	}
);

qrms_test(
	'html varsayılan başlık, mesaj, robots ve cache-bust içerir',
	function () {
		$html = QRMS_Hata_Sayfalari::html();

		qrms_assert_contains( '<!DOCTYPE html>', $html, 'bağımsız belge' );
		qrms_assert_contains( 'lang="tr-TR"', $html, 'dil' );
		qrms_assert_contains( 'name="robots" content="noindex,nofollow"', $html, 'robots' );
		qrms_assert_contains( 'Bulunamadı', $html, 'varsayılan başlık' );
		qrms_assert_contains( 'kaldırılmış olabilir', $html, 'varsayılan mesaj' );
		qrms_assert_contains( 'qrms-hata-kart', $html, 'kart' );
		qrms_assert_contains( 'assets/css/hata-sayfalari.css?ver=', $html, 'stil adresi' );
		qrms_assert_contains(
			QRMS_Helpers::asset_version( QRMS_Hata_Sayfalari::CSS_YOLU ),
			$html,
			'önbellek kırma'
		);
		qrms_assert_false( (bool) preg_match( '/dir="rtl"/', $html ), 'LTR varsayılan' );
	}
);

qrms_test(
	'html verilen başlık/mesajı kaçırır ve RTL işaretler',
	function () {
		$GLOBALS['qrms_test']['is_rtl'] = true;

		$html = QRMS_Hata_Sayfalari::html( 'Başlık <em>x</em>', 'Mesaj "y"' );

		qrms_assert_contains( 'dir="rtl"', $html, 'RTL' );
		qrms_assert_contains( 'Başlık &lt;em&gt;x&lt;/em&gt;', $html, 'başlık kaçışı' );
		qrms_assert_contains( 'Mesaj &quot;y&quot;', $html, 'mesaj kaçışı' );
		qrms_assert_false( (bool) strpos( $html, '<em>x</em>' ), 'ham HTML yok' );
	}
);

qrms_test(
	'kök dosya sınıfı yükler ve bootstrap init çağırır',
	function () {
		$kok = file_get_contents( QRMS_PLUGIN_DIR . 'qr-menu-suite.php' );

		qrms_assert_contains(
			"require_once QRMS_PLUGIN_DIR . 'includes/class-qrms-hata-sayfalari.php';",
			$kok,
			'require'
		);
		qrms_assert_contains( 'QRMS_Hata_Sayfalari::init()', $kok, 'init' );
	}
);

qrms_test(
	'giriş 404 fallback markalı render kullanır, wp_die kullanmaz',
	function () {
		$php = file_get_contents( QRMS_PLUGIN_DIR . 'includes/class-qrms-login.php' );

		qrms_assert_contains( 'QRMS_Hata_Sayfalari::render(', $php, 'paylaşılan render' );
		qrms_assert_contains( 'get_404_template', $php, 'tema şablonu korunur' );
		qrms_assert_false( (bool) strpos( $php, "array( 'response' => 404 )" ), 'wp_die 404 yok' );
	}
);

qrms_test(
	'stil kilit ekranı token’larını taşır, mobil kırılım yok',
	function () {
		$css   = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/hata-sayfalari.css' );
		$kilit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/assets/css/kilit.css' );

		qrms_assert_contains( '#0a0a0c', $css, 'arka plan' );
		qrms_assert_contains( '#e8c766', $css, 'altın başlık' );
		qrms_assert_contains( '#b7b7ae', $css, 'gövde metni' );
		qrms_assert_contains( 'Manrope', $css, 'font' );
		qrms_assert_contains( 'display: flex', $css, 'flex hizalama' );
		qrms_assert_contains( 'max-width: 420px', $css, 'kart genişliği' );
		qrms_assert_contains( 'width: 100%', $css, 'akışkan kart' );
		qrms_assert_false( (bool) preg_match( '/@media/', $css ), 'ayrı mobil CSS yok' );

		qrms_assert_contains( '#0a0a0c', $kilit, 'kilit arka plan referansı' );
		qrms_assert_contains( '#e8c766', $kilit, 'kilit altın referansı' );
		qrms_assert_contains( '#b7b7ae', $kilit, 'kilit gövde referansı' );
	}
);
