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
		// Attachment medya kitaplığından doğrudan silinse bile (galerinin
		// kendi uçları dışında) modülün ürettiği .webp dosyası orphan kalmasın.
		add_action( 'delete_attachment', [ $this, 'cleanup_webp_for_attachment' ] );

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
			'columns_desktop'  => 3,
			'columns_tablet'   => 2,
			'columns_mobile'   => 1,
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
		] + $this->nav_preset_values( 'champagne' ) + [
			'nav_preset'          => 'champagne',
			'nav_sticky'          => 1,
			'nav_sticky_offset'   => 0,
		];
	}

	/**
	 * Kategori navigasyonu hazır renk temaları.
	 *
	 * Her tema, ayar anahtarlarının tam setini verir; kullanıcı tema seçtiğinde
	 * bu değerler renk alanlarına yazılır ve oradan tek tek override edilebilir.
	 *
	 * @return array<string,array{label:string,colors:array<string,mixed>}>
	 */
	public function nav_presets(): array {
		return [
			'champagne' => [
				'label'  => 'Champagne Gold',
				'colors' => [
					'nav_bg'                   => '#0C0B09',
					'nav_bg_opacity'           => 55,
					'nav_border'               => '#D4AF37',
					'nav_border_opacity'       => 22,
					'nav_hover_border'         => '#D4AF37',
					'nav_hover_border_opacity' => 65,
					'nav_active_bg'            => '#D4AF37',
					'nav_active_text'          => '#14110A',
					'nav_text'                 => '#D9D2C5',
					'nav_hover_text'           => '#EBCE86',
					'nav_indicator'            => '#D4AF37',
				],
			],
			'emerald' => [
				'label'  => 'Emerald Noir',
				'colors' => [
					'nav_bg'                   => '#06120E',
					'nav_bg_opacity'           => 58,
					'nav_border'               => '#C9A227',
					'nav_border_opacity'       => 20,
					'nav_hover_border'         => '#C9A227',
					'nav_hover_border_opacity' => 60,
					'nav_active_bg'            => '#0E5C43',
					'nav_active_text'          => '#F3EAD0',
					'nav_text'                 => '#CFE0D6',
					'nav_hover_text'           => '#E8D9A5',
					'nav_indicator'            => '#C9A227',
				],
			],
			'burgundy' => [
				'label'  => 'Burgundy Luxe',
				'colors' => [
					'nav_bg'                   => '#170A0E',
					'nav_bg_opacity'           => 58,
					'nav_border'               => '#C08457',
					'nav_border_opacity'       => 22,
					'nav_hover_border'         => '#C08457',
					'nav_hover_border_opacity' => 62,
					'nav_active_bg'            => '#6E1B2E',
					'nav_active_text'          => '#F7E9DD',
					'nav_text'                 => '#E3CFCF',
					'nav_hover_text'           => '#E0A96D',
					'nav_indicator'            => '#C08457',
				],
			],
			'ivory' => [
				'label'  => 'Ivory & Gold',
				'colors' => [
					'nav_bg'                   => '#FBF8F1',
					'nav_bg_opacity'           => 88,
					'nav_border'               => '#B08D3F',
					'nav_border_opacity'       => 26,
					'nav_hover_border'         => '#B08D3F',
					'nav_hover_border_opacity' => 70,
					'nav_active_bg'            => '#1C1917',
					'nav_active_text'          => '#F5E9CE',
					'nav_text'                 => '#5A5147',
					'nav_hover_text'           => '#8A6B24',
					'nav_indicator'            => '#B08D3F',
				],
			],
			'midnight' => [
				'label'  => 'Midnight Gold',
				'colors' => [
					'nav_bg'                   => '#070A12',
					'nav_bg_opacity'           => 60,
					'nav_border'               => '#C5A14E',
					'nav_border_opacity'       => 20,
					'nav_hover_border'         => '#C5A14E',
					'nav_hover_border_opacity' => 60,
					'nav_active_bg'            => '#C5A14E',
					'nav_active_text'          => '#0A0F1A',
					'nav_text'                 => '#C8CEDA',
					'nav_hover_text'           => '#E3C77D',
					'nav_indicator'            => '#C5A14E',
				],
			],
		];
	}

	/**
	 * Bir hazır temanın renk değerleri.
	 *
	 * @param string $key Tema anahtarı.
	 * @return array<string,mixed>
	 */
	public function nav_preset_values( string $key ): array {
		$presets = $this->nav_presets();
		$preset  = $presets[ $key ] ?? $presets['champagne'];
		return $preset['colors'];
	}

	/**
	 * Hex + opaklık -> rgba(); CSS değişkenleri tek yerden üretilsin.
	 */
	public function nav_rgba( string $hex, int $opacity ): string {
		$hex = ltrim( (string) sanitize_hex_color( $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			$hex = '000000';
		}
		$alpha = max( 0, min( 100, $opacity ) ) / 100;
		return sprintf(
			'rgba(%d,%d,%d,%s)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) ?: '0'
		);
	}

	/**
	 * Kategori navigasyonunun CSS değişkenleri — ön yüz ve admin önizlemesi
	 * AYNI kaynaktan beslenir.
	 *
	 * @param array $s Ayarlar.
	 * @return array<string,string>
	 */
	public function nav_css_vars( array $s ): array {
		return [
			'--qrmgm-nav-bg'           => $this->nav_rgba( (string) $s['nav_bg'], (int) $s['nav_bg_opacity'] ),
			'--qrmgm-nav-border'       => $this->nav_rgba( (string) $s['nav_border'], (int) $s['nav_border_opacity'] ),
			'--qrmgm-nav-hover-border' => $this->nav_rgba( (string) $s['nav_hover_border'], (int) $s['nav_hover_border_opacity'] ),
			'--qrmgm-nav-active-bg'    => (string) $s['nav_active_bg'],
			'--qrmgm-nav-active-text'  => (string) $s['nav_active_text'],
			'--qrmgm-nav-text'         => (string) $s['nav_text'],
			'--qrmgm-nav-hover-text'   => (string) $s['nav_hover_text'],
			'--qrmgm-nav-indicator'    => (string) $s['nav_indicator'],
			'--qrmgm-nav-offset'       => max( 0, min( 240, (int) $s['nav_sticky_offset'] ) ) . 'px',
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
				QRMS_Admin::register_module_subpage(
					'qr-galeri',
					$slug,
					[ $this, $page['render'] ],
					[
						'title' => $page['title'],
						'icon'  => $page['icon'],
					]
				)
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
