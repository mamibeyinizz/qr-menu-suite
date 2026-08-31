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
        $oran     = class_exists( 'QMO_Banner_Slider_Settings' ) ? QMO_Banner_Slider_Settings::get()['oran'] : '16:9';
        $oran_css = class_exists( 'QMO_Banner_Slider_Settings' ) ? QMO_Banner_Slider_Settings::oran_css( $oran ) : '16 / 9';

        // Önizleme, ön yüzün BASACAĞI dosyayı gösterir: kırpılmış sürüm
        // varsa o, yoksa orijinal. Yönetim ile ön yüzün ayrışmasının
        // (aynı CSS, farklı kapsayıcı) kökü buydu.
        $odak     = 'merkez';
        $durum    = 'gorsel-yok';
        $odak_css = 'center center';
        $img_url  = '';

        if ( class_exists( 'QMO_Banner_Kirpma' ) ) {
            $odak     = QMO_Banner_Kirpma::banner_odagi( $post->ID );
            $odak_css = QMO_Banner_Kirpma::odak_css( $odak );

            if ( $image_id ) {
                $durum  = QMO_Banner_Kirpma::durum( $image_id, $oran, $odak );
                $gorsel = QMO_Banner_Kirpma::gorsel( $image_id, $oran, $odak );

                if ( $gorsel ) {
                    $img_url = $gorsel['url'];
                }
            }
        } elseif ( $image_id ) {
            $img_url = (string) wp_get_attachment_image_url( $image_id, 'large' );
        }
        ?>
        <div class="qmo-banner-media" data-qmo-banner-media>
            <input type="hidden" name="<?php echo esc_attr( self::META_IMAGE ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-qmo-banner-input />

            <div class="qmo-banner-preview" data-qmo-banner-preview style="aspect-ratio:<?php echo esc_attr( $oran_css ); ?>;max-width:520px;background:#0d0d10;border:1px solid #ccd0d4;border-radius:6px;overflow:hidden;margin-bottom:10px;<?php echo $img_url ? '' : 'display:none;'; ?>">
                <img src="<?php echo esc_url( $img_url ); ?>" alt="" style="display:block;width:100%;height:100%;object-fit:cover;object-position:<?php echo esc_attr( $odak_css ); ?>;" data-qmo-banner-img />
            </div>

            <p>
                <button type="button" class="button" data-qmo-banner-select><?php echo $image_id ? 'Görseli Değiştir' : 'Görsel Seç'; ?></button>
                <button type="button" class="button-link" data-qmo-banner-remove style="<?php echo $image_id ? '' : 'display:none;'; ?>margin-left:8px;color:#b32d2e;">Kaldır</button>
            </p>
            <p class="description"><?php echo esc_html( self::boyut_notu() ); ?></p>

            <?php if ( class_exists( 'QMO_Banner_Kirpma' ) ) : ?>
                <p style="margin-top:14px;">
                    <label for="qmo_banner_odak"><strong>Kırpma odağı</strong></label><br />
                    <select id="qmo_banner_odak" name="<?php echo esc_attr( QMO_Banner_Kirpma::META_ODAK ); ?>" data-qmo-banner-odak>
                        <?php foreach ( QMO_Banner_Kirpma::odaklar() as $odak_anahtar => $odak_bilgi ) : ?>
                            <option value="<?php echo esc_attr( $odak_anahtar ); ?>"
                                    data-odak-css="<?php echo esc_attr( $odak_bilgi['css'] ); ?>"
                                    <?php selected( $odak, $odak_anahtar ); ?>><?php echo esc_html( $odak_bilgi['etiket'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="description">Görsel kaydedilirken sunucu tarafında bu noktadan kırpılır. Kenarda kalan bir yüz/logo kesiliyorsa odağı o kenara alın; yukarıdaki önizleme sonucun aynısını gösterir.</p>

                <?php if ( 'bekliyor' === $durum ) : ?>
                    <p class="description" style="color:#996800;"><strong>Bu görsel <?php echo esc_html( $oran ); ?> oranına göre henüz kırpılmadı.</strong> Kaydettiğinizde otomatik kırpılır; toplu işlem için Kampanya Banner ekranındaki “Tüm görselleri yeniden kırp” düğmesini kullanabilirsiniz.</p>
                <?php elseif ( 'hazir' === $durum ) : ?>
                    <p class="description" style="color:#1a7f37;">Görsel <?php echo esc_html( $oran ); ?> oranına sunucuda kırpıldı; ön yüzde bu sürüm basılıyor.</p>
                <?php elseif ( 'uygun' === $durum ) : ?>
                    <p class="description" style="color:#1a7f37;">Görsel zaten <?php echo esc_html( $oran ); ?> oranında; kırpmaya gerek yok.</p>
                <?php endif; ?>
            <?php endif; ?>
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
            // wp_update_post() kaydı yeniden yazdığı için `save_post_*`
            // kancasını bir kez daha tetikler; o kanca bu metodun kendisidir.
            // Kanca geçici olarak kaldırılmazsa save_meta -> wp_update_post ->
            // save_meta sonsuz özyinelemeye girer ve istek bellek tükenmesiyle
            // wp-admin/post.php üzerinde fatal error verir.
            remove_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save_meta' ] );

            wp_update_post( [
                'ID'         => $post_id,
                'menu_order' => absint( $_POST['menu_order'] ),
            ] );

            add_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save_meta' ] );
        }

        // Kırpma odağı görselden ÖNCE yazılır: aşağıdaki kırpma çağrısı
        // odağı kayıttan okur.
        if ( class_exists( 'QMO_Banner_Kirpma' ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- odak() beyaz listeye çeker.
            $odak = isset( $_POST[ QMO_Banner_Kirpma::META_ODAK ] )
                ? QMO_Banner_Kirpma::odak( wp_unslash( $_POST[ QMO_Banner_Kirpma::META_ODAK ] ) )
                : 'merkez';

            update_post_meta( $post_id, QMO_Banner_Kirpma::META_ODAK, $odak );
        }

        $image_id = isset( $_POST[ self::META_IMAGE ] ) ? absint( $_POST[ self::META_IMAGE ] ) : 0;
        if ( $image_id && self::is_valid_image( $image_id ) ) {
            update_post_meta( $post_id, self::META_IMAGE, $image_id );

            // ASIL KIRPMA BURADA: görsel seçilen en-boy oranına SUNUCUDA
            // kırpılır ve orijinalin yanına ek boyut olarak yazılır. CSS
            // object-fit artık yalnızca güvenlik ağıdır (bkz.
            // QMO_Banner_Kirpma sınıf başlığı). Hata olursa (GD/Imagick yok,
            // dosya okunamıyor) kayıt yine de tamamlanır; yönetimde
            // "kırpılmadı" uyarısı görünür ve ön yüz eski davranışa düşer.
            if ( class_exists( 'QMO_Banner_Kirpma' ) ) {
                QMO_Banner_Kirpma::banner_kirp( $post_id );
            }
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

    /**
     * `wp_ajax_qmo_banner_sira_kaydet` — yönetim listesindeki sıra değişimi.
     *
     * Gelen ID sırası 1, 2, 3… olarak menu_order'a yazılır (ön yüz kısa kodu
     * aynı alanı okur). save_meta nonce'su bu istekte olmadığı için
     * wp_update_post özyinelemesi tetiklenmez.
     *
     * @return void
     */
    public static function ajax_save_order() {
        check_ajax_referer( 'rma_admin_nonce', 'security' );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_send_json_error( array( 'message' => 'yetki' ), 403 );
        }

        $ids = isset( $_POST['order'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['order'] ) ) : array();
        $ids = array_values( array_filter( $ids ) );

        if ( empty( $ids ) ) {
            wp_send_json_error( array( 'message' => 'bos' ), 400 );
        }

        $sira = 1;

        foreach ( $ids as $id ) {
            if ( $id < 1 || get_post_type( $id ) !== self::POST_TYPE ) {
                continue;
            }
            if ( ! current_user_can( 'edit_post', $id ) ) {
                continue;
            }

            wp_update_post(
                array(
                    'ID'         => $id,
                    'menu_order' => $sira,
                )
            );
            ++$sira;
        }

        wp_send_json_success( array( 'count' => $sira - 1 ) );
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
    var odak    = wrap.querySelector('[data-qmo-banner-odak]');
    var frame   = null;

    function apply(id, url) {
        input.value = id ? String(id) : '';
        img.src = url || '';
        preview.style.display = url ? '' : 'none';
        remove.style.display = url ? '' : 'none';
        select.textContent = url ? 'Görseli Değiştir' : 'Görsel Seç';
    }

    // Odak değişince önizleme hemen o kenardan kırpılmış görünür; kayıttan
    // sonra sunucunun ürettiği dosya bunun birebir aynısıdır.
    if (odak) {
        odak.addEventListener('change', function () {
            var secili = odak.options[odak.selectedIndex];
            img.style.objectPosition = (secili && secili.getAttribute('data-odak-css')) || 'center center';
        });
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

        // Eski (henüz sunucuda kırpılmamış) görseller burada da işaretlenir:
        // kullanıcı WordPress'in kendi liste ekranındayken de hangi kaydın
        // yeniden kırpılması gerektiğini görür.
        if ( class_exists( 'QMO_Banner_Kirpma' ) && 'bekliyor' === QMO_Banner_Kirpma::durum( $image_id, null, QMO_Banner_Kirpma::banner_odagi( $post_id ) ) ) {
            echo '<br /><span style="color:#996800;font-size:11px;">Güncel orana kırpılmadı</span>';
        }
    }

    /**
     * Yayınlanmış banner'ları menu_order'a göre döndürür.
     *
     * Ön yüz kısa kodu ve özet kartı bunu kullanır: yalnızca yayındaki
     * kayıtlar. Yönetim listesi get_admin_banners() kullanır.
     *
     * @return WP_Post[]
     */
    public static function get_published_banners() {
        return self::query_banners( array( 'publish' ) );
    }

    /**
     * Yönetim listesi için tüm banner kayıtları (çöp ve otomatik taslak hariç).
     *
     * posts_per_page -1 + nopaging: varsayılan 5/20 sınırı uygulanmaz.
     * Taslaklar da görünür; aksi hâlde "Sıra: 1, 3, 4" boşlukları oluşur.
     *
     * @return WP_Post[]
     */
    public static function get_admin_banners() {
        return self::query_banners( array( 'publish', 'draft', 'pending', 'private', 'future' ) );
    }

    /**
     * Banner CPT sorgusu — tek kaynak (ön yüz ve yönetim aynı sıra alanını
     * okur: menu_order, sonra ID).
     *
     * @param string[] $statuslar WP post_status listesi.
     * @return WP_Post[]
     */
    private static function query_banners( array $statuslar ) {
        $sorgu = new WP_Query(
            array(
                'post_type'              => self::POST_TYPE,
                'post_status'            => $statuslar,
                'posts_per_page'         => -1,
                'nopaging'               => true,
                'orderby'                => array(
                    'menu_order' => 'ASC',
                    'ID'         => 'ASC',
                ),
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            )
        );

        return is_array( $sorgu->posts ) ? $sorgu->posts : array();
    }

    /**
     * Görsel yükleme alanının boyut önerisi — kayıtlı en-boy oranına göre.
     *
     * @return string
     */
    public static function boyut_notu() {
        $oran = '16:9';
        $px   = array( 1600, 900 );

        if ( class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            $oran = QMO_Banner_Slider_Settings::get()['oran'];
            $px   = QMO_Banner_Slider_Settings::onerilen_px( $oran );
        }

        return sprintf(
            'Önerilen boyut: %dx%dpx (%s), JPG/WEBP, maksimum 300KB. Farklı orandaki görseller kaydedilirken SUNUCUDA bu orana kırpılır; orijinal dosyanız korunur.',
            (int) $px[0],
            (int) $px[1],
            $oran
        );
    }
}
