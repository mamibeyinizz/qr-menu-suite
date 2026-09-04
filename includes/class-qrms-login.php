<?php
/**
 * Özel giriş URL'si ve giriş ekranı özelleştirmesi.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Giriş slug'ı, yönlendirme ve görünüm ayarları.
 */
class QRMS_Login {

	const OPT_SLUG     = 'qrms_login_slug';
	const OPT_GORUNUM  = 'qrms_login_gorunum';
	const DEFAULT_SLUG = 'qrm';

	/**
	 * Yasaklı slug listesi.
	 *
	 * @return string[]
	 */
	public static function yasakli_sluglar() {
		return array(
			'',
			'wp-admin',
			'admin',
			'login',
			'wp-login',
			'wp-login.php',
			'wp-content',
			'wp-includes',
			'feed',
			'sitemap',
		);
	}

	/**
	 * Özellik devre dışı mı?
	 *
	 * @return bool
	 */
	public static function devre_disimi() {
		return defined( 'QRMS_LOGIN_DISABLE' ) && QRMS_LOGIN_DISABLE;
	}

	/**
	 * Çoklu site mi?
	 *
	 * @return bool
	 */
	public static function multisite_mi() {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * Aktif slug'ı döner.
	 *
	 * @return string
	 */
	public static function slug() {
		$slug = get_option( self::OPT_SLUG, self::DEFAULT_SLUG );
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			$slug = self::DEFAULT_SLUG;
		}
		return $slug;
	}

	/**
	 * Giriş URL'si.
	 *
	 * @param string $action Opsiyonel action.
	 * @return string
	 */
	public static function url( $action = '' ) {
		$url = home_url( '/' . self::slug() . '/' );
		if ( '' !== $action ) {
			$url = add_query_arg( 'action', $action, $url );
		}
		return $url;
	}

	/**
	 * Görünüm ayarlarını okur.
	 *
	 * @return array
	 */
	public static function gorunum() {
		$varsayilan = array(
			'logo_url'        => '',
			'logo_yukseklik'  => 60,
			'arkaplan_tip'    => 'renk',
			'arkaplan_renk'   => '#1e1e2e',
			'arkaplan_gradyan'=> 'linear-gradient(135deg, #1e1e2e 0%, #2d2d44 100%)',
			'arkaplan_gorsel' => '',
			'arkaplan_karartma' => 40,
			'vurgu_rengi'     => '#7c5cff',
			'kart_radius'     => 12,
			'kart_golge'      => 1,
			'kart_cam'        => 0,
			'baslik'          => __( 'QR Menü Yönetim Paneli', 'qrms' ),
			'alt_metin'       => '',
			'goster_hatirla'  => 1,
			'goster_sifremi'  => 1,
			'sabitle_dil'     => 0,
		);
		$opt = get_option( self::OPT_GORUNUM, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}
		return array_merge( $varsayilan, $opt );
	}

	/**
	 * Slug geçerli mi?
	 *
	 * @param string $slug Slug.
	 * @return true|WP_Error
	 */
	public static function slug_dogrula( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( in_array( $slug, self::yasakli_sluglar(), true ) ) {
			return new WP_Error( 'yasak', __( 'Bu giriş adresi kullanılamaz.', 'qrms' ) );
		}
		$sayfa = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
		if ( $sayfa ) {
			return new WP_Error( 'cakisma', __( 'Bu adres sitede mevcut bir sayfa ile çakışıyor.', 'qrms' ) );
		}
		return true;
	}

