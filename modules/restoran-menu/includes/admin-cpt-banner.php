<?php
/**
 * Kampanya Banner CPT — sayfa başındaki tam genişlik görsel slider'ın içeriği.
 *
 * Yapı bilinçli olarak admin-cpt-slide.php ile aynı deseni izler (aynı nonce
 * akışı, aynı menu_order kutusu, aynı liste sütunu yaklaşımı); tek fark
 * slide'ın ürün seçmesi, banner'ın tek görsel + opsiyonel bağlantı
 * tutmasıdır.
 *
 * @package QMO
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class QMO_Banner_CPT {

    const POST_TYPE    = 'qmo_banner_slide';
    const NONCE_ACTION = 'qmo_save_banner_meta';
    const NONCE_FIELD  = 'qmo_banner_nonce';

    const META_IMAGE = '_qmo_banner_gorsel_id';
    const META_LINK  = '_qmo_banner_link';

    /** Görsel yükleme alanının altındaki boyut önerisi. */
    const BOYUT_NOTU = 'Önerilen boyut: 1600x900px (16:9), JPG/WEBP, maksimum 300KB';

    public static function init() {
        // init() `init` kancasının içinden çağrılır (QMO_One_Cikan_Slider::boot,
        // öncelik 20). O noktada `init`in 10. önceliği geçmiş olduğundan yeni
        // eklenen bir 10'luk kanca bu istekte artık çalışmaz; CPT doğrudan
        // kaydedilir. Daha erken bir çağrı olursa normal kanca yolu kullanılır.
        if ( did_action( 'init' ) ) {
            self::register_post_type();
        } else {
            add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        }

        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save_meta' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_scripts' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ __CLASS__, 'add_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ __CLASS__, 'render_columns' ], 10, 2 );
    }

    public static function register_post_type() {
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'          => 'Kampanya Banner',
                'singular_name' => 'Banner',
                'menu_name'     => 'Kampanya Banner',
                'add_new'       => 'Banner Ekle',
                'add_new_item'  => 'Yeni Banner Ekle',
                'edit_item'     => 'Banner Düzenle',
                'new_item'      => 'Yeni Banner',
                'view_item'     => 'Banner Görüntüle',
                'search_items'  => 'Banner Ara',
                'not_found'     => 'Banner bulunamadı',
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            // "Öne Çıkan Slider" ile aynı üst menü: ikisi de QR MENÜ'nün
            // altında yan yana durur.
            'show_in_menu'        => 'edit.php?post_type=rma_menu_item',
            'show_in_nav_menus'   => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'supports'            => [ 'title' ],
            'capability_type'     => 'post',
            'menu_position'       => 7,
        ] );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'qmo_banner_gorsel',
            'Banner Görseli',
            [ __CLASS__, 'render_image_meta_box' ],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'qmo_banner_sira',
            'Sıralama',
            [ __CLASS__, 'render_order_meta_box' ],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public static function render_image_meta_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

        $image_id = (int) get_post_meta( $post->ID, self::META_IMAGE, true );
        $link     = (string) get_post_meta( $post->ID, self::META_LINK, true );
        $img_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
        ?>
        <div class="qmo-banner-media" data-qmo-banner-media>
            <input type="hidden" name="<?php echo esc_attr( self::META_IMAGE ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-qmo-banner-input />

            <div class="qmo-banner-preview" data-qmo-banner-preview style="aspect-ratio:16/9;max-width:520px;background:#0d0d10;border:1px solid #ccd0d4;border-radius:6px;overflow:hidden;margin-bottom:10px;<?php echo $img_url ? '' : 'display:none;'; ?>">
                <img src="<?php echo esc_url( $img_url ); ?>" alt="" style="display:block;width:100%;height:100%;object-fit:cover;" data-qmo-banner-img />
            </div>

            <p>
                <button type="button" class="button" data-qmo-banner-select><?php echo $image_id ? 'Görseli Değiştir' : 'Görsel Seç'; ?></button>
                <button type="button" class="button-link" data-qmo-banner-remove style="<?php echo $image_id ? '' : 'display:none;'; ?>margin-left:8px;color:#b32d2e;">Kaldır</button>
            </p>
            <p class="description"><?php echo esc_html( self::BOYUT_NOTU ); ?></p>
        </div>

        <p style="margin-top:18px;">
            <label for="qmo_banner_link"><strong>Bağlantı (opsiyonel)</strong></label><br />
            <input type="url" id="qmo_banner_link" name="<?php echo esc_attr( self::META_LINK ); ?>" value="<?php echo esc_attr( $link ); ?>" class="widefat" placeholder="https://" />
        </p>
        <p class="description">Doldurulursa banner tıklanabilir olur ve bu adrese yönlendirir. Boş bırakılırsa banner yalnızca görsel olarak gösterilir.</p>
        <?php
    }

    public static function render_order_meta_box( $post ) {
        $order = (int) $post->menu_order;
        ?>
        <p>
            <label for="qmo_banner_menu_order"><strong>Sıra No</strong></label><br>
            <input type="number" id="qmo_banner_menu_order" name="menu_order" value="<?php echo esc_attr( $order ); ?>" min="0" step="1" style="width:100%;" />
        </p>
        <p class="description">Küçük numara önce gösterilir.</p>
        <?php
    }

    public static function save_meta( $post_id ) {
        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['menu_order'] ) ) {
            wp_update_post( [
                'ID'         => $post_id,
                'menu_order' => absint( $_POST['menu_order'] ),
            ] );
        }

        $image_id = isset( $_POST[ self::META_IMAGE ] ) ? absint( $_POST[ self::META_IMAGE ] ) : 0;
        if ( $image_id && self::is_valid_image( $image_id ) ) {
            update_post_meta( $post_id, self::META_IMAGE, $image_id );
        } else {
            delete_post_meta( $post_id, self::META_IMAGE );
        }

        $link = isset( $_POST[ self::META_LINK ] ) ? esc_url_raw( wp_unslash( $_POST[ self::META_LINK ] ) ) : '';
        if ( '' !== $link ) {
            update_post_meta( $post_id, self::META_LINK, $link );
        } else {
            delete_post_meta( $post_id, self::META_LINK );
        }
    }

    private static function is_valid_image( $id ) {
        $post = get_post( $id );
        return $post instanceof WP_Post && 'attachment' === $post->post_type;
    }

    public static function admin_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== self::POST_TYPE ) return;

        wp_enqueue_media();
        wp_add_inline_script( 'media-editor', self::uploader_script() );
    }

    /**
     * Meta kutusundaki wp.media seçicisinin davranışı.
     *
     * Kendi dosyasına ayrılmadı: yalnızca bu ekranda çalışan, bağımlılığı
     * olmayan birkaç satır — Açılış Ekranı modülündeki upload-image-btn
     * deseninin sadeleştirilmiş hâli.
     *
     * @return string
     */
    private static function uploader_script() {
        return <<<'JS'
(function () {
    'use strict';

    var wrap = document.querySelector('[data-qmo-banner-media]');
    if (!wrap || !window.wp || !window.wp.media) return;

    var input   = wrap.querySelector('[data-qmo-banner-input]');
    var preview = wrap.querySelector('[data-qmo-banner-preview]');
    var img     = wrap.querySelector('[data-qmo-banner-img]');
    var select  = wrap.querySelector('[data-qmo-banner-select]');
    var remove  = wrap.querySelector('[data-qmo-banner-remove]');
    var frame   = null;

    function apply(id, url) {
        input.value = id ? String(id) : '';
        img.src = url || '';
        preview.style.display = url ? '' : 'none';
        remove.style.display = url ? '' : 'none';
        select.textContent = url ? 'Görseli Değiştir' : 'Görsel Seç';
    }

    select.addEventListener('click', function () {
        if (!frame) {
            frame = wp.media({
                title: 'Banner Görseli Seç',
                button: { text: 'Bu görseli kullan' },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function () {
                var a = frame.state().get('selection').first().toJSON();
                var url = (a.sizes && a.sizes.large) ? a.sizes.large.url : a.url;
                apply(a.id, url);
            });
        }
        frame.open();
    });

    remove.addEventListener('click', function () {
        apply(0, '');
    });
})();
JS;
    }

    public static function add_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['qmo_banner_gorsel'] = 'Görsel';
                $new['qmo_banner_order']  = 'Sıra No';
            }
        }
        return $new;
    }

    public static function render_columns( $column, $post_id ) {
        if ( 'qmo_banner_order' === $column ) {
            echo esc_html( (string) get_post( $post_id )->menu_order );
            return;
        }

        if ( 'qmo_banner_gorsel' !== $column ) return;

        $image_id = (int) get_post_meta( $post_id, self::META_IMAGE, true );
        $url      = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

        if ( ! $url ) {
            echo '<span style="color:#b32d2e;">—</span>';
            return;
        }

        printf(
            '<img src="%s" alt="" style="width:80px;height:45px;object-fit:cover;border-radius:4px;" />',
            esc_url( $url )
        );
    }

    /**
     * Yayınlanmış banner'ları menu_order'a göre döndürür.
     *
     * @return WP_Post[]
     */
    public static function get_published_banners() {
        return get_posts( [
            'post_type'              => self::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => 'menu_order',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ] );
    }
}
