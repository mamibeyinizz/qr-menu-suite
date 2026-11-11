<?php
/**
 * Acil Durum paneli: modülleri anlık kapatma + tek tuşla bakım modu.
 *
 * Erişim: manage_options + PIN (varsayılan 1245). PIN doğrulaması kullanıcıya
 * bağlı kısa ömürlü bir oturum açar; 5 hatalı denemede 15 dk kilit.
 *
 * Kapatılan modül müşteri tarafında (ön yüz, AJAX, REST) HİÇ yüklenmez;
 * admin ekranında yüklenmeye devam eder ki ayarları bozulmasın.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Acil Durum denetleyicisi.
 */
class QRMS_Acil_Durum {

	const SLUG          = 'qrms-acil-durum';
	const OPTION        = 'qrms_acil_durum';
	const PIN_OPTION    = 'qrms_acil_durum_pin_hash';
	const VARSAYILAN_PIN = '1245';
	const OTURUM_SURE   = 900;  // 15 dk.
	const MAKS_DENEME   = 5;
	const KILIT_SURE    = 900;
	const NONCE         = 'qrms_acil_durum';

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_filter( 'qrms_menu_row_slugs', array( __CLASS__, 'menu_row' ) );
		add_action( 'admin_post_qrms_acil_giris', array( __CLASS__, 'handle_login' ) );
		add_action( 'admin_post_qrms_acil_cikis', array( __CLASS__, 'handle_logout' ) );
		add_action( 'admin_post_qrms_acil_kaydet', array( __CLASS__, 'handle_save' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_maintenance' ), 0 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_notice' ), 999 );
	}

	/* ------------------------------------------------------------------ *
	 * Durum
	 * ------------------------------------------------------------------ */

	/**
	 * Kayıtlı durum.
	 *
	 * @return array{kapali:string[],bakim:bool}
	 */
	public static function get_state() {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();

		return array(
			'kapali' => isset( $s['kapali'] ) ? array_values( array_filter( (array) $s['kapali'], array( 'QRMS_Helpers', 'is_valid_module' ) ) ) : array(),
			'bakim'  => ! empty( $s['bakim'] ),
		);
	}

	/**
	 * Modül acil durumda kapatıldı mı?
	 *
	 * @param string $slug Modül slug'ı.
	 * @return bool
	 */
	public static function is_module_disabled( $slug ) {
		return in_array( $slug, self::get_state()['kapali'], true );
	}

	/**
	 * Bu istekte modül yüklenmemeli mi? (Müşteri tarafı: ön yüz, AJAX, REST.)
	 *
	 * @param string $slug Modül slug'ı.
	 * @return bool
	 */
	public static function should_block_module( $slug ) {
		if ( ! self::is_module_disabled( $slug ) ) {
			return false;
		}

		return ! is_admin() || wp_doing_ajax();
	}

	/* ------------------------------------------------------------------ *
	 * PIN / oturum
	 * ------------------------------------------------------------------ */

	/**
	 * PIN hash'i (ilk çağrıda varsayılan PIN'den üretilir).
	 *
	 * @return string
	 */
	private static function get_pin_hash() {
		$hash = get_option( self::PIN_OPTION );

		if ( ! $hash ) {
			$hash = wp_hash_password( self::VARSAYILAN_PIN );
			update_option( self::PIN_OPTION, $hash, false );
		}

		return $hash;
	}

	private static function oturum_key() {
		return 'qrms_acil_oturum_' . get_current_user_id();
	}

	private static function kilit_key() {
		return 'qrms_acil_deneme_' . get_current_user_id();
	}

	/**
	 * Kullanıcı PIN ile kilidi açmış mı?
	 *
	 * @return bool
	 */
	public static function is_unlocked() {
		$token = get_transient( self::oturum_key() );

		return $token && isset( $_COOKIE['qrms_acil'] ) && hash_equals( (string) $token, (string) wp_unslash( $_COOKIE['qrms_acil'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Yetki + nonce kontrolü.
	 *
	 * @param string $action Nonce eylemi.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu alana yalnızca yetkili personel erişebilir.', 'qrms' ), 403 );
		}
		check_admin_referer( self::NONCE . '_' . $action );
	}

	private static function redirect( $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * PIN girişi.
	 *
	 * @return void
	 */
	public static function handle_login() {
		self::guard( 'giris' );

		$deneme = (int) get_transient( self::kilit_key() );
		if ( $deneme >= self::MAKS_DENEME ) {
			self::redirect( array( 'acil' => 'kilit' ) );
		}

		$pin = isset( $_POST['pin'] ) ? preg_replace( '/\D/', '', (string) wp_unslash( $_POST['pin'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( '' === $pin || ! wp_check_password( $pin, self::get_pin_hash() ) ) {
			set_transient( self::kilit_key(), $deneme + 1, self::KILIT_SURE );
			self::redirect( array( 'acil' => 'hata' ) );
		}

		delete_transient( self::kilit_key() );
		$token = wp_generate_password( 40, false );
		set_transient( self::oturum_key(), $token, self::OTURUM_SURE );
		setcookie( 'qrms_acil', $token, array(
			'expires'  => time() + self::OTURUM_SURE,
			'path'     => ADMIN_COOKIE_PATH,
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict',
		) );

		self::redirect();
	}

	/**
	 * Oturumu kapat.
	 *
	 * @return void
	 */
	public static function handle_logout() {
		self::guard( 'cikis' );
		delete_transient( self::oturum_key() );
		setcookie( 'qrms_acil', '', time() - 3600, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, is_ssl(), true );
		self::redirect();
	}

	/**
	 * Tek anahtar değişimi (modül ya da bakım).
	 *
	 * @return void
	 */
	public static function handle_save() {
		self::guard( 'kaydet' );

		if ( ! self::is_unlocked() ) {
			self::redirect( array( 'acil' => 'sure' ) );
		}

		$state  = self::get_state();
		$hedef  = isset( $_POST['hedef'] ) ? sanitize_key( wp_unslash( $_POST['hedef'] ) ) : '';
		$ac     = ! empty( $_POST['ac'] );

		if ( 'bakim' === $hedef ) {
			$state['bakim'] = $ac;
		} elseif ( 'tumu_ac' === $hedef ) {
			$state['kapali'] = array();
			$state['bakim']  = false;
		} elseif ( QRMS_Helpers::is_valid_module( $hedef ) ) {
			$state['kapali'] = $ac
				? array_values( array_diff( $state['kapali'], array( $hedef ) ) )
				: array_values( array_unique( array_merge( $state['kapali'], array( $hedef ) ) ) );
		}

		update_option( self::OPTION, $state, true );

		// Tam sayfa önbelleği kullanan siteler için.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		do_action( 'qrms_acil_durum_degisti', $state, $hedef );

		// Oturumu kayıt anında uzat.
		set_transient( self::oturum_key(), get_transient( self::oturum_key() ), self::OTURUM_SURE );

		self::redirect( array( 'acil' => 'ok' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Bakım modu
	 * ------------------------------------------------------------------ */

	/**
	 * Bakım modu açıksa yöneticiler dışında 503 bakım sayfası.
	 *
	 * @return void
	 */
	public static function maybe_maintenance() {
		if ( ! self::get_state()['bakim'] || current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}

		nocache_headers();
		status_header( 503 );
		header( 'Retry-After: 600' );
		header( 'Content-Type: text/html; charset=utf-8' );

		$site = get_bloginfo( 'name' );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $site ); ?> — <?php esc_html_e( 'Bakımdayız', 'qrms' ); ?></title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f6f7fb;color:#1d2330}
.k{max-width:440px;width:100%;background:#fff;border-radius:18px;padding:clamp(24px,6vw,40px);text-align:center;box-shadow:0 10px 30px rgba(20,30,60,.08)}
.i{font-size:48px;line-height:1}h1{font-size:clamp(20px,5vw,26px);margin:16px 0 8px}p{margin:0;color:#5b6475;line-height:1.55}
@media (prefers-color-scheme:dark){body{background:#12151c;color:#e8ebf2}.k{background:#1c212b;box-shadow:none}p{color:#a5adbd}}
</style>
</head>
<body><div class="k"><div class="i">🛠️</div><h1><?php esc_html_e( 'Kısa bir bakım yapıyoruz', 'qrms' ); ?></h1><p><?php esc_html_e( 'Menümüz birkaç dakika içinde tekrar hizmetinizde olacak. Anlayışınız için teşekkür ederiz.', 'qrms' ); ?></p></div></body>
</html>
		<?php
		exit;
	}

	/**
	 * Acil durum aktifken admin bar'da uyarı.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function admin_bar_notice( $bar ) {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return;
		}
		$s = self::get_state();
		if ( ! $s['bakim'] && ! $s['kapali'] ) {
			return;
		}
		$bar->add_node( array(
			'id'    => 'qrms-acil',
			'title' => $s['bakim'] ? '🚨 ' . __( 'BAKIM MODU AÇIK', 'qrms' ) : '⚠️ ' . sprintf( /* translators: %d: sayı */ __( '%d modül kapalı', 'qrms' ), count( $s['kapali'] ) ),
			'href'  => admin_url( 'admin.php?page=' . self::SLUG ),
			'meta'  => array( 'html' => '<style>#wp-admin-bar-qrms-acil>.ab-item{background:#b32d2e!important;color:#fff!important}</style>' ),
		) );
	}

	/* ------------------------------------------------------------------ *
	 * Admin sayfası
	 * ------------------------------------------------------------------ */

	/**
	 * Menü kaydı.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			QRMS_Admin::MENU_SLUG,
			__( 'Acil Durum', 'qrms' ),
			'<span style="color:#ff6b6b;font-weight:600">🚨 ' . esc_html__( 'Acil Durum', 'qrms' ) . '</span>',
			QRMS_Admin::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Menü beyaz listesine ekle (yoksa QRMS_Admin gizler).
	 *
	 * @param string[] $slugs Slug listesi.
	 * @return string[]
	 */
	public static function menu_row( $slugs ) {
		$slugs[] = self::SLUG;
		return $slugs;
	}

	private static function form_open( $action ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="qrms_acil_' . esc_attr( $action ) . '">';
		wp_nonce_field( self::NONCE . '_' . $action );
	}

	/**
	 * Sayfa.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu alana yalnızca yetkili personel erişebilir.', 'qrms' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg      = isset( $_GET['acil'] ) ? sanitize_key( $_GET['acil'] ) : '';
		$mesajlar = array(
			'hata'  => array( 'err', __( 'Şifre hatalı.', 'qrms' ) ),
			'kilit' => array( 'err', __( 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.', 'qrms' ) ),
			'sure'  => array( 'err', __( 'Oturum süresi doldu, şifreyi tekrar girin.', 'qrms' ) ),
			'ok'    => array( 'ok', __( 'Değişiklik anında uygulandı.', 'qrms' ) ),
		);

		self::styles();
		echo '<div class="wrap qad">';
		echo '<h1 class="qad-h">🚨 ' . esc_html__( 'Acil Durum', 'qrms' ) . '</h1>';
		echo '<div class="qad-not"><strong>' . esc_html__( 'Not:', 'qrms' ) . '</strong> ' . esc_html__( 'Bu alana sadece yetkili personel erişebilir. Buradaki her değişiklik müşteri arayüzüne anında yansır.', 'qrms' ) . '</div>';

		if ( isset( $mesajlar[ $msg ] ) ) {
			echo '<div class="qad-msg qad-' . esc_attr( $mesajlar[ $msg ][0] ) . '">' . esc_html( $mesajlar[ $msg ][1] ) . '</div>';
		}

		if ( ! self::is_unlocked() ) {
			echo '<div class="qad-card qad-lock">';
			echo '<div class="qad-lock-i">🔒</div><h2>' . esc_html__( 'Şifre gerekli', 'qrms' ) . '</h2>';
			self::form_open( 'giris' );
			echo '<input class="qad-pin" type="password" name="pin" inputmode="numeric" pattern="[0-9]*" autocomplete="off" maxlength="12" required autofocus placeholder="••••" aria-label="' . esc_attr__( 'Şifre', 'qrms' ) . '">';
			echo '<button class="qad-btn qad-btn-p" type="submit">' . esc_html__( 'Giriş', 'qrms' ) . '</button></form></div></div>';
			return;
		}

		$state  = self::get_state();
		$active = QRMS_License_Client::get_active_modules();

		// Bakım.
		echo '<div class="qad-card qad-bakim' . ( $state['bakim'] ? ' on' : '' ) . '"><div class="qad-row"><div><h2>' . esc_html__( 'Site Bakım Modu', 'qrms' ) . '</h2><p>' . esc_html__( 'Açıkken ziyaretçiler bakım sayfası görür; yöneticiler siteyi normal görür.', 'qrms' ) . '</p></div>';
		self::form_open( 'kaydet' );
		echo '<input type="hidden" name="hedef" value="bakim"><input type="hidden" name="ac" value="' . ( $state['bakim'] ? '0' : '1' ) . '">';
		echo '<button class="qad-btn ' . ( $state['bakim'] ? 'qad-btn-g' : 'qad-btn-d' ) . '" type="submit">' . ( $state['bakim'] ? esc_html__( 'Bakımı Bitir', 'qrms' ) : esc_html__( 'Bakım Modunu Aç', 'qrms' ) ) . '</button></form></div></div>';

		// Modüller.
		echo '<div class="qad-grid">';
		foreach ( $active as $slug ) {
			$kapali = in_array( $slug, $state['kapali'], true );
			echo '<div class="qad-card qad-mod' . ( $kapali ? ' off' : '' ) . '">';
			echo '<div class="qad-mod-t"><strong>' . esc_html( QRMS_Helpers::get_module_name( $slug ) ) . '</strong><span class="qad-dur">' . ( $kapali ? esc_html__( 'Kapalı', 'qrms' ) : esc_html__( 'Açık', 'qrms' ) ) . '</span></div>';
			self::form_open( 'kaydet' );
			echo '<input type="hidden" name="hedef" value="' . esc_attr( $slug ) . '"><input type="hidden" name="ac" value="' . ( $kapali ? '1' : '0' ) . '">';
			echo '<button type="submit" class="qad-sw' . ( $kapali ? '' : ' on' ) . '" role="switch" aria-checked="' . ( $kapali ? 'false' : 'true' ) . '" aria-label="' . esc_attr( QRMS_Helpers::get_module_name( $slug ) ) . '"><span></span></button></form></div>';
		}
		echo '</div>';

		echo '<div class="qad-foot">';
		if ( $state['kapali'] || $state['bakim'] ) {
			self::form_open( 'kaydet' );
			echo '<input type="hidden" name="hedef" value="tumu_ac"><button class="qad-btn qad-btn-g" type="submit">' . esc_html__( 'Her Şeyi Normale Döndür', 'qrms' ) . '</button></form>';
		}
		self::form_open( 'cikis' );
		echo '<button class="qad-btn" type="submit">' . esc_html__( 'Oturumu Kilitle', 'qrms' ) . '</button></form></div>';
		echo '</div>';
	}

	/**
	 * Satır içi stil (tek sayfaya özel, ayrı istek yok).
	 *
	 * @return void
	 */
	private static function styles() {
		?>
<style>
.qad{max-width:1100px}.qad *{box-sizing:border-box}.qad-h{font-size:24px;margin-bottom:12px}
.qad-not{background:#fff4e5;border-left:4px solid #dba617;padding:12px 16px;border-radius:8px;margin-bottom:16px;color:#5a3d00}
.qad-msg{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-weight:500}.qad-err{background:#fde8e8;color:#8a1f1f}.qad-ok{background:#e6f6ea;color:#1b5e2b}
.qad-card{background:#fff;border:1px solid #e2e5ec;border-radius:14px;padding:18px;margin-bottom:16px}
.qad-card h2{margin:0 0 4px;font-size:17px}.qad-card p{margin:0;color:#646970}
.qad-lock{max-width:380px;margin:40px auto;text-align:center;padding:32px 24px}.qad-lock-i{font-size:42px}.qad-lock form{display:flex;flex-direction:column;gap:12px;margin-top:16px}
.qad-pin{font-size:28px;text-align:center;letter-spacing:12px;padding:10px;border-radius:10px;border:1px solid #c3c4c7;width:100%}
.qad-row{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.qad-bakim.on{border-color:#d63638;background:#fff5f5}
.qad-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-bottom:16px}
.qad-grid .qad-card{margin:0}.qad-mod{display:flex;align-items:center;justify-content:space-between;gap:12px}.qad-mod.off{background:#fafafa;border-color:#f0b4b4}
.qad-mod-t{display:flex;flex-direction:column;gap:4px;min-width:0}.qad-dur{font-size:12px;color:#00a32a;font-weight:600}.qad-mod.off .qad-dur{color:#d63638}
.qad-sw{position:relative;width:56px;height:32px;border-radius:32px;border:0;background:#c3c4c7;cursor:pointer;flex-shrink:0;transition:background .2s}
.qad-sw span{position:absolute;top:4px;left:4px;width:24px;height:24px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
.qad-sw.on{background:#00a32a}.qad-sw.on span{transform:translateX(24px)}.qad-sw:focus-visible{outline:2px solid #2271b1;outline-offset:2px}
.qad-btn{min-height:44px;padding:0 20px;border-radius:10px;border:1px solid #c3c4c7;background:#f6f7f7;font-size:14px;font-weight:600;cursor:pointer}
.qad-btn-p{background:#2271b1;border-color:#2271b1;color:#fff}.qad-btn-d{background:#d63638;border-color:#d63638;color:#fff}.qad-btn-g{background:#00a32a;border-color:#00a32a;color:#fff}
.qad-foot{display:flex;gap:12px;flex-wrap:wrap}
@media (max-width:782px){.qad-row form,.qad-row .qad-btn,.qad-foot form,.qad-foot .qad-btn{width:100%}.qad-grid{grid-template-columns:1fr}.qad-lock{margin:16px auto}}
</style>
		<?php
	}
}