	/**
	 * Hook'ları kaydeder.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::devre_disimi() || self::multisite_mi() ) {
			add_action( 'admin_init', array( __CLASS__, 'ayarlar_kaydet' ) );
			return;
		}

		add_action( 'plugins_loaded', array( __CLASS__, 'istegi_yakala' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'gizli_giris_engelle' ), 1 );
		add_filter( 'site_url', array( __CLASS__, 'url_filtre' ), 10, 4 );
		add_filter( 'network_site_url', array( __CLASS__, 'url_filtre' ), 10, 4 );
		add_filter( 'wp_redirect', array( __CLASS__, 'redirect_filtre' ), 10, 2 );
		add_filter( 'login_url', array( __CLASS__, 'login_url_filtre' ), 10, 3 );
		add_filter( 'logout_url', array( __CLASS__, 'logout_url_filtre' ), 10, 2 );
		add_filter( 'lostpassword_url', array( __CLASS__, 'lostpassword_url_filtre' ), 10, 2 );
		add_filter( 'register_url', array( __CLASS__, 'register_url_filtre' ) );
		add_filter( 'lostpassword_redirect', array( __CLASS__, 'lostpassword_redirect_filtre' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_stilleri' ) );
		add_filter( 'login_headerurl', array( __CLASS__, 'header_url' ) );
		add_filter( 'login_headertext', array( __CLASS__, 'header_text' ) );
		add_action( 'login_head', array( __CLASS__, 'login_inline_css' ) );
		add_action( 'admin_init', array( __CLASS__, 'ayarlar_kaydet' ) );
		add_action( 'admin_notices', array( __CLASS__, 'slug_notice' ) );
	}

	/**
	 * Özel slug isteğini wp-login.php'ye yönlendirir.
	 *
	 * @return void
	 */
	public static function istegi_yakala() {
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
		$slug = self::slug();

		if ( $path === $slug || 0 === strpos( $path, $slug . '/' ) ) {
			global $pagenow;
			$pagenow = 'wp-login.php';
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
	}

	/**
	 * Oturumsuz wp-login.php ve wp-admin erişimini 404 yapar.
	 *
	 * @return void
	 */
	public static function gizli_giris_engelle() {
		if ( is_user_logged_in() ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = strtolower( (string) wp_parse_url( $uri, PHP_URL_PATH ) );

		// admin-ajax.php, wp-cron.php ve REST API etkilenmemeli.
		if ( false !== strpos( $path, 'admin-ajax.php' )
			|| false !== strpos( $path, 'wp-cron.php' )
			|| false !== strpos( $path, 'wp-json/' ) ) {
			return;
		}

		if ( false !== strpos( $path, 'wp-login.php' ) ) {
			global $wp_query;
			if ( isset( $wp_query ) ) {
				$wp_query->set_404();
			}
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			exit;
		}

		if ( preg_match( '#/wp-admin(/|$)#', $path ) ) {
			global $wp_query;
			if ( isset( $wp_query ) ) {
				$wp_query->set_404();
			}
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			exit;
		}
	}

	/**
	 * site_url / network_site_url filtresi.
	 *
	 * @param string $url     URL.
	 * @param string $path    Yol.
	 * @param string $scheme  Şema.
	 * @param int    $blog_id Blog kimliği.
	 * @return string
	 */
	public static function url_filtre( $url, $path, $scheme, $blog_id = null ) {
		if ( false !== strpos( (string) $path, 'wp-login.php' ) ) {
			return self::login_url_cevir( $url );
		}
		return $url;
	}

	/**
	 * wp_redirect filtresi.
	 *
	 * @param string $location Konum.
	 * @param int    $status   Durum kodu.
	 * @return string
	 */
	public static function redirect_filtre( $location, $status ) {
		return self::login_url_cevir( $location );
	}

	/**
	 * login_url filtresi.
	 *
	 * @param string $login_url    URL.
	 * @param string $redirect     Yönlendirme.
	 * @param bool   $force_reauth Yeniden kimlik doğrulama.
	 * @return string
	 */
	public static function login_url_filtre( $login_url, $redirect, $force_reauth ) {
		$url = self::url();
		if ( ! empty( $redirect ) ) {
			$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
		}
		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}
		return $url;
	}

	/**
	 * logout_url filtresi.
	 *
	 * @param string $logout_url URL.
	 * @param string $redirect   Yönlendirme.
	 * @return string
	 */
	public static function logout_url_filtre( $logout_url, $redirect ) {
		return self::login_url_cevir( $logout_url );
	}

	/**
	 * lostpassword_url filtresi.
	 *
	 * @param string $url      URL.
	 * @param string $redirect Yönlendirme.
	 * @return string
	 */
	public static function lostpassword_url_filtre( $url, $redirect ) {
		$lost = self::url( 'lostpassword' );
		if ( $redirect ) {
			$lost = add_query_arg( 'redirect_to', urlencode( $redirect ), $lost );
		}
		return $lost;
	}

	/**
	 * register_url filtresi.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function register_url_filtre( $url ) {
		return self::url( 'register' );
	}

	/**
	 * lostpassword_redirect filtresi.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function lostpassword_redirect_filtre( $url ) {
		return self::login_url_cevir( $url );
	}

	/**
	 * wp-login.php içeren URL'leri özel slug'a çevirir.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function login_url_cevir( $url ) {
		if ( false === strpos( (string) $url, 'wp-login.php' ) ) {
			return $url;
		}
		$parsed = wp_parse_url( $url );
		$query  = array();
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
		}
		$base = self::url();
		if ( ! empty( $query['action'] ) ) {
			$base = self::url( $query['action'] );
			unset( $query['action'] );
		}
		foreach ( $query as $k => $v ) {
			$base = add_query_arg( $k, $v, $base );
		}
		return $base;
	}

	/**
	 * Giriş CSS dosyasını kuyruğa alır.
	 *
	 * @return void
	 */
	public static function login_stilleri() {
		wp_enqueue_style(
			'qrms-login',
			QRMS_PLUGIN_URL . 'assets/css/login.css',
			array(),
			QRMS_Helpers::asset_version( 'assets/css/login.css' )
		);
	}

	/**
	 * Logo bağlantısı.
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
		return get_bloginfo( 'name' );
	}

	/**
	 * Inline CSS değişkenleri.
	 *
	 * @return void
	 */
	public static function login_inline_css() {
		$g = self::gorunum();
		$css = ':root{';
		$css .= '--qrms-login-vurgu:' . esc_attr( $g['vurgu_rengi'] ) . ';';
		$css .= '--qrms-login-radius:' . absint( $g['kart_radius'] ) . 'px;';
		$css .= '--qrms-login-logo-h:' . absint( $g['logo_yukseklik'] ) . 'px;';
		if ( 'gradyan' === $g['arkaplan_tip'] ) {
			$css .= '--qrms-login-bg:' . esc_attr( $g['arkaplan_gradyan'] ) . ';';
		} elseif ( 'gorsel' === $g['arkaplan_tip'] && $g['arkaplan_gorsel'] ) {
			$css .= '--qrms-login-bg-img:url(' . esc_url( $g['arkaplan_gorsel'] ) . ');';
			$css .= '--qrms-login-bg-overlay:' . ( absint( $g['arkaplan_karartma'] ) / 100 ) . ';';
		} else {
			$css .= '--qrms-login-bg:' . esc_attr( $g['arkaplan_renk'] ) . ';';
		}
		$css .= '}';
		if ( empty( $g['goster_hatirla'] ) ) {
			$css .= '.forgetmenot{display:none!important}';
		}
		if ( empty( $g['goster_sifremi'] ) ) {
			$css .= '#nav{display:none!important}';
		}
		if ( ! empty( $g['kart_golge'] ) ) {
			$css .= '#login form{box-shadow:0 8px 32px rgba(0,0,0,.15)}';
		}
		if ( ! empty( $g['kart_cam'] ) ) {
			$css .= '#login form{background:rgba(255,255,255,.85);backdrop-filter:blur(12px)}';
		}
		echo '<style id="qrms-login-vars">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( '' !== $g['baslik'] ) {
			echo '<style>.qrms-login-baslik{display:block;text-align:center;font-size:18px;font-weight:600;margin-bottom:16px;color:#1e1e1e}</style>';
			add_action(
				'login_form',
				function () use ( $g ) {
					echo '<p class="qrms-login-baslik">' . esc_html( $g['baslik'] ) . '</p>';
				},
				1
			);
		}
		if ( '' !== $g['alt_metin'] ) {
			add_action(
				'login_footer',
				function () use ( $g ) {
					echo '<p class="qrms-login-alt">' . wp_kses_post( $g['alt_metin'] ) . '</p>';
				}
			);
		}
		if ( ! empty( $g['logo_url'] ) ) {
			add_action(
				'login_head',
				function () use ( $g ) {
					echo '<style>.login h1 a{background-image:url(' . esc_url( $g['logo_url'] ) . ')!important;background-size:contain;width:auto;height:var(--qrms-login-logo-h)}</style>';
				}
			);
		}
	}

