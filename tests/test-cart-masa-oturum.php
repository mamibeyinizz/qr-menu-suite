<?php
/**
 * Cart / masa oturumu entegrasyonu (1-2-11).
 *
 * Sipariş masası HMAC çerezinden gelir; sepet sessionStorage anahtarı
 * sunucunun localize ettiği masa slug'ına bağlıdır.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-oturum.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php';

echo "\nCart / masa oturumu\n";

qrms_test(
	'QR oturumu kayıtlı masada açılır, geçersiz slug oturum yazmaz',
	function () {
		$init = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-oturum.php' );
		$kilit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-masa-oturum-guvenligi/masa-dogrulama.php' );

		qrms_assert_contains( "if ( ! empty( \$_GET['masa'] ) )", $init, '?masa= okunur' );
		qrms_assert_contains( 'qmo_masa_gecerli_mi( $masa )', $init, 'kayıtlı masa şart' );
		qrms_assert_contains( "\$mevcut['masa'] !== \$masa", $init, 'masa değişince yeni token' );
		qrms_assert_contains( 'httponly\' => true', $init, 'çerez httponly' );

		qrms_assert_contains( "isset( \$_GET['masa'] )", $kilit, 'doğrulama URL slug' );
		qrms_assert_contains( '! qmo_masa_gecerli_mi( $gelen_masa )', $kilit, 'sahte QR kilit' );
		qrms_assert_contains( 'is_front_page() || is_home()', $kilit, 'anasayfa kilitlenmez' );
	}
);

qrms_test(
	'sipariş masası istemci gövdesinden değil HMAC oturumdan okunur',
	function () {
		$rest = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );
		$ajax = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/ajax-order.php' );
		$js   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( '$sess = qmo_oturum()', $rest, 'REST oturum' );
		qrms_assert_contains( "\$masa = (string) \$sess['masa']", $rest, 'masa oturumdan' );
		qrms_assert_contains( 'qmo_siparis_isle( $sess[\'masa\']', $ajax, 'chatbot sipariş oturum masası' );

		qrms_assert_false( false !== strpos( $rest, '$req->get_param( \'masa\'' ), 'REST masa parametresi yok' );
		qrms_assert_false( false !== strpos( $js, 'masa:' ), 'sepet gövdesine masa yazılmaz' );
		qrms_assert_false( false !== strpos( $js, "masaNo" ), 'sepet gövdesine masaNo yazılmaz' );
	}
);

qrms_test(
	'sepet depolama anahtarı HMAC masa slug\'ına bağlıdır',
	function () {
		$js   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		$chat = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/chatbot.js' );
		$php  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );
		$ortak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/assets.php' );

		qrms_assert_contains( "'masa'      => \$masa", $php, 'qmoSepet.masa localize' );
		qrms_assert_contains( "qmo_oturum()", $php, 'masa HMAC oturumdan' );
		qrms_assert_contains( "'qmo_sepet:' + masa", $js, 'sepet anahtarı masalı' );
		qrms_assert_contains( "sessionStorage.removeItem( 'qmo_sepet' )", $js, 'kapsamsız eski sepet düşer' );
		qrms_assert_contains( "'qmo_sepet:' + masa", $chat, 'chatbot aynı anahtar' );
		qrms_assert_contains( "\$veri['masa']", $ortak, 'qmoData.masa chatbot için' );

		qrms_assert_false(
			(bool) preg_match( "/var KEY = 'qmo_sepet';/", $js ),
			'tek global sepet anahtarı yok'
		);
	}
);

qrms_test(
	'masa oturumu WordPress login\'den ayrıdır ve sepet oturum ister',
	function () {
		$help = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/helpers.php' );
		$php  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/includes/shortcode-sepet.php' );

		qrms_assert_contains( 'MASA OTURUMU ≠ WORDPRESS OTURUMU', $help, 'ayrım belgelenir' );
		qrms_assert_contains( 'qmo_oturum()', $php, 'sepet HMAC ister' );
		qrms_assert_contains( "manage_options", $php, 'yönetici muaf' );
	}
);

qrms_test(
	'sipariş sonrası sepet yaz([]) ile boşalır; oturum çerezi silinmez',
	function () {
		$js   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );
		$rest = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/rest-order.php' );

		qrms_assert_contains( 'yaz( [] )', $js, 'başarılı siparişte sepet boşalır' );
		qrms_assert_false( false !== strpos( $js, "document.cookie" ), 'sepet JS masa çerezine dokunmaz' );
		qrms_assert_contains( 'qmo_cookie_yaz( QMO_Oturum::token_uret( $sess[\'masa\'], $sess[\'issued\'] ) )', $rest, 'siparişte oturum tazelenir silinmez' );
	}
);

qrms_test(
	'HMAC token başka masa adına uydurulamaz; slug absint edilmez',
	function () {
		$GLOBALS['qrms_test']['options'][ QMO_Oturum::OPT_KEY ] = 'test-hmac-cart-session-key-yeterince-uzun';

		$a = QMO_Oturum::token_uret( 'masa-1' );
		$b = QMO_Oturum::token_uret( 'masa-2' );

		qrms_assert_true( false !== QMO_Oturum::dogrula( $a ), 'masa-1 geçerli' );
		qrms_assert_same( 'masa-1', QMO_Oturum::dogrula( $a )['masa'], 'slug string' );
		qrms_assert_true( QMO_Oturum::dogrula( $a )['masa'] !== QMO_Oturum::dogrula( $b )['masa'], 'farklı masa' );

		$parca = explode( '|', $a );
		$parca[0] = 'masa-2';
		$sahte = implode( '|', $parca );
		qrms_assert_false( QMO_Oturum::dogrula( $sahte ), 'imza kırılınca reddedilir' );

		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/_qmo-ortak/class-qmo-oturum.php' );
		$govde  = substr( $kaynak, strpos( $kaynak, 'function qmo_oturum_init()' ) );
		qrms_assert_contains( 'sanitize_title', $govde, 'slug absint değil' );
		qrms_assert_false( false !== strpos( $govde, 'absint' ), 'init absint kullanmaz' );
	}
);

qrms_test(
	'fiyat zinciri sepet formülü bu PR ile değişmez',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-chatbot/assets/js/sepet.js' );

		qrms_assert_contains( 'taban + porsiyon.fark + ekstraToplami( ekstralar )', $js, '298 formülü durur' );
		qrms_assert_contains( 'imzaUret', $js, 'seçenek imzası durur' );
		qrms_assert_contains( 't += x.adet * x.fiyat', $js, 'satır toplamı durur' );
	}
);
