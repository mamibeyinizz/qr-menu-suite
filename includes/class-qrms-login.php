<?php
/**
 * Özel giriş adresi ve giriş ekranı görünümü.
 *
 * İki bağımsız iş yapar:
 *
 * 1. YOL — `wp-login.php` yerine site köküne göre serbest bir slug
 *    (varsayılan `qrm`) üzerinden giriş. `wp-login.php` ve oturumsuz
 *    `wp-admin` istekleri 404 döner; WordPress'in ürettiği bütün giriş
 *    adresleri (çıkış, şifre sıfırlama e-postası, yeniden yetkilendirme)
 *    filtrelerle yeni yola çevrilir.
 *
 * 2. GÖRÜNÜM — giriş ekranının WordPress varsayılan arayüzü yerine
 *    ayarlardan yönetilen bir tasarım basılır. Form, nonce'lar ve kimlik
 *    doğrulama akışı WordPress'in kendi kodudur; yalnızca sunum değişir
 *    (iki aşamalı doğrulama gibi eklentiler bozulmasın diye form YENİDEN
 *    YAZILMAZ, yalnızca CSS/az miktarda JS ile giydirilir).
 *
 * GÜVENLİK NOTU / KİLİTLENME: yol değiştirildiğinde slug unutulursa siteye
 * girilemez. Üç koruma vardır: (a) `wp-config.php` içine
 * `define( 'QRMS_LOGIN_DISABLE', true );` yazmak özelliği tamamen kapatır,
 * (b) slug her değiştiğinde site yöneticisine yeni adres e-postayla gider,
 * (c) yönetim ekranlarında adresi gösteren kalıcı bir bilgi kutusu durur.
 *
 * Özellik çok siteli (multisite) kurulumda devreye girmez: ağ genelinde
 * giriş adresi tek bir siteye ait bir option'la yönetilemez.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Giriş yolu + giriş ekranı görünümü.
 */
class QRMS_Login {

	/**
	 * Ayarların tutulduğu option.
	 */
	const OPTION = 'qrms_login_ayarlar';

	/**
	 * Varsayılan giriş slug'ı.
	 */
	const DEFAULT_SLUG = 'qrm';

	/**
	 * Ayar formunun nonce eylemi.
	 */
	const NONCE = 'qrms_login_ayar_kaydet';

	/**
	 * Ayarların kaydedildiği admin-post eylemi.
	 */
	const ACTION = 'qrms_login_kaydet';

	/**
	 * Slug olarak kullanılamayacak değerler.
	 *
	 * WordPress'in kendi uçları ve tipik yeniden yazma çakışmaları. Buraya
	 * yazılmayan bir çakışma (aynı adlı sayfa/yazı) ayrıca kontrol edilir.
	 *
	 * @var string[]
	 */
	const RESERVED = array(
		'wp-admin',
		'admin',
		'login',
		'wp-login',
		'wp-login.php',
		'wp-content',
		'wp-includes',
		'wp-json',
		'wp-signup',
		'wp-activate',
		'wp-cron',
		'xmlrpc',
		'feed',
		'rss',
		'rss2',
		'atom',
		'embed',
		'sitemap',
		'robots',
		'favicon',
		'index',
		'trackback',
		'page',
		'comments',
		'author',
		'category',
		'tag',
	);

	/**
	 * İstek bu istekte 404'e düşürüldü mü?
	 *
	 * @var bool
	 */
	private static $bloke = false;

	/* -----------------------------------------------------------------
	   AYARLAR
	----------------------------------------------------------------- */

	/**
	 * Varsayılan ayarlar.
	 *
	 * `yol_aktif` bilinçli olarak KAPALI gelir: eklenti güncellemesi hiç
	 * kimsenin giriş adresini habersiz değiştirmemeli. Görünüm ise yalnızca
	 * sunum olduğu için açık gelir.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Yol.
			'yol_aktif'         => 0,
			'slug'              => self::DEFAULT_SLUG,
			'wp_admin_koru'     => 1,

			// Görünüm.
			'gorunum_aktif'     => 1,
			'duzen'             => 'bolunmus',
			'tema'              => 'koyu',
			'vurgu'             => '#c9a84c',
			'vurgu2'            => '#e8c874',
			'arkaplan_tip'      => 'gradyan',
			'arkaplan_renk'     => '#0f1115',
			'arkaplan_renk2'    => '#1c222c',
			'arkaplan_gorsel'   => 0,
			'arkaplan_karartma' => 55,
			'arkaplan_bulanik'  => 0,
			'logo'              => 0,
			'logo_yukseklik'    => 64,
			'baslik'            => '',
			'alt_metin'         => '',
			'footer_metin'      => '',
			'kart_yaricap'      => 18,
			'kart_golge'        => 1,
			'kart_cam'          => 1,
			'beni_hatirla'      => 1,
			'sifremi_unuttum'   => 1,
			'siteye_don'        => 1,
			'dil_secici'        => 0,
		);
	}

	/**
	 * Kayıtlı ayarlar (varsayılanlarla tamamlanmış).
	 *
	 * @return array
	 */
	public static function get_settings() {
		$kayitli = get_option( self::OPTION, array() );

		if ( ! is_array( $kayitli ) ) {
			$kayitli = array();
		}

		return array_merge( self::defaults(), $kayitli );
	}

