<?php
/**
 * Post-login routing and WordPress admin escape for the premium shell.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Login redirect + classic wp-admin return path (does not alter authentication).
 */
class QRMS_Admin_Shell_Auth {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'login_redirect', array( __CLASS__, 'filter_login_redirect' ), 99, 3 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_return_link' ), 100 );
	}

	/**
	 * @param string           $redirect_to           Computed redirect.
	 * @param string           $requested_redirect_to Raw redirect_to from the request.
	 * @param WP_User|WP_Error $user                  Authenticated user.
	 * @return string
	 */
	public static function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		return self::resolve_login_redirect( $redirect_to, $requested_redirect_to, $user );
	}

	/**
	 * Testable login destination resolver.
	 *
	 * @param string           $redirect_to           Computed redirect.
	 * @param string           $requested_redirect_to Raw redirect_to from the request.
	 * @param WP_User|WP_Error $user                  Authenticated user.
	 * @return string
	 */
	public static function resolve_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return (string) $redirect_to;
		}

		if ( self::user_is_servis_only( $user ) ) {
			return self::servis_panel_admin_url();
		}

		if ( user_can( $user, 'manage_options' ) && self::should_force_qrms_overview( $redirect_to, $requested_redirect_to ) ) {
			return self::overview_admin_url();
		}

		return (string) $redirect_to;
	}

	/**
	 * @param WP_User $user User.
	 * @return bool
	 */
	public static function user_is_servis_only( $user ) {
		if ( user_can( $user, QRMS_Admin::CAPABILITY ) ) {
			return false;
		}

		if ( ! class_exists( 'QRMS_SP_Rol' ) ) {
			return false;
		}

		return user_can( $user, QRMS_SP_Rol::YETENEK );
	}

	/**
	 * @param string $redirect_to           Computed redirect.
	 * @param string $requested_redirect_to Requested redirect.
	 * @return bool
	 */
	public static function should_force_qrms_overview( $redirect_to, $requested_redirect_to ) {
		$candidates = array();

		foreach ( array( $redirect_to, $requested_redirect_to ) as $raw ) {
			if ( ! is_string( $raw ) ) {
				continue;
			}

			$trimmed = trim( $raw );
			if ( '' !== $trimmed ) {
				$candidates[] = $trimmed;
			}
		}

		if ( empty( $candidates ) ) {
			return true;
		}

		foreach ( $candidates as $url ) {
			if ( ! self::is_generic_wp_admin_dashboard_url( $url ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the URL is the default wp-admin dashboard landing (not a deep admin screen).
	 *
	 * @param string $url URL (absolute or relative).
	 * @return bool
	 */
	public static function is_generic_wp_admin_dashboard_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return true;
		}

		$query = '';
		$path  = self::normalize_login_redirect_path( $url, $query );

		if ( null === $path ) {
			return false;
		}

		if ( '' === $path ) {
			return self::is_benign_dashboard_query( $query );
		}

		$admin_root = self::normalized_admin_root_path();
		$index_path = self::normalized_admin_index_path();
		$admin_php  = $admin_root . '/admin.php';

		if ( $path === $admin_root || $path === $index_path || $path === $admin_php ) {
			return self::is_benign_dashboard_query( $query );
		}

		return false;
	}

	/**
	 * Canonical wp-admin root path (leading slash, no trailing slash).
	 *
	 * @return string
	 */
	private static function normalized_admin_root_path() {
		return untrailingslashit( (string) wp_parse_url( admin_url(), PHP_URL_PATH ) );
	}

	/**
	 * Canonical dashboard index.php path.
	 *
	 * @return string
	 */
	private static function normalized_admin_index_path() {
		return untrailingslashit( (string) wp_parse_url( admin_url( 'index.php' ), PHP_URL_PATH ) );
	}

	/**
	 * Normalizes login redirect targets (absolute, root-relative, WP-relative wp-admin/).
	 *
	 * @param string $url        Raw redirect value.
	 * @param string $query_out  Query string extracted from the URL (by reference).
	 * @return string|null Normalized path, empty string if URL is path-less, null if unrecognized.
	 */
	private static function normalize_login_redirect_path( $url, &$query_out ) {
		$query_out = '';
		$url       = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		// WordPress core uses this exact relative dashboard shorthand in wp-login.php.
		if ( 'wp-admin/' === $url ) {
			return self::normalized_admin_root_path();
		}

		$relative = ltrim( $url, '/' );
		$rel_core = untrailingslashit( strtolower( $relative ) );

		if ( 'wp-admin' === $rel_core ) {
			return self::normalized_admin_root_path();
		}

		if ( 'wp-admin/index.php' === $rel_core ) {
			self::extract_query_from_url( $url, $query_out );

			return self::normalized_admin_index_path();
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return null;
		}

		if ( isset( $parts['query'] ) ) {
			$query_out = (string) $parts['query'];
		}

		if ( ! isset( $parts['scheme'] ) && ! isset( $parts['host'] ) ) {
			$path = isset( $parts['path'] ) ? (string) $parts['path'] : $relative;

			if ( '' === $path && '' !== $relative && false !== strpos( $url, '?' ) ) {
				self::extract_query_from_url( $url, $query_out );
				$path = $relative;
			}

			$path = '/' . ltrim( $path, '/' );
			$rel  = ltrim( $path, '/' );
			$rel_core = untrailingslashit( strtolower( $rel ) );

			if ( 'wp-admin' === $rel_core || 'wp-admin/index.php' === $rel_core ) {
				return 'wp-admin/index.php' === $rel_core
					? self::normalized_admin_index_path()
					: self::normalized_admin_root_path();
			}

			return untrailingslashit( $path );
		}

		$path = isset( $parts['path'] ) ? untrailingslashit( (string) $parts['path'] ) : '';

		return '' === $path ? '' : $path;
	}

	/**
	 * @param string $url       Full URL possibly containing a query string.
	 * @param string $query_out Query string (by reference).
	 * @return void
	 */
	private static function extract_query_from_url( $url, &$query_out ) {
		$pos = strpos( (string) $url, '?' );
		if ( false === $pos ) {
			return;
		}

		$query_out = substr( (string) $url, $pos + 1 );
	}

	/**
	 * @param string $query Query string without leading ?.
	 * @return bool
	 */
	private static function is_benign_dashboard_query( $query ) {
		if ( '' === trim( (string) $query ) ) {
			return true;
		}

		parse_str( (string) $query, $args );
		if ( ! is_array( $args ) || empty( $args ) ) {
			return true;
		}

		unset( $args['wp_lang'] );

		if ( isset( $args['page'] ) && '' !== (string) $args['page'] ) {
			return false;
		}

		return empty( $args );
	}

	/**
	 * @return string
	 */
	public static function overview_admin_url() {
		return admin_url( 'admin.php?page=' . QRMS_Admin::MENU_SLUG );
	}

	/**
	 * @return string
	 */
	public static function servis_panel_admin_url() {
		$page = defined( 'QRMS_SP_PANEL_SAYFA' ) ? QRMS_SP_PANEL_SAYFA : QRMS_Admin::get_module_page_slug( 'qr-servis-paneli' );

		return admin_url( 'admin.php?page=' . $page );
	}

	/**
	 * @return string
	 */
	public static function wordpress_admin_url() {
		return admin_url( 'index.php' );
	}

	/**
	 * Premium shell «Çıkış Yap» — WordPress logout + QRMS giriş ekranına dönüş.
	 *
	 * Oturum/nonce işlemi çekirdek `wp_logout_url()` ile kalır; yalnızca hedef adres shell UX içindir.
	 *
	 * @return string
	 */
	public static function shell_logout_url() {
		$redirect = home_url( '/' );

		if ( class_exists( 'QRMS_Login' ) && method_exists( 'QRMS_Login', 'login_url' ) ) {
			$redirect = QRMS_Login::login_url();
		}

		return wp_logout_url( $redirect );
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public static function admin_bar_return_link( $wp_admin_bar ) {
		if ( ! is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'add_node' ) ) {
			return;
		}

		if ( class_exists( 'QRMS_Admin_Shell' ) && QRMS_Admin_Shell::is_active() ) {
			return;
		}

		if ( QRMS_Admin::MENU_SLUG === self::current_admin_page_slug() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'qrms-return-overview',
				'title' => __( 'QR Menü → Genel Bakış', 'qrms' ),
				'href'  => self::overview_admin_url(),
				'meta'  => array(
					'class' => 'qrms-wp-admin-return',
				),
			)
		);
	}

	/**
	 * @return string
	 */
	private static function current_admin_page_slug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}
}
