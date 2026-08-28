<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class QMO_Slide_CPT {

    const NONCE_ACTION = 'qmo_save_slide_meta';
    const NONCE_FIELD  = 'qmo_slide_nonce';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_qmo_slide', [ __CLASS__, 'save_meta' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_scripts' ] );
        add_filter( 'manage_qmo_slide_posts_columns', [ __CLASS__, 'add_columns' ] );
        add_action( 'manage_qmo_slide_posts_custom_column', [ __CLASS__, 'render_columns' ], 10, 2 );
    }

    public static function register_post_type() {
        register_post_type( 'qmo_slide', [
            'labels' => [
                'name'          => 'Öne Çıkan Slider',
                'singular_name' => 'Slide',
                'menu_name'     => 'Öne Çıkan Slider',
                'add_new'       => 'Slide Ekle',
                'add_new_item'  => 'Yeni Slide Ekle',
                'edit_item'     => 'Slide Düzenle',
                'new_item'      => 'Yeni Slide',
                'view_item'     => 'Slide Görüntüle',
                'search_items'  => 'Slide Ara',
                'not_found'     => 'Slide bulunamadı',
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=rma_menu_item',
            'show_in_nav_menus'   => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'supports'            => [ 'title' ],
            'capability_type'     => 'post',
            'menu_position'       => 6,
        ] );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'qmo_slide_urunler',
            'Slide Ürünleri',
            [ __CLASS__, 'render_products_meta_box' ],
            'qmo_slide',
            'normal',
            'high'
        );

        add_meta_box(
            'qmo_slide_sira',
            'Sıralama',
            [ __CLASS__, 'render_order_meta_box' ],
            'qmo_slide',
            'side',
            'default'
        );
    }

    public static function render_products_meta_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
        echo '<p style="color:#666;font-size:12px;margin-top:0;">Her slide\'da en fazla 4 ürün gösterilir. Başlık (post title) frontend\'de opsiyonel olarak kullanılır.</p>';
        // Slide'ın görselleri seçilen ürünlerin öne çıkan görselleridir;
        // boyut önerisi bu yüzden ürün görseli için verilir.
        echo '<p class="description" style="margin-top:4px;">Ürün görselleri için önerilen boyut: 1080x1080px (1:1 kare), JPG/WEBP, maksimum 200KB</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:12px;">';

        for ( $i = 1; $i <= 4; $i++ ) {
            $selected = (int) get_post_meta( $post->ID, '_qmo_slide_urun_' . $i, true );
            printf(
                '<div><label for="qmo_slide_urun_%1$d"><strong>Ürün %1$d</strong></label><br>
                <select id="qmo_slide_urun_%1$d" name="_qmo_slide_urun_%1$d" class="qmo-product-select" style="width:100%%;" data-placeholder="Ürün ara…">',
                $i
            );
            self::render_selected_option( $selected );
            echo '</select></div>';
        }

        echo '</div>';
    }

    public static function render_order_meta_box( $post ) {
        $order = (int) $post->menu_order;
        ?>
        <p>
            <label for="qmo_slide_menu_order"><strong>Sıra No</strong></label><br>
            <input type="number" id="qmo_slide_menu_order" name="menu_order" value="<?php echo esc_attr( $order ); ?>" min="0" step="1" style="width:100%;" />
        </p>
        <p class="description">Küçük numara önce gösterilir.</p>
        <?php
    }

    private static function render_selected_option( $post_id ) {
        if ( ! $post_id ) {
            echo '<option value=""></option>';
            return;
        }
        $title = get_the_title( $post_id );
        if ( ! $title ) {
            echo '<option value=""></option>';
            return;
        }
        printf(
            '<option value="%d" selected="selected">%s</option>',
            (int) $post_id,
            esc_html( $title )
        );
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

        for ( $i = 1; $i <= 4; $i++ ) {
            $key = '_qmo_slide_urun_' . $i;
            $val = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;

            if ( $val && self::is_valid_menu_item( $val ) ) {
                update_post_meta( $post_id, $key, $val );
            } else {
                delete_post_meta( $post_id, $key );
            }
        }
    }

    private static function is_valid_menu_item( $id ) {
        $post = get_post( $id );
        return $post instanceof WP_Post
            && $post->post_type === 'rma_menu_item'
            && $post->post_status === 'publish';
    }

    public static function admin_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'qmo_slide' ) return;

        QMO_Kombin_Meta::enqueue_select2_assets( 'QMO_SLIDE' );
    }

    public static function add_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['qmo_slide_order'] = 'Sıra No';
            }
        }
        return $new;
    }

    public static function render_columns( $column, $post_id ) {
        if ( 'qmo_slide_order' !== $column ) return;
        echo esc_html( (string) get_post( $post_id )->menu_order );
    }

    /**
     * Yayınlanmış slide'ları menu_order'a göre döndürür.
     *
     * @return WP_Post[]
     */
    public static function get_published_slides() {
        return get_posts( [
            'post_type'              => 'qmo_slide',
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
