<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class QMO_Kombin_Meta {

    const NONCE_ACTION = 'qmo_save_kombin_meta';
    const NONCE_FIELD  = 'qmo_kombin_nonce';

    public static function init() {
        add_action( 'add_meta_boxes',        [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post_rma_menu_item', [ __CLASS__, 'save_meta' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_scripts' ] );
        add_action( 'wp_ajax_qmo_search_menu_items', [ __CLASS__, 'ajax_search_items' ] );
    }

    public static function add_meta_box() {
        add_meta_box(
            'qmo_kombin_urun',
            'Kombin Ürün',
            [ __CLASS__, 'render_meta_box' ],
            'rma_menu_item',
            'normal',
            'default'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

        $is_kombin = get_post_meta( $post->ID, '_qmo_is_kombin', true ) === '1';
        $urun_1    = (int) get_post_meta( $post->ID, '_qmo_kombin_urun_1', true );
        $urun_2    = (int) get_post_meta( $post->ID, '_qmo_kombin_urun_2', true );
        $fiyat     = get_post_meta( $post->ID, '_qmo_kombin_fiyat', true );

        $fields_style = $is_kombin ? '' : 'display:none;';
        ?>
        <p>
            <label>
                <input type="checkbox" id="qmo_is_kombin" name="_qmo_is_kombin" value="1" <?php checked( $is_kombin ); ?> />
                <strong>Kombin Ürün mü?</strong>
            </label>
        </p>

        <div id="qmo-kombin-fields" style="<?php echo esc_attr( $fields_style ); ?>">
            <div style="display:flex;flex-wrap:wrap;gap:20px;margin-top:12px;">
                <div style="flex:1 1 40%;min-width:220px;">
                    <label for="qmo_kombin_urun_1"><strong>1. Ürün</strong></label><br>
                    <select id="qmo_kombin_urun_1" name="_qmo_kombin_urun_1" class="qmo-product-select" style="width:100%;" data-placeholder="Ürün ara…">
                        <?php self::render_selected_option( $urun_1, $post->ID ); ?>
                    </select>
                </div>
                <div style="flex:1 1 40%;min-width:220px;">
                    <label for="qmo_kombin_urun_2"><strong>2. Ürün</strong></label><br>
                    <select id="qmo_kombin_urun_2" name="_qmo_kombin_urun_2" class="qmo-product-select" style="width:100%;" data-placeholder="Ürün ara…">
                        <?php self::render_selected_option( $urun_2, $post->ID ); ?>
                    </select>
                </div>
                <div style="flex:1 1 20%;min-width:140px;">
                    <label for="qmo_kombin_fiyat"><strong>Kombin Fiyatı (₺)</strong></label><br>
                    <input type="number" id="qmo_kombin_fiyat" name="_qmo_kombin_fiyat" value="<?php echo esc_attr( $fiyat ); ?>" step="0.01" min="0" style="width:100%;" />
                </div>
            </div>
            <p style="color:#777;font-size:12px;margin-top:10px;">Kombin ürün menüde normal ürün gibi listelenir. Eski fiyat, seçilen iki ürünün fiyat toplamından otomatik hesaplanır.</p>
        </div>
        <?php
    }

    private static function render_selected_option( $post_id, $current_id ) {
        if ( ! $post_id ) {
            echo '<option value=""></option>';
            return;
        }
        if ( (int) $post_id === (int) $current_id ) {
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

        $is_kombin = isset( $_POST['_qmo_is_kombin'] ) ? '1' : '0';
        update_post_meta( $post_id, '_qmo_is_kombin', $is_kombin );

        if ( '1' !== $is_kombin ) {
            delete_post_meta( $post_id, '_qmo_kombin_urun_1' );
            delete_post_meta( $post_id, '_qmo_kombin_urun_2' );
            delete_post_meta( $post_id, '_qmo_kombin_fiyat' );
            return;
        }

        $urun_1 = isset( $_POST['_qmo_kombin_urun_1'] ) ? absint( $_POST['_qmo_kombin_urun_1'] ) : 0;
        $urun_2 = isset( $_POST['_qmo_kombin_urun_2'] ) ? absint( $_POST['_qmo_kombin_urun_2'] ) : 0;
        $fiyat  = isset( $_POST['_qmo_kombin_fiyat'] ) ? self::sanitize_price( wp_unslash( $_POST['_qmo_kombin_fiyat'] ) ) : '';

        if ( $urun_1 && self::is_valid_menu_item( $urun_1, $post_id ) ) {
            update_post_meta( $post_id, '_qmo_kombin_urun_1', $urun_1 );
        } else {
            delete_post_meta( $post_id, '_qmo_kombin_urun_1' );
        }

        if ( $urun_2 && self::is_valid_menu_item( $urun_2, $post_id ) ) {
            update_post_meta( $post_id, '_qmo_kombin_urun_2', $urun_2 );
        } else {
            delete_post_meta( $post_id, '_qmo_kombin_urun_2' );
        }

        if ( $fiyat !== '' ) {
            update_post_meta( $post_id, '_qmo_kombin_fiyat', $fiyat );
        } else {
            delete_post_meta( $post_id, '_qmo_kombin_fiyat' );
        }
    }

    private static function sanitize_price( $raw ) {
        $raw = sanitize_text_field( $raw );
        if ( $raw === '' ) return '';
        $val = (float) str_replace( ',', '.', $raw );
        return $val >= 0 ? (string) $val : '';
    }

    private static function is_valid_menu_item( $id, $exclude_id = 0 ) {
        if ( ! $id || (int) $id === (int) $exclude_id ) return false;
        $post = get_post( $id );
        return $post instanceof WP_Post
            && $post->post_type === 'rma_menu_item'
            && $post->post_status === 'publish';
    }

    public static function admin_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'rma_menu_item' ) return;

        self::enqueue_select2_assets( 'QMO_KOMBIN' );
    }

    public static function enqueue_select2_assets( $global_key = 'QMO_KOMBIN' ) {
        if ( wp_script_is( 'qmo-select2', 'registered' ) && wp_script_is( 'qmo-select2', 'enqueued' ) ) {
            return;
        }

        if ( ! wp_style_is( 'qmo-select2', 'enqueued' ) ) {
            wp_enqueue_style(
                'qmo-select2',
                'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css',
                [],
                '4.0.13'
            );
        }

        wp_enqueue_script(
            'qmo-select2',
            'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js',
            [ 'jquery' ],
            '4.0.13',
            true
        );

        $cfg = [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'qmo_search_items' ),
        ];
        if ( 'QMO_KOMBIN' === $global_key ) {
            $cfg['excludeId'] = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        }

        wp_add_inline_script(
            'qmo-select2',
            'var ' . $global_key . ' = ' . wp_json_encode( $cfg ) . '; window.QMO_KOMBIN = window.QMO_KOMBIN || ' . $global_key . '; window.QMO_SLIDE = window.QMO_SLIDE || ' . $global_key . ';',
            'before'
        );

        wp_add_inline_script( 'qmo-select2', self::get_select2_inline_js(), 'after' );
    }

    public static function get_select2_inline_js() {
        return <<<'JS'
(function ($) {
    'use strict';
    var cfg = window.QMO_KOMBIN || window.QMO_SLIDE || {};
    function initProductSelects() {
        if (typeof $.fn.select2 !== 'function') return;
        $('.qmo-product-select').each(function () {
            var $el = $(this);
            if ($el.data('select2')) return;
            $el.select2({
                width: '100%',
                allowClear: true,
                placeholder: $el.data('placeholder') || 'Ürün ara…',
                ajax: {
                    url: cfg.ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: 'qmo_search_menu_items',
                            security: cfg.nonce,
                            q: params.term || '',
                            page: params.page || 1,
                            exclude: cfg.excludeId || 0
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results || [],
                            pagination: { more: !!(data.pagination && data.pagination.more) }
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });
        });
    }
    $(function () {
        var $toggle = $('#qmo_is_kombin');
        var $fields = $('#qmo-kombin-fields');
        if ($toggle.length && $fields.length) {
            $toggle.on('change', function () {
                $fields.toggle(this.checked);
            });
        }
        initProductSelects();
    });
})(jQuery);
JS;
    }

    public static function ajax_search_items() {
        check_ajax_referer( 'qmo_search_items', 'security' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json( [ 'results' => [] ] );
        }

        $term      = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
        $exclude   = absint( $_GET['exclude'] ?? 0 );
        $page      = max( 1, absint( $_GET['page'] ?? 1 ) );
        $per_page  = 20;

        $args = [
            'post_type'              => 'rma_menu_item',
            'post_status'            => 'publish',
            'posts_per_page'         => $per_page,
            'paged'                  => $page,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            's'                      => $term,
            'post__not_in'           => $exclude ? [ $exclude ] : [],
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        $query   = new WP_Query( $args );
        $results = [];

        foreach ( $query->posts as $post ) {
            $price = get_post_meta( $post->ID, 'rma_price', true );
            $text  = $post->post_title;
            if ( $price !== '' ) {
                $text .= ' — ' . $price . ' ₺';
            }
            $results[] = [
                'id'   => $post->ID,
                'text' => $text,
            ];
        }

        wp_send_json( [
            'results'    => $results,
            'pagination' => [ 'more' => $page < (int) $query->max_num_pages ],
        ] );
    }

    /* -----------------------------------------------------------------
       FRONTEND HELPERS
    ----------------------------------------------------------------- */

    public static function is_kombin( $post_id ) {
        return get_post_meta( $post_id, '_qmo_is_kombin', true ) === '1';
    }

    /**
     * Kombin fiyat HTML'i. Geçersiz durumda boş string döner.
     */
    public static function get_price_html( $post_id ) {
        if ( ! self::is_kombin( $post_id ) ) return '';

        $combo_price = get_post_meta( $post_id, '_qmo_kombin_fiyat', true );
        if ( $combo_price === '' || ! is_numeric( $combo_price ) ) return '';

        $urun_1 = (int) get_post_meta( $post_id, '_qmo_kombin_urun_1', true );
        $urun_2 = (int) get_post_meta( $post_id, '_qmo_kombin_urun_2', true );

        $old_total = self::sum_component_prices( $urun_1, $urun_2 );
        $old_html  = '';

        if ( $old_total !== null ) {
            $old_html = sprintf(
                '<span class="qmo-kombin-old-price">%s ₺</span>',
                esc_html( self::format_price( $old_total ) )
            );
        }

        $new_html = sprintf(
            '<span class="qmo-kombin-new-price">%s ₺</span>',
            esc_html( self::format_price( (float) $combo_price ) )
        );

        return $old_html . $new_html;
    }

    private static function sum_component_prices( $id_1, $id_2 ) {
        if ( ! $id_1 || ! $id_2 ) return null;

        $price_1 = self::get_item_price( $id_1 );
        $price_2 = self::get_item_price( $id_2 );

        if ( $price_1 === null || $price_2 === null ) return null;

        return $price_1 + $price_2;
    }

    private static function get_item_price( $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'rma_menu_item' || $post->post_status !== 'publish' ) {
            return null;
        }
        $price = get_post_meta( $id, 'rma_price', true );
        if ( $price === '' || ! is_numeric( $price ) ) return null;
        return (float) $price;
    }

    private static function format_price( $value ) {
        $formatted = number_format( (float) $value, 2, ',', '.' );
        return rtrim( rtrim( $formatted, '0' ), ',' );
    }
}

/**
 * Frontend için global köprü — RMA render_card tarafından çağrılır.
 */
function qmo_get_kombin_price_html( $post_id ) {
    return QMO_Kombin_Meta::get_price_html( $post_id );
}
