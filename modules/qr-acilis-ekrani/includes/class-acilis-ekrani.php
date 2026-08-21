<?php
/**
 * Açılış Ekranı modülünün çekirdeği.
 *
 * Bağımsız "Açılış Ekranı (Splash Screen)" eklentisinden (v3.7) taşındı.
 * Frontend davranışı birebir korundu: çerez kararı client-side verilir, kritik
 * CSS `wp_head`'de basılır, giriş animasyonu tek seferliktir. Değişen tek şey
 * yönetim tarafıdır — kendi üst menüsü yerine suite'in hub + alt sayfa deseni.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/frontend.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/admin.php';

/**
 * Modülün tek sınıfı; işlevler trait'lere bölünmüştür.
 */
class QRMS_Acilis_Ekrani {

	use QRMS_AE_Helpers;
	use QRMS_AE_Icons;
	use QRMS_AE_Payments;
	use QRMS_AE_Frontend;
	use QRMS_AE_Settings;
	use QRMS_AE_Admin;

	/**
	 * Modül slug'ı (lisans sözleşmesindeki adla aynı).
	 */
	const MODULE = 'qr-acilis-ekrani';

	/**
	 * Ayarların saklandığı option.
	 *
	 * Bağımsız eklentiyle AYNI ad: mevcut kurulumların ayarları taşınma
	 * gerektirmeden okunmaya devam eder.
	 *
	 * @var string
	 */
	private $option_name = 'splash_screen_options';

	/**
	 * Varsayılan ayarlar.
	 *
	 * @var array
	 */
	private $defaults;

	/**
	 * Modülün tek örneği.
	 *
	 * @var QRMS_Acilis_Ekrani|null
	 */
	private static $instance = null;

	/**
	 * Örneği kurar ve hook'ları kaydeder.
	 *
	 * @return QRMS_Acilis_Ekrani
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_hooks();
		}

		return self::$instance;
	}

	/**
	 * Kurulu örnek (test ve dış çağrılar için).
	 *
	 * @return QRMS_Acilis_Ekrani|null
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Varsayılanları hazırlar.
	 */
	public function __construct() {
		$this->defaults = array(
			'logo'                => '',
			'bg_image'            => '',
			'bg_image_wide'       => '',
			'bg_overlay_strength' => 55,
			'bg_scheme'           => 'auto',
			'bg_color'            => '#f7f9fc',
			'button_bg_color'     => '#0073aa',
			'button_text_color'   => '#ffffff',
			// NOT: button_padding ve button_font_size v3.6'dan beri frontend'i
			// etkilemiyor — CTA'nın ölçüsü stylesheet'te sabittir. Anahtarlar
			// veri kaybı olmasın diye korunuyor, admin alanları da duruyor.
			'button_padding'      => '14px 28px',
			'button_font_size'    => '16px',
			'button_opacity'      => 22,
			// Rozet/cam yüzeylerin (4'lü grid, sosyal, ödeme satırı) dolgusu.
			// CSS'e --splash-btn-bg / --splash-btn-opacity olarak enjekte edilir.
			'btn_surface_color'   => '#ffffff',
			'btn_surface_opacity' => 14,
			'btn_surface_apply_cta' => 0,
			'divider_text'        => 'Bizi takip edin',
			'animation_type'      => 'anim-blur-up',
			'button_texts'        => array(
				'btn1' => 'Menüye Git',
				'btn2' => 'İletişim',
				'btn3' => 'Rezervasyon İste',
				'btn4' => 'Yorum Yap',
				'btn5' => 'Wifi Şifresi',
				'btn6' => 'Sosyal Medya',
			),
			// NOT: 'footer_icons' artık hiçbir yerde okunmuyor. Veri kaybı olmaması
			// için option anahtarı korunuyor; admin alanları kaldırıldı.
			'footer_icons'        => array( '', '', '', '' ),
			'payment_methods'     => array(),
			// Ödeme satırının render biçimi: icon_text | text_only | marquee
			'payment_display_mode'      => 'icon_text',
			'payment_marquee_with_icon' => 1,
			'payment_marquee_speed'     => 18,
			// Logo şeridi (tam genişlik üst banner)
			'logo_bar_height'     => 96,
			'logo_bar_color'      => '#000000',
			'logo_bar_opacity'    => 32,
			// Sağ üst yüklenme göstergesi. Konum sabittir (top-right).
			'loader_position'     => 'top-right',
			'loader_type'         => 'spinner',
			'loader_color'        => '#ffffff',
			'loader_size'         => 28,
			'redirect_url'        => '',
			'redirect_seconds'    => 7,
			'dismiss_duration'    => 10,
			'button_links'        => array(
				'btn1' => '',
				'btn2' => '',
				'btn3' => '',
				'btn4' => '',
			),
			'wifi_password'       => '',
			// v3.5 ve öncesinden kalan 3 sabit alanlı sosyal sistem. Anahtar
			// veri kaybı olmasın diye korunuyor; admin'de artık gösterilmiyor,
			// resolve_social_media_state() tarafından social_media_active
			// boşken otomatik olarak yeni sisteme taşınıyor.
			'social_links'        => array(
				'facebook'  => '',
				'instagram' => '',
				'twitter'   => '',
			),
			// v3.7: genişletilmiş sosyal medya sistemi (key => url, en fazla
			// 6 aktif platform). social_media_active render/kayıt sırasını taşır.
			'social_media'        => array(),
			'social_media_active' => array(),
		);
	}

	/**
	 * Frontend ve yönetim hook'ları.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// --- Ön yüz ---
		// Öncelik 2: FOUC'u önlemek için tema/eklenti çıktısından önce basılır.
		add_action( 'wp_head', array( $this, 'print_critical_head' ), 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'handle_frontend' ), 9999 );

		if ( ! is_admin() ) {
			return;
		}

		// --- Yönetim ---
		QRMS_Admin::register_module_page( self::MODULE, array( $this, 'render_hub' ) );

		// Öncelik 20: QRMS_Admin::register_menu() öncelik 10'da çalışır, yani
		// modülün satırı biz alt sayfaları eklerken $submenu'de hazırdır.
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ), 20 );
		add_action( 'admin_init', array( $this, 'maybe_redirect_legacy_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar' ), 100 );
	}
}
