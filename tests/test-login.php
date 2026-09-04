<?php
/**
 * Özel giriş adresi ve giriş ekranı görünümü testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * DİKKAT: bu dosyanın SON testi `QRMS_LOGIN_DISABLE` sabitini tanımlar ve
 * sabit geri alınamaz. Bu yüzden o test dosyanın en sonundadır; dosyanın
 * kendisi koşucuda en başta yüklenir (sabit yalnızca QRMS_Login'i etkiler,
 * başka hiçbir test dosyası bu sınıfa dokunmaz).
 *
 * @package QR_Menu_Suite
 */

echo "\nGiriş adresi ve giriş ekranı\n";

qrms_test(
	'slug temizlenir; rezerve ve çok kısa değerler reddedilir',
	function () {
		qrms_assert_same( 'qrm', QRMS_Login::normalize_slug( ' QRM ' ), 'boşluk ve büyük harf' );
		qrms_assert_same( 'giris-yolu', QRMS_Login::normalize_slug( 'Giriş Yolu' ), 'Türkçe karakter ve boşluk' );
		qrms_assert_same( 'panel', QRMS_Login::normalize_slug( '/panel/' ), 'eğik çizgiler düşer' );

		qrms_assert_same( '', QRMS_Login::validate_slug( 'qrm' ), 'geçerli slug' );
		qrms_assert_same( '', QRMS_Login::validate_slug( 'yonetim-2' ), 'rakam ve tire geçerli' );

		qrms_assert_true( '' !== QRMS_Login::validate_slug( '' ), 'boş reddedilir' );
		qrms_assert_true( '' !== QRMS_Login::validate_slug( 'a' ), 'tek karakter reddedilir' );
		qrms_assert_true( '' !== QRMS_Login::validate_slug( 'wp-admin' ), 'wp-admin reddedilir' );
		qrms_assert_true( '' !== QRMS_Login::validate_slug( 'wp-login' ), 'wp-login reddedilir' );
		qrms_assert_true( '' !== QRMS_Login::validate_slug( 'feed' ), 'feed reddedilir' );
		qrms_assert_true( '' !== QRMS_Login::validate_slug( str_repeat( 'a', 65 ) ), 'çok uzun reddedilir' );
	}
);

qrms_test(
	'giriş adresi kalıcı bağlantı yapısına göre üretilir',
	function () {
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( QRMS_Login::OPTION, array( 'slug' => 'qrm' ) );

		qrms_assert_same( 'https://restoran.test/qrm/', QRMS_Login::login_url(), 'kalıcı bağlantı açık' );

		update_option( 'permalink_structure', '' );

		qrms_assert_same( 'https://restoran.test/?qrm', QRMS_Login::login_url(), 'kalıcı bağlantı kapalı' );
	}
);

qrms_test(
	'wp-login.php adresleri sorgu parametreleri korunarak çevrilir',
	function () {
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( QRMS_Login::OPTION, array( 'slug' => 'qrm' ) );

		// Şifre sıfırlama e-postasındaki bağlantı bu yoldan geçer: anahtar ve
		// kullanıcı adı kaybolursa bağlantı çalışmaz.
		$sifirlama = QRMS_Login::filter_site_url(
			'https://restoran.test/wp-login.php?action=rp&key=abc123&login=admin',
			'wp-login.php?action=rp&key=abc123&login=admin'
		);

		qrms_assert_contains( 'https://restoran.test/qrm/', $sifirlama, 'yeni yol' );
		qrms_assert_contains( 'action=rp', $sifirlama, 'action korunur' );
		qrms_assert_contains( 'key=abc123', $sifirlama, 'key korunur' );
		qrms_assert_contains( 'login=admin', $sifirlama, 'login korunur' );
		qrms_assert_false( strpos( $sifirlama, 'wp-login.php' ), 'eski yol kalmaz' );

		// İçinde wp-login.php geçmeyen adres hiç ellenmez.
		$normal = QRMS_Login::filter_site_url( 'https://restoran.test/wp-admin/', 'wp-admin/' );

		qrms_assert_same( 'https://restoran.test/wp-admin/', $normal, 'ilgisiz adres değişmez' );

		// wp_redirect ve login_url filtreleri de aynı çevrimi yapar.
		qrms_assert_contains(
			'/qrm/',
			QRMS_Login::filter_redirect( 'https://restoran.test/wp-login.php?loggedout=true' ),
			'yönlendirme çevrilir'
		);
		qrms_assert_contains(
			'/qrm/',
			QRMS_Login::filter_generic_url( 'https://restoran.test/wp-login.php' ),
			'login_url çevrilir'
		);
	}
);

