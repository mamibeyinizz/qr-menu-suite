<?php
/**
 * Ürün Vitrini — frontend kısa kodu, varlıkları ve Elementor widget'ı.
 *
 * `[qrms_urun_vitrini id="3"]`
 *
 * Vitrin, "Menü Görünümü" sayfasındaki tema ayarlarından BAĞIMSIZDIR: kendi
 * `--qrms-vitrin-*` değişkenleriyle gelir, menü renk/yazı tipi option'larını
 * hiç okumaz. Böylece restoran sahibi vitrini menüden ayrı bir yere (ana
 * sayfa, kampanya sayfası) koyabilir ve menü temasını değiştirdiğinde
 * vitrin bozulmaz.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Vitrin_Shortcode' ) ) :

class RMA_Vitrin_Shortcode {

    const SHORTCODE = 'qrms_urun_vitrini';

    /** Varlıklar sayfada en fazla bir kez kuyruğa alınır. */
    private static $assets_loaded = false;

    /**
     * Kancaları kaydeder.
     *
     * @return void
     */
    public static function init() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
    }

    /**
     * Kısa kod sayfa içeriğinde geçiyorsa varlıkları önden kuyruğa alır.
     *
     * Elementor widget'ı gibi içerikte görünmeyen kullanımlarda render()
     * kendi enqueue'sunu yapar; bu yüzden burada bulunamaması sorun değil.
     * (Deseni: QMO_Shortcode_Slider::maybe_enqueue_assets)
     *
     * @return void
     */
    public static function maybe_enqueue_assets() {
        if ( self::$assets_loaded ) {
            return;
        }

        $post = get_post();

        if ( ! $post instanceof WP_Post ) {
            return;
        }

        if ( ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
            return;
        }

        self::enqueue_assets();
    }

    /**
     * CSS/JS'i kuyruğa alır.
     *
     * @return void
     */
    private static function enqueue_assets() {
        if ( self::$assets_loaded ) {
            return;
        }

        self::$assets_loaded = true;

        $url = RMA_PLUGIN_URL . 'assets/';

        // Sürüm dosyanın son değişiklik zamanından gelir. Suite yoksa (eski
        // tekil eklenti kurulumu) yardımcı sınıf da yoktur; o zaman dosyanın
        // kendi filemtime'ı doğrudan kullanılır.
        $vitrin = static function ( $dosya ) {
            if ( class_exists( 'QRMS_Helpers' ) ) {
                return QRMS_Helpers::asset_version( 'modules/restoran-menu/assets/' . $dosya );
            }

            $yol = RMA_PLUGIN_DIR . 'assets/' . $dosya;

            return is_readable( $yol ) ? (string) filemtime( $yol ) : '1.0.0';
        };

        wp_enqueue_style( 'rma-detail-modal', $url . 'css/rma-detail-modal.css', array(), $vitrin( 'css/rma-detail-modal.css' ) );
        wp_enqueue_script( 'rma-detail-modal', $url . 'js/rma-detail-modal.js', array(), $vitrin( 'js/rma-detail-modal.js' ), true );
        self::enqueue_modal_config();

        wp_enqueue_style( 'rma-vitrin', $url . 'css/vitrin.css', array(), $vitrin( 'css/vitrin.css' ) );
        wp_enqueue_script( 'rma-vitrin', $url . 'js/vitrin.js', array( 'rma-detail-modal' ), $vitrin( 'js/vitrin.js' ), true );
    }

    /**
     * Ürün detay modalının AJAX yapılandırmasını sayfaya basar.
     *
     * Kartlar tıklandığında `rma-detail-modal.js` bu global'i okuyarak
     * ana menüyle AYNI uç noktayı (`rma_get_product_details`) çağırır —
     * bkz. modules/restoran-menu/includes/trait-ajax.php.
     *
     * @return void
     */
    private static function enqueue_modal_config() {
        $config = array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rma_ajax_nonce' ),
            'lang'    => function_exists( 'rma_get_current_lang' ) ? rma_get_current_lang() : 'tr',
            'i18n'    => array(
                'kapat' => function_exists( 'qmo_ceviri_ui' ) ? qmo_ceviri_ui( __( 'Kapat', 'qrms' ) ) : __( 'Kapat', 'qrms' ),
            ),
        );

        wp_add_inline_script(
            'rma-detail-modal',
            'window.RMA_MODAL_CFG = ' . wp_json_encode( $config ) . ';',
            'before'
        );
    }

    /**
     * Kart yazı tipi Google Fonts'tan geliyorsa onu kuyruğa alır.
     *
     * Tema fontu, sistem yığını ve Georgia için HİÇBİR istek yapılmaz —
     * ayar eklenmeden önceki davranış (vitrin hiç font indirmez) bu
     * seçeneklerde aynen korunur. Aile spec'i RMA_Vitrin_DB::yazi_tipleri()
     * içinde menü modülüyle birebir aynı yazıldığı için, menü de aynı
     * sayfadaysa tarayıcı aynı adresi ikinci kez indirmez.
     *
     * @param string $anahtar yazi_tipleri() anahtarı.
     * @return void
     */
    private static function maybe_enqueue_font( $anahtar ) {
        $tipler = RMA_Vitrin_DB::yazi_tipleri();

        if ( ! isset( $tipler[ $anahtar ] ) || '' === $tipler[ $anahtar ]['google'] ) {
            return;
        }

        wp_enqueue_style(
            'rma-vitrin-fonts',
            'https://fonts.googleapis.com/css2?family=' . $tipler[ $anahtar ]['google'] . '&display=swap',
            array(),
            null
        );
    }

    /**
     * Kısa kodu render eder.
     *
     * @param array $atts Kısa kod nitelikleri.
     * @return string
     */
    public static function render( $atts ) {
        $a = shortcode_atts( array( 'id' => 0 ), $atts, self::SHORTCODE );

        $vitrin = RMA_Vitrin_DB::getir( (int) $a['id'] );

        if ( ! $vitrin || 'active' !== $vitrin->status ) {
            return '';
        }

        $urunler = self::urunleri_hazirla( RMA_Vitrin_DB::urun_idleri( (int) $vitrin->id ), (int) $vitrin->show_price );

        if ( empty( $urunler ) ) {
            return '';
        }

        self::enqueue_assets();

        $sutun        = (int) $vitrin->grid_columns;
        $satir        = (int) $vitrin->grid_rows;
        $mobil_sutun  = (int) $vitrin->mobile_columns;
        $mobil_satir  = (int) $vitrin->mobile_rows;

        // Sütun/satır sayısı grid YAPISINI belirler; boşluk, min-genişlik ve
        // görsel oranı ise kart BOYUTUNU — ikisi birbirini bozmadan, ayrı
        // CSS değişkenleriyle taşınır (bkz. vitrin.css).
        $masaustu_bosluk = (int) $vitrin->desktop_gap;
        $masaustu_min    = (int) $vitrin->desktop_card_min;
        $masaustu_oran   = (int) $vitrin->desktop_image_ratio;
        $mobil_bosluk    = (int) $vitrin->mobile_gap;
        $mobil_min       = (int) $vitrin->mobile_card_min;
        $mobil_oran      = (int) $vitrin->mobile_image_ratio;

        $stil = sprintf(
            '--qrms-vitrin-cols:%1$d;--qrms-vitrin-rows:%2$d;--qrms-vitrin-mobile-cols:%3$d;--qrms-vitrin-mobile-rows:%4$d;'
            . '--qrms-vitrin-gap:%5$dpx;--qrms-vitrin-card-min:%6$dpx;--qrms-vitrin-image-ratio:%7$d;'
            . '--qrms-vitrin-mobile-gap:%8$dpx;--qrms-vitrin-mobile-card-min:%9$dpx;--qrms-vitrin-mobile-image-ratio:%10$d;',
            $sutun,
            $satir,
            $mobil_sutun,
            $mobil_satir,
            $masaustu_bosluk,
            $masaustu_min,
            $masaustu_oran,
            $mobil_bosluk,
            $mobil_min,
            $mobil_oran
        );

        // Kart yazı tipi ayarları ("5. Yazı Tipi" adımı). Boyut/kalınlık/
        // hizalama masaüstü ve mobil için AYRI; font ailesi ve renkler
        // cihazdan bağımsız tek ayardır. Mobil değerler --*-mobile
        // değişkenlerine yazılır, breakpoint'te vitrin.css onları temel
        // değişkenlere çevirir — kart boyutu ayarlarındaki desenin aynısı.
        $stil .= sprintf(
            '--qrms-vitrin-title-size:%1$dpx;--qrms-vitrin-title-weight:%2$d;--qrms-vitrin-title-align:%3$s;'
            . '--qrms-vitrin-title-size-mobile:%4$dpx;--qrms-vitrin-title-weight-mobile:%5$d;--qrms-vitrin-title-align-mobile:%6$s;'
            . '--qrms-vitrin-price-size:%7$dpx;--qrms-vitrin-price-weight:%8$d;--qrms-vitrin-price-justify:%9$s;'
            . '--qrms-vitrin-price-size-mobile:%10$dpx;--qrms-vitrin-price-weight-mobile:%11$d;--qrms-vitrin-price-justify-mobile:%12$s;',
            (int) RMA_Vitrin_DB::ayar( $vitrin, 'title_size' ),
            (int) RMA_Vitrin_DB::ayar( $vitrin, 'title_weight' ),
            RMA_Vitrin_DB::hizalama( RMA_Vitrin_DB::ayar( $vitrin, 'title_align' ) ),
            (int) RMA_Vitrin_DB::ayar( $vitrin, 'title_size_mobile' ),
            (int) RMA_Vitrin_DB::ayar( $vitrin, 'title_weight_mobile' ),
            RMA_Vitrin_DB::hizalama( RMA_Vitrin_DB::ayar( $vitrin, 'title_align_mobile' ) ),
            (int) RMA_Vitrin_DB::ayar( $vitrin, 'price_size' ),
            (int) RMA_Vitrin_DB::ayar( $vitrin, 'price_weight' ),
            RMA_Vitrin_DB::hizalama_justify( RMA_Vitrin_DB::ayar( $vitrin, 'price_align' ) ),
            (int) RMA_Vitrin_DB::ayar( $vitrin, 'price_size_mobile' ),
            (int) RMA_Vitrin_DB::ayar( $vitrin, 'price_weight_mobile' ),
            RMA_Vitrin_DB::hizalama_justify( RMA_Vitrin_DB::ayar( $vitrin, 'price_align_mobile' ) )
        );

        // Boş bırakılan alanlarda vitrin.css'in kendi varsayılanı geçerli
        // kalır (tema fontu / --qrms-vitrin-text / --qrms-vitrin-accent).
        $font_stack = RMA_Vitrin_DB::yazi_tipi_stack( RMA_Vitrin_DB::ayar( $vitrin, 'title_font' ) );

        if ( '' !== $font_stack ) {
            $stil .= sprintf( '--qrms-vitrin-card-font:%s;', $font_stack );
        }

        $baslik_rengi = (string) RMA_Vitrin_DB::ayar( $vitrin, 'title_color' );
        $fiyat_rengi  = (string) RMA_Vitrin_DB::ayar( $vitrin, 'price_color' );

        if ( '' !== $baslik_rengi ) {
            $stil .= sprintf( '--qrms-vitrin-title-color:%s;', $baslik_rengi );
        }

        if ( '' !== $fiyat_rengi ) {
            $stil .= sprintf( '--qrms-vitrin-price-color:%s;', $fiyat_rengi );
        }

        self::maybe_enqueue_font( (string) RMA_Vitrin_DB::ayar( $vitrin, 'title_font' ) );

        // Boşsa vitrin.css'in kendi --qrms-vitrin-bg varsayılanı (şeffaf)
        // geçerli kalır; admin'de renk seçilmişse burada ezilir.
        if ( '' !== (string) $vitrin->bg_color ) {
            $stil .= sprintf( '--qrms-vitrin-bg:%s;', $vitrin->bg_color );
        }

        // Kartlar TEK bir düz liste olarak basılır; kaç tanesinin yan yana
        // görüneceğine CSS karar verir (dar ekranda sütun sayısı düşer).
        // Sunucuda "sayfa"lara bölmek, mobilde bir sayfanın 4 kartı alt alta
        // yığması demek olurdu — bin pikselden uzun bir vitrin.
        ob_start();
        ?>
<div class="qrms-vitrin qrms-vitrin-fullwidth"
     data-qrms-vitrin
     data-autoplay="<?php echo (int) $vitrin->autoplay; ?>"
     data-speed="<?php echo (int) $vitrin->autoplay_speed; ?>"
     data-drag="<?php echo (int) $vitrin->drag_enabled; ?>"
     style="<?php echo esc_attr( $stil ); ?>"
     role="region"
     aria-roledescription="karusel"
     aria-label="<?php echo esc_attr( $vitrin->title ); ?>">

    <div class="qrms-vitrin-viewport" data-qrms-viewport tabindex="0"
         aria-label="<?php echo esc_attr( sprintf( qmo_ceviri_ui( __( '%d ürün — kaydırarak gezinin', 'qrms' ) ), count( $urunler ) ) ); ?>">
        <?php foreach ( $urunler as $urun ) : ?>
            <article class="qrms-vitrin-card<?php echo ! empty( $urun['tukendi'] ) ? ' is-tukendi' : ''; ?>"
                      data-id="<?php echo (int) $urun['id']; ?>"
                      tabindex="0" role="button"
                      aria-label="<?php echo esc_attr( $urun['title'] ); ?>">
                <div class="qrms-vitrin-media">
                    <?php if ( '' !== $urun['img'] ) : ?>
                        <img src="<?php echo esc_url( $urun['img'] ); ?>"
                             alt="<?php echo esc_attr( $urun['title'] ); ?>"
                             class="qrms-vitrin-img"
                             loading="lazy" decoding="async">
                    <?php else : ?>
                        <span class="qrms-vitrin-img qrms-vitrin-img-empty" aria-hidden="true">◆</span>
                    <?php endif; ?>
                    <?php if ( ! empty( $urun['tukendi'] ) ) : ?>
                        <span class="rma-tukendi-rozet"><?php echo esc_html( $urun['tukendi_etiket'] ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="qrms-vitrin-body">
                    <h3 class="qrms-vitrin-title"><?php echo esc_html( $urun['title'] ); ?></h3>
                    <?php if ( '' !== $urun['price_html'] ) : ?>
                        <p class="qrms-vitrin-price"><?php echo wp_kses_post( $urun['price_html'] ); ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php
    /*
     * Denetimler `hidden` başlar ve yalnızca vitrin.js onları açar: script
     * yüklenmezse çalışmayan butonlar görünmesin. JS olmadan da vitrin
     * parmakla/scroll ile kaydırılabilir kalır — kaydırma tamamen CSS'in işi.
     *
     * Sayfa sayısı ekran genişliğine göre değiştiği için noktalar sunucuda
     * değil, vitrin.js tarafından üretilir.
     */
    ?>
    <button type="button" class="qrms-vitrin-nav qrms-vitrin-prev" data-qrms-prev aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Önceki', 'qrms' ) ) ); ?>" hidden>&#8249;</button>
    <button type="button" class="qrms-vitrin-nav qrms-vitrin-next" data-qrms-next aria-label="<?php echo esc_attr( qmo_ceviri_ui( __( 'Sonraki', 'qrms' ) ) ); ?>" hidden>&#8250;</button>

    <div class="qrms-vitrin-dots" data-qrms-dots hidden></div>
</div>
        <?php
        return trim( (string) ob_get_clean() );
    }

    /**
     * Ürün ID'lerinden render'a hazır kart verisi üretir.
     *
     * Veri CPT'den CANLI okunur — vitrin tablosunda kopyası tutulmaz.
     * Süzme kuralı shortcode-slider.php'dekiyle aynı: yayında olmayan,
     * silinmiş ya da menüde gizlenmiş (`rma_active !== '1'`) ürün vitrine
     * de düşmez.
     *
     * @param int[] $idler      Sıralı ürün ID listesi.
     * @param int   $fiyat_goster 1 ise fiyat hazırlanır.
     * @return array<int,array{id:int,title:string,price_html:string,img:string,tukendi:bool,tukendi_etiket:string}>
     */
    private static function urunleri_hazirla( array $idler, $fiyat_goster ) {
        if ( empty( $idler ) ) {
            return array();
        }

        // Tek sorgu + toplu cache ısıtma: ürün başına ek sorgu doğmasın.
        $sorgu = new WP_Query(
            array(
                'post_type'              => 'rma_menu_item',
                'post_status'            => 'publish',
                'post__in'               => $idler,
                'posts_per_page'         => count( $idler ),
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            )
        );

        if ( $sorgu->posts && function_exists( 'update_post_thumbnail_cache' ) ) {
            update_post_thumbnail_cache( $sorgu );
        }

        $havuz = array();

        foreach ( $sorgu->posts as $post ) {
            $havuz[ (int) $post->ID ] = $post;
        }

        $kartlar = array();

        // Sıra vitrin tablosundan gelir; WP_Query'nin döndürdüğü sıra değil.
        foreach ( $idler as $id ) {
            if ( ! isset( $havuz[ $id ] ) ) {
                continue;
            }

            if ( '1' !== (string) get_post_meta( $id, 'rma_active', true ) ) {
                continue;
            }

            $tukendi = RMA_Tukendi::urun_tukendi( $id );

            $kartlar[] = array(
                'id'             => $id,
                'title'          => get_the_title( $id ),
                'price_html'     => $fiyat_goster ? self::fiyat_html( $id ) : '',
                'img'            => (string) ( get_the_post_thumbnail_url( $id, 'medium' ) ?: '' ),
                'tukendi'        => $tukendi,
                'tukendi_etiket' => $tukendi ? RMA_Tukendi::etiket() : '',
            );
        }

        return $kartlar;
    }

    /**
     * Ürünün fiyat HTML'i.
     *
     * Aktif fiyat kampanyası, kombin fiyatı ve düz fiyat dallarının hepsi
     * RMA_Kampanya::fiyat_html() içinde tek yerde çözülür.
     *
     * @param int $id Ürün ID.
     * @return string
     */
    private static function fiyat_html( $id ) {
        return RMA_Kampanya::fiyat_html( $id );
    }
}

