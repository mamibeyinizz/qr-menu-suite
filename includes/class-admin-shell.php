<?php
/**
 * Premium admin shell — presentation layer for QRMS admin screens.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'QRMS_PREMIUM_SHELL' ) ) {
	define( 'QRMS_PREMIUM_SHELL', true );
}

/**
 * Wraps QRMS admin screens in the premium SaaS chrome.
 */
class QRMS_Admin_Shell {

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_open' ), -99999 );
		add_action( 'admin_notices', array( __CLASS__, 'render_native_hybrid_back_link' ), 0 );
		add_action( 'admin_footer', array( __CLASS__, 'render_close' ), 99999 );
	}

	/**
	 * Feature flag master switch.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		/**
		 * Premium shell master toggle.
		 *
		 * @param bool $enabled Default from QRMS_PREMIUM_SHELL constant.
		 */
		return (bool) apply_filters( 'qrms_premium_shell_enabled', (bool) QRMS_PREMIUM_SHELL );
	}

	/**
	 * Shell active on this request.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( ! is_admin() ) {
			return false;
		}

		if ( class_exists( 'QRMS_SP_Rol' ) && QRMS_SP_Rol::yalniz_servis_mi() ) {
			return self::is_servis_panel_screen();
		}

		if ( ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return false;
		}

		$active = self::is_qrms_admin_screen();

		/**
		 * Whether the premium shell should wrap the current admin screen.
		 *
		 * @param bool   $active Default from QRMS screen detection.
		 * @param string $page   Current `page` query arg (may be empty on native screens).
		 */
		return (bool) apply_filters( 'qrms_premium_shell_active', $active, self::get_request_page() );
	}

	/**
	 * Markup for module page titles that duplicate the shell header H1.
	 *
	 * Visual title stays for layout/CSS (Fix 10A.1); premium shell screens
	 * expose a single real H1 in the accessibility tree (h1.qrms-shell__title).
	 *
	 * @return string Leading space + aria-hidden attribute, or empty when shell is off.
	 */
	public static function duplicate_page_title_a11y_attr() {
		if ( ! self::is_active() ) {
			return '';
		}

		return ' aria-hidden="true"';
	}

	/**
	 * Premium shell hybrid ürün ekle/düzenle (native post.php) ekranı.
	 *
	 * @return bool
	 */
	public static function is_hybrid_product_editor_screen() {
		if ( ! self::is_active() || ! self::is_native_hybrid_screen() ) {
			return false;
		}

		return class_exists( 'RMA_Urun_Editor' ) && RMA_Urun_Editor::ekran_mi();
	}

	/**
	 * Whether the current user is on the servis panel screen only.
	 *
	 * @return bool
	 */
	public static function is_servis_panel_screen() {
		if ( ! defined( 'QRMS_SP_PANEL_SAYFA' ) ) {
			return false;
		}

		return QRMS_SP_PANEL_SAYFA === self::get_request_page();
	}

	/**
	 * QRMS plugin admin screen (suite pages, subpages, hybrid native menu screens).
	 *
	 * @return bool
	 */
	public static function is_qrms_admin_screen() {
		$page = self::get_request_page();

		if ( QRMS_Admin::is_plugin_screen() ) {
			return true;
		}

		if ( '' !== $page && QRMS_Admin::is_module_subpage( $page ) ) {
			return true;
		}

		return self::is_native_hybrid_screen();
	}

	/**
	 * Restoran Menü native WordPress CPT/taxonomy screens (hybrid shell).
	 *
	 * @return bool
	 */
	public static function is_native_hybrid_screen() {
		return function_exists( 'qrms_module_restoran_menu_ekranimiz_mi' )
			&& qrms_module_restoran_menu_ekranimiz_mi();
	}

	/**
	 * Current admin `page` query argument.
	 *
	 * @return string
	 */
	public static function get_request_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	/**
	 * Visually hides core wp-admin chrome on shell screens (inline — keeps repo CSS scan clean).
	 *
	 * @return string
	 */
	private static function chrome_hide_css() {
		$css = 'body.qrms-premium-shell-active #adminmenuback,'
			. 'body.qrms-premium-shell-active #adminmenuwrap,'
			. 'body.qrms-premium-shell-active #wpadminbar{'
			. 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;'
			. 'clip:rect(0,0,0,0);white-space:nowrap;border:0;}'
			. 'body.qrms-premium-shell-active #wpcontent,'
			. 'body.qrms-premium-shell-active #wpbody,'
			. 'body.qrms-premium-shell-active #wpbody-content{margin:0;padding:0;}'
			. 'body.qrms-premium-shell-active #wpcontent{margin-left:0!important;}'
			. 'body.qrms-premium-shell-active #wpbody-content{padding-bottom:0;}'
			. 'body.qrms-premium-shell-active #wpfooter{display:none;}'
			. 'html.wp-toolbar:has(body.qrms-premium-shell-active){padding-top:0!important;}';

		if ( ! self::is_native_hybrid_screen() ) {
			$css .= 'body.qrms-premium-shell-active #screen-meta,'
				. 'body.qrms-premium-shell-active #screen-meta-links{display:none;}';
		}

		return $css;
	}

	/**
	 * @param string $classes Space-separated admin body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( ! self::is_active() ) {
			return $classes;
		}

		$classes .= ' qrms-premium-shell qrms-premium-shell-active';

		if ( self::is_native_hybrid_screen() ) {
			$classes .= ' qrms-premium-shell-hybrid';
		}

		if ( class_exists( 'QRMS_SP_Rol' ) && QRMS_SP_Rol::yalniz_servis_mi() ) {
			$classes .= ' qrms-premium-shell-servis';
		}

		return trim( $classes );
	}

	/**
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! self::is_active() ) {
			return;
		}

		self::ensure_qrms_admin_assets();

		wp_enqueue_style(
			'qrms-admin-shell',
			QRMS_PLUGIN_URL . 'assets/css/admin-shell.css',
			array( 'qrms-admin' ),
			QRMS_Helpers::asset_version( 'assets/css/admin-shell.css' )
		);

		wp_add_inline_style( 'qrms-admin-shell', self::chrome_hide_css() );

		wp_enqueue_script(
			'qrms-admin-shell',
			QRMS_PLUGIN_URL . 'assets/js/admin-shell.js',
			array(),
			QRMS_Helpers::asset_version( 'assets/js/admin-shell.js' ),
			true
		);

		wp_localize_script(
			'qrms-admin-shell',
			'qrmsShell',
			array(
				'drawerLabel' => __( 'Ana menü', 'qrms' ),
				'openMenu'    => __( 'Menüyü aç', 'qrms' ),
				'closeMenu'   => __( 'Menüyü kapat', 'qrms' ),
			)
		);
	}

	/**
	 * Loads shared QRMS admin assets when the screen is hybrid/native (admin.css is otherwise skipped).
	 *
	 * @return void
	 */
	private static function ensure_qrms_admin_assets() {
		if ( wp_style_is( 'qrms-admin', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'qrms-admin',
			QRMS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			QRMS_Helpers::asset_version( 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'qrms-admin',
			QRMS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			QRMS_Helpers::asset_version( 'assets/js/admin.js' ),
			true
		);
	}

	/**
	 * Opens shell markup (content area stays open until admin_footer).
	 *
	 * @return void
	 */
	public static function render_open() {
		if ( ! self::is_active() ) {
			return;
		}

		$user   = wp_get_current_user();
		$nav    = self::build_sidebar_nav();
		$header = self::get_header_context();
		$role   = ( class_exists( 'QRMS_SP_Rol' ) && QRMS_SP_Rol::yalniz_servis_mi() )
			? __( 'Servis Personeli', 'qrms' )
			: __( 'Yönetici', 'qrms' );

		$content_class = 'qrms-shell__content';
		if ( self::is_native_hybrid_screen() ) {
			$content_class .= ' qrms-shell__content--hybrid';
		}
		?>
		<div class="qrms-shell" id="qrms-shell">
			<div class="qrms-shell__backdrop" id="qrms-shell-backdrop" hidden aria-hidden="true"></div>
			<aside class="qrms-shell__sidebar" id="qrms-shell-sidebar" aria-label="<?php esc_attr_e( 'QR Menü ana gezinme', 'qrms' ); ?>">
				<div class="qrms-shell__drawer-top">
					<div class="qrms-shell__brand">
						<span class="qrms-shell__brand-mark" aria-hidden="true"></span>
						<span class="qrms-shell__brand-text">
							<span class="qrms-shell__brand-name"><?php esc_html_e( 'QR MENU', 'qrms' ); ?></span>
							<span class="qrms-shell__brand-sub"><?php esc_html_e( 'OFFICIAL', 'qrms' ); ?></span>
						</span>
					</div>
					<button
						type="button"
						class="qrms-shell__drawer-close"
						id="qrms-shell-drawer-close"
						aria-label="<?php echo esc_attr( self::l10n( 'closeMenu' ) ); ?>"
					>
						<span class="qrms-shell__drawer-close-icon" aria-hidden="true"></span>
					</button>
				</div>
				<nav class="qrms-shell__nav" aria-label="<?php esc_attr_e( 'Panel', 'qrms' ); ?>">
					<ul class="qrms-shell__nav-list">
						<?php foreach ( $nav as $item ) : ?>
							<?php self::render_sidebar_row( $item ); ?>
						<?php endforeach; ?>
					</ul>
				</nav>
				<div class="qrms-shell__sidebar-foot">
					<?php if ( ! ( class_exists( 'QRMS_SP_Rol' ) && QRMS_SP_Rol::yalniz_servis_mi() ) ) : ?>
						<?php self::render_nav_item( self::get_settings_nav_item() ); ?>
					<?php endif; ?>
					<?php if ( current_user_can( 'manage_options' ) && ! ( class_exists( 'QRMS_SP_Rol' ) && QRMS_SP_Rol::yalniz_servis_mi() ) ) : ?>
						<a class="qrms-shell__wp-admin-link" href="<?php echo esc_url( QRMS_Admin_Shell_Auth::wordpress_admin_url() ); ?>">
							<span class="qrms-shell__wp-admin-icon dashicons dashicons-wordpress" aria-hidden="true"></span>
							<span class="qrms-shell__wp-admin-label"><?php esc_html_e( 'WordPress Yönetimi', 'qrms' ); ?></span>
						</a>
					<?php endif; ?>
					<?php self::render_account_control( $user, $role, 'sidebar' ); ?>
				</div>
			</aside>
			<div class="qrms-shell__main">
				<header class="qrms-shell__header">
					<div class="qrms-shell__header-start">
						<button type="button" class="qrms-shell__menu-toggle" id="qrms-shell-menu-toggle" aria-expanded="false" aria-controls="qrms-shell-sidebar">
							<span class="qrms-shell__menu-toggle-bar" aria-hidden="true"></span>
							<span class="qrms-shell__menu-toggle-bar" aria-hidden="true"></span>
							<span class="qrms-shell__menu-toggle-bar" aria-hidden="true"></span>
							<span class="qrms-shell__sr-only"><?php echo esc_html( self::l10n( 'openMenu' ) ); ?></span>
						</button>
						<div class="qrms-shell__titles">
							<p class="qrms-shell__breadcrumb"><?php echo esc_html( $header['breadcrumb'] ); ?></p>
							<h1 class="qrms-shell__title"><?php echo esc_html( $header['title'] ); ?></h1>
						</div>
					</div>
					<div class="qrms-shell__header-end">
						<?php self::render_account_control( $user, $role, 'header' ); ?>
					</div>
				</header>
				<div class="<?php echo esc_attr( $content_class ); ?>" id="qrms-shell-content">
		<?php
	}

	/**
	 * Closes shell content wrapper.
	 *
	 * @return void
	 */
	public static function render_close() {
		if ( ! self::is_active() ) {
			return;
		}
		?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $key openMenu|closeMenu|accountMenu|logout.
	 * @return string
	 */
	private static function l10n( $key ) {
		$map = array(
			'openMenu'    => __( 'Menüyü aç', 'qrms' ),
			'closeMenu'   => __( 'Menüyü kapat', 'qrms' ),
			'accountMenu' => __( 'Hesap menüsü', 'qrms' ),
			'logout'      => __( 'Çıkış Yap', 'qrms' ),
		);

		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * Sidebar / header hesap kontrolü (dropdown + güvenli çıkış).
	 *
	 * @param WP_User $user       Oturum.
	 * @param string  $role       Görünen rol etiketi.
	 * @param string  $placement  sidebar|header.
	 * @return void
	 */
	private static function render_account_control( $user, $role, $placement ) {
		$placement = 'header' === $placement ? 'header' : 'sidebar';
		$menu_id   = 'qrms-shell-account-menu-' . $placement;
		$trigger_id = 'qrms-shell-account-trigger-' . $placement;
		$logout_url = QRMS_Admin_Shell_Auth::shell_logout_url();
		$initials   = self::user_initials( $user );
		$name       = $user->display_name;
		?>
		<div class="qrms-shell__account-wrap qrms-shell__account-wrap--<?php echo esc_attr( $placement ); ?>">
			<button
				type="button"
				class="qrms-shell__account-trigger"
				id="<?php echo esc_attr( $trigger_id ); ?>"
				aria-expanded="false"
				aria-haspopup="true"
				aria-controls="<?php echo esc_attr( $menu_id ); ?>"
			>
				<span class="qrms-shell__account-avatar<?php echo 'header' === $placement ? ' qrms-shell__account-avatar--sm' : ''; ?>" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
				<?php if ( 'sidebar' === $placement ) : ?>
					<span class="qrms-shell__account-meta">
						<span class="qrms-shell__account-name"><?php echo esc_html( $name ); ?></span>
						<span class="qrms-shell__account-role"><?php echo esc_html( $role ); ?></span>
					</span>
				<?php else : ?>
					<span class="qrms-shell__account-name"><?php echo esc_html( $name ); ?></span>
				<?php endif; ?>
				<span class="qrms-shell__sr-only"><?php echo esc_html( self::l10n( 'accountMenu' ) ); ?></span>
			</button>
			<div class="qrms-shell__account-menu" id="<?php echo esc_attr( $menu_id ); ?>" role="menu" hidden>
				<div class="qrms-shell__account-menu-meta" role="none">
					<span class="qrms-shell__account-name"><?php echo esc_html( $name ); ?></span>
					<span class="qrms-shell__account-role"><?php echo esc_html( $role ); ?></span>
				</div>
				<div class="qrms-shell__account-menu-divider" role="separator"></div>
				<a class="qrms-shell__account-logout" role="menuitem" href="<?php echo esc_url( $logout_url ); ?>">
					<?php echo esc_html( self::l10n( 'logout' ) ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Sidebar rows from existing menu groups (license + capability aware).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_sidebar_nav() {
		if ( class_exists( 'QRMS_SP_Rol' ) && QRMS_SP_Rol::yalniz_servis_mi() ) {
			return array(
				self::nav_link_item(
					defined( 'QRMS_SP_PANEL_SAYFA' ) ? QRMS_SP_PANEL_SAYFA : QRMS_Admin::get_module_page_slug( 'qr-servis-paneli' ),
					__( 'Servis Paneli', 'qrms' ),
					true
				),
			);
		}

		$ctx   = self::resolve_screen_context();
		$rows  = array();
		$rows[] = self::nav_link_item( QRMS_Admin::MENU_SLUG, __( 'Genel Bakış', 'qrms' ), self::is_nav_current( QRMS_Admin::MENU_SLUG, $ctx ) );

		foreach ( QRMS_Admin::get_menu_groups() as $group ) {
			$group_items = array();

			foreach ( (array) $group['items'] as $page_slug ) {
				if ( QRMS_Admin::MENU_SLUG === $page_slug || QRMS_Admin::SETTINGS_SLUG === $page_slug ) {
					continue;
				}

				if ( ! self::user_can_view_menu_slug( $page_slug ) ) {
					continue;
				}

				$group_items[] = self::nav_link_item(
					$page_slug,
					self::label_for_menu_slug( $page_slug ),
					self::is_nav_current( $page_slug, $ctx )
				);
			}

			if ( empty( $group_items ) ) {
				continue;
			}

			$rows[] = array(
				'type'  => 'group',
				'title' => isset( $group['title'] ) ? (string) $group['title'] : '',
			);

			foreach ( $group_items as $item ) {
				$rows[] = $item;
			}
		}

		/**
		 * Premium shell sidebar rows (after defaults).
		 *
		 * @param array $rows  Nav rows (links + group headers).
		 * @param array $ctx   Screen context from resolve_screen_context().
		 */
		return apply_filters( 'qrms_premium_shell_nav', $rows, $ctx );
	}

	/**
	 * @param string $page_slug Menu row slug.
	 * @param string $label     Link label.
	 * @param bool   $current   Active state.
	 * @return array<string,mixed>
	 */
	private static function nav_link_item( $page_slug, $label, $current ) {
		return array(
			'type'     => 'link',
			'label'    => $label,
			'url'      => admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) ),
			'current'  => $current,
			'dashicon' => QRMS_Admin::get_menu_row_icon( $page_slug ),
		);
	}

	/**
	 * @param string $page_slug Admin menu row slug.
	 * @return bool
	 */
	private static function user_can_view_menu_slug( $page_slug ) {
		if ( QRMS_Admin::MENU_SLUG === $page_slug ) {
			return current_user_can( QRMS_Admin::CAPABILITY );
		}

		if ( QRMS_Admin::SETTINGS_SLUG === $page_slug ) {
			return current_user_can( QRMS_Admin::CAPABILITY );
		}

		if ( QRMS_Admin::SHORTCODES_SLUG === $page_slug ) {
			return QRMS_Shortcodes::has_any() && current_user_can( QRMS_Admin::CAPABILITY );
		}

		$prefix = QRMS_Admin::MODULE_PAGE_PREFIX;
		if ( 0 !== strpos( (string) $page_slug, $prefix ) ) {
			return false;
		}

		$module = substr( (string) $page_slug, strlen( $prefix ) );
		if ( ! QRMS_Helpers::is_valid_module( $module ) ) {
			return false;
		}

		if ( ! in_array( $module, QRMS_License_Client::get_active_modules(), true ) ) {
			return false;
		}

		return current_user_can( QRMS_Admin::get_module_page_capability( $module ) );
	}

	/**
	 * @param string $page_slug Menu row slug.
	 * @return string
	 */
	private static function label_for_menu_slug( $page_slug ) {
		if ( QRMS_Admin::SHORTCODES_SLUG === $page_slug ) {
			return __( 'Entegrasyonlar & Kısa Kodlar', 'qrms' );
		}

		$prefix = QRMS_Admin::MODULE_PAGE_PREFIX;
		if ( 0 === strpos( (string) $page_slug, $prefix ) ) {
			$module = substr( (string) $page_slug, strlen( $prefix ) );
			if ( QRMS_Helpers::is_valid_module( $module ) ) {
				return QRMS_Helpers::get_module_name( $module );
			}
		}

		return (string) $page_slug;
	}

	/**
	 * @return array{page:string,module:string,group:string}
	 */
	private static function resolve_screen_context() {
		$page   = self::get_request_page();
		$module = '';

		if ( '' !== $page ) {
			$module = QRMS_Admin::get_subpage_owner_module( $page );
			if ( '' === $module ) {
				$module = self::module_slug_for_hub_page( $page );
			}
		}

		if ( self::is_native_hybrid_screen() ) {
			$module = 'restoran-menu';
		}

		return array(
			'page'   => $page,
			'module' => $module,
			'group'  => self::group_title_for_module( $module, $page ),
		);
	}

	/**
	 * @param string $page Hub `page` slug.
	 * @return string Module slug or empty.
	 */
	private static function module_slug_for_hub_page( $page ) {
		$prefix = QRMS_Admin::MODULE_PAGE_PREFIX;
		if ( 0 !== strpos( (string) $page, $prefix ) ) {
			return '';
		}

		$module = substr( (string) $page, strlen( $prefix ) );

		return QRMS_Helpers::is_valid_module( $module ) ? $module : '';
	}

	/**
	 * @param string $menu_slug Menu row slug.
	 * @param array  $ctx       Screen context.
	 * @return bool
	 */
	private static function is_nav_current( $menu_slug, array $ctx ) {
		if ( (string) $menu_slug === (string) $ctx['page'] ) {
			return true;
		}

		if ( '' !== $ctx['module'] ) {
			return $menu_slug === QRMS_Admin::get_module_page_slug( $ctx['module'] );
		}

		return false;
	}

	/**
	 * @param string $module Module slug (may be empty).
	 * @param string $page   Current page slug.
	 * @return string
	 */
	private static function group_title_for_module( $module, $page ) {
		if ( QRMS_Admin::MENU_SLUG === $page ) {
			return __( 'Genel Bakış', 'qrms' );
		}

		if ( QRMS_Admin::SETTINGS_SLUG === $page ) {
			return __( 'Sistem', 'qrms' );
		}

		if ( QRMS_Admin::SHORTCODES_SLUG === $page ) {
			return __( 'Sistem', 'qrms' );
		}

		foreach ( QRMS_Admin::get_nav_groups() as $group ) {
			foreach ( (array) $group['items'] as $item ) {
				$slug = self::nav_item_to_menu_slug( $item );
				if ( '' === $slug ) {
					continue;
				}

				if ( $slug === $page ) {
					return isset( $group['overview_title'] ) ? (string) $group['overview_title'] : (string) $group['title'];
				}

				if ( '' !== $module ) {
					$item_module = QRMS_Helpers::is_valid_module( $item ) ? $item : self::module_slug_for_hub_page( $slug );
					if ( $module === $item_module ) {
						return isset( $group['overview_title'] ) ? (string) $group['overview_title'] : (string) $group['title'];
					}
				}
			}
		}

		if ( '' !== $module ) {
			return QRMS_Helpers::get_module_name( $module );
		}

		return __( 'QR Menü', 'qrms' );
	}

	/**
	 * Mirrors QRMS_Admin nav item → menu slug (public helpers only).
	 *
	 * @param string $item Nav group item key.
	 * @return string
	 */
	private static function nav_item_to_menu_slug( $item ) {
		if ( QRMS_Helpers::is_valid_module( $item ) ) {
			return QRMS_Admin::get_module_page_slug( $item );
		}

		if ( QRMS_Admin::OVERVIEW_CORE_SHORTCODES === $item ) {
			return QRMS_Admin::SHORTCODES_SLUG;
		}

		if ( QRMS_Admin::OVERVIEW_CORE_SETTINGS === $item ) {
			return QRMS_Admin::SETTINGS_SLUG;
		}

		if ( QRMS_Admin::MENU_SLUG === $item ) {
			return QRMS_Admin::MENU_SLUG;
		}

		return (string) $item;
	}

	/**
	 * Native ürün listesi (edit.php?post_type=rma_menu_item).
	 *
	 * @return bool
	 */
	private static function is_menu_item_list_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && 'edit' === $screen->base && 'rma_menu_item' === $screen->post_type;
	}

	/**
	 * Native ürün ekle/düzenle (post-new.php / post.php).
	 *
	 * @return bool
	 */
	private static function is_menu_item_edit_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && 'post' === $screen->base && 'rma_menu_item' === $screen->post_type;
	}

	/**
	 * Tükenen Ürünler modül alt sayfası.
	 *
	 * @return bool
	 */
	private static function is_urunum_yok_screen() {
		return 'qrms-rm-urunum-yok' === self::get_request_page();
	}

	/**
	 * Öne Çıkanlar modül alt sayfası.
	 *
	 * @return bool
	 */
	private static function is_one_cikanlar_screen() {
		return 'qrms-rm-one-cikanlar' === self::get_request_page();
	}

	/**
	 * Kampanya Görselleri modül alt sayfası.
	 *
	 * @return bool
	 */
	private static function is_kampanya_banner_screen() {
		return 'qrms-rm-kampanya-banner' === self::get_request_page();
	}

	/**
	 * @return array{breadcrumb:string,title:string}
	 */
	private static function get_header_context() {
		if ( self::is_menu_item_list_screen() ) {
			return array(
				'breadcrumb' => sprintf(
					/* translators: 1: hub group label, 2: module line label */
					__( '%1$s / %2$s', 'qrms' ),
					__( 'Menü Yönetimi', 'qrms' ),
					__( 'Restoran Menü', 'qrms' )
				),
				'title'      => __( 'Ürünler', 'qrms' ),
			);
		}

		if ( self::is_menu_item_edit_screen() ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$is_new = $screen && 'add' === $screen->action;

			return array(
				'breadcrumb' => sprintf(
					/* translators: 1: hub group label, 2: module line label */
					__( '%1$s / %2$s', 'qrms' ),
					__( 'Menü Yönetimi', 'qrms' ),
					__( 'Restoran Menü', 'qrms' )
				),
				'title'      => $is_new ? __( 'Yeni Ürün', 'qrms' ) : __( 'Ürünü Düzenle', 'qrms' ),
			);
		}

		if ( self::is_urunum_yok_screen() ) {
			return array(
				'breadcrumb' => sprintf(
					/* translators: 1: hub group label, 2: screen title */
					__( '%1$s / %2$s', 'qrms' ),
					__( 'Menü Yönetimi', 'qrms' ),
					__( 'Tükenen Ürünler', 'qrms' )
				),
				'title'      => __( 'Tükenen Ürünler', 'qrms' ),
			);
		}

		if ( self::is_one_cikanlar_screen() ) {
			return array(
				'breadcrumb' => sprintf(
					/* translators: 1: hub group label, 2: screen title */
					__( '%1$s / %2$s', 'qrms' ),
					__( 'Menü Yönetimi', 'qrms' ),
					__( 'Öne Çıkanlar', 'qrms' )
				),
				'title'      => __( 'Öne Çıkanlar', 'qrms' ),
			);
		}

		if ( self::is_kampanya_banner_screen() ) {
			return array(
				'breadcrumb' => sprintf(
					/* translators: 1: hub group label, 2: screen title */
					__( '%1$s / %2$s', 'qrms' ),
					__( 'Menü Yönetimi', 'qrms' ),
					__( 'Kampanya Görselleri', 'qrms' )
				),
				'title'      => __( 'Kampanya Görselleri', 'qrms' ),
			);
		}

		$taxonomy = self::get_restoran_taxonomy_screen_context();
		if ( null !== $taxonomy ) {
			return array(
				'breadcrumb' => self::breadcrumb_join(
					array(
						__( 'Menü Yönetimi', 'qrms' ),
						__( 'Restoran Menü', 'qrms' ),
						$taxonomy['label'],
					)
				),
				'title'      => $taxonomy['label'],
			);
		}

		$banner = self::get_banner_cpt_screen_context();
		if ( null !== $banner ) {
			return array(
				'breadcrumb' => self::breadcrumb_join(
					array(
						__( 'Menü Yönetimi', 'qrms' ),
						__( 'Restoran Menü', 'qrms' ),
						$banner['breadcrumb_leaf'],
					)
				),
				'title'      => $banner['title'],
			);
		}

		$ctx = self::resolve_screen_context();

		$title = function_exists( 'get_admin_page_title' ) ? get_admin_page_title() : '';
		if ( '' === $title && isset( $GLOBALS['title'] ) ) {
			$title = (string) $GLOBALS['title'];
		}

		if ( QRMS_Admin::MENU_SLUG === $ctx['page'] && '' === trim( $title ) ) {
			$title = __( 'QR Menü Kontrol Merkezi', 'qrms' );
		}

		if ( '' === trim( $title ) && '' !== $ctx['module'] ) {
			$title = QRMS_Helpers::get_module_name( $ctx['module'] );
		}

		return array(
			'breadcrumb' => self::build_shell_breadcrumb( $ctx, $title ),
			'title'      => $title,
		);
	}

	/**
	 * Breadcrumb parçalarını " / " ile birleştirir.
	 *
	 * @param string[] $parts Etiketler.
	 * @return string
	 */
	private static function breadcrumb_join( array $parts ) {
		$clean = array();

		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' !== $part ) {
				$clean[] = $part;
			}
		}

		return implode( ' / ', $clean );
	}

	/**
	 * Shell üst breadcrumb — grup, modül hub ve alt sayfa tutarlılığı.
	 *
	 * @param array  $ctx   resolve_screen_context() çıktısı.
	 * @param string $title Görünen sayfa başlığı.
	 * @return string
	 */
	private static function build_shell_breadcrumb( array $ctx, $title ) {
		$group  = isset( $ctx['group'] ) ? (string) $ctx['group'] : '';
		$module = isset( $ctx['module'] ) ? (string) $ctx['module'] : '';
		$page   = isset( $ctx['page'] ) ? (string) $ctx['page'] : '';

		if ( 'qrms-analiz-ayarlar' === $page || 'qrms-guvenlik-oturum' === $page ) {
			return self::breadcrumb_join(
				array(
					$group,
					__( 'Masa Oturumu Güvenliği', 'qrms' ),
					$title,
				)
			);
		}

		if ( 'restoran-menu' === $module && class_exists( 'QRMS_Admin' ) ) {
			$hub_slug = QRMS_Admin::get_module_page_slug( 'restoran-menu' );

			if ( $page === $hub_slug ) {
				return self::breadcrumb_join(
					array(
						__( 'Menü Yönetimi', 'qrms' ),
						__( 'Restoran Menü', 'qrms' ),
					)
				);
			}

			if ( '' !== $page && QRMS_Admin::is_module_subpage( $page ) ) {
				return self::breadcrumb_join(
					array(
						__( 'Menü Yönetimi', 'qrms' ),
						$title,
					)
				);
			}
		}

		if ( '' !== $module && class_exists( 'QRMS_Admin' ) ) {
			$hub_slug = QRMS_Admin::get_module_page_slug( $module );
			$hub_name = QRMS_Helpers::get_module_name( $module );

			if ( '' !== $page && QRMS_Admin::is_module_subpage( $page ) ) {
				return self::breadcrumb_join( array( $group, $hub_name, $title ) );
			}

			if ( $page === $hub_slug ) {
				return self::breadcrumb_join( array( $group, $hub_name ) );
			}

			return self::breadcrumb_join( array( $group, $hub_name ) );
		}

		return $group;
	}

	/**
	 * Restoran menü taxonomy ekranı bağlamı (hybrid).
	 *
	 * @return array{label:string,back_url:string,back_label:string}|null
	 */
	private static function get_restoran_taxonomy_screen_context() {
		global $pagenow;

		if ( ! self::is_native_hybrid_screen() ) {
			return null;
		}

		if ( 'edit-tags.php' !== $pagenow && 'term.php' !== $pagenow ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( ! in_array( $taxonomy, array( 'rma_category', 'rma_allergen', 'rma_ingredient' ), true ) ) {
			return null;
		}

		$tax_obj = get_taxonomy( $taxonomy );
		$label   = ( $tax_obj && isset( $tax_obj->labels->name ) ) ? (string) $tax_obj->labels->name : $taxonomy;

		return array(
			'label'       => $label,
			'back_url'    => QRMS_Admin::get_module_page_url( 'restoran-menu' ),
			'back_label'  => __( 'Menü Yönetimi\'ne Dön', 'qrms' ),
		);
	}

	/**
	 * Banner / slider CPT ekranı bağlamı (hybrid).
	 *
	 * @return array{title:string,breadcrumb_leaf:string,back_url:string,back_label:string}|null
	 */
	private static function get_banner_cpt_screen_context() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->post_type, array( 'qmo_slide', 'qmo_banner_slide' ), true ) ) {
			return null;
		}

		if ( 'qmo_slide' === $screen->post_type ) {
			return array(
				'title'           => __( 'Öne Çıkan Slider', 'qrms' ),
				'breadcrumb_leaf' => __( 'Öne Çıkanlar', 'qrms' ),
				'back_url'        => admin_url( 'admin.php?page=qrms-rm-one-cikanlar' ),
				'back_label'      => __( 'Öne Çıkanlar\'a Dön', 'qrms' ),
			);
		}

		return array(
			'title'           => __( 'Kampanya Banner', 'qrms' ),
			'breadcrumb_leaf' => __( 'Kampanya Görselleri', 'qrms' ),
			'back_url'        => admin_url( 'admin.php?page=qrms-rm-kampanya-banner' ),
			'back_label'      => __( 'Kampanya Görselleri\'ne Dön', 'qrms' ),
		);
	}

	/**
	 * Hybrid native ekranlarda eksik geri bağlantısı (taxonomy, banner CPT).
	 *
	 * @return void
	 */
	public static function render_native_hybrid_back_link() {
		if ( ! self::is_active() || ! self::is_native_hybrid_screen() ) {
			return;
		}

		if ( self::is_menu_item_list_screen() || self::is_menu_item_edit_screen() ) {
			return;
		}

		$page = self::get_request_page();
		if ( class_exists( 'QRMS_Admin' ) && '' !== $page && QRMS_Admin::is_module_subpage( $page ) ) {
			return;
		}

		$back = null;

		$taxonomy = self::get_restoran_taxonomy_screen_context();
		if ( null !== $taxonomy ) {
			$back = $taxonomy;
		} else {
			$banner = self::get_banner_cpt_screen_context();
			if ( null !== $banner ) {
				$back = array(
					'back_url'   => $banner['back_url'],
					'back_label' => $banner['back_label'],
				);
			}
		}

		if ( null === $back ) {
			return;
		}

		?>
		<div class="qrms-subpage-nav">
			<a class="qrms-back-link" href="<?php echo esc_url( $back['back_url'] ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
				<?php echo esc_html( $back['back_label'] ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function get_settings_nav_item() {
		return self::nav_link_item(
			QRMS_Admin::SETTINGS_SLUG,
			__( 'Ayarlar', 'qrms' ),
			QRMS_Admin::SETTINGS_SLUG === self::get_request_page()
		);
	}

	/**
	 * @param array<string,mixed> $row Nav row.
	 * @return void
	 */
	private static function render_sidebar_row( array $row ) {
		if ( isset( $row['type'] ) && 'group' === $row['type'] ) {
			echo '<li class="qrms-shell__nav-group" role="presentation">';
			echo '<span class="qrms-shell__nav-group-title">' . esc_html( isset( $row['title'] ) ? (string) $row['title'] : '' ) . '</span>';
			echo '</li>';
			return;
		}

		self::render_nav_item( $row );
	}

	/**
	 * @param array<string,mixed> $item Nav item.
	 * @return void
	 */
	private static function render_nav_item( array $item ) {
		$label    = isset( $item['label'] ) ? (string) $item['label'] : '';
		$current  = ! empty( $item['current'] );
		$url      = isset( $item['url'] ) ? (string) $item['url'] : '';
		$dashicon = isset( $item['dashicon'] ) ? (string) $item['dashicon'] : '';

		$classes = 'qrms-shell__nav-item';
		if ( $current ) {
			$classes .= ' is-current';
		}

		echo '<li class="' . esc_attr( $classes ) . '">';
		echo '<a class="qrms-shell__nav-link" href="' . esc_url( $url ) . '">';

		if ( '' !== $dashicon ) {
			echo '<span class="qrms-shell__nav-icon dashicons ' . esc_attr( $dashicon ) . '" aria-hidden="true"></span>';
		} else {
			echo '<span class="qrms-shell__nav-icon" aria-hidden="true"></span>';
		}

		echo '<span class="qrms-shell__nav-label">' . esc_html( $label ) . '</span>';
		echo '</a>';
		echo '</li>';
	}

	/**
	 * @param WP_User $user User object.
	 * @return string
	 */
	private static function user_initials( $user ) {
		if ( ! $user instanceof WP_User ) {
			return '?';
		}

		$name = trim( (string) $user->display_name );
		if ( '' === $name ) {
			return '?';
		}

		$parts = preg_split( '/\s+/u', $name );
		if ( ! is_array( $parts ) || empty( $parts ) ) {
			return mb_strtoupper( mb_substr( $name, 0, 1 ) );
		}

		$first = mb_substr( $parts[0], 0, 1 );
		$last  = count( $parts ) > 1 ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '';

		return mb_strtoupper( $first . $last );
	}
}
