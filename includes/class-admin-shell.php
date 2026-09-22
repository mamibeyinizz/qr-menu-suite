<?php
/**
 * Premium admin shell — presentation layer for QRMS screens (Phase 1: overview only).
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'QRMS_PREMIUM_SHELL' ) ) {
	define( 'QRMS_PREMIUM_SHELL', true );
}

/**
 * Wraps selected QRMS admin pages in the premium SaaS chrome.
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
		add_action( 'admin_footer', array( __CLASS__, 'render_close' ), 99999 );
	}

	/**
	 * Feature flag: master switch (scope is still overview-only in this phase).
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
	 * Shell active on this request (Phase 1: Genel Bakış only).
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( ! is_admin() || ! current_user_can( QRMS_Admin::CAPABILITY ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		$active = ( QRMS_Admin::MENU_SLUG === $page );

		/**
		 * Whether the premium shell should wrap the current admin screen.
		 *
		 * @param bool   $active  Default overview-only in Phase 1.
		 * @param string $page    Current `page` query arg.
		 */
		return (bool) apply_filters( 'qrms_premium_shell_active', $active, $page );
	}

	/**
	 * Visually hides core wp-admin chrome on shell screens (inline — keeps repo CSS scan clean).
	 *
	 * @return string
	 */
	private static function chrome_hide_css() {
		return 'body.qrms-premium-shell-active #adminmenuback,'
			. 'body.qrms-premium-shell-active #adminmenuwrap,'
			. 'body.qrms-premium-shell-active #wpadminbar{'
			. 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;'
			. 'clip:rect(0,0,0,0);white-space:nowrap;border:0;}'
			. 'body.qrms-premium-shell-active #wpcontent,'
			. 'body.qrms-premium-shell-active #wpbody,'
			. 'body.qrms-premium-shell-active #wpbody-content{margin:0;padding:0;}'
			. 'body.qrms-premium-shell-active #wpcontent{margin-left:0!important;}'
			. 'body.qrms-premium-shell-active #wpbody-content{padding-bottom:0;}'
			. 'body.qrms-premium-shell-active #wpfooter,'
			. 'body.qrms-premium-shell-active #screen-meta,'
			. 'body.qrms-premium-shell-active #screen-meta-links{display:none;}';
	}

	/**
	 * @param string $classes Space-separated admin body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( ! self::is_active() ) {
			return $classes;
		}

		return trim( $classes . ' qrms-premium-shell qrms-premium-shell-active' );
	}

	/**
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! self::is_active() ) {
			return;
		}

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
	 * Opens shell markup (content area stays open until admin_footer).
	 *
	 * @return void
	 */
	public static function render_open() {
		if ( ! self::is_active() ) {
			return;
		}

		$user = wp_get_current_user();
		$nav  = self::get_primary_nav();
		?>
		<div class="qrms-shell" id="qrms-shell">
			<div class="qrms-shell__backdrop" id="qrms-shell-backdrop" hidden aria-hidden="true"></div>
			<aside class="qrms-shell__sidebar" id="qrms-shell-sidebar" aria-label="<?php esc_attr_e( 'QR Menü ana gezinme', 'qrms' ); ?>">
				<div class="qrms-shell__brand">
					<span class="qrms-shell__brand-mark" aria-hidden="true"></span>
					<span class="qrms-shell__brand-text">
						<span class="qrms-shell__brand-name"><?php esc_html_e( 'QR MENU', 'qrms' ); ?></span>
						<span class="qrms-shell__brand-sub"><?php esc_html_e( 'OFFICIAL', 'qrms' ); ?></span>
					</span>
				</div>
				<nav class="qrms-shell__nav" aria-label="<?php esc_attr_e( 'Panel', 'qrms' ); ?>">
					<ul class="qrms-shell__nav-list">
						<?php foreach ( $nav as $item ) : ?>
							<?php self::render_nav_item( $item ); ?>
						<?php endforeach; ?>
					</ul>
				</nav>
				<div class="qrms-shell__sidebar-foot">
					<?php self::render_nav_item( self::get_settings_nav_item() ); ?>
					<div class="qrms-shell__account">
						<span class="qrms-shell__account-avatar" aria-hidden="true"><?php echo esc_html( self::user_initials( $user ) ); ?></span>
						<span class="qrms-shell__account-meta">
							<span class="qrms-shell__account-name"><?php echo esc_html( $user->display_name ); ?></span>
							<span class="qrms-shell__account-role"><?php esc_html_e( 'Yönetici', 'qrms' ); ?></span>
						</span>
					</div>
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
							<p class="qrms-shell__breadcrumb"><?php esc_html_e( 'Genel Bakış', 'qrms' ); ?></p>
							<h1 class="qrms-shell__title"><?php esc_html_e( 'QR Menü Kontrol Merkezi', 'qrms' ); ?></h1>
						</div>
					</div>
					<div class="qrms-shell__header-end">
						<div class="qrms-shell__header-account">
							<span class="qrms-shell__account-avatar qrms-shell__account-avatar--sm" aria-hidden="true"><?php echo esc_html( self::user_initials( $user ) ); ?></span>
							<span class="qrms-shell__account-name"><?php echo esc_html( $user->display_name ); ?></span>
						</div>
					</div>
				</header>
				<div class="qrms-shell__content" id="qrms-shell-content">
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
	 * @param string $key openMenu|closeMenu.
	 * @return string
	 */
	private static function l10n( $key ) {
		$map = array(
			'openMenu'  => __( 'Menüyü aç', 'qrms' ),
			'closeMenu' => __( 'Menüyü kapat', 'qrms' ),
		);

		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * Primary sidebar items (Phase 1: real URLs where modules exist).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_primary_nav() {
		$active_modules = QRMS_License_Client::get_active_modules();
		$is_mod         = static function ( $slug ) use ( $active_modules ) {
			return in_array( $slug, $active_modules, true );
		};

		$items = array(
			array(
				'label'   => __( 'Genel Bakış', 'qrms' ),
				'url'     => admin_url( 'admin.php?page=' . QRMS_Admin::MENU_SLUG ),
				'current' => true,
				'icon'    => 'overview',
			),
			array(
				'label'    => __( 'Menü', 'qrms' ),
				'url'      => $is_mod( 'restoran-menu' ) ? admin_url( 'admin.php?page=' . QRMS_Admin::get_module_page_slug( 'restoran-menu' ) ) : '',
				'disabled' => ! $is_mod( 'restoran-menu' ),
				'icon'     => 'menu',
			),
			array(
				'label'    => __( 'Masalar', 'qrms' ),
				'url'      => $is_mod( 'qr-masa' ) ? admin_url( 'admin.php?page=' . QRMS_Admin::get_module_page_slug( 'qr-masa' ) ) : '',
				'disabled' => ! $is_mod( 'qr-masa' ),
				'icon'     => 'tables',
			),
			array(
				'label'    => __( 'Siparişler', 'qrms' ),
				'url'      => '',
				'disabled' => true,
				'note'     => __( 'Yakında', 'qrms' ),
				'icon'     => 'orders',
			),
			array(
				'label'    => __( 'Servis', 'qrms' ),
				'url'      => $is_mod( 'qr-servis-paneli' ) ? admin_url( 'admin.php?page=' . QRMS_Admin::get_module_page_slug( 'qr-servis-paneli' ) ) : '',
				'disabled' => ! $is_mod( 'qr-servis-paneli' ),
				'icon'     => 'service',
			),
			array(
				'label'    => __( 'Müşteriler', 'qrms' ),
				'url'      => $is_mod( 'yorum-feedback' ) ? admin_url( 'admin.php?page=' . QRMS_Admin::get_module_page_slug( 'yorum-feedback' ) ) : '',
				'disabled' => ! $is_mod( 'yorum-feedback' ),
				'icon'     => 'customers',
			),
			array(
				'label'    => __( 'AI', 'qrms' ),
				'url'      => $is_mod( 'qr-chatbot' ) ? admin_url( 'admin.php?page=' . QRMS_Admin::get_module_page_slug( 'qr-chatbot' ) ) : '',
				'disabled' => ! $is_mod( 'qr-chatbot' ),
				'icon'     => 'ai',
			),
			array(
				'label'    => __( 'Analitik', 'qrms' ),
				'url'      => $is_mod( 'qr-analiz' ) ? admin_url( 'admin.php?page=' . QRMS_Admin::get_module_page_slug( 'qr-analiz' ) ) : '',
				'disabled' => ! $is_mod( 'qr-analiz' ),
				'icon'     => 'analytics',
			),
		);

		/**
		 * Premium shell primary navigation items.
		 *
		 * @param array $items Nav item definitions.
		 */
		return apply_filters( 'qrms_premium_shell_nav', $items );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function get_settings_nav_item() {
		return array(
			'label'   => __( 'Ayarlar', 'qrms' ),
			'url'     => admin_url( 'admin.php?page=' . QRMS_Admin::SETTINGS_SLUG ),
			'icon'    => 'settings',
			'current' => false,
		);
	}

	/**
	 * @param array<string,mixed> $item Nav item.
	 * @return void
	 */
	private static function render_nav_item( array $item ) {
		$label    = isset( $item['label'] ) ? (string) $item['label'] : '';
		$icon     = isset( $item['icon'] ) ? (string) $item['icon'] : '';
		$current  = ! empty( $item['current'] );
		$disabled = ! empty( $item['disabled'] ) || empty( $item['url'] );
		$note     = isset( $item['note'] ) ? (string) $item['note'] : '';

		$classes = 'qrms-shell__nav-item';
		if ( $current ) {
			$classes .= ' is-current';
		}
		if ( $disabled ) {
			$classes .= ' is-disabled';
		}

		echo '<li class="' . esc_attr( $classes ) . '">';

		if ( $disabled ) {
			echo '<span class="qrms-shell__nav-link">';
		} else {
			echo '<a class="qrms-shell__nav-link" href="' . esc_url( $item['url'] ) . '">';
		}

		echo '<span class="qrms-shell__nav-icon qrms-shell__nav-icon--' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
		echo '<span class="qrms-shell__nav-label">' . esc_html( $label ) . '</span>';

		if ( '' !== $note ) {
			echo '<span class="qrms-shell__nav-note">' . esc_html( $note ) . '</span>';
		}

		if ( $disabled ) {
			echo '</span>';
		} else {
			echo '</a>';
		}

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