qrms_test(
	'giriş yolu yalnızca ayar açıkken ve slug geçerliyken devrededir',
	function () {
		qrms_assert_false( QRMS_Login::is_disabled_by_constant(), 'sabit tanımlı değil' );

		// Varsayılan KAPALI: eklenti güncellemesi kimsenin adresini değiştirmemeli.
		qrms_assert_false( QRMS_Login::is_active(), 'varsayılan kapalı' );

		update_option( QRMS_Login::OPTION, array( 'yol_aktif' => 1, 'slug' => 'qrm' ) );
		qrms_assert_true( QRMS_Login::is_active(), 'açıkken devrede' );

		update_option( QRMS_Login::OPTION, array( 'yol_aktif' => 1, 'slug' => 'wp-admin' ) );
		qrms_assert_false( QRMS_Login::is_active(), 'rezerve slug devreye almaz' );

		// Görünüm yoldan bağımsızdır ve varsayılan olarak açıktır.
		update_option( QRMS_Login::OPTION, array() );
		qrms_assert_true( QRMS_Login::is_skin_active(), 'görünüm varsayılan açık' );

		update_option( QRMS_Login::OPTION, array( 'gorunum_aktif' => 0 ) );
		qrms_assert_false( QRMS_Login::is_skin_active(), 'görünüm kapatılabilir' );
	}
);

qrms_test(
	'yol kapalıyken istek yakalama kancaları hiç bağlanmaz',
	function () {
		update_option( QRMS_Login::OPTION, array( 'yol_aktif' => 0 ) );

		QRMS_Login::init();

		$kancalar = $GLOBALS['qrms_test']['actions'];

		qrms_assert_false( isset( $kancalar['plugins_loaded'] ), 'istek yakalama yok' );
		qrms_assert_false( isset( $kancalar['site_url'] ), 'adres filtresi yok' );

		// Görünüm açık olduğu için giriş ekranı kancaları bağlanır.
		qrms_assert_true( isset( $kancalar['login_enqueue_scripts'] ), 'görünüm kancası var' );

		// Yönetim tarafı her hâlükârda kayıtlı: kullanıcı kapattığı özelliği
		// geri açabilmeli.
		qrms_assert_true( isset( $kancalar['admin_post_' . QRMS_Login::ACTION ] ), 'ayar kaydı kayıtlı' );
	}
);

qrms_test(
	'yol açıkken istek yakalama ve adres filtreleri bağlanır',
	function () {
		update_option( QRMS_Login::OPTION, array( 'yol_aktif' => 1, 'slug' => 'qrm' ) );

		QRMS_Login::init();

		$kancalar = $GLOBALS['qrms_test']['actions'];

		foreach ( array( 'plugins_loaded', 'wp_loaded', 'site_url', 'network_site_url', 'wp_redirect', 'login_url', 'logout_url', 'lostpassword_url' ) as $kanca ) {
			qrms_assert_true( isset( $kancalar[ $kanca ] ), $kanca . ' bağlandı' );
		}

		// İstek en erken noktada yakalanmalı; öncelik 1 olmazsa WordPress
		// kendi giriş yönlendirmesini önce yapar.
		qrms_assert_same( 1, $GLOBALS['qrms_test']['priorities']['plugins_loaded'][0], 'plugins_loaded önceliği' );
	}
);