	/**
	 * Ayarları kaydeder.
	 *
	 * @return void
	 */
	public static function ayarlar_kaydet() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}
		if ( empty( $_POST['qrms_login_save'] ) ) {
			return;
		}
		if ( ! isset( $_POST['qrms_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qrms_login_nonce'] ) ), 'qrms_login_save' ) ) {
			return;
		}

		if ( isset( $_POST['qrms_login_slug'] ) && ! self::multisite_mi() && ! self::devre_disimi() ) {
			$slug = sanitize_title( wp_unslash( $_POST['qrms_login_slug'] ) );
			$dogrula = self::slug_dogrula( $slug );
			if ( is_wp_error( $dogrula ) ) {
				add_settings_error( 'qrms_login', 'slug', $dogrula->get_error_message(), 'error' );
			} else {
				$eski = self::slug();
				update_option( self::OPT_SLUG, $slug, false );
				if ( $eski !== $slug ) {
					set_transient( 'qrms_login_slug_notice', home_url( '/' . $slug . '/' ), DAY_IN_SECONDS );
				}
			}
		}

		if ( isset( $_POST['qrms_login_gorunum'] ) && is_array( $_POST['qrms_login_gorunum'] ) ) {
			$raw = wp_unslash( $_POST['qrms_login_gorunum'] );
			$g   = self::gorunum();
			$g['logo_url']         = esc_url_raw( $raw['logo_url'] ?? '' );
			$g['logo_yukseklik']   = absint( $raw['logo_yukseklik'] ?? 60 );
			$g['arkaplan_tip']     = in_array( $raw['arkaplan_tip'] ?? '', array( 'renk', 'gradyan', 'gorsel' ), true ) ? $raw['arkaplan_tip'] : 'renk';
			$g['arkaplan_renk']    = sanitize_hex_color( $raw['arkaplan_renk'] ?? '#1e1e2e' ) ?: '#1e1e2e';
			$g['arkaplan_gradyan'] = sanitize_text_field( $raw['arkaplan_gradyan'] ?? '' );
			$g['arkaplan_gorsel']  = esc_url_raw( $raw['arkaplan_gorsel'] ?? '' );
			$g['arkaplan_karartma']= absint( $raw['arkaplan_karartma'] ?? 40 );
			$g['vurgu_rengi']      = sanitize_hex_color( $raw['vurgu_rengi'] ?? '#7c5cff' ) ?: '#7c5cff';
			$g['kart_radius']      = absint( $raw['kart_radius'] ?? 12 );
			$g['kart_golge']       = ! empty( $raw['kart_golge'] ) ? 1 : 0;
			$g['kart_cam']         = ! empty( $raw['kart_cam'] ) ? 1 : 0;
			$g['baslik']           = sanitize_text_field( $raw['baslik'] ?? '' );
			$g['alt_metin']        = wp_kses_post( $raw['alt_metin'] ?? '' );
			$g['goster_hatirla']   = ! empty( $raw['goster_hatirla'] ) ? 1 : 0;
			$g['goster_sifremi']   = ! empty( $raw['goster_sifremi'] ) ? 1 : 0;
			$g['sabitle_dil']      = ! empty( $raw['sabitle_dil'] ) ? 1 : 0;
			update_option( self::OPT_GORUNUM, $g, false );
		}
	}

	/**
	 * Slug kaydı sonrası admin notice.
	 *
	 * @return void
	 */
	public static function slug_notice() {
		$url = get_transient( 'qrms_login_slug_notice' );
		if ( ! $url ) {
			return;
		}
		delete_transient( 'qrms_login_slug_notice' );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php esc_html_e( 'Yeni giriş adresiniz:', 'qrms' ); ?>
				<strong id="qrms-login-url"><?php echo esc_html( $url ); ?></strong>
				<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('qrms-login-url').textContent)"><?php esc_html_e( 'Kopyala', 'qrms' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Giriş ekranı ayarlar sekmesini basar.
	 *
	 * @return void
	 */
	public static function render_tab() {
		$g      = self::gorunum();
		$slug   = self::slug();
		$devre  = self::devre_disimi();
		$multi  = self::multisite_mi();
		settings_errors( 'qrms_login' );
		?>
		<form method="post" class="qrms-card" id="qrms-login-ayarlar">
			<?php wp_nonce_field( 'qrms_login_save', 'qrms_login_nonce' ); ?>
			<input type="hidden" name="qrms_login_save" value="1" />

			<?php if ( $devre ) : ?>
				<div class="qrms-alert"><?php esc_html_e( 'Özel giriş devre dışı: wp-config.php içinde define( \'QRMS_LOGIN_DISABLE\', true ); tanımlı.', 'qrms' ); ?></div>
			<?php elseif ( $multi ) : ?>
				<div class="qrms-alert"><?php esc_html_e( 'Çoklu site kurulumlarında özel giriş adresi desteklenmez.', 'qrms' ); ?></div>
			<?php else : ?>
				<table class="form-table">
					<tr>
						<th><label for="qrms_login_slug"><?php esc_html_e( 'Giriş adresi', 'qrms' ); ?></label></th>
						<td>
							<code><?php echo esc_html( home_url( '/' ) ); ?></code>
							<input type="text" id="qrms_login_slug" name="qrms_login_slug" value="<?php echo esc_attr( $slug ); ?>" class="regular-text" />
						</td>
					</tr>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Görünüm', 'qrms' ); ?></h2>
			<div class="qrms-login-onizleme-wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:16px">
				<div class="qrms-login-onizleme" id="qrms-login-onizleme" style="min-height:320px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:var(--qrms-login-bg,#1e1e2e)">
					<div class="qrms-login-onizleme-kart" style="width:min(100%,280px);background:#fff;border-radius:var(--qrms-login-radius,12px);padding:24px">
						<div class="qrms-login-onizleme-logo"></div>
						<p class="qrms-login-onizleme-baslik"></p>
						<div class="qrms-login-onizleme-input"></div>
						<div class="qrms-login-onizleme-input"></div>
						<div class="qrms-login-onizleme-btn"></div>
					</div>
				</div>
				<div class="qrms-login-alanlar">
					<table class="form-table">
						<tr>
							<th><label for="logo_url"><?php esc_html_e( 'Logo URL', 'qrms' ); ?></label></th>
							<td>
								<input type="url" id="logo_url" name="qrms_login_gorunum[logo_url]" value="<?php echo esc_attr( $g['logo_url'] ); ?>" class="regular-text qrms-login-field" data-var="--qrms-login-logo" />
								<button type="button" class="button qrms-login-media"><?php esc_html_e( 'Medya seç', 'qrms' ); ?></button>
							</td>
						</tr>
						<tr>
							<th><label for="logo_yukseklik"><?php esc_html_e( 'Logo yüksekliği (px)', 'qrms' ); ?></label></th>
							<td><input type="number" id="logo_yukseklik" name="qrms_login_gorunum[logo_yukseklik]" value="<?php echo esc_attr( (string) $g['logo_yukseklik'] ); ?>" class="qrms-login-field" data-var="--qrms-login-logo-h" data-suffix="px" /></td>
						</tr>
						<tr>
							<th><label for="vurgu_rengi"><?php esc_html_e( 'Vurgu rengi', 'qrms' ); ?></label></th>
							<td><input type="text" id="vurgu_rengi" name="qrms_login_gorunum[vurgu_rengi]" value="<?php echo esc_attr( $g['vurgu_rengi'] ); ?>" class="qrms-color-picker qrms-login-field" data-var="--qrms-login-vurgu" /></td>
						</tr>
						<tr>
							<th><label for="arkaplan_tip"><?php esc_html_e( 'Arka plan', 'qrms' ); ?></label></th>
							<td>
								<select id="arkaplan_tip" name="qrms_login_gorunum[arkaplan_tip]" class="qrms-login-field" data-bg-tip>
									<option value="renk" <?php selected( $g['arkaplan_tip'], 'renk' ); ?>><?php esc_html_e( 'Düz renk', 'qrms' ); ?></option>
									<option value="gradyan" <?php selected( $g['arkaplan_tip'], 'gradyan' ); ?>><?php esc_html_e( 'Gradyan', 'qrms' ); ?></option>
									<option value="gorsel" <?php selected( $g['arkaplan_tip'], 'gorsel' ); ?>><?php esc_html_e( 'Görsel', 'qrms' ); ?></option>
								</select>
							</td>
						</tr>
						<tr data-bg="renk">
							<th><label for="arkaplan_renk"><?php esc_html_e( 'Arka plan rengi', 'qrms' ); ?></label></th>
							<td><input type="text" id="arkaplan_renk" name="qrms_login_gorunum[arkaplan_renk]" value="<?php echo esc_attr( $g['arkaplan_renk'] ); ?>" class="qrms-color-picker qrms-login-field" data-var="--qrms-login-bg" /></td>
						</tr>
						<tr>
							<th><label for="baslik"><?php esc_html_e( 'Başlık metni', 'qrms' ); ?></label></th>
							<td><input type="text" id="baslik" name="qrms_login_gorunum[baslik]" value="<?php echo esc_attr( $g['baslik'] ); ?>" class="regular-text qrms-login-field" data-text=".qrms-login-onizleme-baslik" /></td>
						</tr>
						<tr>
							<th><label for="kart_radius"><?php esc_html_e( 'Kart köşe yarıçapı', 'qrms' ); ?></label></th>
							<td><input type="number" id="kart_radius" name="qrms_login_gorunum[kart_radius]" value="<?php echo esc_attr( (string) $g['kart_radius'] ); ?>" class="qrms-login-field" data-var="--qrms-login-radius" data-suffix="px" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Kart', 'qrms' ); ?></th>
							<td>
								<label><input type="checkbox" name="qrms_login_gorunum[kart_golge]" value="1" <?php checked( ! empty( $g['kart_golge'] ) ); ?> class="qrms-login-field" data-shadow /> <?php esc_html_e( 'Gölge', 'qrms' ); ?></label>
								<label><input type="checkbox" name="qrms_login_gorunum[kart_cam]" value="1" <?php checked( ! empty( $g['kart_cam'] ) ); ?> class="qrms-login-field" data-glass /> <?php esc_html_e( 'Cam efekti', 'qrms' ); ?></label>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Form', 'qrms' ); ?></th>
							<td>
								<label><input type="checkbox" name="qrms_login_gorunum[goster_hatirla]" value="1" <?php checked( ! empty( $g['goster_hatirla'] ) ); ?> /> <?php esc_html_e( 'Beni hatırla', 'qrms' ); ?></label>
								<label><input type="checkbox" name="qrms_login_gorunum[goster_sifremi]" value="1" <?php checked( ! empty( $g['goster_sifremi'] ) ); ?> /> <?php esc_html_e( 'Şifremi unuttum', 'qrms' ); ?></label>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Kaydet', 'qrms' ); ?></button></p>
		</form>
		<?php
	}
}