	/**
	 * Ham form verisini güvenli ayar dizisine çevirir.
	 *
	 * Bilinmeyen anahtar düşer, geçersiz değer varsayılana döner. Slug
	 * geçersizse ESKİ slug korunur — kullanıcı yanlış bir değer yazdığında
	 * adresin boşa düşüp kilitlenmeye yol açmaması için.
	 *
	 * @param array $raw   Ham veri ($_POST).
	 * @param array $eski  Mevcut ayarlar (slug geri düşüşü için).
	 * @return array
	 */
	public static function sanitize_settings( $raw, $eski = array() ) {
		$v   = self::defaults();
		$raw = is_array( $raw ) ? $raw : array();

		if ( ! is_array( $eski ) || empty( $eski ) ) {
			$eski = self::get_settings();
		}

		$bool = static function ( $anahtar ) use ( $raw ) {
			return empty( $raw[ $anahtar ] ) ? 0 : 1;
		};

		$v['yol_aktif']     = $bool( 'yol_aktif' );
		$v['wp_admin_koru'] = $bool( 'wp_admin_koru' );
		$v['gorunum_aktif'] = $bool( 'gorunum_aktif' );
		$v['kart_golge']    = $bool( 'kart_golge' );
		$v['kart_cam']      = $bool( 'kart_cam' );
		$v['beni_hatirla']  = $bool( 'beni_hatirla' );
		$v['sifremi_unuttum'] = $bool( 'sifremi_unuttum' );
		$v['siteye_don']      = $bool( 'siteye_don' );
		$v['dil_secici']      = $bool( 'dil_secici' );

		$slug = isset( $raw['slug'] ) ? self::normalize_slug( $raw['slug'] ) : '';
		$v['slug'] = ( '' !== $slug && '' === self::validate_slug( $slug ) )
			? $slug
			: ( isset( $eski['slug'] ) ? $eski['slug'] : self::DEFAULT_SLUG );

		$duzen       = isset( $raw['duzen'] ) ? sanitize_key( $raw['duzen'] ) : '';
		$v['duzen']  = in_array( $duzen, array( 'merkez', 'bolunmus' ), true ) ? $duzen : 'bolunmus';

		$tema        = isset( $raw['tema'] ) ? sanitize_key( $raw['tema'] ) : '';
		$v['tema']   = in_array( $tema, array( 'acik', 'koyu', 'otomatik' ), true ) ? $tema : 'koyu';

		$tip                 = isset( $raw['arkaplan_tip'] ) ? sanitize_key( $raw['arkaplan_tip'] ) : '';
		$v['arkaplan_tip']   = in_array( $tip, array( 'renk', 'gradyan', 'gorsel' ), true ) ? $tip : 'gradyan';

		foreach ( array( 'vurgu', 'vurgu2', 'arkaplan_renk', 'arkaplan_renk2' ) as $renk ) {
			$temiz = isset( $raw[ $renk ] ) ? sanitize_hex_color( (string) $raw[ $renk ] ) : '';
			if ( $temiz ) {
				$v[ $renk ] = $temiz;
			}
		}

		$v['arkaplan_gorsel']   = isset( $raw['arkaplan_gorsel'] ) ? absint( $raw['arkaplan_gorsel'] ) : 0;
		$v['logo']              = isset( $raw['logo'] ) ? absint( $raw['logo'] ) : 0;
		$v['arkaplan_karartma'] = self::clamp( isset( $raw['arkaplan_karartma'] ) ? $raw['arkaplan_karartma'] : 55, 0, 90 );
		$v['arkaplan_bulanik']  = self::clamp( isset( $raw['arkaplan_bulanik'] ) ? $raw['arkaplan_bulanik'] : 0, 0, 20 );
		$v['logo_yukseklik']    = self::clamp( isset( $raw['logo_yukseklik'] ) ? $raw['logo_yukseklik'] : 64, 24, 160 );
		$v['kart_yaricap']      = self::clamp( isset( $raw['kart_yaricap'] ) ? $raw['kart_yaricap'] : 18, 0, 40 );

		$v['baslik']    = isset( $raw['baslik'] ) ? sanitize_text_field( $raw['baslik'] ) : '';
		$v['alt_metin'] = isset( $raw['alt_metin'] ) ? sanitize_textarea_field( $raw['alt_metin'] ) : '';

		// Alt bilgi sınırlı HTML kabul eder (telefon/adres bağlantısı yazılabilsin).
		$v['footer_metin'] = isset( $raw['footer_metin'] )
			? wp_kses(
				(string) $raw['footer_metin'],
				array(
					'a'      => array(
						'href'   => array(),
						'title'  => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'strong' => array(),
					'em'     => array(),
					'br'     => array(),
					'span'   => array(),
				)
			)
			: '';

		return $v;
	}

	/**
	 * Sayıyı aralığa sıkıştırır.
	 *
	 * @param mixed $deger Değer.
	 * @param int   $alt   Alt sınır.
	 * @param int   $ust   Üst sınır.
	 * @return int
	 */
	private static function clamp( $deger, $alt, $ust ) {
		$sayi = (int) $deger;

		return max( $alt, min( $ust, $sayi ) );
	}

	/* -----------------------------------------------------------------
	   SLUG
	----------------------------------------------------------------- */

	/**
	 * Slug'ı temizler.
	 *
	 * @param string $ham Ham değer.
	 * @return string
	 */
	public static function normalize_slug( $ham ) {
		$slug = sanitize_title( (string) $ham );

		return trim( $slug, '/-' );
	}

	/**
	 * Slug geçerli mi?
	 *
	 * @param string $slug Temizlenmiş slug.
	 * @return string Geçerliyse boş metin, değilse kullanıcıya gösterilecek sebep.
	 */
	public static function validate_slug( $slug ) {
		$slug = (string) $slug;

		if ( '' === $slug ) {
			return __( 'Giriş adresi boş bırakılamaz.', 'qrms' );
		}

		if ( strlen( $slug ) < 2 ) {
			return __( 'Giriş adresi en az iki karakter olmalı.', 'qrms' );
		}

		if ( strlen( $slug ) > 64 ) {
			return __( 'Giriş adresi en fazla 64 karakter olabilir.', 'qrms' );
		}

		if ( in_array( $slug, self::RESERVED, true ) ) {
			return __( 'Bu adres WordPress tarafından kullanılıyor, başka bir ad seçin.', 'qrms' );
		}

		// Aynı adla bir sayfa/yazı varsa o içerik erişilemez hâle gelirdi.
		if ( function_exists( 'get_page_by_path' ) ) {
			$cakisan = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );

			if ( $cakisan ) {
				return __( 'Sitenizde aynı adrese sahip bir sayfa var, başka bir ad seçin.', 'qrms' );
			}
		}

		return '';
	}