qrms_test(
	'istek yolu giriş yoluyla eşleşir, wp-login.php ayırt edilir',
	function () {
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( QRMS_Login::OPTION, array( 'yol_aktif' => 1, 'slug' => 'qrm' ) );

		$_SERVER['REQUEST_URI'] = '/qrm';
		qrms_assert_same( '/qrm', QRMS_Login::request_path(), 'yol ayrıştırılır' );
		qrms_assert_true( QRMS_Login::is_login_path( QRMS_Login::request_path() ), 'giriş yolu' );

		$_SERVER['REQUEST_URI'] = '/qrm/';
		qrms_assert_true( QRMS_Login::is_login_path( QRMS_Login::request_path() ), 'sondaki eğik çizgi fark etmez' );

		$_SERVER['REQUEST_URI'] = '/qrm/?redirect_to=%2Fwp-admin%2F';
		qrms_assert_same( '/qrm', QRMS_Login::request_path(), 'sorgu dizesi yoldan ayrılır' );
		qrms_assert_true( QRMS_Login::is_login_path( QRMS_Login::request_path() ), 'sorgulu giriş yolu' );

		// Yüzde kodlu yol bozulmadan çözülür: sanitize_text_field() burada
		// kullanılsaydı %C5%9F silinir, Türkçe slug hiç eşleşmezdi.
		update_option( QRMS_Login::OPTION, array( 'yol_aktif' => 1, 'slug' => 'giris' ) );
		$_SERVER['REQUEST_URI'] = '/giri%73/';
		qrms_assert_same( '/giris', QRMS_Login::request_path(), 'yüzde kodlu yol çözülür' );

		update_option( QRMS_Login::OPTION, array( 'yol_aktif' => 1, 'slug' => 'qrm' ) );
		$_SERVER['REQUEST_URI'] = '/menu/';
		qrms_assert_false( QRMS_Login::is_login_path( QRMS_Login::request_path() ), 'müşteri sayfası giriş yolu değil' );
		qrms_assert_false( QRMS_Login::is_wp_login_path( QRMS_Login::request_path() ), 'müşteri sayfası wp-login değil' );

		$_SERVER['REQUEST_URI'] = '/wp-login.php?action=lostpassword';
		qrms_assert_true( QRMS_Login::is_wp_login_path( QRMS_Login::request_path() ), 'wp-login tanınır' );

		unset( $_SERVER['REQUEST_URI'] );
	}
);

qrms_test(
	'wp-login.php yalnızca oturumsuz ve postpass dışı isteklerde engellenir',
	function () {
		qrms_assert_true( QRMS_Login::should_block_wp_login( '', false ), 'oturumsuz giriş denemesi engellenir' );
		qrms_assert_true( QRMS_Login::should_block_wp_login( 'lostpassword', false ), 'oturumsuz şifre sıfırlama engellenir' );

		// Şifre korumalı yazıların form gönderimi wp-login.php'ye POST eder;
		// engellenirse müşteriye açık sayfalar bozulur.
		qrms_assert_false( QRMS_Login::should_block_wp_login( 'postpass', false ), 'postpass geçer' );

		// Oturumu açık kullanıcı için çıkış ve ara giriş penceresi çalışmalı.
		qrms_assert_false( QRMS_Login::should_block_wp_login( 'logout', true ), 'oturum açıkken engellenmez' );
	}
);

qrms_test(
	'ayarlar temizlenir: geçersiz değer varsayılana, slug eskiye düşer',
	function () {
		$eski = array_merge( QRMS_Login::defaults(), array( 'slug' => 'panel' ) );

		$yeni = QRMS_Login::sanitize_settings(
			array(
				'slug'              => 'wp-admin',   // rezerve -> eski slug korunur
				'duzen'             => 'uydurma',    // bilinmeyen -> varsayılan
				'tema'              => 'acik',
				'vurgu'             => 'kırmızı',    // geçersiz hex -> varsayılan
				'vurgu2'            => '#0f0',       // kısa hex geçerli
				'arkaplan_tip'      => 'gorsel',
				'arkaplan_karartma' => 250,          // aralık dışı -> 90
				'arkaplan_bulanik'  => -5,           // aralık dışı -> 0
				'logo_yukseklik'    => 5,            // aralık dışı -> 24
				'kart_yaricap'      => 18,
				'baslik'            => '  Restoran Paneli  ',
				'gorunum_aktif'     => '1',
				'uydurma_anahtar'   => 'x',          // bilinmeyen -> düşer
			),
			$eski
		);

		qrms_assert_same( 'panel', $yeni['slug'], 'geçersiz slug eskiye düşer' );
		qrms_assert_same( 'bolunmus', $yeni['duzen'], 'bilinmeyen düzen varsayılana düşer' );
		qrms_assert_same( 'acik', $yeni['tema'], 'geçerli tema korunur' );
		qrms_assert_same( '#c9a84c', $yeni['vurgu'], 'geçersiz renk varsayılana düşer' );
		qrms_assert_same( '#0f0', $yeni['vurgu2'], 'kısa hex kabul' );
		qrms_assert_same( 90, $yeni['arkaplan_karartma'], 'üst sınır' );
		qrms_assert_same( 0, $yeni['arkaplan_bulanik'], 'alt sınır' );
		qrms_assert_same( 24, $yeni['logo_yukseklik'], 'logo alt sınırı' );
		qrms_assert_same( 'Restoran Paneli', $yeni['baslik'], 'başlık kırpılır' );
		qrms_assert_same( 1, $yeni['gorunum_aktif'], 'işaretli kutu 1' );
		qrms_assert_same( 0, $yeni['yol_aktif'], 'gönderilmeyen kutu 0' );
		qrms_assert_false( isset( $yeni['uydurma_anahtar'] ), 'bilinmeyen anahtar düşer' );

		// Geçerli slug gönderildiğinde kabul edilir.
		$gecerli = QRMS_Login::sanitize_settings( array( 'slug' => 'Yeni Panel' ), $eski );
		qrms_assert_same( 'yeni-panel', $gecerli['slug'], 'geçerli slug kabul' );
	}
);

