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

        if ( has_shortcode( $post->post_content, self::SHORTCODE ) ) {
            self::enqueue_styles();
            return;
        }

        // Elementor'un Shortcode widget'ına (veya bir Global Widget/Şablon
        // Ekle referansına — bkz. rma_elementor_data_contains()) yazılan
        // kısa kod post_content'te görünmez; render_shortcode() içindeki
        // geç (wp_head sonrası) yedek çağrıya düşmemesi için _elementor_data
        // de taranır.
        if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
            $data = get_post_meta( $post->ID, '_elementor_data', true );
            if ( rma_elementor_data_contains( self::SHORTCODE, $data ) ) {
                self::enqueue_styles();
            }
        }
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
        $ayar = class_exists( 'QMO_Banner_Slider_Settings' )
            ? QMO_Banner_Slider_Settings::get()
            : array(
                'show_nav'   => 0,
                'show_dots'  => 1,
                'show_title' => 0,
                'gecis'      => 'slide',
                'autoplay'   => 4500,
                'oran'       => '16:9',
            );

        $banners = self::payloadlar( $ayar );
        if ( empty( $banners ) ) return '';

        self::enqueue_styles();

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

        return self::kok_html( $banners, $ayar, array( 'autoplay' => $autoplay ) );
    }

    /**
     * Frontend HTML'ini üreten TEK yer.
     *
     * render_shortcode() ve AJAX önizleme ucu (bkz.
     * RMA_Kampanya_Banner_Admin_Trait::ajax_banner_onizleme()) BURAYA
     * düşer; ikinci bir HTML üretim yolu yoktur. `$ayar` kaydedilmiş
     * ayarlar OLMAK ZORUNDA DEĞİLDİR — önizleme ucu henüz kaydedilmemiş
     * oran/oran_mobil/oran_mobil_farkli değerleriyle çağırır.
     *
     * @param array $banners payloadlar() çıktısı.
     * @param array $ayar    QMO_Banner_Slider_Settings::get() şeklinde bir dizi.
     * @param array $opsiyon {
     *     @type int  $autoplay Otomatik geçiş (ms); verilmezse $ayar['autoplay'].
     *     @type bool $betik    Alt kısımdaki <script> etiketi basılsın mı (varsayılan true).
     * }
     * @return string
     */
    public static function kok_html( array $banners, array $ayar, array $opsiyon = array() ) {
        $count = count( $banners );

        $autoplay = isset( $opsiyon['autoplay'] ) ? (int) $opsiyon['autoplay'] : (int) ( $ayar['autoplay'] ?? 0 );
        $betik    = ! isset( $opsiyon['betik'] ) || $opsiyon['betik'];

        $show_nav   = ! empty( $ayar['show_nav'] ) && $count > 1;
        $show_dots  = ! empty( $ayar['show_dots'] ) && $count > 1;
        $show_title = ! empty( $ayar['show_title'] );
        $gecis      = class_exists( 'QMO_Banner_Slider_Settings' )
            ? QMO_Banner_Slider_Settings::gecis( $ayar['gecis'] ?? 'slide' )
            : 'slide';

        $stil = class_exists( 'QMO_Banner_Slider_Settings' ) ? QMO_Banner_Slider_Settings::css_degiskenleri( $ayar ) : '';

        // width/height ipucu CSS --qmo-banner-oran ile aynı oranda olsun;
        // aksi hâlde her <img> 1600×900 (16:9) basılır, 3:1 viewport'ta
        // 2. ve 3. slaytların intrinsic kutusu patlardı.
        $img_w = 1600;
        $img_h = 900;
        if ( class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            $oran_px = QMO_Banner_Slider_Settings::onerilen_px( $ayar['oran'] ?? null );
            $img_w   = (int) $oran_px[0];
            $img_h   = (int) $oran_px[1];
        }

        // Mobil oran masaüstünden farklıysa dar ekrana AYRI dosya verilir.
        // Kırılım CSS'teki @media kuralıyla AYNI referansa (viewport) ve aynı
        // sayıya bağlıdır — tek kaynak MOBIL_KIRILIM. Kapsayıcı sorgusu
        // kullanılamaz: <source media> yalnızca viewport sorgusu kabul eder,
        // ikisi ayrı referansa bakarsa kutu ile dosya çelişir.
        $mobil_medya = class_exists( 'QMO_Banner_Slider_Settings' )
            ? QMO_Banner_Slider_Settings::mobil_medya()
            : '(max-width: 720px)';
        $mobil_var   = class_exists( 'QMO_Banner_Slider_Settings' ) && QMO_Banner_Slider_Settings::mobil_oran_farkli( $ayar );

        $kok_sinif = 'qmo-banner-root';
        if ( 'fade' === $gecis ) {
            $kok_sinif .= ' is-fade';
        }

        // Peek (komşu slaytların kenarının görünmesi) yalnızca birden fazla
        // banner varken anlamlıdır: tek banner'da yanlarda gösterilecek
        // komşu olmadığı için iki yanda boş koyu şerit kalırdı. Solma
        // modunda CSS peek'i zaten devre dışı bırakır
        // (bkz. frontend-banner-slider.css).
        if ( $count > 1 ) {
            $kok_sinif .= ' is-peek';
        }

        ob_start();
        ?>
<div class="<?php echo esc_attr( $kok_sinif ); ?>" data-qmo-banner-slider data-autoplay="<?php echo esc_attr( (string) $autoplay ); ?>" data-gecis="<?php echo esc_attr( $gecis ); ?>" role="region" aria-roledescription="karusel" aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Kampanya banner\'ları', 'qrms' ) ) ); ?>"<?php echo '' !== $stil ? ' style="' . esc_attr( $stil ) . '"' : ''; ?>>
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
                    <?php $mobil_kaynak = $mobil_var && '' !== $banner['mobil_img']; ?>
                    <?php if ( $mobil_kaynak ) : ?><picture class="qmo-banner-picture"><source media="<?php echo esc_attr( $mobil_medya ); ?>" srcset="<?php echo esc_url( $banner['mobil_img'] ); ?>"><?php endif; ?>
                    <img src="<?php echo esc_url( $banner['img'] ); ?>"
                         <?php if ( '' !== $banner['srcset'] ) : ?>srcset="<?php echo esc_attr( $banner['srcset'] ); ?>" sizes="100vw"<?php endif; ?>
                         alt="<?php echo esc_attr( $banner['alt'] ); ?>"
                         class="qmo-banner-img"
                         <?php if ( '' !== $banner['odak'] ) : ?>style="object-position:<?php echo esc_attr( $banner['odak'] ); ?>;"<?php endif; ?>
                         width="<?php echo (int) $img_w; ?>" height="<?php echo (int) $img_h; ?>"
                         loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
                         decoding="async"><?php if ( $mobil_kaynak ) : ?></picture><?php endif; ?>
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
            <button type="button" class="qmo-banner-nav-btn qmo-banner-nav-prev" data-qmo-banner-prev aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Önceki banner', 'qrms' ) ) ); ?>">&#8249;</button>
            <button type="button" class="qmo-banner-nav-btn qmo-banner-nav-next" data-qmo-banner-next aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Sonraki banner', 'qrms' ) ) ); ?>">&#8250;</button>
        </div>
    <?php endif; ?>

    <?php if ( $show_dots ) : ?>
        <div class="qmo-banner-dots" role="tablist" aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Banner seçimi', 'qrms' ) ) ); ?>">
            <?php foreach ( $banners as $index => $banner ) : ?>
                <button type="button"
                        class="qmo-banner-dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
                        aria-label="<?php echo esc_attr( sprintf( qmo_ceviri_ui( __( '%d. banner', 'qrms' ) ), $index + 1 ) ); ?>"
                        data-qmo-banner-dot="<?php echo esc_attr( (string) $index ); ?>"></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php if ( $betik ) : ?>