	/**
	 * Yürürlükteki slug.
	 *
	 * @return string
	 */
	public static function get_slug() {
		$ayar = self::get_settings();
		$slug = self::normalize_slug( isset( $ayar['slug'] ) ? $ayar['slug'] : '' );

		return '' !== $slug ? $slug : self::DEFAULT_SLUG;
	}

	/* -----------------------------------------------------------------
	   DURUM
	----------------------------------------------------------------- */

	/**
	 * wp-config.php'den kapatılmış mı?
	 *
	 * @return bool
	 */
	public static function is_disabled_by_constant() {
		return defined( 'QRMS_LOGIN_DISABLE' ) && QRMS_LOGIN_DISABLE;
	}

	/**
	 * Özel giriş YOLU devrede mi?
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( self::is_disabled_by_constant() ) {
			return false;
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			return false;
		}

		$ayar = self::get_settings();

		if ( empty( $ayar['yol_aktif'] ) ) {
			return false;
		}

		return '' === self::validate_slug( self::get_slug() );
	}

	/**
	 * Giriş ekranı GÖRÜNÜMÜ devrede mi?
	 *
	 * Yoldan bağımsızdır: adres değişmeden de tasarım uygulanabilir.
	 *
	 * @return bool
	 */
	public static function is_skin_active() {
		if ( self::is_disabled_by_constant() ) {
			return false;
		}

		$ayar = self::get_settings();

		return ! empty( $ayar['gorunum_aktif'] );
	}

	/* -----------------------------------------------------------------
	   KANCALAR
	----------------------------------------------------------------- */

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		// Yönetim tarafı her hâlükârda kayıtlı: ayar ekranı olmadan kullanıcı
		// kapattığı özelliği geri açamaz.
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_settings_submit' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );

