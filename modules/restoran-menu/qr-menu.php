<?php
/**
 * Plugin Name: QR MENÜ
 * Description: Premium Elementor uyumlu, AJAX destekli restoran menü sistemi.

 * Version: 1.0
 * Author: QRmenuofficial
 * Text Domain: rma
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Suite modülü olarak da, eski tekil eklenti olarak da yüklenebilsin diye
// guard'lı: eski standalone QR MENÜ eklentisi hâlâ aktifse sabitleri o
// tanımlar ve buradaki define'lar "already defined" notice'ı üretirdi.
if ( ! defined( 'RMA_PLUGIN_DIR' ) ) {
    define( 'RMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RMA_PLUGIN_URL' ) ) {
    define( 'RMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once __DIR__ . '/includes/trait-helpers.php';
require_once __DIR__ . '/includes/trait-post-types.php';
require_once __DIR__ . '/includes/trait-admin-columns.php';
require_once __DIR__ . '/includes/trait-admin-pages.php';
require_once __DIR__ . '/includes/trait-nav-design.php';
require_once __DIR__ . '/includes/trait-import-export.php';
require_once __DIR__ . '/includes/trait-suggestions.php';
require_once __DIR__ . '/includes/trait-frontend.php';
require_once __DIR__ . '/includes/trait-ajax.php';
require_once __DIR__ . '/includes/trait-category-fields.php';
require_once __DIR__ . '/includes/class-vitrin-db.php';
require_once __DIR__ . '/includes/trait-vitrin-admin.php';
require_once __DIR__ . '/includes/class-kampanya-db.php';
require_once __DIR__ . '/includes/class-kampanya.php';
require_once __DIR__ . '/includes/trait-kampanya-admin.php';
require_once __DIR__ . '/includes/trait-kampanya-banner-admin.php';
require_once __DIR__ . '/includes/class-tukendi.php';
require_once __DIR__ . '/includes/shortcode-vitrin.php';
require_once __DIR__ . '/qmo-one-cikan-slider.php';

/* -----------------------------------------------------------------
   "ÜRÜNÜM YOK" — malzeme bazlı stok katmanı.
   Mevcut CPT/taksonomi/render dosyalarının HİÇBİRİNE dokunmadan, üzerine
   eklenen ayrı bir katman (bkz. includes/urunum-yok/). Standalone
   sınıflar kendi hook'larını kendi init()'inde kaydeder (RMA_Vitrin_Shortcode
   ile aynı desen); yalnızca admin ekranları trait olarak sınıfa eklenir.
----------------------------------------------------------------- */
require_once __DIR__ . '/includes/urunum-yok/class-ingredient-taxonomy.php';
require_once __DIR__ . '/includes/urunum-yok/class-stock.php';
require_once __DIR__ . '/includes/urunum-yok/class-cron.php';
require_once __DIR__ . '/includes/urunum-yok/class-badge.php';
require_once __DIR__ . '/includes/urunum-yok/trait-admin.php';

/* =====================================================================
   MAIN CLASS
===================================================================== */
class Restaurant_Menu_Automation {