<script src="<?php echo esc_url( self::script_url() ); ?>" defer></script>
<?php endif; ?>
        <?php
        return trim( ob_get_clean() );
    }

    /**
     * Görseli çözülebilen yayınlanmış banner'lar.
     *
     * Görseli olmayan (ya da eki silinmiş) kayıt sessizce atlanır; hiç
     * kalmazsa render_shortcode() boş döner.
     *
     * AJAX önizleme ucu (bkz. ajax_banner_onizleme()) henüz KAYDEDİLMEMİŞ
     * oran/oran_mobil değerleriyle çağırabilsin diye `$ayar` dışarıdan
     * verilebilir; verilmezse kayıtlı ayar okunur — davranış eskisiyle
     * birebir aynıdır.
     *
     * @param array|null $ayar QMO_Banner_Slider_Settings::get() şeklinde bir dizi; null ise kayıtlı ayar okunur.
     * @return array<int,array{img:string,srcset:string,alt:string,title:string,link:string,odak:string,mobil_img:string}>
     */
    public static function payloadlar( $ayar = null ) {
        $banners = [];
        $temel   = class_exists( 'QMO_Banner_Slider_Settings' )
            ? QMO_Banner_Slider_Settings::get()
            : array( 'oran' => '16:9' );
        $ayar    = is_array( $ayar ) ? array_merge( $temel, $ayar ) : $temel;
        $oran    = class_exists( 'QMO_Banner_Slider_Settings' )
            ? QMO_Banner_Slider_Settings::oran( $ayar['oran'] ?? '16:9' )
            : (string) ( $ayar['oran'] ?? '16:9' );

        // Mobil oran masaüstüyle aynıysa ikinci bir çözümleme hiç yapılmaz:
        // ne ek sorgu, ne ek dosya, ne <picture>.
        $mobil_oran = ( class_exists( 'QMO_Banner_Slider_Settings' ) && QMO_Banner_Slider_Settings::mobil_oran_farkli( $ayar ) )
            ? QMO_Banner_Slider_Settings::oran_mobil( $ayar )
            : '';

        foreach ( QMO_Banner_CPT::get_published_banners() as $post ) {
            $image_id = (int) get_post_meta( $post->ID, QMO_Banner_CPT::META_IMAGE, true );
            if ( ! $image_id ) continue;

            // Sunucuda kırpılmış sürüm varsa ON U basılır: tüm slaytlar
            // dosya düzeyinde aynı orandadır, dolayısıyla hepsi aynı
            // biçimde görünür. Kırpma yoksa (eski kayıt ya da görsel
            // zaten doğru oranda) orijinale düşülür.
            $kirpildi  = false;
            $odak_css  = 'center center';
            $img       = '';
            $mobil_img = '';

            if ( class_exists( 'QMO_Banner_Kirpma' ) ) {
                $odak     = QMO_Banner_Kirpma::banner_odagi( $post->ID );
                $odak_css = QMO_Banner_Kirpma::odak_css( $odak );
                $gorsel   = QMO_Banner_Kirpma::gorsel( $image_id, $oran, $odak );

                if ( $gorsel ) {
                    $img      = $gorsel['url'];
                    $kirpildi = ! empty( $gorsel['kirpildi'] );
                }

                // Mobil sürüm, MOBİL ORANA GERÇEKTEN UYAN dosyadır:
                // kırpılmış sürüm varsa o, kaynak zaten mobil orandaysa
                // orijinalin kendisi. Ölçüt "yeni bir kırpma dosyası
                // üretildi mi" DEĞİLDİR — natif 4:3 bir görselde kırpma
                // üretilmez ama orijinal zaten doğrudur ve kullanılmalıdır.
                // Uymayan dosya (kırpma bekleniyor) null döner; o zaman
                // <source> hiç basılmaz ve tarayıcı masaüstü dosyasına
                // düşer — yönetimde de "bekliyor" uyarısı görünür.
                if ( '' !== $mobil_oran ) {
                    $mobil = QMO_Banner_Kirpma::oranli_gorsel( $image_id, $mobil_oran, $odak );

                    if ( $mobil && '' !== $mobil['url'] ) {
                        $mobil_img = (string) $mobil['url'];
                    }
                }
            }

            if ( '' === $img ) {
                $img = (string) wp_get_attachment_image_url( $image_id, 'full' );
            }

            if ( '' === $img ) continue;

            // Mobil dosya masaüstüyle aynıysa <source> basmanın faydası yok
            // (kaynak her iki orana da uyuyor demektir); gereksiz bir
            // kaynak adayı üretmeden tek <img> ile devam edilir.
            if ( $mobil_img === $img ) {
                $mobil_img = '';
            }

            $alt = (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true );
            if ( '' === trim( $alt ) ) {
                $alt = trim( $post->post_title );
            }

            // srcset YALNIZCA kırpılmamış orijinal basılırken anlamlıdır:
            // adayların hepsi orijinalle aynı orandadır. Kırpılmış sürüm
            // tek dosyadır; orijinalin srcset'iyle karıştırılırsa tarayıcı
            // farklı oranda bir adayı seçip kırpma tutarlılığını bozardı.
            $srcset = $kirpildi ? '' : wp_get_attachment_image_srcset( $image_id, 'full' );

            $banners[] = [
                'img'    => $img,
                'srcset' => is_string( $srcset ) ? $srcset : '',
                'alt'    => $alt,
                'title'  => trim( $post->post_title ),
                'link'   => (string) get_post_meta( $post->ID, QMO_Banner_CPT::META_LINK, true ),
                // Kırpılmış dosyada kadraj zaten doğru; object-position
                // yalnızca henüz kırpılmamış eski görsellerde işe yarar.
                'odak'   => $kirpildi ? '' : $odak_css,
                // Dar ekrana verilecek ayrı dosya; mobil oran masaüstüyle
                // aynıysa ya da kırpma henüz üretilmemişse boş kalır.
                'mobil_img' => $mobil_img,
            ];
        }

        return $banners;
    }

}
