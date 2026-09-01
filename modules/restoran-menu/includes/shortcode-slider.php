<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class QMO_Shortcode_Slider {

    private static $assets_loaded = false;

    public static function init() {
        add_shortcode( 'qmo_one_cikan_slider', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ] );
    }

    public static function maybe_enqueue_assets() {
        if ( self::$assets_loaded ) return;

        $post = get_post();
        if ( ! $post instanceof WP_Post ) return;
        if ( ! has_shortcode( $post->post_content, 'qmo_one_cikan_slider' ) ) return;

        self::enqueue_styles();
    }

    private static function enqueue_styles() {
        if ( self::$assets_loaded ) return;
        self::$assets_loaded = true;

        wp_enqueue_style(
            'qmo-slider-fonts',
            'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'qmo-slider',
            QMO_PLUGIN_URL . 'includes/frontend-slider.css',
            [ 'qmo-slider-fonts' ],
            file_exists( QMO_PLUGIN_DIR . 'includes/frontend-slider.css' ) ? filemtime( QMO_PLUGIN_DIR . 'includes/frontend-slider.css' ) : '1.0.0'
        );

        self::enqueue_detail_modal();
    }

    /**
     * Ürün detay modalını (Vitrin ile paylaşılan) kuyruğa alır.
     *
     * Slaytlardaki ürünler tıklanınca `rma-detail-modal.js` bu modalı
     * açar ve ana menüyle AYNI uç noktayı (`rma_get_product_details`)
     * çağırır — bkz. modules/restoran-menu/includes/trait-ajax.php ve
     * shortcode-vitrin.php::enqueue_modal_config() (aynı desen).
     *
     * @return void
     */
    private static function enqueue_detail_modal() {
        $css = QMO_PLUGIN_DIR . 'assets/css/rma-detail-modal.css';
        $js  = QMO_PLUGIN_DIR . 'assets/js/rma-detail-modal.js';

        wp_enqueue_style(
            'rma-detail-modal',
            QMO_PLUGIN_URL . 'assets/css/rma-detail-modal.css',
            [],
            file_exists( $css ) ? filemtime( $css ) : '1.0.0'
        );

        wp_enqueue_script(
            'rma-detail-modal',
            QMO_PLUGIN_URL . 'assets/js/rma-detail-modal.js',
            [],
            file_exists( $js ) ? filemtime( $js ) : '1.0.0',
            true
        );

        $config = [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rma_ajax_nonce' ),
            'lang'    => function_exists( 'rma_get_current_lang' ) ? rma_get_current_lang() : 'tr',
            'i18n'    => array(
                'kapat' => function_exists( 'qmo_ceviri_ui' ) ? qmo_ceviri_ui( __( 'Kapat', 'qrms' ) ) : __( 'Kapat', 'qrms' ),
            ),
        ];

        wp_add_inline_script(
            'rma-detail-modal',
            'window.RMA_MODAL_CFG = ' . wp_json_encode( $config ) . ';',
            'before'
        );
    }

    private static function script_url() {
        return QMO_PLUGIN_URL . 'includes/frontend-slider.js?v=' . ( file_exists( QMO_PLUGIN_DIR . 'includes/frontend-slider.js' ) ? filemtime( QMO_PLUGIN_DIR . 'includes/frontend-slider.js' ) : '1.0.0' );
    }

    public static function render_shortcode( $atts ) {
        $slides = self::build_slide_payloads();
        if ( empty( $slides ) ) return '';

        self::enqueue_styles();

        $ayar = class_exists( 'QMO_Slider_Settings' ) ? QMO_Slider_Settings::get() : array(
            'show_nav'   => 1,
            'show_title' => 1,
        );

        // Kısa kod niteliği hâlâ geçerlidir ve admin varsayılanını ezer
        // (eski [qmo_one_cikan_slider show_title="no"] kullanımları bozulmasın).
        $a = shortcode_atts( [
            'show_title' => '',
        ], $atts, 'qmo_one_cikan_slider' );

        if ( 'no' === $a['show_title'] ) {
            $show_title = false;
        } elseif ( 'yes' === $a['show_title'] ) {
            $show_title = true;
        } else {
            $show_title = ! empty( $ayar['show_title'] );
        }

        $show_nav = ! empty( $ayar['show_nav'] ) && count( $slides ) > 1;
        $stil     = class_exists( 'QMO_Slider_Settings' ) ? QMO_Slider_Settings::css_degiskenleri( $ayar ) : '';

        ob_start();
        ?>
<div class="qmo-slider-root" data-qmo-slider<?php echo '' !== $stil ? ' style="' . esc_attr( $stil ) . '"' : ''; ?>>
    <div class="qmo-slider-viewport">
        <div class="qmo-slider-track">
            <?php foreach ( $slides as $index => $slide ) : ?>
                <div class="qmo-slider-slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-qmo-slide="<?php echo esc_attr( (string) $index ); ?>">
                    <?php if ( $show_title && $slide['title'] !== '' ) : ?>
                        <h2 class="qmo-slider-title"><?php echo esc_html( $slide['title'] ); ?></h2>
                    <?php endif; ?>
                    <div class="qmo-slider-grid">
                        <?php foreach ( $slide['products'] as $product ) : ?>
                            <article class="qmo-slider-product<?php echo ! empty( $product['tukendi'] ) ? ' is-tukendi' : ''; ?>"
                                     data-id="<?php echo (int) $product['id']; ?>"
                                     tabindex="0" role="button"
                                     aria-label="<?php echo esc_attr( $product['title'] ); ?>">
                                <div class="qmo-slider-product-img-wrap">
                                    <img src="<?php echo esc_url( $product['img'] ); ?>" alt="<?php echo esc_attr( $product['title'] ); ?>" class="qmo-slider-product-img" loading="lazy" decoding="async" width="220" height="275">
                                    <?php if ( ! empty( $product['tukendi'] ) ) : ?>
                                        <span class="rma-tukendi-rozet"><?php echo esc_html( $product['tukendi_etiket'] ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="qmo-slider-product-body">
                                    <h3 class="qmo-slider-product-title"><?php echo esc_html( $product['title'] ); ?></h3>
                                    <?php if ( $product['desc'] !== '' ) : ?>
                                        <p class="qmo-slider-product-desc"><?php echo esc_html( $product['desc'] ); ?></p>
                                    <?php endif; ?>
                                    <p class="qmo-slider-product-price"><?php echo wp_kses_post( $product['price_html'] ); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ( $show_nav ) : ?>
        <div class="qmo-slider-nav" aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Slide navigasyonu', 'qrms' ) ) ); ?>">
            <button type="button" class="qmo-slider-nav-btn qmo-slider-nav-prev" aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Önceki slide', 'qrms' ) ) ); ?>">&#8249;</button>
            <button type="button" class="qmo-slider-nav-btn qmo-slider-nav-next" aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Sonraki slide', 'qrms' ) ) ); ?>">&#8250;</button>
        </div>
    <?php endif; ?>
</div>
<script src="<?php echo esc_url( self::script_url() ); ?>" defer></script>
        <?php
        return trim( ob_get_clean() );
    }

    /**
     * @return array<int,array{title:string,products:array}>
     */
    private static function build_slide_payloads() {
        $posts  = QMO_Slide_CPT::get_published_slides();
        $slides = [];

        foreach ( $posts as $post ) {
            $products = self::get_slide_products( $post->ID );
            if ( empty( $products ) ) continue;

            $slides[] = [
                'title'    => trim( $post->post_title ),
                'products' => $products,
            ];
        }

        return $slides;
    }

    /**
     * @return array<int,array{id:int,title:string,desc:string,price_html:string,img:string,tukendi:bool,tukendi_etiket:string}>
     */
    private static function get_slide_products( $slide_id ) {
        $items = [];

        for ( $i = 1; $i <= 4; $i++ ) {
            $product_id = (int) get_post_meta( $slide_id, '_qmo_slide_urun_' . $i, true );
            if ( ! $product_id ) continue;

            $post = get_post( $product_id );
            if ( ! $post || $post->post_type !== 'rma_menu_item' || $post->post_status !== 'publish' ) continue;
            if ( get_post_meta( $product_id, 'rma_active', true ) !== '1' ) continue;

            $title = get_the_title( $product_id );
            $excerpt_raw = (string) get_post_field( 'post_excerpt', $product_id );
            $desc = trim( $excerpt_raw ) !== ''
                ? wp_trim_words( strip_shortcodes( $excerpt_raw ), 12, '…' )
                : '';

            // Aktif fiyat kampanyası, kombin fiyatı ve düz fiyat tek yerden
            // çözülür (bkz. class-kampanya.php).
            $price_html = RMA_Kampanya::fiyat_html( $product_id );

            $img = get_the_post_thumbnail_url( $product_id, 'medium' )
                ?: 'https://placehold.co/440x550/111111/c9a84c?text=%E2%97%86';

            $tukendi = class_exists( 'RMA_Tukendi' ) && RMA_Tukendi::urun_tukendi( $product_id );

            $items[] = [
                'id'             => $product_id,
                'title'          => $title,
                'desc'           => $desc,
                'price_html'     => $price_html,
                'img'            => $img,
                'tukendi'        => $tukendi,
                'tukendi_etiket' => $tukendi ? RMA_Tukendi::etiket() : '',
            ];
        }

        return $items;
    }
}
