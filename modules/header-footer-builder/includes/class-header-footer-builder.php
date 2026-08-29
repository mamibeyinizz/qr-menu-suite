<?php
/**
 * Header Footer Builder — ana sınıf.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/trait-settings-page.php';
require_once __DIR__ . '/trait-elementor.php';
require_once __DIR__ . '/trait-admin.php';
require_once __DIR__ . '/trait-frontend.php';
require_once __DIR__ . '/trait-live-preview.php';

/**
 * Header ve footer modülü.
 *
 * Varsayılan palet projenin dark-gold kimliğidir (siyah #0a0a0c + gold
 * #c9a84c); kullanıcı artık header görünümünü, logo boyutunu, ikon
 * renklerini ve hamburger panelini ayar sayfasından değiştirir.
 */
class QRMS_Header_Footer_Builder {

	use QRMS_HFB_Settings_Page;
	use QRMS_HFB_Elementor;
	use QRMS_HFB_Admin;
	use QRMS_HFB_Frontend;
	use QRMS_HFB_Live_Preview;

	/**
	 * Modül slug'ı.
	 */
	const MODULE = 'header-footer-builder';

	/**
	 * Çeviri modülünün dil seçici kısa kodu.
	 *
	 * Gevşek bağ: modül kapalıysa kısa kod kayıtlı olmaz, bayrak sessizce
	 * çıkmaz — hata üretilmez.
	 */
	const LANG_SHORTCODE = 'qrmenu_flags_only';

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
	 * Hamburger panel ayar option anahtarı.
	 *
	 * @var string
	 */
	private $hamburger_option = 'hfb_hamburger_options';

	/**
	 * Logo genişlik aralığı (px).
	 */
	const LOGO_WIDTH_MIN = 80;
	const LOGO_WIDTH_MAX = 320;

	/**
	 * Logo yükseklik aralığı (px). 0 = otomatik oran.
	 */
	const LOGO_HEIGHT_MIN = 24;
	const LOGO_HEIGHT_MAX = 200;

	/**
	 * Hamburger panel yazı boyutu aralığı (px).
	 */
	const FONT_SIZE_MIN         = 12;
	const FONT_SIZE_MAX         = 32;
	const FONT_SIZE_MOBILE_MIN  = 12;
	const FONT_SIZE_MOBILE_MAX  = 28;

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
	 * Hamburger panel varsayılanları.
	 *
	 * @var array<string,mixed>
	 */
	private $hamburger_defaults;

	/**
	 * Bu istekte hangi bölümler render edildi.
	 *
	 * Aynı sayfada kısa kod iki kez bulunursa (ör. hem Elementor şablonunda
	 * hem içerikte) ikinci çıktı basılmaz: mobil panel `id` çakışması ve
	 * çift sticky header oluşmasın.
	 *
	 * @var array<string,bool>
	 */
	private $rendered = array(
		'header' => false,
		'footer' => false,
	);

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
			'logo'                     => 0,
			'brand_line1'              => 'QR MENU',
			'brand_line2'              => 'OFFİCİAL',
			'menu_id'                  => 0,
			'sticky'                   => 1,
			'sticky_blur'              => 0,
			'cta_phone'                => '',
			'lang_show'                => 1,
			'social_media'             => array(),
			'social_media_active'      => array( 'facebook', 'x', 'youtube' ),
			'logo_width_desktop'       => 160,
			'logo_height_desktop'      => 0,
			'logo_height_auto_desktop' => 1,
			'logo_width_tablet'        => 140,
			'logo_height_tablet'       => 0,
			'logo_height_auto_tablet'  => 1,
			'logo_width_mobile'        => 120,
			'logo_height_mobile'       => 0,
			'logo_height_auto_mobile'  => 1,
			'bg_color'                 => '#0a0a0c',
			'icon_color'               => '#c9a84c',
			'hamburger_icon_color'     => '#c9a84c',
		);

		$this->footer_defaults = array(
			'logo'                => 0,
			'brand_line1'         => 'QR MENU',
			'brand_line2'         => 'OFFİCİAL',
			'description'         => '',
			'phone'               => '',
			'email'               => '',
			'copyright'           => '© ' . gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ),
			'menu_id'             => 0,
			'social_media'        => array(),
			'social_media_active' => array( 'facebook', 'x', 'youtube' ),
		);

		$this->hamburger_defaults = array(
			'close_icon_color'     => '#c9a84c',
			'panel_bg_color'       => '#0a0a0c',
			'block_order'          => array( 'logo', 'menu', 'social', 'text' ),
			'block_logo'           => 1,
			'block_menu'           => 1,
			'block_social'         => 1,
			'block_text'           => 0,
			'text'                 => '',
			'font_family'          => 'Playfair Display',
			'font_color'           => '#f5f0e8',
			'font_size_desktop'    => 17,
			'font_weight_desktop'  => 500,
			'font_align_desktop'   => 'center',
			'font_size_mobile'     => 16,
			'font_weight_mobile'   => 500,
			'font_align_mobile'    => 'center',
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
