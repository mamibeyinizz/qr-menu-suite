<?php
/**
 * QR Menu Gallery Manager — ana sınıf.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/trait-admin-pages.php';
require_once __DIR__ . '/includes/trait-ajax.php';
require_once __DIR__ . '/includes/trait-frontend.php';
require_once __DIR__ . '/includes/trait-assets.php';

final class QRMenu_Gallery_Manager {

	use QRMGM_Admin_Pages_Trait;
	use QRMGM_Ajax_Trait;
	use QRMGM_Frontend_Trait;
	use QRMGM_Assets_Trait;

	private static ?self $instance = null;

	const CPT_SECTION     = 'qrmgm_section';
	const CPT_IMAGE       = 'qrmgm_image';
	const OPTION_SETTINGS = 'qrmgm_settings';
	const NONCE           = 'qrmgm_nonce';
	const CAP             = 'manage_options';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( QRMGM_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( QRMGM_FILE, [ $this, 'deactivate' ] );

		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_frontend_assets' ] );
		add_shortcode( 'qrmenu_gallery', [ $this, 'render_shortcode' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( QRMGM_FILE ), [ $this, 'settings_link' ] );

		$ajax = [
			'save_section', 'delete_section', 'reorder_sections', 'toggle_section_status',
			'upload_image', 'save_image', 'delete_image', 'reorder_images',
			'toggle_image_status', 'toggle_image_featured', 'duplicate_image',
			'save_settings', 'get_section_images',
		];
		foreach ( $ajax as $action ) {
			add_action( 'wp_ajax_qrmgm_' . $action, [ $this, 'ajax_' . $action ] );
		}
	}

	public function activate(): void {
		if ( false === get_option( self::OPTION_SETTINGS ) ) {
			update_option( self::OPTION_SETTINGS, $this->default_settings() );
		}
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}

	public function default_settings(): array {
		return [
			'radius'           => 16,
			'shadow'           => 'medium',
			'gap'              => 18,
			'columns_desktop'  => 4,
			'columns_tablet'   => 3,
			'columns_mobile'   => 2,
			'hover_effect'     => 'glass',
			'animations'       => 1,
			'lightbox'         => 1,
			'filter_bar'       => 1,
			'lazy_load'        => 1,
			'webp'             => 1,
			'color_dark'       => '#0F172A',
			'color_gold'       => '#D4AF37',
			'color_light'      => '#F8FAFC',
			'color_white'      => '#FFFFFF',
			'font'             => 'Poppins',
			'overlay_opacity'  => 55,
		];
	}

	public function get_settings(): array {
		$saved = get_option( self::OPTION_SETTINGS, [] );
		return wp_parse_args( is_array( $saved ) ? $saved : [], $this->default_settings() );
	}

	public function settings_link( array $links ): array {
		$url            = admin_url( 'admin.php?page=qrmgm-settings' );
		$links[]        = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Ayarlar', 'qrmenu-gallery-manager' ) . '</a>';
		return $links;
	}

	public function register_post_types(): void {
		register_post_type( self::CPT_SECTION, [
			'label'           => 'QR Menu Galeri Bölümleri',
			'public'          => false,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'capability_type' => 'page',
			'capabilities'    => [ 'edit_posts' => self::CAP, 'edit_others_posts' => self::CAP, 'publish_posts' => self::CAP, 'read_private_posts' => self::CAP, 'delete_posts' => self::CAP ],
			'map_meta_cap'    => true,
			'supports'        => [ 'title' ],
			'hierarchical'    => false,
		] );

		register_post_type( self::CPT_IMAGE, [
			'label'           => 'QR Menu Galeri Görselleri',
			'public'          => false,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'capability_type' => 'page',
			'capabilities'    => [ 'edit_posts' => self::CAP, 'edit_others_posts' => self::CAP, 'publish_posts' => self::CAP, 'read_private_posts' => self::CAP, 'delete_posts' => self::CAP ],
			'map_meta_cap'    => true,
			'supports'        => [ 'title' ],
			'hierarchical'    => false,
		] );
	}

	public function register_admin_menu(): void {
		add_submenu_page(
			QRMS_Admin::MENU_SLUG,
			'QR Galeri — Tüm Görseller',
			'— Galeri Görselleri',
			self::CAP,
			'qrmgm-images',
			[ $this, 'page_images' ]
		);
		add_submenu_page(
			QRMS_Admin::MENU_SLUG,
			'QR Galeri — Ayarlar',
			'— Galeri Ayarları',
			self::CAP,
			'qrmgm-settings',
			[ $this, 'page_settings' ]
		);
	}

	private function current_admin_page(): string {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	public function admin_assets( string $hook ): void {
		$page        = $this->current_admin_page();
		$module_page = QRMS_Admin::get_module_page_slug( 'qr-galeri' );
		if ( ! in_array( $page, [ $module_page, 'qrmgm-images', 'qrmgm-settings' ], true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_register_style( 'qrmgm-admin', false );
		wp_enqueue_style( 'qrmgm-admin' );
		wp_add_inline_style( 'qrmgm-admin', $this->admin_css() );

		wp_register_script( 'qrmgm-admin', false, [ 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ], QRMGM_VERSION, true );
		wp_enqueue_script( 'qrmgm-admin' );
		wp_localize_script( 'qrmgm-admin', 'QRMGM', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE ),
			'i18n'    => [
				'confirmDelete' => 'Bu kaydı silmek istediğinize emin misiniz?',
				'saved'         => 'Kaydedildi.',
				'error'         => 'Bir hata oluştu.',
			],
		] );
		wp_add_inline_script( 'qrmgm-admin', $this->admin_js() );
	}

}