qrms_test(
	'CSS değişkenleri ve gövde sınıfları ayarlardan üretilir',
	function () {
		$s = array_merge(
			QRMS_Login::defaults(),
			array(
				'vurgu'             => '#ff0000',
				'arkaplan_tip'      => 'gorsel',
				'arkaplan_karartma' => 40,
				'kart_yaricap'      => 24,
				'logo_yukseklik'    => 80,
			)
		);

		$css = QRMS_Login::css_variables( $s, 'https://restoran.test/bg.jpg', 'https://restoran.test/logo.png' );

		qrms_assert_contains( '--qrms-lg-vurgu: #ff0000', $css, 'vurgu rengi' );
		qrms_assert_contains( '--qrms-lg-karartma: 0.4', $css, 'karartma orana çevrilir' );
		qrms_assert_contains( '--qrms-lg-radius: 24px', $css, 'köşe yarıçapı' );
		qrms_assert_contains( '--qrms-lg-logo-h: 80px', $css, 'logo yüksekliği' );
		qrms_assert_contains( '--qrms-lg-bg-image: url(https://restoran.test/bg.jpg)', $css, 'arka plan görseli' );
		qrms_assert_contains( '--qrms-lg-logo: url(https://restoran.test/logo.png)', $css, 'logo' );

		// Arka plan tipi "gorsel" değilse görsel değişkeni hiç basılmaz;
		// aksi hâlde düz renge geçen kullanıcının eski görseli geri gelirdi.
		$s['arkaplan_tip'] = 'renk';
		$css_renk = QRMS_Login::css_variables( $s, 'https://restoran.test/bg.jpg', '' );

		qrms_assert_false( strpos( $css_renk, '--qrms-lg-bg-image' ), 'düz renkte görsel basılmaz' );
		qrms_assert_false( strpos( $css_renk, '--qrms-lg-logo:' ), 'logosuzken logo basılmaz' );

		$siniflar = QRMS_Login::skin_classes(
			array_merge(
				QRMS_Login::defaults(),
				array(
					'duzen'           => 'merkez',
					'tema'            => 'otomatik',
					'arkaplan_tip'    => 'gorsel',
					'kart_cam'        => 0,
					'beni_hatirla'    => 0,
					'sifremi_unuttum' => 0,
					'logo'            => 12,
				)
			)
		);

		qrms_assert_true( in_array( 'qrms-login', $siniflar, true ), 'kök sınıf' );
		qrms_assert_true( in_array( 'qrms-login-duzen-merkez', $siniflar, true ), 'düzen sınıfı' );
		qrms_assert_true( in_array( 'qrms-login-tema-otomatik', $siniflar, true ), 'tema sınıfı' );
		qrms_assert_true( in_array( 'qrms-login-bg-gorsel', $siniflar, true ), 'arka plan sınıfı' );
		qrms_assert_true( in_array( 'qrms-login-hatirla-gizli', $siniflar, true ), 'beni hatırla gizlenir' );
		qrms_assert_true( in_array( 'qrms-login-nav-gizli', $siniflar, true ), 'şifremi unuttum gizlenir' );
		qrms_assert_true( in_array( 'qrms-login-logolu', $siniflar, true ), 'logo sınıfı' );
		qrms_assert_false( in_array( 'qrms-login-cam', $siniflar, true ), 'cam efekti kapalı' );
	}
);