    use RMA_Helpers_Trait;
    use RMA_Post_Types_Trait;
    use RMA_Admin_Columns_Trait;
    use RMA_Admin_Pages_Trait;
    use RMA_Nav_Design_Trait;
    use RMA_Import_Export_Trait;
    use RMA_Suggestions_Trait;
    use RMA_Frontend_Trait;
    use RMA_Ajax_Trait;
    use RMA_Category_Fields_Trait;
    use RMA_Vitrin_Admin_Trait;
    use RMA_Kampanya_Admin_Trait;
    use RMA_Kampanya_Banner_Admin_Trait;
    use RMA_Urunum_Yok_Admin_Trait;

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init',                  [ $this, 'register_post_types' ] );
        add_action( 'add_meta_boxes',        [ $this, 'add_menu_item_meta_boxes' ] );
        add_action( 'save_post',             [ $this, 'save_menu_item_meta' ] );
        add_action( 'wp_insert_post',        [ $this, 'set_default_active_status' ], 10, 3 );
        add_filter( 'manage_rma_menu_item_posts_columns',        [ $this, 'add_admin_columns' ] );
        add_action( 'manage_rma_menu_item_posts_custom_column',  [ $this, 'render_admin_columns' ], 10, 2 );
        add_action( 'quick_edit_custom_box', [ $this, 'render_quick_edit_box' ], 10, 2 );
        add_action( 'add_inline_data',       [ $this, 'add_quick_edit_inline_data' ], 10, 2 );
        add_action( 'save_post_rma_menu_item', [ $this, 'save_quick_edit_fields' ] );
        add_filter( 'posts_clauses', [ $this, 'admin_group_by_category' ], 10, 2 );
        add_filter( 'post_row_actions',              [ $this, 'add_duplicate_post_link' ], 10, 2 );
        add_action( 'admin_action_rma_duplicate_post', [ $this, 'duplicate_post_action' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
        add_action( 'wp_ajax_rma_toggle_status',       [ $this, 'ajax_toggle_status' ] );
        add_action( 'wp_ajax_rma_toggle_tukendi',      [ $this, 'ajax_toggle_tukendi' ] );
        add_action( 'wp_ajax_rma_save_category_order', [ $this, 'ajax_save_category_order' ] );
        add_action( 'admin_menu',  [ $this, 'add_admin_menus' ] );
        add_action( 'admin_init',  [ $this, 'register_settings' ] );
        add_action( 'admin_init',  [ $this, 'handle_csv_import' ] );
        add_action( 'admin_init',  [ $this, 'handle_menu_import' ] );
        add_action( 'admin_post_rma_export_menu', [ $this, 'handle_export_menu' ] );
        add_action( 'rma_category_add_form_fields',  [ $this, 'add_category_custom_fields' ] );
        add_action( 'rma_category_edit_form_fields', [ $this, 'edit_category_custom_fields' ] );
        add_action( 'created_rma_category',          [ $this, 'save_category_custom_fields' ] );
        add_action( 'edited_rma_category',           [ $this, 'save_category_custom_fields' ] );
        add_action( 'wp_enqueue_scripts',                    [ $this, 'frontend_scripts_styles' ] );
        add_action( 'wp_ajax_rma_load_items',                [ $this, 'ajax_load_items' ] );
        add_action( 'wp_ajax_nopriv_rma_load_items',         [ $this, 'ajax_load_items' ] );
        add_action( 'wp_ajax_rma_get_product_details',       [ $this, 'ajax_get_product_details' ] );
        add_action( 'wp_ajax_nopriv_rma_get_product_details',[ $this, 'ajax_get_product_details' ] );
        add_action( 'wp_ajax_rma_save_suggestions',          [ $this, 'ajax_save_suggestions' ] );

        /* -----------------------------------------------------------------
           ÜRÜN VİTRİNİ
           Menü temasından bağımsız, kendi kısa kodu olan vitrin bileşeni.
           Kaydetme/silme admin-post üzerinden gider (bkz. trait-vitrin-admin).
        ----------------------------------------------------------------- */
        add_action( 'admin_post_rma_vitrin_kaydet', [ $this, 'handle_vitrin_save' ] );
        add_action( 'admin_post_rma_vitrin_sil',    [ $this, 'handle_vitrin_delete' ] );
        add_action( 'admin_post_qmo_slider_kaydet', [ $this, 'handle_slider_settings_save' ] );

        /* -----------------------------------------------------------------
           TOPLU FİYAT KAMPANYASI
           Ürün fiyatına HİÇ dokunulmaz: kural ayrı bir kayıtta durur, menüdeki
           fiyat her render'da orijinal fiyat + kural birleştirilerek üretilir
           (bkz. class-kampanya.php). Kaydetme/geri alma vitrindeki gibi
           admin-post üzerinden; yalnızca önizleme AJAX'tır, çünkü henüz
           kaydedilmemiş form değerleriyle çalışması gerekir.
        ----------------------------------------------------------------- */
        add_action( 'admin_post_rma_kampanya_kaydet',  [ $this, 'handle_kampanya_save' ] );
        add_action( 'admin_post_rma_kampanya_geri_al', [ $this, 'handle_kampanya_undo' ] );
        add_action( 'admin_post_rma_kampanya_sil',     [ $this, 'handle_kampanya_delete' ] );
        add_action( 'wp_ajax_rma_kampanya_onizleme',   [ $this, 'ajax_kampanya_onizleme' ] );

        /* -----------------------------------------------------------------
           KAMPANYA BANNER (banner GÖRSELLERİ — fiyat kampanyasıyla ilgisi yok)
           Yönetimin tamamı "Menü Görünümü" sayfasındaki üç adımlı sihirbazda
           toplandı (bkz. trait-kampanya-banner-admin.php). Görünüm ayarı
           admin-post ile, hazır şablonla görsel üretme ise AJAX ile gider:
           görsel tarayıcıda (canvas) çizilir, sunucu yalnızca PNG'yi
           doğrulayıp medya kütüphanesine ve yeni bir qmo_banner_slide
           kaydına yazar. Veri katmanı (CPT, meta, option) değişmedi.
        ----------------------------------------------------------------- */
        add_action( 'admin_post_qmo_banner_ayar_kaydet', [ $this, 'handle_banner_settings_save' ] );
        add_action( 'wp_ajax_qmo_banner_gorsel_olustur', [ $this, 'ajax_banner_gorsel_olustur' ] );
        add_action( 'wp_ajax_qmo_banner_sira_kaydet', [ 'QMO_Banner_CPT', 'ajax_save_order' ] );

        // Sunucu tarafı kırpmanın geriye dönük ucu: eskiden yüklenmiş
        // (yalnızca CSS ile kesilen) görselleri güncel orana göre yeniden
        // kırpar. Bkz. QMO_Banner_Kirpma.
        add_action( 'admin_post_qmo_banner_kirp', [ $this, 'handle_banner_kirp' ] );

        add_shortcode( 'restaurant_menu', [ $this, 'shortcode_menu' ] );
        add_shortcode( 'rma_qr_notice', [ $this, 'shortcode_qr_notice' ] );
        add_action( 'init', [ $this, 'register_default_allergen_terms' ], 20 );

        /* -----------------------------------------------------------------
           MENÜ ÖNBELLEĞİ — GEÇERSİZ KILMA KANCALARI

           Frontend menü yanıtı (AJAX) ve ürün detayları sürüm damgalı
           transient'larda saklanır. Aşağıdaki olaylardan biri gerçekleşince
           damga artırılır ve tüm önbellek tek hamlede geçersizleşir; bir
           sonraki ziyaretçi taze içeriği görür.

           Not: rma_views sayacı bilinçli olarak hariç tutulur (bkz.
           maybe_bump_cache_on_meta) — aksi hâlde her ürün görüntülemesi
           önbelleği düşürürdü.
        ----------------------------------------------------------------- */
        add_action( 'save_post_rma_menu_item', [ $this, 'maybe_bump_cache_on_post' ], 20, 2 );
        add_action( 'deleted_post',            [ $this, 'maybe_bump_cache_on_post' ], 20, 2 );
        add_action( 'trashed_post',            [ $this, 'maybe_bump_cache_on_post' ], 20, 2 );
        add_action( 'untrashed_post',          [ $this, 'maybe_bump_cache_on_post' ], 20, 2 );
        add_action( 'added_post_meta',         [ $this, 'maybe_bump_cache_on_meta' ], 20, 3 );
        add_action( 'updated_post_meta',       [ $this, 'maybe_bump_cache_on_meta' ], 20, 3 );
        add_action( 'set_object_terms',        [ $this, 'maybe_bump_cache_on_terms' ], 20, 4 );
        add_action( 'created_rma_category',    [ $this, 'bump_cache_version' ], 20 );
        add_action( 'edited_rma_category',     [ $this, 'bump_cache_version' ], 20 );
        add_action( 'delete_rma_category',     [ $this, 'bump_cache_version' ], 20 );
        add_action( 'delete_rma_allergen',     [ $this, 'bump_cache_version' ], 20 );
        add_action( 'update_option_rma_suggestions_settings', [ $this, 'bump_cache_version' ], 20 );
        add_action( 'update_option_qmo_slider_settings',      [ $this, 'bump_cache_version' ], 20 );
        add_action( 'update_option_qmo_banner_slider_settings', [ $this, 'bump_cache_version' ], 20 );

        // Chatbot / REST sipariş ucu varsa tükendi ürünleri orada kesilir.
        // Filtre chatbot modülünde tanımlıdır; modül yoksa kanca no-op'dur.
        add_filter( 'qmo_siparis_onay_oncesi', [ 'RMA_Tukendi', 'siparis_filtresi' ], 10, 2 );

        /* -----------------------------------------------------------------
           "ÜRÜNÜM YOK" — işaretleme formu, tekil geri alma ve CSV dışa/içe
           aktarma admin-post uçları. Sayfanın kendisi (qrms-rm-urunum-yok)
           get_subpages() üzerinden otomatik kaydolur.
        ----------------------------------------------------------------- */
        add_action( 'admin_post_qmo_uy_isaretle',             [ $this, 'handle_urunum_yok_mark' ] );
        add_action( 'admin_post_qmo_uy_aktiflestir',           [ $this, 'handle_urunum_yok_reactivate' ] );
        add_action( 'admin_post_qmo_uy_csv_export',            [ $this, 'handle_ingredient_csv_export' ] );
        add_action( 'admin_post_qmo_uy_csv_import_preview',    [ $this, 'handle_ingredient_csv_import_preview' ] );
        add_action( 'admin_post_qmo_uy_csv_import_confirm',    [ $this, 'handle_ingredient_csv_import_confirm' ] );
    }
}

Restaurant_Menu_Automation::get_instance();

// Vitrin kısa kodu ve varlıkları — sınıftan bağımsız, kendi kancalarını kurar.
RMA_Vitrin_Shortcode::init();

// "Ürünüm Yok" katmanı — taksonomi, stok motoru, cron ve rozet köprüleri de
// kendi kancalarını kendi init()'lerinde kurar.
RMA_Ingredient_Taxonomy::init();
RMA_Urunum_Yok_Cron::init();
RMA_Urunum_Yok_Badge::init();

/* =====================================================================
   ELEMENTOR WIDGET
===================================================================== */
require_once __DIR__ . '/includes/class-elementor-widget.php';
