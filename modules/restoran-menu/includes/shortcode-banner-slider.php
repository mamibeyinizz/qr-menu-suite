<?php
/**
 * [qmo_banner_slider] — sayfa başındaki tam genişlik kampanya banner'ı.
 *
 * Ürün vitrini slider'ından (shortcode-slider.php) tamamen bağımsızdır:
 * kendi CPT'si, kendi varlıkları, kendi betiği vardır; ortak tek şey
 * dosya/enqueue desenidir. Görseli olan yayınlanmış banner yoksa modül
 * sessizce hiç basılmaz.
 *
 * @package QMO
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class QMO_Shortcode_Banner_Slider {

    const SHORTCODE = 'qmo_banner_slider';

    private static $assets_loaded = false;

    public static function init() {
        add_shortcode( self::SHORTCODE, [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ] );
    }

    public static function maybe_enqueue_assets() {
        if ( self::$assets_loaded ) return;

        $post = get_post();
        if ( ! $post instanceof WP_Post ) return;
        if ( ! has_shortcode( $post->post_content, self::SHORTCODE ) ) return;

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

        $css = QMO_PLUGIN_DIR . 'includes/frontend-banner-slider.css';

        wp_enqueue_style(
            'qmo-banner-slider',
            QMO_PLUGIN_URL . 'includes/frontend-banner-slider.css',
            [ 'qmo-slider-fonts' ],
            file_exists( $css ) ? filemtime( $css ) : '1.0.0'
        );
    }

    private static function script_url() {
        $js = QMO_PLUGIN_DIR . 'includes/frontend-banner-slider.js';

        return QMO_PLUGIN_URL . 'includes/frontend-banner-slider.js?v=' . ( file_exists( $js ) ? filemtime( $js ) : '1.0.0' );
    }

    public static function render_shortcode( $atts ) {
        $banners = self::build_banner_payloads();
        if ( empty( $banners ) ) return '';

        self::enqueue_styles();

        $ayar = class_exists( 'QMO_Banner_Slider_Settings' )
            ? QMO_Banner_Slider_Settings::get()
            : array(
                'show_nav'   => 0,
                'show_dots'  => 1,
                'show_title' => 0,
                'gecis'      => 'slide',
                'autoplay'   => 4500,
            );

        // Kısa kod niteliği yönetim varsayılanını ezer; nitelik hiç
        // yazılmamışsa ayar geçerlidir (eski [qmo_banner_slider autoplay="0"]
        // kullanımları bozulmasın diye ham dizi de kontrol edilir).
        $ham_atts = is_array( $atts ) ? $atts : array();

        $a = shortcode_atts( array(
            'autoplay' => (string) $ayar['autoplay'],
        ), $atts, self::SHORTCODE );

        // 0 = otomatik geçiş kapalı. Aksi hâlde 1.5–15 sn arasına kırpılır.
        $autoplay = isset( $ham_atts['autoplay'] )
            ? absint( $a['autoplay'] )
            : (int) $ayar['autoplay'];

        if ( $autoplay > 0 ) {
            $autoplay = min( 15000, max( 1500, $autoplay ) );
        }

        $count = count( $banners );

        $show_nav   = ! empty( $ayar['show_nav'] ) && $count > 1;
        $show_dots  = ! empty( $ayar['show_dots'] ) && $count > 1;
        $show_title = ! empty( $ayar['show_title'] );
        $gecis      = class_exists( 'QMO_Banner_Slider_Settings' )
            ? QMO_Banner_Slider_Settings::gecis( $ayar['gecis'] )
            : 'slide';

        $stil = class_exists( 'QMO_Banner_Slider_Settings' ) ? QMO_Banner_Slider_Settings::css_degiskenleri( $ayar ) : '';

        $kok_sinif = 'qmo-banner-root';
        if ( 'fade' === $gecis ) {
            $kok_sinif .= ' is-fade';
        }

        ob_start();
        ?>
<div class="<?php echo esc_attr( $kok_sinif ); ?>" data-qmo-banner-slider data-autoplay="<?php echo esc_attr( (string) $autoplay ); ?>" data-gecis="<?php echo esc_attr( $gecis ); ?>" role="region" aria-roledescription="karusel" aria-label="Kampanya banner'ları"<?php echo '' !== $stil ? ' style="' . esc_attr( $stil ) . '"' : ''; ?>>
    <div class="qmo-banner-viewport">
        <div class="qmo-banner-track" data-qmo-banner-track>
            <?php foreach ( $banners as $index => $banner ) : ?>
                <?php
                $tag   = '' !== $banner['link'] ? 'a' : 'div';
                $label = sprintf( '%d / %d', $index + 1, $count );
                ?>
                <<?php echo $tag; ?> class="qmo-banner-slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
                    <?php if ( 'a' === $tag ) : ?>href="<?php echo esc_url( $banner['link'] ); ?>" rel="noopener"<?php endif; ?>
                    role="group" aria-roledescription="slayt" aria-label="<?php echo esc_attr( $label ); ?>"
                    data-qmo-banner-slide="<?php echo esc_attr( (string) $index ); ?>">
                    <img src="<?php echo esc_url( $banner['img'] ); ?>"
                         srcset="<?php echo esc_attr( $banner['srcset'] ); ?>"
                         sizes="100vw"
                         alt="<?php echo esc_attr( $banner['alt'] ); ?>"
                         class="qmo-banner-img"
                         width="1600" height="900"
                         loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
                         decoding="async">
                    <?php if ( $show_title && '' !== $banner['title'] ) : ?>
                        <span class="qmo-banner-caption">
                            <span class="qmo-banner-title"><?php echo esc_html( $banner['title'] ); ?></span>
                        </span>
                    <?php endif; ?>
                </<?php echo $tag; ?>>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ( $show_nav ) : ?>
        <div class="qmo-banner-nav">
            <button type="button" class="qmo-banner-nav-btn qmo-banner-nav-prev" data-qmo-banner-prev aria-label="Önceki banner">&#8249;</button>
            <button type="button" class="qmo-banner-nav-btn qmo-banner-nav-next" data-qmo-banner-next aria-label="Sonraki banner">&#8250;</button>
        </div>
    <?php endif; ?>

    <?php if ( $show_dots ) : ?>
        <div class="qmo-banner-dots" role="tablist" aria-label="Banner seçimi">
            <?php foreach ( $banners as $index => $banner ) : ?>
                <button type="button"
                        class="qmo-banner-dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
                        aria-label="<?php echo esc_attr( sprintf( '%d. banner', $index + 1 ) ); ?>"
                        data-qmo-banner-dot="<?php echo esc_attr( (string) $index ); ?>"></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script src="<?php echo esc_url( self::script_url() ); ?>" defer></script>
        <?php
        return trim( ob_get_clean() );
    }

    /**
     * Görseli çözülebilen yayınlanmış banner'lar.
     *
     * Görseli olmayan (ya da eki silinmiş) kayıt sessizce atlanır; hiç
     * kalmazsa render_shortcode() boş döner.
     *
     * @return array<int,array{img:string,srcset:string,alt:string,title:string,link:string}>
     */
    private static function build_banner_payloads() {
        $banners = [];

        foreach ( QMO_Banner_CPT::get_published_banners() as $post ) {
            $image_id = (int) get_post_meta( $post->ID, QMO_Banner_CPT::META_IMAGE, true );
            if ( ! $image_id ) continue;

            $img = wp_get_attachment_image_url( $image_id, 'full' );
            if ( ! $img ) continue;

            $alt = (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true );
            if ( '' === trim( $alt ) ) {
                $alt = trim( $post->post_title );
            }

            $srcset = wp_get_attachment_image_srcset( $image_id, 'full' );

            $banners[] = [
                'img'    => $img,
                'srcset' => is_string( $srcset ) ? $srcset : '',
                'alt'    => $alt,
                'title'  => trim( $post->post_title ),
                'link'   => (string) get_post_meta( $post->ID, QMO_Banner_CPT::META_LINK, true ),
            ];
        }

        return $banners;
    }
}