endif;

/* =====================================================================
   ELEMENTOR WIDGET
   Deseni includes/class-elementor-widget.php ile aynı: widget yalnızca
   kısa kodu çağırır, render mantığı tek yerde kalır.
===================================================================== */

add_action( 'elementor/widgets/register', 'rma_vitrin_register_elementor_widget' );

/**
 * Vitrin widget'ını Elementor'a kaydeder.
 *
 * @param mixed $widgets_manager Elementor widget yöneticisi.
 * @return void
 */
function rma_vitrin_register_elementor_widget( $widgets_manager ) {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
        return;
    }

    if ( ! class_exists( 'RMA_Elementor_Vitrin_Widget' ) ) {

        class RMA_Elementor_Vitrin_Widget extends \Elementor\Widget_Base {

            public function get_name()       { return 'rma_vitrin_widget'; }
            public function get_title()      { return 'Ürün Vitrini'; }
            public function get_icon()       { return 'eicon-slider-push'; }
            public function get_categories() { return array( 'general' ); }

            protected function register_controls() {
                $this->start_controls_section(
                    'content_section',
                    array(
                        'label' => 'Vitrin',
                        'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
                    )
                );

                $secenekler = array( 0 => '— Vitrin seçin —' );

                foreach ( RMA_Vitrin_DB::hepsi() as $vitrin ) {
                    $secenekler[ (int) $vitrin->id ] = $vitrin->title . ' (' . (int) $vitrin->urun_sayisi . ' ürün)';
                }

                $this->add_control(
                    'vitrin_id',
                    array(
                        'label'   => 'Gösterilecek vitrin',
                        'type'    => \Elementor\Controls_Manager::SELECT,
                        'options' => $secenekler,
                        'default' => 0,
                    )
                );

                $this->end_controls_section();
            }

            protected function render() {
                $ayarlar = $this->get_settings_for_display();
                $id      = isset( $ayarlar['vitrin_id'] ) ? (int) $ayarlar['vitrin_id'] : 0;

                if ( ! $id ) {
                    return;
                }

                echo do_shortcode( '[' . RMA_Vitrin_Shortcode::SHORTCODE . ' id="' . $id . '"]' );
            }
        }
    }

    $widgets_manager->register( new RMA_Elementor_Vitrin_Widget() );
}