qrms_test(
	'marka bloğu WordPress mesajını yok etmez',
	function () {
		update_option(
			QRMS_Login::OPTION,
			array(
				'baslik'    => 'Restoran Paneli',
				'alt_metin' => 'Personel girişi',
			)
		);

		$cikti = QRMS_Login::login_message( '<p class="message">Şifreniz sıfırlandı.</p>' );

		qrms_assert_contains( 'qrms-login-brand', $cikti, 'marka bloğu basılır' );
		qrms_assert_contains( 'Restoran Paneli', $cikti, 'başlık' );
		qrms_assert_contains( 'Personel girişi', $cikti, 'alt metin' );
		qrms_assert_contains( 'Şifreniz sıfırlandı.', $cikti, 'WordPress mesajı korunur' );

		// Başlık boşsa site adına düşülür.
		update_option( QRMS_Login::OPTION, array( 'baslik' => '' ) );
		qrms_assert_contains( get_bloginfo( 'name' ), QRMS_Login::login_message( '' ), 'site adına düşer' );
	}
);

qrms_test(
	'gövde sınıfları temanın kendi sınıflarını ezmez',
	function () {
		update_option( QRMS_Login::OPTION, array( 'duzen' => 'merkez' ) );

		$siniflar = QRMS_Login::body_class( array( 'login', 'wp-core-ui' ) );

		qrms_assert_true( in_array( 'login', $siniflar, true ), 'mevcut sınıf korunur' );
		qrms_assert_true( in_array( 'wp-core-ui', $siniflar, true ), 'mevcut sınıf korunur' );
		qrms_assert_true( in_array( 'qrms-login-duzen-merkez', $siniflar, true ), 'yeni sınıf eklenir' );
	}
);

qrms_test(
	'giriş ekranının stil dosyası önizlemeyle aynı kuralları paylaşır',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'assets/css/login.css' );

		// Önizleme, gerçek ekranın stylesheet'ini kullanır: her yapısal kural
		// iki seçiciyle birden yazılmalı, yoksa iki görünüm zamanla ayrışır.
		qrms_assert_contains( '.qrms-login .qrms-lp-box', $css, 'önizleme kartı' );
		qrms_assert_contains( '.qrms-login #login', $css, 'gerçek kart' );
		qrms_assert_contains( '.qrms-lp-brand', $css, 'önizleme marka bloğu' );
		qrms_assert_contains( '.qrms-login-brand', $css, 'gerçek marka bloğu' );

		// Mobil kuralları: iOS'ta yakınlaştırmayı önleyen 16px ve çentik payı.
		qrms_assert_contains( 'font-size: 16px', $css, 'input yazı boyu' );
		qrms_assert_contains( 'env(safe-area-inset-bottom)', $css, 'çentik payı' );
		qrms_assert_contains( 'prefers-reduced-motion', $css, 'hareket tercihi' );
	}
);

qrms_test(
	'varlıklar önbellek kıran sürümle kuyruğa alınır',
	function () {
		update_option( QRMS_Login::OPTION, array( 'gorunum_aktif' => 1 ) );

		QRMS_Login::enqueue_login_assets();

		$surum = '';

		foreach ( $GLOBALS['qrms_test']['styles'] as $stil ) {
			if ( 'qrms-login' === $stil['handle'] ) {
				$surum = (string) $stil['ver'];
			}
		}

		qrms_assert_true( '' !== $surum, 'stil kuyruğa alındı' );
		qrms_assert_true( QRMS_VERSION !== $surum, 'sabit sürümle değil' );
		qrms_assert_contains( QRMS_VERSION . '.', $surum, 'sürüm + dosya zamanı' );
	}
);

qrms_test(
	'QRMS_LOGIN_DISABLE tanımlıyken hiçbir giriş kancası bağlanmaz',
	function () {
		// Bu sabit geri alınamaz; bu yüzden dosyanın SON testidir ve
		// tests/test-suite.php bu dosyayı en sona yükler.
		define( 'QRMS_LOGIN_DISABLE', true );

		update_option( QRMS_Login::OPTION, array( 'yol_aktif' => 1, 'gorunum_aktif' => 1, 'slug' => 'qrm' ) );

		qrms_assert_true( QRMS_Login::is_disabled_by_constant(), 'sabit okunur' );
		qrms_assert_false( QRMS_Login::is_active(), 'yol kapalı' );
		qrms_assert_false( QRMS_Login::is_skin_active(), 'görünüm kapalı' );

		QRMS_Login::init();

		$kancalar = $GLOBALS['qrms_test']['actions'];

		qrms_assert_false( isset( $kancalar['plugins_loaded'] ), 'istek yakalama yok' );
		qrms_assert_false( isset( $kancalar['login_enqueue_scripts'] ), 'görünüm kancası yok' );
		qrms_assert_false( isset( $kancalar['site_url'] ), 'adres filtresi yok' );
	}
);
