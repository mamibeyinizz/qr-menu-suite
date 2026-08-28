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
 * Tasarım tektir ve koda sabitlenmiştir (siyah #0a0a0c + gold #c9a84c);
 * kullanıcı yalnızca içeriği ve birkaç davranış anahtarını yönetir.
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
			'logo'                => 0,
			'brand_line1'         => 'QR MENU',
			'brand_line2'         => 'OFFİCİAL',
			'menu_id'             => 0,
			'sticky'              => 1,
			'cta_phone'           => '',
			'lang_show'           => 1,
			'social_media'        => array(),
			'social_media_active' => array( 'facebook', 'x', 'youtube' ),
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
