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
		$requested = is_string( $requested_redirect_to ) ? trim( $requested_redirect_to ) : '';
		$candidate = '' !== $requested ? $requested : (string) $redirect_to;

		return self::is_generic_wp_admin_dashboard_url( $candidate );
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

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$path  = isset( $parts['path'] ) ? untrailingslashit( (string) $parts['path'] ) : '';
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';

		if ( '' === $path ) {
			return true;
		}

		$admin_root = untrailingslashit( (string) wp_parse_url( admin_url(), PHP_URL_PATH ) );
		$index_path = untrailingslashit( (string) wp_parse_url( admin_url( 'index.php' ), PHP_URL_PATH ) );

		if ( $path === $admin_root || $path === $index_path ) {
			return self::is_benign_dashboard_query( $query );
		}

		if ( preg_match( '#/wp-admin/?$#i', $path ) ) {
			return self::is_benign_dashboard_query( $query );
		}

		return false;
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
