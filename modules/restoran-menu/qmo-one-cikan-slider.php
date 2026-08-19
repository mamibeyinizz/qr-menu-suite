<?php
/**
 * QMO Öne Çıkan Slider & Kombin Ürün
 *
 * rma_menu_item için kombin ürün meta kutusu ve qmo_slide tabanlı
 * öne çıkan ürünler slider'ı. QR MENÜ eklentisi tarafından yüklenir.
 *
 * @package QMO
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// qr-menu.php'deki RMA_* sabitleriyle aynı gerekçe: eski standalone QR MENÜ
// eklentisi hâlâ aktifse sabitleri o tanımlar, guard'sız define notice üretirdi.
if ( ! defined( 'QMO_PLUGIN_DIR' ) ) {
    define( 'QMO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'QMO_PLUGIN_URL' ) ) {
    define( 'QMO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once __DIR__ . '/includes/admin-kombin-meta.php';
require_once __DIR__ . '/includes/admin-cpt-slide.php';
require_once __DIR__ . '/includes/shortcode-slider.php';

/* =====================================================================
   MAIN CLASS
===================================================================== */
final class QMO_One_Cikan_Slider {

    private static $instance = null;
    private static $booted   = false;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ __CLASS__, 'boot' ], 20 );
    }

    public static function boot() {
        if ( self::$booted ) return;
        self::$booted = true;

        if ( ! post_type_exists( 'rma_menu_item' ) ) {
            add_action( 'admin_notices', [ __CLASS__, 'missing_dependency_notice' ] );
            return;
        }

        QMO_Kombin_Meta::init();
        QMO_Slide_CPT::init();
        QMO_Shortcode_Slider::init();

        add_action( 'updated_post_meta', [ __CLASS__, 'maybe_bump_rma_cache' ], 20, 4 );
        add_action( 'added_post_meta',   [ __CLASS__, 'maybe_bump_rma_cache' ], 20, 4 );
        add_action( 'save_post_qmo_slide', [ __CLASS__, 'bump_rma_cache_on_slide_save' ], 20 );
    }

    public static function missing_dependency_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) return;
        echo '<div class="notice notice-error"><p><strong>QMO Öne Çıkan Slider &amp; Kombin Ürün</strong> modülü <em>QR MENÜ</em> eklentisine bağımlıdır.</p></div>';
    }

    public static function bump_rma_cache_on_slide_save( $post_id ) {
        if ( wp_is_post_revision( $post_id ) ) return;
        self::bump_rma_cache();
    }

    public static function maybe_bump_rma_cache( $meta_id, $object_id, $meta_key, $meta_value ) {
        unset( $meta_value, $meta_id );
        if ( 0 !== strpos( (string) $meta_key, '_qmo_' ) ) return;

        $post_type = get_post_type( $object_id );
        if ( 'rma_menu_item' !== $post_type && 'qmo_slide' !== $post_type ) return;

        self::bump_rma_cache();
    }

    private static function bump_rma_cache() {
        if ( ! class_exists( 'Restaurant_Menu_Automation' ) ) return;
        $rma = Restaurant_Menu_Automation::get_instance();
        if ( method_exists( $rma, 'bump_cache_version' ) ) {
            $rma->bump_cache_version();
        }
    }
}

QMO_One_Cikan_Slider::get_instance();
