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
		// Öncelik 20: QRMS_Admin::register_menu() öncelik 10'da çalışır, yani
		// "Fotoğraf Galerisi" satırı biz eklerken $submenu'de hazırdır.
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
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
		// CPT kaydı yalnızca init'te yapılır. Modül yüklemesi plugins_loaded
		// sırasında olduğu için $wp_rewrite henüz yok; rewrite flush'u da
		// init'e ertelenir.
		if ( isset( $GLOBALS['wp_rewrite'] ) && $GLOBALS['wp_rewrite'] instanceof WP_Rewrite ) {
			flush_rewrite_rules();
		} else {
			add_action( 'init', 'flush_rewrite_rules', 99 );
		}
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
			'title_font'       => 'Poppins',
			'title_size'       => 30,
			'title_color'      => '#0F172A',
			'title_weight'     => 800,
			'title_align'      => 'left',
			'title_transform'  => 'none',
			'divider_show'      => 1,
			'divider_align'     => 'left',
			'divider_color'     => '#D4AF37',
			'divider_width'     => 48,
			'divider_thickness' => 3,
			'divider_radius'    => 2,
			'desc_font'        => 'Poppins',
			'desc_size'        => 15,
			'desc_color'       => '#475569',
			'desc_weight'      => 400,
			'desc_align'       => 'left',
			'desc_max_width'   => 70,
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

	/**
	 * Modülün ekranları — TEK KAYNAK.
	 *
	 * Sayfa kaydı ve hub kartları aynı listeden beslenir; sıra kart sırasıdır.
	 *
	 * @return array<string,array{title:string,render:string,desc:string,icon:string}>
	 */
	public function admin_pages(): array {
		return [
			'qrmgm-sections' => [
				'title'  => 'Galeri Bölümleri',
				'render' => 'page_sections',
				'desc'   => 'Galerinizin bölümleri: ekleyin, sıralayın, yayından kaldırın.',
				'icon'   => 'dashicons-category',
			],
			'qrmgm-images'   => [
				'title'  => 'Tüm Görseller',
				'render' => 'page_images',
				'desc'   => 'Bölümlerdeki görselleri yükleyin, sıralayın ve silin.',
				'icon'   => 'dashicons-format-gallery',
			],
			'qrmgm-settings' => [
				'title'  => 'Galeri Ayarları',
				'render' => 'page_settings',
				'desc'   => 'Grid düzeni, renkler, lightbox ve WebP dönüşümü.',
				'icon'   => 'dashicons-admin-settings',
			],
		];
	}

	/**
	 * Modülün ekranlarını kaydeder — hepsi sol menüde GİZLİDİR.
	 *
	 * Sol menüde yalnızca "Fotoğraf Galerisi" satırı durur ve üç ekranı kart olarak
	 * listeleyen hub ekranını (page_hub) açar. Ekranlar gerçek, ayrı WordPress
	 * sayfaları olarak kaydolur (bkz. QRMS_Admin::hide_module_subpages).
	 *
	 * NOT: "Galeri Bölümleri" v1.0'da modülün kendi satırındaydı
	 * (qrms-module-qr-galeri). Sol menü tek seviyeye indirilince o slug hub
	 * ekranı oldu; bölümler `qrmgm-sections` slug'ına taşındı. Eski adres
	 * kırılmaz — hub'ı açar, bölümler oradan bir kart uzaktadır.
	 */
	public function register_admin_menu(): void {
		global $submenu;

		// Modül lisansta aktif değilse "Fotoğraf Galerisi" satırı hiç kaydolmaz; o zaman
		// ekranlarının da kaydedilmemesi gerekir.
		if ( empty( $submenu[ QRMS_Admin::MENU_SLUG ] ) ) {
			return;
		}

		foreach ( $this->admin_pages() as $slug => $page ) {
			add_submenu_page(
				QRMS_Admin::MENU_SLUG,
				QRMS_Helpers::get_module_name( 'qr-galeri' ) . ' — ' . $page['title'],
				$page['title'],
				self::CAP,
				$slug,
				QRMS_Admin::register_module_subpage( 'qr-galeri', $slug, [ $this, $page['render'] ] )
			);
		}
	}

	/**
	 * "Fotoğraf Galerisi" satırının açtığı hub ekranı.
	 */
	public function page_hub(): void {
		$cards = [];

		foreach ( $this->admin_pages() as $slug => $page ) {
			$cards[] = [
				'url'   => admin_url( 'admin.php?page=' . $slug ),
				'title' => $page['title'],
				'desc'  => $page['desc'],
				'icon'  => $page['icon'],
			];
		}

		QRMS_Admin::render_hub( [
			// Modülün görünen adı tek yerdedir (QRMS_Helpers::get_modules);
			// hub başlığı sol menüdeki satırla ve "geri" bağlantısıyla aynı
			// kelimeyi kullansın.
			'title' => QRMS_Helpers::get_module_name( 'qr-galeri' ),
			'intro' => 'Bölümleriniz, görselleriniz ve galerinin görünüm ayarları burada.',
			'cards' => $cards,
		] );
	}

	private function current_admin_page(): string {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	public function admin_assets( string $hook ): void {
		// Hub ekranının stili suite'in ortak admin.css'inden gelir; medya
		// kitaplığı, sürükle-bırak ve renk seçici yalnızca üç yönetim
		// ekranında gerekir.
		$page = $this->current_admin_page();
		if ( ! array_key_exists( $page, $this->admin_pages() ) ) {
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
