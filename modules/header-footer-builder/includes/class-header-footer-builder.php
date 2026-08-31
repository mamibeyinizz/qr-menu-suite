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
	 * Hamburger panelindeki logo genişlik alt sınırı (px).
	 *
	 * Header logosu bir marka bloğudur ve 80px'in altında okunmaz; panel
	 * logosu ise tek sütunlu bir listenin başında durur ve çok daha küçük
	 * kullanılmak istenir. Bu yüzden alt sınır yalnızca panel için
	 * gevşetilir — header/footer LOGO_WIDTH_MIN'i kullanmaya devam eder.
	 */
	const PANEL_LOGO_WIDTH_MIN = 50;

	/**
	 * Logo yükseklik aralığı (px). 0 = otomatik oran.
	 */
	const LOGO_HEIGHT_MIN = 24;
	const LOGO_HEIGHT_MAX = 200;

	/**
	 * Header içerik genişliği aralığı (px). 0 = tam genişlik (max-width: none).
	 */
	const CONTENT_WIDTH_MIN = 960;
	const CONTENT_WIDTH_MAX = 1600;

	/**
	 * Header iç boşluk aralıkları (px). Mobil ayrı ve daha dar tutulur.
	 */
	const PADDING_X_MIN        = 0;
	const PADDING_X_MAX        = 80;
	const PADDING_Y_MIN        = 0;
	const PADDING_Y_MAX        = 40;
	const PADDING_X_MOBILE_MAX = 32;
	const PADDING_Y_MOBILE_MAX = 32;

	/**
	 * Yazı boyutu aralıkları (px).
	 *
	 * MOBILE seti footer'ın masaüstü/mobil ayrımı içindir. Hamburger paneli
	 * yalnızca mobilde açıldığı için tek bir sete indirgenmiştir ve
	 * FONT_SIZE_MIN/MAX kullanır.
	 */
	const FONT_SIZE_MIN         = 12;
	const FONT_SIZE_MAX         = 32;
	const FONT_SIZE_MOBILE_MIN  = 12;
	const FONT_SIZE_MOBILE_MAX  = 28;

	/**
	 * Hamburger panel arka plan görselinin örtü opaklığı (%).
	 *
	 * 0 = görsel tamamen saydam (yalnızca zemin rengi görünür),
	 * 100 = görsel zemin rengini tümüyle kapatır.
	 */
	const PANEL_BG_OPACITY_MIN = 0;
	const PANEL_BG_OPACITY_MAX = 100;

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
			'content_width'            => 1200,
			'content_full_width'       => 0,
			'padding_x_desktop'        => 20,
			'padding_y_desktop'        => 12,
			'padding_x_mobile'         => 20,
			'padding_y_mobile'         => 12,
		);

		$this->footer_defaults = array(
			'logo'                       => 0,
			'brand_line1'                => 'QR MENU',
			'brand_line2'                => 'OFFİCİAL',
			'description'                => '',
			'phone'                      => '',
			'email'                      => '',
			'address'                    => '',
			'copyright'                  => '© ' . gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ),
			'menu_id'                    => 0,
			'social_media'               => array(),
			'social_media_active'        => array( 'facebook', 'x', 'youtube' ),
			'logo_width_desktop'         => 160,
			'logo_height_desktop'        => 0,
			'logo_height_auto_desktop'   => 1,
			'logo_width_mobile'          => 120,
			'logo_height_mobile'         => 0,
			'logo_height_auto_mobile'    => 1,
			'brand_align'                => 'left',
			'brand_font_family'          => 'Playfair Display',
			'brand_font_color'           => '#f5f0e8',
			'brand_font_weight'          => 400,
			'brand_font_size_desktop'    => 16,
			'brand_font_size_mobile'     => 14,
			// P0: kod sabiti hfb_cevir_option_varsayilan ile çevrilir.
			// Yönetici değiştirirse P1 — dokunulmaz.
			'links_title'                => 'Hızlı Menü',
			'links_align'                => 'left',
			'links_title_font_family'    => 'Playfair Display',
			'links_title_font_color'     => '#c9a84c',
			'links_title_font_weight'    => 600,
			'links_title_font_size_desktop' => 18,
			'links_title_font_size_mobile'  => 16,
			'links_item_font_family'     => 'Playfair Display',
			'links_item_font_color'      => '#f5f0e8',
			'links_item_font_weight'     => 400,
			'links_item_font_size_desktop' => 15,
			'links_item_font_size_mobile'  => 14,
			'links_item_hover_color'     => '#c9a84c',
			'hours_title'                => 'Çalışma Saatlerimiz',
			'hours_align'                => 'left',
			'hours_title_font_family'    => 'Playfair Display',
			'hours_title_font_color'     => '#c9a84c',
			'hours_title_font_weight'    => 600,
			'hours_title_font_size_desktop' => 18,
			'hours_title_font_size_mobile'  => 16,
			'hours_item_font_family'     => 'Playfair Display',
			'hours_item_font_color'      => '#f5f0e8',
			'hours_item_font_weight'     => 400,
			'hours_item_font_size_desktop' => 14,
			'hours_item_font_size_mobile'  => 13,
			'contact_title'              => 'İletişim',
			'contact_align'              => 'left',
			'contact_title_font_family'  => 'Playfair Display',
			'contact_title_font_color'   => '#c9a84c',
			'contact_title_font_weight'  => 600,
			'contact_title_font_size_desktop' => 18,
			'contact_title_font_size_mobile'  => 16,
			'contact_item_font_family'   => 'Playfair Display',
			'contact_item_font_color'    => '#f5f0e8',
			'contact_item_font_weight'   => 400,
			'contact_item_font_size_desktop' => 14,
			'contact_item_font_size_mobile'  => 13,
			'call_enabled'               => 0,
			'call_garson_label'          => 'Garson Çağır',
			'call_hesap_label'           => 'Hesap İste',
			'btn_bg_color'               => '#c9a84c',
			'btn_text_color'             => '#0a0a0c',
			'btn_shape'                  => 'pill',
			'btn_font_family'            => 'Playfair Display',
			'btn_font_size'              => 14,
			'btn_font_weight'            => 600,
		);

		$this->hamburger_defaults = array(
			'close_icon_color'     => '#c9a84c',
			'panel_bg_color'       => '#0a0a0c',
			'blocks'               => array(
				array(
					'id'      => 'blk_1',
					'type'    => 'logo',
					'enabled' => true,
					'align'   => 'center',
				),
				array(
					'id'      => 'blk_2',
					'type'    => 'menu',
					'enabled' => true,
					'align'   => 'center',
				),
				array(
					'id'      => 'blk_3',
					'type'    => 'social',
					'enabled' => true,
					'align'   => 'center',
				),
				array(
					'id'      => 'blk_4',
					'type'    => 'text',
					'enabled' => false,
					'align'   => 'center',
					'content' => '',
				),
			),
			'font_family'          => 'Playfair Display',
			'font_color'           => '#f5f0e8',

			/*
			 * Panel yalnızca mobilde açılır: header'da masaüstü hamburger
			 * ikonu yoktur. Bu yüzden yazı ve logo ölçüleri TEK settir —
			 * masaüstü/mobil ayrımı kaldırılmıştır (eski `*_desktop` /
			 * `*_mobile` kayıtları migrate_hamburger_breakpoints() ile
			 * buraya birleşir).
			 */
			'font_size'            => 16,
			'font_weight'          => 500,
			'font_align'           => 'center',

			/*
			 * Görünüm adımı. Varsayılanlar, adım eklenmeden ÖNCEKİ sabit
			 * görünümün birebir karşılığıdır: panel bu alanlar kaydedilmeden
			 * de bugünküyle aynı görünür.
			 *
			 * - Menü satırı/ok/ayraç ve sosyal çerçeve rengi eskiden
			 *   `--hfb-icon-color` (altın) üzerinden geliyordu.
			 * - Sosyal ikon zemini eskiden hiç boyanmıyordu; boş dize
			 *   "şeffaf" demektir ve CSS değişkeni hiç basılmaz.
			 * - Panel logosu eskiden header'ın mobil logo ölçüsünü
			 *   (120px + otomatik oran) kullanıyordu.
			 */
			'panel_bg_image'       => 0,
			'panel_bg_opacity'     => 100,
			'logo_width'           => 120,
			'logo_height'          => 0,
			'logo_height_auto'     => 1,
			'menu_link_color'      => '#f5f0e8',
			'menu_hover_color'     => '#c9a84c',
			'menu_divider_color'   => '#c9a84c',
			'menu_arrow_color'     => '#c9a84c',
			'social_border_color'  => '#c9a84c',
			'social_bg_color'      => '',
			'social_icon_color'    => '#c9a84c',
			'btn_bg_color'         => '#c9a84c',
			'btn_text_color'       => '#0a0a0c',
			'btn_shape'            => 'pill',
			'btn_font_family'      => 'Playfair Display',
			'btn_font_size'        => 15,
			'btn_font_weight'      => 600,
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
