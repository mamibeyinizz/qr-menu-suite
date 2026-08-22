<?php
/**
 * Ürün Vitrini — yönetim ekranı.
 *
 * Liste görünümü (vitrinler + shortcode) ve düzenleme formu. Kaydetme AJAX
 * değil, `admin_post_` + nonce + redirect ile yapılır (modüldeki
 * `admin_post_rma_export_menu` deseninin aynısı): ürün sırası da formla
 * birlikte gittiği için ayrı bir uç noktaya gerek yok, ve kullanıcı
 * "kaydettim mi?" sorusunu sormaz.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Vitrin_Admin_Trait {

    /** Kaydetme/silme formlarının nonce eylemi. */
    private $vitrin_nonce_action = 'rma_vitrin_kaydet';

    /**
     * Vitrin ekranının adresi.
     *
     * @param array $args Ek query arg'ları.
     * @return string
     */
    private function vitrin_url( array $args = array() ) {
        return add_query_arg(
            array_merge( array( 'page' => 'qrms-rm-vitrin' ), $args ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * Vitrin ekranı: `id` varsa düzenleme formu, yoksa liste.
     *
     * @return void
     */
    public function render_showcase_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $duzenle = isset( $_GET['vitrin'] ) ? sanitize_text_field( wp_unslash( $_GET['vitrin'] ) ) : '';

        if ( 'yeni' === $duzenle ) {
            $this->render_vitrin_form( null );
            return;
        }

        if ( '' !== $duzenle && ctype_digit( $duzenle ) ) {
            $kayit = RMA_Vitrin_DB::getir( (int) $duzenle );

            if ( $kayit ) {
                $this->render_vitrin_form( $kayit );
                return;
            }
        }

        $this->render_vitrin_list();
    }

    /**
     * Kaydetme/silme sonrası bildirimi basar.
     *
     * @return void
     */
    private function vitrin_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $durum = isset( $_GET['vitrin_msg'] ) ? sanitize_key( wp_unslash( $_GET['vitrin_msg'] ) ) : '';

        $mesajlar = array(
            'kaydedildi' => array( 'success', 'Vitrin kaydedildi.' ),
            'silindi'    => array( 'success', 'Vitrin silindi.' ),
            'hata'       => array( 'error', 'Vitrin kaydedilemedi. Lütfen tekrar deneyin.' ),
        );

        if ( ! isset( $mesajlar[ $durum ] ) ) {
            return;
        }

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr( $mesajlar[ $durum ][0] ),
            esc_html( $mesajlar[ $durum ][1] )
        );
    }

    /* -----------------------------------------------------------------
       LİSTE
    ----------------------------------------------------------------- */

    /**
     * Vitrin listesi: shortcode, ürün sayısı, düzenle/sil.
     *
     * @return void
     */
    private function render_vitrin_list() {
        $this->page_header(
            'Ürün Vitrini',
            'Menünüzden seçtiğiniz ürünleri kayan bir vitrin hâlinde gösterin. Her vitrinin kendi kısa kodu vardır — istediğiniz sayfaya yapıştırabilirsiniz.'
        );

        $this->vitrin_notice();

        $vitrinler = RMA_Vitrin_DB::hepsi();
        ?>
        <div class="rma-card">
            <div class="rma-vitrin-list-head">
                <h2 class="rma-card-title">Vitrinlerim</h2>
                <a class="button button-primary" href="<?php echo esc_url( $this->vitrin_url( array( 'vitrin' => 'yeni' ) ) ); ?>">+ Yeni Vitrin</a>
            </div>

            <?php if ( empty( $vitrinler ) ) : ?>
                <p class="rma-empty">Henüz vitrin oluşturmadınız. "Yeni Vitrin" ile başlayın.</p>
            <?php else : ?>
                <div class="rma-vitrin-cards">
                    <?php foreach ( $vitrinler as $v ) :
                        $shortcode = '[qrms_urun_vitrini id="' . (int) $v->id . '"]';
                        ?>
                        <div class="rma-vitrin-card">
                            <div class="rma-vitrin-card-main">
                                <h3 class="rma-vitrin-card-title"><?php echo esc_html( $v->title ); ?></h3>
                                <p class="rma-vitrin-card-meta">
                                    <?php echo (int) $v->urun_sayisi; ?> ürün ·
                                    <?php echo (int) $v->grid_columns; ?> sütun × <?php echo (int) $v->grid_rows; ?> satır (masaüstü) ·
                                    mobilde <?php echo (int) $v->mobile_columns; ?> sütun ·
                                    <?php echo $v->autoplay ? 'otomatik kayma açık' : 'otomatik kayma kapalı'; ?>
                                </p>

                                <div class="rma-shortcode-row">
                                    <input type="text" class="rma-shortcode-input" readonly value="<?php echo esc_attr( $shortcode ); ?>">
                                    <button type="button" class="button rma-copy-shortcode" data-shortcode="<?php echo esc_attr( $shortcode ); ?>">Kopyala</button>
                                </div>
                            </div>

                            <div class="rma-vitrin-card-actions">
                                <a class="button" href="<?php echo esc_url( $this->vitrin_url( array( 'vitrin' => (int) $v->id ) ) ); ?>">Düzenle</a>

                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Bu vitrin silinsin mi? Ürünleriniz silinmez, yalnızca vitrin kaldırılır.');">
                                    <?php wp_nonce_field( $this->vitrin_nonce_action ); ?>
                                    <input type="hidden" name="action" value="rma_vitrin_sil">
                                    <input type="hidden" name="vitrin_id" value="<?php echo (int) $v->id; ?>">
                                    <button type="submit" class="button rma-btn-danger">Sil</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="rma-card">
            <h2 class="rma-card-title">Vitrini Sayfaya Nasıl Eklerim?</h2>
            <ul class="rma-vitrin-help">
                <li><strong>Sayfa/yazı içine:</strong> yukarıdaki kısa kodu kopyalayıp içeriğin istediğiniz yerine yapıştırın.</li>
                <li><strong>Elementor ile:</strong> "Ürün Vitrini" widget'ını sürükleyin ve listeden vitrini seçin.</li>
                <li><strong>Tema/menü konumuna:</strong> kısa kodu bir Shortcode/HTML bloğunun içine koyun.</li>
            </ul>
        </div>
        <?php
        $this->page_footer();
    }

    /* -----------------------------------------------------------------
       DÜZENLEME FORMU
    ----------------------------------------------------------------- */

    /**
     * Yeni/mevcut vitrin formu.
     *
     * @param object|null $kayit Mevcut vitrin ya da null (yeni).
     * @return void
     */
    private function render_vitrin_form( $kayit ) {
        $yeni = ( null === $kayit );
        $id   = $yeni ? 0 : (int) $kayit->id;
        $v    = RMA_Vitrin_DB::varsayilanlar();

        $deger = array(
            'title'          => $yeni ? '' : $kayit->title,
            'grid_columns'   => $yeni ? $v['grid_columns'] : (int) $kayit->grid_columns,
            'grid_rows'      => $yeni ? $v['grid_rows'] : (int) $kayit->grid_rows,
            'mobile_columns' => $yeni ? $v['mobile_columns'] : (int) $kayit->mobile_columns,
            'autoplay'       => $yeni ? $v['autoplay'] : (int) $kayit->autoplay,
            'autoplay_speed' => $yeni ? $v['autoplay_speed'] : (int) $kayit->autoplay_speed,
            'drag_enabled'   => $yeni ? $v['drag_enabled'] : (int) $kayit->drag_enabled,
            'show_price'     => $yeni ? $v['show_price'] : (int) $kayit->show_price,
        );

        $secili_idler = $yeni ? array() : RMA_Vitrin_DB::urun_idleri( $id );
        $urunler      = $this->vitrin_urun_listesi();

        $this->page_header(
            $yeni ? 'Yeni Vitrin' : 'Vitrini Düzenle',
            'Ürünleri seçin, sırayı sürükleyerek belirleyin, düzen ve kayma ayarlarını yapın.'
        );

        $this->vitrin_notice();
        ?>
        <p><a class="rma-back-link" href="<?php echo esc_url( $this->vitrin_url() ); ?>">&larr; Tüm vitrinler</a></p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rma-vitrin-form">
            <?php wp_nonce_field( $this->vitrin_nonce_action ); ?>
            <input type="hidden" name="action" value="rma_vitrin_kaydet">
            <input type="hidden" name="vitrin_id" value="<?php echo (int) $id; ?>">
            <?php /* Sürükle-bırak sırası bu alana yazılır (admin-ui.js). */ ?>
            <input type="hidden" name="urun_sirasi" id="rma-vitrin-order" value="<?php echo esc_attr( implode( ',', $secili_idler ) ); ?>">

            <div class="rma-card">
                <h2 class="rma-card-title">1. Vitrin Adı</h2>
                <p class="rma-card-desc">Yalnızca yönetim panelinde görünür; vitrinleri birbirinden ayırmanız için.</p>
                <input type="text" name="title" class="regular-text" value="<?php echo esc_attr( $deger['title'] ); ?>" placeholder="Örn. Şefin Önerileri">
            </div>

            <div class="rma-card">
                <h2 class="rma-card-title">2. Ürünleri Seç</h2>
                <p class="rma-card-desc">Vitrinde gösterilecek ürünleri işaretleyin. Seçtikleriniz "Vitrindeki Sıra" listesine eklenir.</p>

                <?php if ( empty( $urunler ) ) : ?>
                    <p class="rma-empty">Yayında ürün yok. Önce "Ürün Ekle" sayfasından ürün ekleyin.</p>
                <?php else : ?>
                    <div class="rma-vitrin-picker">
                        <div class="rma-vitrin-pool">
                            <label class="rma-vitrin-search-label" for="rma-vitrin-search">Ürün ara</label>
                            <input type="search" id="rma-vitrin-search" class="rma-vitrin-search" placeholder="Ürün adı yazın…">

                            <div class="rma-vitrin-pool-list">
                                <?php foreach ( $urunler as $urun ) :
                                    $secili = in_array( $urun['id'], $secili_idler, true );
                                    ?>
                                    <label class="rma-vitrin-pool-row<?php echo $secili ? ' is-selected' : ''; ?>"
                                           data-id="<?php echo (int) $urun['id']; ?>"
                                           <?php /* Küçük harfe çevirme JS'te yapılır: hem mbstring
                                                     bağımlılığı doğmaz, hem de arama kutusuyla aynı
                                                     Unicode kuralları kullanılır (Türkçe İ/I). */ ?>
                                           data-title="<?php echo esc_attr( $urun['title'] ); ?>">
                                        <input type="checkbox" class="rma-vitrin-cb" value="<?php echo (int) $urun['id']; ?>" <?php checked( $secili ); ?>>
                                        <?php if ( '' !== $urun['img'] ) : ?>
                                            <img src="<?php echo esc_url( $urun['img'] ); ?>" alt="" class="rma-vitrin-thumb">
                                        <?php else : ?>
                                            <span class="rma-vitrin-thumb rma-vitrin-thumb-empty">◆</span>
                                        <?php endif; ?>
                                        <span class="rma-vitrin-pool-main">
                                            <span class="rma-vitrin-pool-title"><?php echo esc_html( $urun['title'] ); ?></span>
                                            <span class="rma-vitrin-pool-cat"><?php echo esc_html( $urun['category'] ); ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rma-vitrin-chosen">
                            <h3 class="rma-section-title">Vitrindeki Sıra</h3>
                            <p class="rma-desc">Sıralamak için kartları sürükleyin.</p>

                            <ul class="rma-vitrin-sortable" id="rma-vitrin-sortable">
                                <?php
                                // Seçili listenin sırası tablodaki sort_order'dan gelir.
                                $harita = array();
                                foreach ( $urunler as $urun ) {
                                    $harita[ $urun['id'] ] = $urun;
                                }
                                foreach ( $secili_idler as $secili_id ) :
                                    if ( ! isset( $harita[ $secili_id ] ) ) {
                                        continue;
                                    }
                                    $urun = $harita[ $secili_id ];
                                    ?>
                                    <li class="rma-vitrin-chip" data-id="<?php echo (int) $urun['id']; ?>">
                                        <span class="rma-vitrin-drag" aria-hidden="true">⋮⋮</span>
                                        <?php if ( '' !== $urun['img'] ) : ?>
                                            <img src="<?php echo esc_url( $urun['img'] ); ?>" alt="" class="rma-vitrin-thumb">
                                        <?php else : ?>
                                            <span class="rma-vitrin-thumb rma-vitrin-thumb-empty">◆</span>
                                        <?php endif; ?>
                                        <span class="rma-vitrin-chip-title"><?php echo esc_html( $urun['title'] ); ?></span>
                                        <button type="button" class="rma-vitrin-remove" aria-label="Vitrinden çıkar">&times;</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <p class="rma-empty rma-vitrin-empty"<?php echo empty( $secili_idler ) ? '' : ' style="display:none;"'; ?>>Henüz ürün seçmediniz.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rma-card">
                <h2 class="rma-card-title">3. Düzen</h2>
                <p class="rma-card-desc">Bir sayfada kaç ürün görünsün? Dar ekranlarda sütun sayısı otomatik azalır, vitrin yatay kaydırılır.</p>

                <table class="form-table rma-form-table">
                    <tr>
                        <th><label for="rma-vitrin-cols">Sütun sayısı</label></th>
                        <td>
                            <select name="grid_columns" id="rma-vitrin-cols" class="rma-select-narrow">
                                <?php for ( $i = RMA_Vitrin_DB::MIN_COLUMNS; $i <= RMA_Vitrin_DB::MAX_COLUMNS; $i++ ) : ?>
                                    <option value="<?php echo (int) $i; ?>" <?php selected( $deger['grid_columns'], $i ); ?>><?php echo (int) $i; ?> sütun</option>
                                <?php endfor; ?>
                            </select>
                            <p class="description rma-desc">Masaüstünde bir sayfada yan yana kaç ürün duracağı.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rma-vitrin-rows">Satır sayısı</label></th>
                        <td>
                            <select name="grid_rows" id="rma-vitrin-rows" class="rma-select-narrow">
                                <?php for ( $i = RMA_Vitrin_DB::MIN_ROWS; $i <= RMA_Vitrin_DB::MAX_ROWS; $i++ ) : ?>
                                    <option value="<?php echo (int) $i; ?>" <?php selected( $deger['grid_rows'], $i ); ?>><?php echo (int) $i; ?> satır</option>
                                <?php endfor; ?>
                            </select>
                            <p class="description rma-desc">Alt alta kaç sıra gösterileceği. Sayfa başına ürün = sütun × satır.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rma-vitrin-mobile-cols">Mobilde sütun sayısı</label></th>
                        <td>
                            <select name="mobile_columns" id="rma-vitrin-mobile-cols" class="rma-select-narrow">
                                <?php for ( $i = RMA_Vitrin_DB::MIN_MOBILE_COLUMNS; $i <= RMA_Vitrin_DB::MAX_MOBILE_COLUMNS; $i++ ) : ?>
                                    <option value="<?php echo (int) $i; ?>" <?php selected( $deger['mobile_columns'], $i ); ?>><?php echo (int) $i; ?> sütun</option>
                                <?php endfor; ?>
                            </select>
                            <p class="description rma-desc">Telefonda bir ekranda yan yana kaç ürün duracağı. Ekran çok dar kalırsa kart yine de okunabilir bir genişliğin altına düşmez.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Fiyat</th>
                        <td>
                            <label class="rma-check-row">
                                <input type="checkbox" name="show_price" value="1" <?php checked( 1, $deger['show_price'] ); ?>>
                                <span>Kartlarda fiyatı göster</span>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="rma-card">
                <h2 class="rma-card-title">4. Kayma Davranışı</h2>
                <p class="rma-card-desc">Vitrin kendiliğinden mi kaysın, yoksa ziyaretçi mi kaydırsın?</p>

                <table class="form-table rma-form-table">
                    <tr>
                        <th>Otomatik kayma</th>
                        <td>
                            <label class="rma-check-row">
                                <input type="checkbox" name="autoplay" id="rma-vitrin-autoplay" value="1" <?php checked( 1, $deger['autoplay'] ); ?>>
                                <span>Vitrin kendiliğinden kaysın</span>
                            </label>
                            <p class="description rma-desc">Ziyaretçi vitrine dokunduğunda ya da fareyle üzerine geldiğinde kayma durur.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rma-vitrin-speed">Kayma hızı</label></th>
                        <td>
                            <div class="rma-range-row">
                                <input type="range" name="autoplay_speed" id="rma-vitrin-speed"
                                       min="<?php echo (int) RMA_Vitrin_DB::MIN_SPEED; ?>"
                                       max="<?php echo (int) RMA_Vitrin_DB::MAX_SPEED; ?>"
                                       step="500"
                                       value="<?php echo (int) $deger['autoplay_speed']; ?>"
                                       oninput="this.nextElementSibling.textContent=(this.value/1000).toFixed(1)+' sn'">
                                <span class="rma-range-val"><?php echo esc_html( number_format( $deger['autoplay_speed'] / 1000, 1 ) ); ?> sn</span>
                            </div>
                            <p class="description rma-desc">İki sayfa arasındaki bekleme süresi.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Elle kaydırma</th>
                        <td>
                            <label class="rma-check-row">
                                <input type="checkbox" name="drag_enabled" value="1" <?php checked( 1, $deger['drag_enabled'] ); ?>>
                                <span>Fareyle sürükleyerek kaydırılabilsin</span>
                            </label>
                            <p class="description rma-desc">Dokunmatik ekranda parmakla kaydırma bu ayardan bağımsız olarak her zaman açıktır.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary">Vitrini Kaydet</button>
                <a class="button" href="<?php echo esc_url( $this->vitrin_url() ); ?>">Vazgeç</a>
            </p>
        </form>
        <?php
        $this->page_footer();
    }

    /**
     * Seçilebilecek ürünler.
     *
     * Sorgu deseni render_suggestions_page()'in aynısı: post/meta/terim
     * cache'leri ve öne çıkan görseller toplu ısıtılır, böylece ürün başına
     * ek sorgu doğmaz.
     *
     * @return array<int,array{id:int,title:string,category:string,img:string}>
     */
    private function vitrin_urun_listesi() {
        $sorgu = new WP_Query(
            array(
                'post_type'              => 'rma_menu_item',
                'post_status'            => 'publish',
                'posts_per_page'         => (int) apply_filters( 'rma_max_menu_items', 800 ),
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => true,
                'orderby'                => 'title',
                'order'                  => 'ASC',
            )
        );

        if ( $sorgu->posts && function_exists( 'update_post_thumbnail_cache' ) ) {
            update_post_thumbnail_cache( $sorgu );
        }

        $liste = array();

        foreach ( $sorgu->posts as $post ) {
            $terimler = get_the_terms( $post->ID, 'rma_category' );
            $kategori = ( ! empty( $terimler ) && ! is_wp_error( $terimler ) ) ? reset( $terimler )->name : 'Kategorisiz';

            $liste[] = array(
                'id'       => (int) $post->ID,
                'title'    => $post->post_title,
                'category' => $kategori,
                'img'      => (string) ( get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '' ),
            );
        }

        return $liste;
    }

    /* -----------------------------------------------------------------
       KAYDETME / SİLME
    ----------------------------------------------------------------- */

    /**
     * `admin_post_rma_vitrin_kaydet` — formu kaydeder ve listeye döner.
     *
     * @return void
     */
    public function handle_vitrin_save() {
        $this->vitrin_yetki_kontrol();

        $id = isset( $_POST['vitrin_id'] ) ? absint( wp_unslash( $_POST['vitrin_id'] ) ) : 0;

        $ayarlar = RMA_Vitrin_DB::ayarlari_temizle(
            array(
                'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
                'grid_columns'   => isset( $_POST['grid_columns'] ) ? wp_unslash( $_POST['grid_columns'] ) : null,
                'grid_rows'      => isset( $_POST['grid_rows'] ) ? wp_unslash( $_POST['grid_rows'] ) : null,
                'mobile_columns' => isset( $_POST['mobile_columns'] ) ? wp_unslash( $_POST['mobile_columns'] ) : null,
                'autoplay'       => isset( $_POST['autoplay'] ) ? wp_unslash( $_POST['autoplay'] ) : 0,
                'autoplay_speed' => isset( $_POST['autoplay_speed'] ) ? wp_unslash( $_POST['autoplay_speed'] ) : null,
                'drag_enabled'   => isset( $_POST['drag_enabled'] ) ? wp_unslash( $_POST['drag_enabled'] ) : 0,
                'show_price'     => isset( $_POST['show_price'] ) ? wp_unslash( $_POST['show_price'] ) : 0,
            )
        );

        $ham_sira = isset( $_POST['urun_sirasi'] ) ? sanitize_text_field( wp_unslash( $_POST['urun_sirasi'] ) ) : '';
        $idler    = RMA_Vitrin_DB::urun_idlerini_temizle( $ham_sira );

        // Var olmayan/silinmiş ürünler listeye sızmasın.
        $idler = array_values(
            array_filter(
                $idler,
                static function ( $urun_id ) {
                    return 'rma_menu_item' === get_post_type( $urun_id );
                }
            )
        );

        $kayit_id = RMA_Vitrin_DB::kaydet( $id, $ayarlar, $idler );

        if ( ! $kayit_id ) {
            wp_safe_redirect( $this->vitrin_url( array( 'vitrin_msg' => 'hata' ) ) );
            exit;
        }

        wp_safe_redirect( $this->vitrin_url( array( 'vitrin' => $kayit_id, 'vitrin_msg' => 'kaydedildi' ) ) );
        exit;
    }

    /**
     * `admin_post_rma_vitrin_sil` — vitrini siler ve listeye döner.
     *
     * @return void
     */
    public function handle_vitrin_delete() {
        $this->vitrin_yetki_kontrol();

        $id = isset( $_POST['vitrin_id'] ) ? absint( wp_unslash( $_POST['vitrin_id'] ) ) : 0;

        if ( $id ) {
            RMA_Vitrin_DB::sil( $id );
        }

        wp_safe_redirect( $this->vitrin_url( array( 'vitrin_msg' => 'silindi' ) ) );
        exit;
    }

    /**
     * Nonce ve yetki kontrolü — her iki handler'ın ortak girişi.
     *
     * @return void
     */
    private function vitrin_yetki_kontrol() {
        check_admin_referer( $this->vitrin_nonce_action );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ), '', array( 'response' => 403 ) );
        }
    }
}
