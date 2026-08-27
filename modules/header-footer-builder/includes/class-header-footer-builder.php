<?php
/**
 * Header Footer Builder — ana sınıf.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/trait-settings-page.php';
require_once __DIR__ . '/trait-admin.php';
require_once __DIR__ . '/trait-frontend.php';
require_once __DIR__ . '/trait-live-preview.php';

/**
 * Header ve footer oluşturucu modülü.
 */
class QRMS_Header_Footer_Builder {

	use QRMS_HFB_Settings_Page;
	use QRMS_HFB_Admin;
	use QRMS_HFB_Frontend;
	use QRMS_HFB_Live_Preview;

	/**
	 * Modül slug'ı.
	 */
	const MODULE = 'header-footer-builder';

	/**
	 * Header ayar option anahtarı.
	 *
	 * @var string
	 */
	private $header_option = 'hfb_header_options';

	/**
	 * Footer ayar option anahtarı.
	 *
	 * @var string
	 */
	private $footer_option = 'hfb_footer_options';

	/**
	 * Header varsayılanları.
	 *
	 * @var array<string,mixed>
	 */
	private $header_defaults;

	/**
	 * Footer varsayılanları.
	 *
	 * @var array<string,mixed>
	 */
	private $footer_defaults;

	/**
	 * Tek örnek.
	 *
	 * @var QRMS_Header_Footer_Builder|null
	 */
	private static $instance = null;

	/**
	 * Modülü başlatır.
	 *
	 * @return QRMS_Header_Footer_Builder
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_hooks();
		}

		return self::$instance;
	}

	/**
	 * Kurulu örnek.
	 *
	 * @return QRMS_Header_Footer_Builder|null
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Varsayılanları hazırlar.
	 */
	public function __construct() {
		$this->header_defaults = array(
			'variant'                 => 'minimal-sticky',
			'logo'                    => 0,
			'logo_width'              => 140,
			'logo_text_size'          => 22,
			'logo_alignment'          => 'left',
			'menu_id'                 => 0,
			'bg_color'                => '#ffffff',
			'text_color'              => '#111827',
			'border_color'            => '#e5e7eb',
			'brand_color'             => '#e91e8c',
			'sticky'                  => 1,
			'mobile_panel_style'      => 'slide',
			'mobile_panel_bg'         => '#0f172a',
			'mobile_panel_bg_opacity' => 95,
			'mobile_panel_gradient_start' => '#e91e8c',
			'mobile_panel_gradient_end'   => '#6b21a8',
			'mobile_panel_font'       => 'Inter',
			'mobile_panel_text_color' => '#f8fafc',
			'mobile_panel_text_size'  => 18,
			'mobile_close_icon'       => 'x',
			'mobile_close_icon_color' => '#f8fafc',
			'mobile_close_icon_size'  => 24,
			'hamburger_color'         => '#111827',
			'lang_code'               => 'TR',
			'lang_url'                => '',
			'lang_alt_code'           => 'EN',
			'lang_alt_url'            => '',
			'lang_border_color'       => '#ffffff',
			'lang_text_color'         => '#ffffff',
			'cta_phone'               => '',
			'cta_bg_color'            => '#39d339',
			'cta_text_color'          => '#ffffff',
			'social_media'            => array(),
			'social_media_active'     => array(),
			'social_color'            => '#ffffff',
		);

		$this->footer_defaults = array(
			'variant'             => 'utility-minimal',
			'logo'                => 0,
			'description'         => '',
			'phone'               => '',
			'email'               => '',
			'copyright'           => '© ' . gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ),
			'menu_id'             => 0,
			'social_media'        => array(),
			'social_media_active' => array(),
		);
	}

	/**
	 * Hook kayıtları.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'hfb_header', array( $this, 'shortcode_header' ) );
		add_shortcode( 'hfb_footer', array( $this, 'shortcode_footer' ) );

		QRMS_Shortcodes::register(
			self::MODULE,
			array(
				array(
					'tag'   => 'hfb_header',
					'title' => __( 'Header', 'qrms' ),
					'desc'  => __( 'Yapılandırılmış header bileşenini sayfaya yerleştirir. Elementor Shortcode widget\'ında kullanın.', 'qrms' ),
				),
				array(
					'tag'   => 'hfb_footer',
					'title' => __( 'Footer', 'qrms' ),
					'desc'  => __( 'Yapılandırılmış footer bileşenini sayfaya yerleştirir. Elementor Shortcode widget\'ında kullanın.', 'qrms' ),
				),
			)
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );

		if ( ! is_admin() ) {
			return;
		}

		QRMS_Admin::register_module_page( self::MODULE, array( $this, 'render_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_hfb_preview', array( $this, 'ajax_preview' ) );
	}
}