		if ( self::is_skin_active() ) {
			add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login_assets' ) );
			add_action( 'login_head', array( __CLASS__, 'login_head' ) );
			add_filter( 'login_body_class', array( __CLASS__, 'body_class' ) );
			add_filter( 'login_headerurl', array( __CLASS__, 'header_url' ) );
			add_filter( 'login_headertext', array( __CLASS__, 'header_text' ) );
			add_filter( 'login_message', array( __CLASS__, 'login_message' ) );
			add_filter( 'login_display_language_dropdown', array( __CLASS__, 'language_dropdown' ) );
			add_action( 'login_footer', array( __CLASS__, 'login_footer' ) );
		}

		if ( ! self::is_active() ) {
			return;
		}

		// İsteği en erken noktada yakala: WordPress kendi giriş yönlendirmesini
		// yapmadan önce $pagenow'u düzeltmiş olmamız gerekir.
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ), 1 );
		add_action( 'wp_loaded', array( __CLASS__, 'wp_loaded' ) );

		// WordPress'in ürettiği bütün wp-login.php adresleri yeni yola çevrilir.
		add_filter( 'site_url', array( __CLASS__, 'filter_site_url' ), 10, 2 );
		add_filter( 'network_site_url', array( __CLASS__, 'filter_site_url' ), 10, 2 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_redirect' ) );
		add_filter( 'login_url', array( __CLASS__, 'filter_generic_url' ) );
		add_filter( 'logout_url', array( __CLASS__, 'filter_generic_url' ) );
		add_filter( 'lostpassword_url', array( __CLASS__, 'filter_generic_url' ) );
		add_filter( 'register_url', array( __CLASS__, 'filter_generic_url' ) );
	}

	/* -----------------------------------------------------------------
	   İSTEK YAKALAMA
	----------------------------------------------------------------- */

	/**
	 * Ham istek yolu (sorgu dizesi olmadan, sondaki eğik çizgi atılmış).
	 *
	 * @return string
	 */
	public static function request_path() {
		/*
		 * DİKKAT: burada sanitize_text_field() KULLANILMAZ. O fonksiyon
		 * yüzde kodlu oktetleri (%2F, %C5%9F…) adresten tamamen siler; Türkçe
		 * karakter içeren bir slug ya da kodlanmış bir yol sessizce bozulur
		 * ve giriş adresi hiç eşleşmez. Ham değer önce ayrıştırılır, sonra
		 * YALNIZCA yol kısmı çözülür; sonuç hiçbir yere basılmaz, sadece
		 * bilinen slug ile karşılaştırılır.
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( ! is_string( $uri ) || '' === $uri ) {
			return '';
		}

		$parca = wp_parse_url( $uri );
		$yol   = isset( $parca['path'] ) ? rawurldecode( $parca['path'] ) : '';

		return untrailingslashit( $yol );
	}

	/**
	 * Sitenin kök yolu (alt dizin kurulumlarında `/blog` gibi).
	 *
	 * @return string
	 */
	public static function home_path() {
		$parca = wp_parse_url( home_url() );

		return isset( $parca['path'] ) ? untrailingslashit( $parca['path'] ) : '';
	}

	/**
	 * Verilen yol giriş yolu mu?
	 *
	 * @param string $yol İstek yolu.
	 * @return bool
	 */
	public static function is_login_path( $yol ) {
		$hedef = self::home_path() . '/' . self::get_slug();

		if ( untrailingslashit( (string) $yol ) === $hedef ) {
			return true;
		}

		// Kalıcı bağlantı yapısı kapalı sitelerde /?qrm biçimi de çalışır.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ self::get_slug() ] ) && ! get_option( 'permalink_structure' );
	}

	/**
	 * İstek `wp-login.php`'ye mi gidiyor?
	 *
	 * @param string $yol İstek yolu.
	 * @return bool
	 */
	public static function is_wp_login_path( $yol ) {
		return false !== strpos( (string) $yol, 'wp-login.php' );
	}

	/**
	 * `wp-login.php` isteği engellenmeli mi?
	 *
	 * Şifre korumalı yazıların form gönderimi (`action=postpass`) her zaman
	 * geçer; oturumu açık kullanıcı da engellenmez (çıkış bağlantısı, ara
	 * giriş penceresi). Geri kalan her şey 404'tür.
	 *
	 * @param string $eylem      `action` sorgu parametresi.
	 * @param bool   $oturum_var Kullanıcının oturumu açık mı?
	 * @return bool
	 */
	public static function should_block_wp_login( $eylem, $oturum_var ) {
		if ( $oturum_var ) {
			return false;
		}

		return 'postpass' !== (string) $eylem;
	}

	/**
	 * `plugins_loaded` — isteği sınıflandırır.
	 *
	 * @return void
	 */
	public static function plugins_loaded() {
		global $pagenow;

		if ( self::arka_plan_istegi() ) {
			return;
		}

		$yol = self::request_path();

		if ( self::is_login_path( $yol ) ) {
			// WordPress bu isteği giriş sayfası saysın.
			$pagenow = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			return;
		}

		if ( self::is_wp_login_path( $yol ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$eylem = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

			if ( self::should_block_wp_login( $eylem, is_user_logged_in() ) ) {
				self::$bloke = true;
				$pagenow     = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * `wp_loaded` — giriş sayfasını basar veya 404'e düşürür.
	 *
	 * @return void
	 */
	public static function wp_loaded() {
		global $pagenow;

		if ( self::arka_plan_istegi() ) {
			return;
		}

		if ( self::$bloke ) {
			self::render_404();
			return;
		}

		if ( 'wp-login.php' === $pagenow && self::is_login_path( self::request_path() ) ) {
			// wp-login.php kendi içinde bu global'leri bekler.
			global $error, $interim_login, $action, $user_login; // phpcs:ignore Generic.CodeAnalysis.UnusedVariable.FoundInScopeBeforeUse

			require_once ABSPATH . 'wp-login.php';
			exit;
		}

		$ayar = self::get_settings();

		if ( empty( $ayar['wp_admin_koru'] ) ) {
			return;
		}

		// Oturumsuz kullanıcı yönetim paneline gidiyorsa 404. AJAX, admin-post
		// ve cron dışarıda: bunlar oturumsuz da meşru şekilde çağrılır.
		if ( is_admin() && ! is_user_logged_in() && 'admin-ajax.php' !== $pagenow && 'admin-post.php' !== $pagenow ) {
			self::render_404();
		}
	}

	/**
	 * Bu istek arka plan işi mi (cron / REST / CLI / AJAX)?
	 *
	 * @return bool
	 */
	private static function arka_plan_istegi() {
		if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// REST_REQUEST sabiti yalnızca istek yönlendirildikten sonra tanımlanır;
		// yol üzerinden erken kontrol REST isteğini korumaya alır.
		$yol = self::request_path();

		return false !== strpos( $yol, '/wp-json' );
	}

	/**
	 * İsteği 404'e düşürür.
	 *
	 * @return void
	 */
	private static function render_404() {
		global $wp_query;

		if ( isset( $wp_query ) && is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();

		$sablon = function_exists( 'get_404_template' ) ? get_404_template() : '';

		if ( $sablon && file_exists( $sablon ) ) {
			require $sablon;
			exit;
		}

		QRMS_Hata_Sayfalari::render(
			__( 'Bulunamadı', 'qrms' ),
			__( 'Sayfa bulunamadı.', 'qrms' )
		);
	}

	/* -----------------------------------------------------------------
	   ADRES ÜRETİMİ VE FİLTRELER
	----------------------------------------------------------------- */

	/**
	 * Yeni giriş adresi.
	 *
	 * @param array $args Sorgu parametreleri.
	 * @return string
	 */
	public static function login_url( $args = array() ) {
		if ( get_option( 'permalink_structure' ) ) {
			$url = home_url( '/' . self::get_slug() . '/' );
		} else {
			$url = home_url( '/?' . self::get_slug() );
		}

		if ( ! empty( $args ) && is_array( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}

	/**
	 * İçinde `wp-login.php` geçen bir adresi yeni yola çevirir.
	 *
	 * Sorgu parametreleri (`action`, `redirect_to`, `key`, `login`…) korunur;
	 * şifre sıfırlama e-postasındaki bağlantı bu sayede çalışmaya devam eder.
	 *
	 * @param string $url Adres.
	 * @return string
	 */
	private static function replace_login_url( $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) ) {
			return $url;
		}

		$parca = wp_parse_url( $url );
		$args  = array();

		if ( ! empty( $parca['query'] ) ) {
			parse_str( $parca['query'], $args );
		}

		$yeni = self::login_url( $args );

		if ( ! empty( $parca['fragment'] ) ) {
			$yeni .= '#' . $parca['fragment'];
		}

		return $yeni;
	}

	/**
	 * `site_url` / `network_site_url` filtresi.
	 *
	 * @param string $url  Üretilen adres.
	 * @param string $path İstenen yol.
	 * @return string
	 */
	public static function filter_site_url( $url, $path = '' ) {
		if ( is_string( $path ) && false === strpos( $path, 'wp-login.php' ) ) {
			return $url;
		}

		return self::replace_login_url( $url );
	}

	/**
	 * `wp_redirect` filtresi.
	 *
	 * @param string $location Hedef.
	 * @return string
	 */
	public static function filter_redirect( $location ) {
		return self::replace_login_url( $location );
	}

	/**
	 * Tek argümanlı adres filtreleri (`login_url`, `logout_url`…).
	 *
	 * @param string $url Adres.
	 * @return string
	 */
	public static function filter_generic_url( $url ) {
		return self::replace_login_url( $url );
	}

	/* -----------------------------------------------------------------
	   GİRİŞ EKRANI GÖRÜNÜMÜ
	----------------------------------------------------------------- */

	/**
	 * Ayarlardan CSS değişkeni bloğu üretir.
	 *
	 * Saf fonksiyondur (WordPress durumu okumaz, yalnızca verilen ayarları
	 * çevirir); testler bu yüzden doğrudan çağırabilir.
	 *
	 * @param array  $s          Ayarlar.
	 * @param string $arkaplan_url Arka plan görselinin adresi (çözülmüş).
	 * @param string $logo_url     Logonun adresi (çözülmüş).
	 * @return string
	 */
	public static function css_variables( array $s, $arkaplan_url = '', $logo_url = '' ) {
		$s = array_merge( self::defaults(), $s );

		$satir = array(
			'--qrms-lg-vurgu: ' . $s['vurgu'],
			'--qrms-lg-vurgu2: ' . $s['vurgu2'],
			'--qrms-lg-bg1: ' . $s['arkaplan_renk'],
			'--qrms-lg-bg2: ' . ( 'gradyan' === $s['arkaplan_tip'] ? $s['arkaplan_renk2'] : $s['arkaplan_renk'] ),
			'--qrms-lg-karartma: ' . ( (int) $s['arkaplan_karartma'] / 100 ),
			'--qrms-lg-bulanik: ' . (int) $s['arkaplan_bulanik'] . 'px',
			'--qrms-lg-radius: ' . (int) $s['kart_yaricap'] . 'px',
			'--qrms-lg-logo-h: ' . (int) $s['logo_yukseklik'] . 'px',
		);

		if ( '' !== $arkaplan_url && 'gorsel' === $s['arkaplan_tip'] ) {
			$satir[] = '--qrms-lg-bg-image: url(' . $arkaplan_url . ')';
		}

		if ( '' !== $logo_url ) {
			$satir[] = '--qrms-lg-logo: url(' . $logo_url . ')';
		}

		return implode( '; ', $satir ) . ';';
	}

	/**
	 * Ayarlardan gövde sınıflarını üretir.
	 *
	 * @param array $s Ayarlar.
	 * @return string[]
	 */
	public static function skin_classes( array $s ) {
		$s = array_merge( self::defaults(), $s );

		$siniflar = array(
			'qrms-login',
			'qrms-login-duzen-' . $s['duzen'],
			'qrms-login-tema-' . $s['tema'],
			'qrms-login-bg-' . $s['arkaplan_tip'],
		);

		if ( ! empty( $s['kart_cam'] ) ) {
			$siniflar[] = 'qrms-login-cam';
		}

		if ( ! empty( $s['kart_golge'] ) ) {
			$siniflar[] = 'qrms-login-golge';
		}

		if ( empty( $s['beni_hatirla'] ) ) {
			$siniflar[] = 'qrms-login-hatirla-gizli';
		}

		if ( empty( $s['sifremi_unuttum'] ) ) {
			$siniflar[] = 'qrms-login-nav-gizli';
		}

		if ( empty( $s['siteye_don'] ) ) {
			$siniflar[] = 'qrms-login-geri-gizli';
		}

		if ( ! empty( $s['logo'] ) ) {
			$siniflar[] = 'qrms-login-logolu';
		}

		return $siniflar;
	}

	/**
	 * Giriş ekranı varlıkları.
	 *
	 * @return void
	 */
	public static function enqueue_login_assets() {
		$s = self::get_settings();

		wp_enqueue_style(
			'qrms-login',
			QRMS_PLUGIN_URL . 'assets/css/login.css',
			array(),
			QRMS_Helpers::asset_version( 'assets/css/login.css' )
		);

		wp_add_inline_style( 'qrms-login', ':root, body.login { ' . self::css_variables( $s, self::attachment_url( $s['arkaplan_gorsel'] ), self::attachment_url( $s['logo'] ) ) . ' }' );

		wp_enqueue_script(
			'qrms-login',
			QRMS_PLUGIN_URL . 'assets/js/login.js',
			array(),
			QRMS_Helpers::asset_version( 'assets/js/login.js' ),
			true
		);

		wp_localize_script(
			'qrms-login',
			'QRMS_LOGIN',
			array(
				'capsLock' => __( 'Caps Lock açık', 'qrms' ),
				'bekleyin' => __( 'Giriş yapılıyor…', 'qrms' ),
				'goster'   => __( 'Şifreyi göster', 'qrms' ),
			)
		);
	}

	/**
	 * Ek `<head>` çıktısı — WordPress'in kendi giriş stillerini bastırmak için
	 * gereken tek satırlık düzeltme ve renk şeması bildirimi.
	 *
	 * @return void
	 */
	public static function login_head() {
		$s = self::get_settings();

		$sema = 'koyu' === $s['tema'] ? 'dark' : ( 'acik' === $s['tema'] ? 'light' : 'light dark' );

		echo '<meta name="color-scheme" content="' . esc_attr( $sema ) . '">' . "\n";
	}

	/**
	 * Giriş gövdesine sınıfları ekler.
	 *
	 * @param array $classes Mevcut sınıflar.
	 * @return array
	 */
	public static function body_class( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();

		return array_merge( $classes, self::skin_classes( self::get_settings() ) );
	}

	/**
	 * Logo bağlantısı — WordPress.org yerine sitenin kendisi.
	 *
	 * @return string
	 */
	public static function header_url() {
		return home_url( '/' );
	}

	/**
	 * Logo metni.
	 *
	 * @return string
	 */
	public static function header_text() {
		$s = self::get_settings();

		return '' !== $s['baslik'] ? $s['baslik'] : get_bloginfo( 'name' );
	}

	/**
	 * Formun üstündeki marka bloğu.
	 *
	 * WordPress'in kendi mesajı (şifre sıfırlama açıklaması, "kaydınız
	 * tamamlandı" gibi) KORUNUR; marka bloğu onun önüne eklenir.
	 *
	 * @param string $message Mevcut mesaj.
	 * @return string
	 */
	public static function login_message( $message ) {
		$s = self::get_settings();

		$baslik = '' !== $s['baslik'] ? $s['baslik'] : get_bloginfo( 'name' );
		$alt    = $s['alt_metin'];

		$html  = '<div class="qrms-login-brand">';
		$html .= '<h2 class="qrms-login-brand-title">' . esc_html( $baslik ) . '</h2>';

		if ( '' !== $alt ) {
			$html .= '<p class="qrms-login-brand-text">' . nl2br( esc_html( $alt ) ) . '</p>';
		}

		$html .= '</div>';

		return $html . $message;
	}

	/**
	 * Dil seçici gösterilsin mi?
	 *
	 * @param bool $goster Mevcut karar.
	 * @return bool
	 */
	public static function language_dropdown( $goster ) {
		$s = self::get_settings();

		return empty( $s['dil_secici'] ) ? false : $goster;
	}

	/**
	 * Alt bilgi metni.
	 *
	 * @return void
	 */
	public static function login_footer() {
		$s = self::get_settings();

		if ( '' === $s['footer_metin'] ) {
			return;
		}

		echo '<div class="qrms-login-footer">' . wp_kses_post( $s['footer_metin'] ) . '</div>';
	}

	/**
	 * Ek dosyanın adresi (yoksa boş).
	 *
	 * @param int $id Ek ID'si.
	 * @return string
	 */
	public static function attachment_url( $id ) {
		$id = absint( $id );

		if ( ! $id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $id, 'full' );

		return $url ? $url : '';
	}

	/* -----------------------------------------------------------------
	   YÖNETİM
	----------------------------------------------------------------- */

	/**
	 * Ayar ekranının varlıkları.
	 *
	 * @param string $hook Ekran kancası.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook = '' ) {
		unset( $hook );

		if ( ! self::is_settings_tab() ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		// Önizleme, giriş ekranının GERÇEK stylesheet'ini kullanır: ayrı bir
		// taklit yazılsaydı iki dosya zamanla birbirinden ayrı düşerdi.
		wp_enqueue_style(
			'qrms-login',
			QRMS_PLUGIN_URL . 'assets/css/login.css',
			array(),
			QRMS_Helpers::asset_version( 'assets/css/login.css' )
		);

		wp_enqueue_style(
			'qrms-login-admin',
			QRMS_PLUGIN_URL . 'assets/css/login-admin.css',
			array( 'qrms-admin', 'wp-color-picker', 'qrms-login' ),
			QRMS_Helpers::asset_version( 'assets/css/login-admin.css' )
		);

		wp_enqueue_script(
			'qrms-login-admin',
			QRMS_PLUGIN_URL . 'assets/js/login-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			QRMS_Helpers::asset_version( 'assets/js/login-admin.js' ),
			true
		);

		wp_localize_script(
			'qrms-login-admin',
			'QRMS_LOGIN_ADMIN',
			array(
				'sec'      => __( 'Görsel Seç', 'qrms' ),
				'kullan'   => __( 'Bu görseli kullan', 'qrms' ),
				'kopyalandi' => __( 'Kopyalandı', 'qrms' ),
			)
		);
	}

	/**
	 * Şu an Giriş Ekranı sekmesinde miyiz?
	 *
	 * @return bool
	 */
	public static function is_settings_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return QRMS_Admin::SETTINGS_SLUG === $page && 'giris' === $tab;
	}

	/**
	 * Ayar formunu işler.
	 *
	 * @return void
	 */
	public static function handle_settings_submit() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ) );
		}

		check_admin_referer( self::NONCE );

		$eski = self::get_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce yukarıda doğrulandı.
		$ham = isset( $_POST['qrms_login'] ) && is_array( $_POST['qrms_login'] )
			? wp_unslash( $_POST['qrms_login'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_settings() içinde alan alan temizlenir.
			: array();

		$istenen_slug = isset( $ham['slug'] ) ? self::normalize_slug( $ham['slug'] ) : '';
		$slug_hatasi  = '' !== $istenen_slug ? self::validate_slug( $istenen_slug ) : '';

		$yeni = self::sanitize_settings( $ham, $eski );

		update_option( self::OPTION, $yeni );

		$slug_degisti = ( $eski['slug'] !== $yeni['slug'] ) || ( (int) $eski['yol_aktif'] !== (int) $yeni['yol_aktif'] );

		if ( $slug_degisti && ! empty( $yeni['yol_aktif'] ) ) {
			self::notify_admin_email( $yeni['slug'] );
		}

		$args = array(
			'page'        => QRMS_Admin::SETTINGS_SLUG,
			'tab'         => 'giris',
			'kaydedildi'  => 1,
		);

		if ( '' !== $slug_hatasi ) {
			$args['slug_hata'] = rawurlencode( $slug_hatasi );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Yeni giriş adresini site yöneticisine e-postayla bildirir.
	 *
	 * Kilitlenmeye karşı en pratik koruma budur: adres yalnızca ekranda
	 * gösterilseydi sekme kapandığında kaybolurdu.
	 *
	 * @param string $slug Yeni slug.
	 * @return void
	 */
	private static function notify_admin_email( $slug ) {
		if ( ! function_exists( 'wp_mail' ) ) {
			return;
		}

		$alici = get_option( 'admin_email' );

		if ( ! $alici ) {
			return;
		}

		$konu = sprintf(
			/* translators: %s: site adı. */
			__( '[%s] Yönetim paneli giriş adresiniz değişti', 'qrms' ),
			get_bloginfo( 'name' )
		);

		$mesaj = sprintf(
			/* translators: 1: yeni giriş adresi, 2: slug. */
			__( "Yönetim paneline artık şu adresten giriyorsunuz:\n\n%1\$s\n\nBu adresi kaydedin. wp-login.php ve wp-admin adresleri artık 404 döner.\n\nAdresi unutursanız sunucudaki wp-config.php dosyasına şu satırı ekleyerek özelliği kapatabilir ve eski giriş adresine dönebilirsiniz:\n\ndefine( 'QRMS_LOGIN_DISABLE', true );\n", 'qrms' ),
			self::login_url(),
			$slug
		);

		wp_mail( $alici, $konu, $mesaj );
	}

	/**
	 * Yönetim ekranlarında giriş adresini hatırlatan bilgi kutusu.
	 *
	 * @return void
	 */
	public static function admin_notice() {
		if ( ! self::is_active() || ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}

		if ( ! QRMS_Admin::is_plugin_screen() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['kaydedildi'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success"><p><strong>%1$s</strong> %2$s <code>%3$s</code></p></div>',
			esc_html__( 'Ayarlar kaydedildi.', 'qrms' ),
			esc_html__( 'Yönetim paneli giriş adresiniz:', 'qrms' ),
			esc_url( self::login_url() )
		);
	}

	/**
	 * Genel Ayarlar → Giriş Ekranı sekmesini basar.
	 *
	 * @return void
	 */
	public static function render_settings_tab() {
		require_once QRMS_PLUGIN_DIR . 'includes/login-ayar-sayfasi.php';

		qrms_login_ayar_sayfasi();
	}
}
