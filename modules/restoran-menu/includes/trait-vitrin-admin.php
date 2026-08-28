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
            'title'               => $yeni ? '' : $kayit->title,
            'grid_columns'        => $yeni ? $v['grid_columns'] : (int) $kayit->grid_columns,
            'grid_rows'           => $yeni ? $v['grid_rows'] : (int) $kayit->grid_rows,
            'mobile_columns'      => $yeni ? $v['mobile_columns'] : (int) $kayit->mobile_columns,
            'mobile_rows'         => $yeni ? $v['mobile_rows'] : (int) $kayit->mobile_rows,
            'desktop_gap'         => $yeni ? $v['desktop_gap'] : (int) $kayit->desktop_gap,
            'desktop_card_min'    => $yeni ? $v['desktop_card_min'] : (int) $kayit->desktop_card_min,
            'desktop_image_ratio' => $yeni ? $v['desktop_image_ratio'] : (int) $kayit->desktop_image_ratio,
            'mobile_gap'          => $yeni ? $v['mobile_gap'] : (int) $kayit->mobile_gap,
            'mobile_card_min'     => $yeni ? $v['mobile_card_min'] : (int) $kayit->mobile_card_min,
            'mobile_image_ratio'  => $yeni ? $v['mobile_image_ratio'] : (int) $kayit->mobile_image_ratio,
            'bg_color'            => $yeni ? $v['bg_color'] : (string) $kayit->bg_color,
            'autoplay'            => $yeni ? $v['autoplay'] : (int) $kayit->autoplay,
            'autoplay_speed'      => $yeni ? $v['autoplay_speed'] : (int) $kayit->autoplay_speed,
            'drag_enabled'        => $yeni ? $v['drag_enabled'] : (int) $kayit->drag_enabled,
            'show_price'          => $yeni ? $v['show_price'] : (int) $kayit->show_price,
            'title_font'          => $yeni ? $v['title_font'] : (string) RMA_Vitrin_DB::ayar( $kayit, 'title_font' ),
            'title_size'          => $yeni ? $v['title_size'] : (int) RMA_Vitrin_DB::ayar( $kayit, 'title_size' ),
            'title_size_mobile'   => $yeni ? $v['title_size_mobile'] : (int) RMA_Vitrin_DB::ayar( $kayit, 'title_size_mobile' ),
            'title_weight'        => $yeni ? $v['title_weight'] : (int) RMA_Vitrin_DB::ayar( $kayit, 'title_weight' ),
            'title_weight_mobile' => $yeni ? $v['title_weight_mobile'] : (int) RMA_Vitrin_DB::ayar( $kayit, 'title_weight_mobile' ),
            'title_align'         => $yeni ? $v['title_align'] : (string) RMA_Vitrin_DB::ayar( $kayit, 'title_align' ),
            'title_align_mobile'  => $yeni ? $v['title_align_mobile'] : (string) RMA_Vitrin_DB::ayar( $kayit, 'title_align_mobile' ),
            'title_color'         => $yeni ? $v['title_color'] : (string) RMA_Vitrin_DB::ayar( $kayit, 'title_color' ),
            'price_size'          => $yeni ? $v['price_size'] : (int) RMA_Vitrin_DB::ayar( $kayit, 'price_size' ),
            'price_size_mobile'   => $yeni ? $v['price_size_mobile'] : (int) RMA_Vitrin_DB::ayar( $kayit, 'price_size_mobile' ),
            'price_weight'        => $yeni ? $v['price_weight'] : (int) RMA_Vitrin_DB::ayar( $kayit, 'price_weight' ),
            'price_weight_mobile' => $yeni ? $v['price_weight_mobile'] : (int) RMA_Vitrin_DB::ayar( $kayit, 'price_weight_mobile' ),
            'price_align'         => $yeni ? $v['price_align'] : (string) RMA_Vitrin_DB::ayar( $kayit, 'price_align' ),
            'price_align_mobile'  => $yeni ? $v['price_align_mobile'] : (string) RMA_Vitrin_DB::ayar( $kayit, 'price_align_mobile' ),
            'price_color'         => $yeni ? $v['price_color'] : (string) RMA_Vitrin_DB::ayar( $kayit, 'price_color' ),
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

            <?php
            $rma_vitrin_adimlar = array(
                1 => array( 'Ad', 'Vitrin Adı' ),
                2 => array( 'Ürünler', 'Ürünleri Seç' ),
                3 => array( 'Düzen', 'Düzen' ),
                4 => array( 'Boyut', 'Kart Boyutu' ),
                5 => array( 'Yazı', 'Yazı Tipi' ),
                6 => array( 'Kayma', 'Kayma Davranışı' ),
            );
            ?>
            <div class="rma-vitrin-steps" id="rma-vitrin-steps" role="tablist" aria-label="Vitrin ayarları adımları">
                <?php foreach ( $rma_vitrin_adimlar as $adim_no => $adim ) : ?>
                    <button type="button" class="rma-vitrin-step-btn<?php echo 1 === $adim_no ? ' is-active' : ''; ?>"
                            data-step-target="<?php echo (int) $adim_no; ?>"
                            role="tab" aria-selected="<?php echo 1 === $adim_no ? 'true' : 'false'; ?>">
                        <span class="rma-vitrin-step-num"><?php echo (int) $adim_no; ?></span>
                        <span class="rma-vitrin-step-label"><?php echo esc_html( $adim[0] ); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="rma-vitrin-step-compact" id="rma-vitrin-step-compact">Adım 1/<?php echo (int) count( $rma_vitrin_adimlar ); ?>: <?php echo esc_html( $rma_vitrin_adimlar[1][1] ); ?></p>

            <div class="rma-vitrin-layout-wrap">
                <div class="rma-vitrin-layout-fields">
            <div class="rma-card rma-vitrin-step" data-step="1" data-step-title="Vitrin Adı">
                <h2 class="rma-card-title">1. Vitrin Adı</h2>
                <p class="rma-card-desc">Yalnızca yönetim panelinde görünür; vitrinleri birbirinden ayırmanız için.</p>
                <input type="text" name="title" class="regular-text" value="<?php echo esc_attr( $deger['title'] ); ?>" placeholder="Örn. Şefin Önerileri">
            </div>

            <div class="rma-card rma-vitrin-step" data-step="2" data-step-title="Ürünleri Seç" style="display:none;">
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
                                    <?php
                                    // Canlı önizleme JS'i bu satırdan okur (bkz. admin-ui.js
                                    // vitrinBuildProductMap): checkbox işaretlenince/ürün
                                    // sıralanınca sayfa yenilenmeden gerçek ad/görsel/fiyat
                                    // önizlemeye yansısın diye — placeholder yalnızca hiç
                                    // ürün seçilmediğinde gösterilir.
                                    $fiyat_veri = '' !== $urun['price'] ? $urun['price'] : '₺0,00';
                                    ?>
                                    <label class="rma-vitrin-pool-row<?php echo $secili ? ' is-selected' : ''; ?>"
                                           data-id="<?php echo (int) $urun['id']; ?>"
                                           <?php /* Küçük harfe çevirme JS'te yapılır: hem mbstring
                                                     bağımlılığı doğmaz, hem de arama kutusuyla aynı
                                                     Unicode kuralları kullanılır (Türkçe İ/I). */ ?>
                                           data-title="<?php echo esc_attr( $urun['title'] ); ?>"
                                           data-img="<?php echo esc_url( $urun['img'] ); ?>"
                                           data-price="<?php echo esc_attr( $fiyat_veri ); ?>">
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

                    <div class="rma-card rma-vitrin-step" data-step="3" data-step-title="Düzen" style="display:none;">
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
                                <th><label for="rma-vitrin-mobile-rows">Mobilde satır sayısı</label></th>
                                <td>
                                    <select name="mobile_rows" id="rma-vitrin-mobile-rows" class="rma-select-narrow">
                                        <?php for ( $i = RMA_Vitrin_DB::MIN_MOBILE_ROWS; $i <= RMA_Vitrin_DB::MAX_MOBILE_ROWS; $i++ ) : ?>
                                            <option value="<?php echo (int) $i; ?>" <?php selected( $deger['mobile_rows'], $i ); ?>><?php echo (int) $i; ?> satır</option>
                                        <?php endfor; ?>
                                    </select>
                                    <p class="description rma-desc">Telefonda bir ekranda kaç sıra ürün gösterileceği. Ekran başına ürün = mobil sütun × mobil satır.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Fiyat</th>
                                <td>
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="show_price" id="rma-vitrin-show-price" value="1" <?php checked( 1, $deger['show_price'] ); ?>>
                                        <span>Kartlarda fiyatı göster</span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="rma-card rma-vitrin-step" data-step="4" data-step-title="Kart Boyutu" style="display:none;">
                        <h2 class="rma-card-title">4. Kart Boyutu</h2>
                        <p class="rma-card-desc">Sütun/satır sayısı grid yapısını belirler; buradaki ayarlar kartın kendisini — genişliğini, boşluğunu ve görselinin oranını — tamamlar. Masaüstü ve mobil için ayrı ayarlanır.</p>

                        <h3 class="rma-section-title">Genel</h3>
                        <table class="form-table rma-form-table">
                            <tr>
                                <th><label for="rma-vitrin-bg-color">Vitrin Arka Plan Rengi</label></th>
                                <td>
                                    <input type="text" name="bg_color" id="rma-vitrin-bg-color"
                                           value="<?php echo esc_attr( $deger['bg_color'] ); ?>"
                                           class="rma-vitrin-color-picker"
                                           data-default-color="">
                                    <p class="description rma-desc">Vitrin kartlarının arkasındaki alan. Boş bırakılırsa şeffaf kalır (sayfanın kendi arka planı görünür).</p>
                                </td>
                            </tr>
                        </table>

                        <h3 class="rma-section-title">Masaüstü</h3>
                        <table class="form-table rma-form-table">
                            <tr>
                                <th><label for="rma-vitrin-desktop-card-min">Kart min-genişliği</label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" name="desktop_card_min" id="rma-vitrin-desktop-card-min"
                                               min="<?php echo (int) RMA_Vitrin_DB::MIN_DESKTOP_CARD_MIN; ?>"
                                               max="<?php echo (int) RMA_Vitrin_DB::MAX_DESKTOP_CARD_MIN; ?>"
                                               step="10"
                                               value="<?php echo (int) $deger['desktop_card_min']; ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'px'">
                                        <span class="rma-range-val"><?php echo (int) $deger['desktop_card_min']; ?>px</span>
                                    </div>
                                    <p class="description rma-desc">Kart, sütun sayısı ne olursa olsun bu genişliğin altına sıkışmaz; sığmayan sütunlar kaydırmayla görülür.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rma-vitrin-desktop-gap">Kartlar arası boşluk</label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" name="desktop_gap" id="rma-vitrin-desktop-gap"
                                               min="<?php echo (int) RMA_Vitrin_DB::MIN_GAP; ?>"
                                               max="<?php echo (int) RMA_Vitrin_DB::MAX_GAP; ?>"
                                               step="2"
                                               value="<?php echo (int) $deger['desktop_gap']; ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'px'">
                                        <span class="rma-range-val"><?php echo (int) $deger['desktop_gap']; ?>px</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rma-vitrin-desktop-ratio">Görsel yükseklik oranı</label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" name="desktop_image_ratio" id="rma-vitrin-desktop-ratio"
                                               min="<?php echo (int) RMA_Vitrin_DB::MIN_IMAGE_RATIO; ?>"
                                               max="<?php echo (int) RMA_Vitrin_DB::MAX_IMAGE_RATIO; ?>"
                                               step="1"
                                               value="<?php echo (int) $deger['desktop_image_ratio']; ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'%'">
                                        <span class="rma-range-val"><?php echo (int) $deger['desktop_image_ratio']; ?>%</span>
                                    </div>
                                    <p class="description rma-desc">Ürün görselinin yüksekliği, genişliğinin yüzde kaçı olsun. 100 = kare, düşük değer = daha yatay/kısa.</p>
                                </td>
                            </tr>
                        </table>

                        <h3 class="rma-section-title">Mobil</h3>
                        <table class="form-table rma-form-table">
                            <tr>
                                <th><label for="rma-vitrin-mobile-card-min">Kart min-genişliği</label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" name="mobile_card_min" id="rma-vitrin-mobile-card-min"
                                               min="<?php echo (int) RMA_Vitrin_DB::MIN_MOBILE_CARD_MIN; ?>"
                                               max="<?php echo (int) RMA_Vitrin_DB::MAX_MOBILE_CARD_MIN; ?>"
                                               step="4"
                                               value="<?php echo (int) $deger['mobile_card_min']; ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'px'">
                                        <span class="rma-range-val"><?php echo (int) $deger['mobile_card_min']; ?>px</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rma-vitrin-mobile-gap">Kartlar arası boşluk</label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" name="mobile_gap" id="rma-vitrin-mobile-gap"
                                               min="<?php echo (int) RMA_Vitrin_DB::MIN_GAP; ?>"
                                               max="<?php echo (int) RMA_Vitrin_DB::MAX_GAP; ?>"
                                               step="2"
                                               value="<?php echo (int) $deger['mobile_gap']; ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'px'">
                                        <span class="rma-range-val"><?php echo (int) $deger['mobile_gap']; ?>px</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rma-vitrin-mobile-ratio">Görsel yükseklik oranı</label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" name="mobile_image_ratio" id="rma-vitrin-mobile-ratio"
                                               min="<?php echo (int) RMA_Vitrin_DB::MIN_IMAGE_RATIO; ?>"
                                               max="<?php echo (int) RMA_Vitrin_DB::MAX_IMAGE_RATIO; ?>"
                                               step="1"
                                               value="<?php echo (int) $deger['mobile_image_ratio']; ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'%'">
                                        <span class="rma-range-val"><?php echo (int) $deger['mobile_image_ratio']; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="rma-card rma-vitrin-step" data-step="5" data-step-title="Yazı Tipi" style="display:none;">
                        <h2 class="rma-card-title">5. Yazı Tipi</h2>
                        <p class="rma-card-desc">Kart üzerindeki ürün adı ve fiyatın nasıl görüneceği. Yazı tipi ve renkler tüm cihazlarda ortaktır; boyut, kalınlık ve hizalama masaüstü ve mobil için ayrı ayarlanır — telefonda kart daraldığı için ad çoğu zaman bir tık küçük olmalıdır.</p>

                        <h3 class="rma-section-title">Genel</h3>
                        <table class="form-table rma-form-table">
                            <tr>
                                <th><label for="rma-vitrin-title-font">Yazı tipi</label></th>
                                <td>
                                    <select name="title_font" id="rma-vitrin-title-font" class="rma-select-wide">
                                        <?php foreach ( RMA_Vitrin_DB::yazi_tipleri() as $font_anahtar => $font_bilgi ) : ?>
                                            <option value="<?php echo esc_attr( $font_anahtar ); ?>" <?php selected( $deger['title_font'], $font_anahtar ); ?>><?php echo esc_html( $font_bilgi['etiket'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description rma-desc">Ürün adı ve fiyat bu yazı tipiyle basılır. "Tema yazı tipi" seçiliyken vitrin sayfanın kendi fontunu kullanır ve hiçbir ek font indirilmez.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rma-vitrin-title-color">Ürün adı rengi</label></th>
                                <td>
                                    <input type="text" name="title_color" id="rma-vitrin-title-color"
                                           value="<?php echo esc_attr( $deger['title_color'] ); ?>"
                                           class="rma-vitrin-color-picker"
                                           data-default-color="">
                                    <p class="description rma-desc">Boş bırakılırsa vitrinin kendi açık gri metin rengi kullanılır.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rma-vitrin-price-color">Fiyat rengi</label></th>
                                <td>
                                    <input type="text" name="price_color" id="rma-vitrin-price-color"
                                           value="<?php echo esc_attr( $deger['price_color'] ); ?>"
                                           class="rma-vitrin-color-picker"
                                           data-default-color="">
                                    <p class="description rma-desc">Boş bırakılırsa altın vurgu rengi (#c9a84c) kullanılır.</p>
                                </td>
                            </tr>
                        </table>

                        <h3 class="rma-section-title">Ürün adı — Masaüstü</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->vitrin_font_size_row(
                                'rma-vitrin-title-size',
                                'title_size',
                                $deger['title_size'],
                                RMA_Vitrin_DB::MIN_FONT_SIZE,
                                RMA_Vitrin_DB::MAX_FONT_SIZE,
                                'Yazı boyutu',
                                'Ürün adı, kart genişliği ne olursa olsun bu boyutta basılır; iki satırı aşan adlar kırpılır. 15-16px çoğu kart genişliğinde tek/iki satıra sığar.'
                            );
                            $this->vitrin_weight_row( 'rma-vitrin-title-weight', 'title_weight', $deger['title_weight'], 'Yazı kalınlığı', 'Koyu zeminde 600 (Semibold) okunurluğu artırır; 700 daha vurgulu, 400 daha sakin durur.' );
                            $this->vitrin_align_row( 'rma-vitrin-title-align', 'title_align', $deger['title_align'], 'Metin hizalama', 'Ürün adının kart içindeki yaslanması.' );
                            ?>
                        </table>

                        <h3 class="rma-section-title">Ürün adı — Mobil</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->vitrin_font_size_row(
                                'rma-vitrin-title-size-mobile',
                                'title_size_mobile',
                                $deger['title_size_mobile'],
                                RMA_Vitrin_DB::MIN_MOBILE_FONT_SIZE,
                                RMA_Vitrin_DB::MAX_MOBILE_FONT_SIZE,
                                'Yazı boyutu',
                                'Telefonda kart daralır: aynı ad masaüstündeki boyutta taşar. 13-14px iki satırda rahat okunur.'
                            );
                            $this->vitrin_weight_row( 'rma-vitrin-title-weight-mobile', 'title_weight_mobile', $deger['title_weight_mobile'], 'Yazı kalınlığı', 'Küçük boyutta kalınlık okunurluğu taşıdığı için mobilde bir kademe artırmak işe yarar.' );
                            $this->vitrin_align_row( 'rma-vitrin-title-align-mobile', 'title_align_mobile', $deger['title_align_mobile'], 'Metin hizalama', 'Dar kartta ortalı hizalama iki satırlı adlarda daha derli toplu görünür.' );
                            ?>
                        </table>

                        <h3 class="rma-section-title">Fiyat — Masaüstü</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->vitrin_font_size_row(
                                'rma-vitrin-price-size',
                                'price_size',
                                $deger['price_size'],
                                RMA_Vitrin_DB::MIN_FONT_SIZE,
                                RMA_Vitrin_DB::MAX_FONT_SIZE,
                                'Yazı boyutu',
                                'Kampanyalı üründe üstü çizili eski fiyat bu boyutun %85\'i kadar basılır, ikisi tek satıra sığar.'
                            );
                            $this->vitrin_weight_row( 'rma-vitrin-price-weight', 'price_weight', $deger['price_weight'], 'Yazı kalınlığı', 'Fiyat karttaki en belirleyici bilgidir; 700 (Bold) onu ürün adından ayırır.' );
                            $this->vitrin_align_row( 'rma-vitrin-price-align', 'price_align', $deger['price_align'], 'Metin hizalama', 'Fiyatın kart içindeki yaslanması. Ürün adıyla aynı olması gerekmez.' );
                            ?>
                        </table>

                        <h3 class="rma-section-title">Fiyat — Mobil</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->vitrin_font_size_row(
                                'rma-vitrin-price-size-mobile',
                                'price_size_mobile',
                                $deger['price_size_mobile'],
                                RMA_Vitrin_DB::MIN_MOBILE_FONT_SIZE,
                                RMA_Vitrin_DB::MAX_MOBILE_FONT_SIZE,
                                'Yazı boyutu',
                                'Telefonda fiyat ürün adıyla aynı boyutta kalırsa kart kalabalıklaşır; bir tık küçük iyi çalışır.'
                            );
                            $this->vitrin_weight_row( 'rma-vitrin-price-weight-mobile', 'price_weight_mobile', $deger['price_weight_mobile'], 'Yazı kalınlığı', 'Mobilde fiyatın kalın kalması, küçülen boyutta bile ilk okunan bilgi olmasını sağlar.' );
                            $this->vitrin_align_row( 'rma-vitrin-price-align-mobile', 'price_align_mobile', $deger['price_align_mobile'], 'Metin hizalama', 'Fiyatın dar karttaki yaslanması.' );
                            ?>
                        </table>
                    </div>

                    <div class="rma-card rma-vitrin-step" data-step="6" data-step-title="Kayma Davranışı" style="display:none;">
                        <h2 class="rma-card-title">6. Kayma Davranışı</h2>
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

                    <div class="rma-vitrin-step-nav" id="rma-vitrin-step-nav">
                        <button type="button" class="button rma-vitrin-step-prev" disabled>&larr; Geri Dön</button>
                        <button type="button" class="button button-primary rma-vitrin-step-next">Devam Et &rarr;</button>
                        <button type="submit" class="button button-primary rma-vitrin-step-submit" style="display:none;">Vitrini Kaydet</button>
                        <a class="button rma-vitrin-step-cancel" href="<?php echo esc_url( $this->vitrin_url() ); ?>">Vazgeç</a>
                    </div>
                </div>

                <div class="rma-vitrin-layout-preview">
                    <div class="rma-card rma-vitrin-preview-card">
                        <h2 class="rma-card-title">Canlı Önizleme</h2>
                        <p class="rma-card-desc">Kaydetmeden önce nasıl görüneceğini kontrol edin. Soldaki her değişiklik anında yansır.</p>

                        <div class="rma-vitrin-preview-toggle">
                            <button type="button" class="button rma-vitrin-preview-btn is-active" data-preview-mode="desktop">Masaüstü Önizleme</button>
                            <button type="button" class="button rma-vitrin-preview-btn" data-preview-mode="mobile">Mobil Önizleme</button>
                        </div>

                        <div class="rma-vitrin-preview-stage<?php echo $deger['show_price'] ? '' : ' is-price-hidden'; ?>" id="rma-vitrin-preview-stage">
                            <div class="qrms-vitrin" id="rma-vitrin-preview">
                                <div class="qrms-vitrin-viewport">
                                    <?php $this->render_vitrin_preview_cards( $urunler, $secili_idler ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php
        $this->page_footer();
    }

    /**
     * "Yazı Tipi" adımındaki px slider satırı.
     *
     * Slider + değer kutusu ikilisi, "4. Kart Boyutu" adımındaki
     * .rma-range-row deseninin aynısıdır (oninput ile anlık px yazımı);
     * canlı önizleme ayrıca admin-ui.js tarafından bağlanır.
     *
     * @param string $id      Alan id'si (önizleme JS'i bunu okur).
     * @param string $name    Form alanı adı = veritabanı sütunu.
     * @param int    $deger   Mevcut değer.
     * @param int    $min     Alt sınır (px).
     * @param int    $max     Üst sınır (px).
     * @param string $etiket  Satır başlığı.
     * @param string $aciklama Slider altındaki açıklama.
     * @return void
     */
    private function vitrin_font_size_row( $id, $name, $deger, $min, $max, $etiket, $aciklama ) {
        ?>
        <tr>
            <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $etiket ); ?></label></th>
            <td>
                <div class="rma-range-row">
                    <input type="range" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
                           min="<?php echo (int) $min; ?>"
                           max="<?php echo (int) $max; ?>"
                           step="1"
                           value="<?php echo (int) $deger; ?>"
                           oninput="this.nextElementSibling.textContent=this.value+'px'">
                    <span class="rma-range-val"><?php echo (int) $deger; ?>px</span>
                </div>
                <p class="description rma-desc"><?php echo esc_html( $aciklama ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * "Yazı Tipi" adımındaki kalınlık açılır listesi.
     *
     * @param string $id       Alan id'si.
     * @param string $name     Form alanı adı = veritabanı sütunu.
     * @param int    $deger    Mevcut değer.
     * @param string $etiket   Satır başlığı.
     * @param string $aciklama Alan altındaki açıklama.
     * @return void
     */
    private function vitrin_weight_row( $id, $name, $deger, $etiket, $aciklama ) {
        $secenekler = array(
            400 => '400 — Normal',
            500 => '500 — Medium',
            600 => '600 — Semibold',
            700 => '700 — Bold',
        );
        ?>
        <tr>
            <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $etiket ); ?></label></th>
            <td>
                <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="rma-select-narrow">
                    <?php foreach ( $secenekler as $kalinlik => $kalinlik_etiket ) : ?>
                        <option value="<?php echo (int) $kalinlik; ?>" <?php selected( (int) $deger, $kalinlik ); ?>><?php echo esc_html( $kalinlik_etiket ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description rma-desc"><?php echo esc_html( $aciklama ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * "Yazı Tipi" adımındaki Sol/Orta/Sağ buton grubu.
     *
     * Görsel dil "Renk seçin" düğmeleriyle aynı (.rma-align-btn seçili
     * hâlde altın vurgu alır); erişilebilirlik için altta gerçek radio
     * girdileri durur, böylece klavye ve ekran okuyucu doğal çalışır ve
     * form gönderimi JS'siz de doğru değeri taşır.
     *
     * @param string $id       Grubun id ön eki (önizleme JS'i bunu okur).
     * @param string $name     Form alanı adı = veritabanı sütunu.
     * @param string $deger    Mevcut değer (left|center|right).
     * @param string $etiket   Satır başlığı.
     * @param string $aciklama Grup altındaki açıklama.
     * @return void
     */
    private function vitrin_align_row( $id, $name, $deger, $etiket, $aciklama ) {
        $deger = RMA_Vitrin_DB::hizalama( $deger );

        // İkonlar: üç çizgi, hizaya göre kaydırılmış (WordPress'in kendi
        // hizalama düğmelerinin sadeleştirilmiş hâli).
        $secenekler = array(
            'left'   => array( 'Sol', array( 0, 0, 0 ) ),
            'center' => array( 'Orta', array( 0, 3, 1.5 ) ),
            'right'  => array( 'Sağ', array( 0, 6, 3 ) ),
        );
        ?>
        <tr>
            <th><span class="rma-align-label"><?php echo esc_html( $etiket ); ?></span></th>
            <td>
                <div class="rma-align-group" role="radiogroup" aria-label="<?php echo esc_attr( $etiket ); ?>">
                    <?php foreach ( $secenekler as $hiza => $secenek ) :
                        list( $hiza_etiket, $ofset ) = $secenek;
                        $secili = $hiza === $deger;
                        ?>
                        <label class="rma-align-btn<?php echo $secili ? ' is-selected' : ''; ?>">
                            <input type="radio" name="<?php echo esc_attr( $name ); ?>"
                                   id="<?php echo esc_attr( $id . '-' . $hiza ); ?>"
                                   class="rma-align-input"
                                   value="<?php echo esc_attr( $hiza ); ?>"
                                   data-align-field="<?php echo esc_attr( $id ); ?>"
                                   <?php checked( $secili ); ?>>
                            <svg class="rma-align-ic" viewBox="0 0 16 12" width="16" height="12" aria-hidden="true" focusable="false">
                                <rect x="<?php echo esc_attr( (string) $ofset[0] ); ?>" y="1" width="16" height="2" rx="1"></rect>
                                <rect x="<?php echo esc_attr( (string) $ofset[1] ); ?>" y="5" width="10" height="2" rx="1"></rect>
                                <rect x="<?php echo esc_attr( (string) $ofset[2] ); ?>" y="9" width="13" height="2" rx="1"></rect>
                            </svg>
                            <span><?php echo esc_html( $hiza_etiket ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description rma-desc"><?php echo esc_html( $aciklama ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Seçilebilecek ürünler.
     *
     * Sorgu deseni render_suggestions_page()'in aynısı: post/meta/terim
     * cache'leri ve öne çıkan görseller toplu ısıtılır, böylece ürün başına
     * ek sorgu doğmaz.
     *
     * @return array<int,array{id:int,title:string,category:string,img:string,price:string}>
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
                'price'    => RMA_Kampanya::fiyat_html( $post->ID ),
            );
        }

        return $liste;
    }

    /**
     * Canlı önizleme kartları — formun sağ sütunundaki
     * `.qrms-vitrin` örneğini doldurur.
     *
     * Gerçek frontend markup'ıyla (bkz. RMA_Vitrin_Shortcode::render())
     * BİREBİR aynı sınıflar kullanılır ki önizleme sahte bir stil değil,
     * vitrin.css'in kendisiyle boyansın.
     *
     * Yer tutucu kartlar YALNIZCA hiç ürün seçilmemişken basılır (form ilk
     * açıldığında bile önizleme boş kalmasın diye). Ürün seçiliyse önizleme
     * SADECE gerçek seçimi gösterir — 6'ya tamamlamak için sahte kartlarla
     * doldurulmaz. Aynı kural admin-ui.js'teki vitrinRenderPreviewCards()'ta
     * da geçerli: adımlar arası geçişte/canlı seçimde bu fonksiyon yalnızca
     * SAYFA İLK YÜKLENİRKEN çalışır, sonrası JS'in elindedir.
     *
     * Fiyat satırı "Kartlarda fiyatı göster" kapalı olsa da HER ZAMAN
     * basılır: JS bu kutuyu işaretlediğinde önizlemede gösterecek bir
     * öğe bulabilsin (bkz. admin-ui.js initVitrinPreview, ve
     * .rma-vitrin-preview-stage.is-price-hidden kuralı).
     *
     * @param array $urunler      vitrin_urun_listesi() çıktısı.
     * @param int[] $secili_idler Seçili ürünlerin sıralı ID listesi.
     * @return void
     */
    private function render_vitrin_preview_cards( array $urunler, array $secili_idler ) {
        $azami = 8;

        if ( empty( $secili_idler ) ) {
            for ( $i = 0; $i < 6; $i++ ) {
                $this->render_vitrin_preview_card( null );
            }
            return;
        }

        $harita = array();
        foreach ( $urunler as $urun ) {
            $harita[ $urun['id'] ] = $urun;
        }

        $basilan = 0;
        foreach ( $secili_idler as $id ) {
            if ( $basilan >= $azami ) {
                break;
            }
            if ( ! isset( $harita[ $id ] ) ) {
                continue;
            }
            $this->render_vitrin_preview_card( $harita[ $id ] );
            $basilan++;
        }
    }

    /**
     * Tek bir önizleme kartı basar.
     *
     * @param array|null $urun vitrin_urun_listesi() satırı ya da null (yer tutucu).
     * @return void
     */
    private function render_vitrin_preview_card( $urun ) {
        $baslik = $urun ? $urun['title'] : 'Ürün Adı';
        $gorsel = $urun ? $urun['img'] : '';
        $fiyat  = $urun && '' !== $urun['price'] ? $urun['price'] : '';
        ?>
        <article class="qrms-vitrin-card">
            <div class="qrms-vitrin-media">
                <?php if ( '' !== $gorsel ) : ?>
                    <img src="<?php echo esc_url( $gorsel ); ?>" alt="" class="qrms-vitrin-img">
                <?php else : ?>
                    <span class="qrms-vitrin-img qrms-vitrin-img-empty" aria-hidden="true">◆</span>
                <?php endif; ?>
            </div>
            <div class="qrms-vitrin-body">
                <h3 class="qrms-vitrin-title"><?php echo esc_html( $baslik ); ?></h3>
                <p class="qrms-vitrin-price"><?php echo '' !== $fiyat ? wp_kses_post( $fiyat ) : '₺0,00'; ?></p>
            </div>
        </article>
        <?php
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
                'title'               => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
                'grid_columns'        => isset( $_POST['grid_columns'] ) ? wp_unslash( $_POST['grid_columns'] ) : null,
                'grid_rows'           => isset( $_POST['grid_rows'] ) ? wp_unslash( $_POST['grid_rows'] ) : null,
                'mobile_columns'      => isset( $_POST['mobile_columns'] ) ? wp_unslash( $_POST['mobile_columns'] ) : null,
                'mobile_rows'         => isset( $_POST['mobile_rows'] ) ? wp_unslash( $_POST['mobile_rows'] ) : null,
                'desktop_gap'         => isset( $_POST['desktop_gap'] ) ? wp_unslash( $_POST['desktop_gap'] ) : null,
                'desktop_card_min'    => isset( $_POST['desktop_card_min'] ) ? wp_unslash( $_POST['desktop_card_min'] ) : null,
                'desktop_image_ratio' => isset( $_POST['desktop_image_ratio'] ) ? wp_unslash( $_POST['desktop_image_ratio'] ) : null,
                'mobile_gap'          => isset( $_POST['mobile_gap'] ) ? wp_unslash( $_POST['mobile_gap'] ) : null,
                'mobile_card_min'     => isset( $_POST['mobile_card_min'] ) ? wp_unslash( $_POST['mobile_card_min'] ) : null,
                'mobile_image_ratio'  => isset( $_POST['mobile_image_ratio'] ) ? wp_unslash( $_POST['mobile_image_ratio'] ) : null,
                'bg_color'            => isset( $_POST['bg_color'] ) ? sanitize_text_field( wp_unslash( $_POST['bg_color'] ) ) : '',
                'autoplay'            => isset( $_POST['autoplay'] ) ? wp_unslash( $_POST['autoplay'] ) : 0,
                'autoplay_speed'      => isset( $_POST['autoplay_speed'] ) ? wp_unslash( $_POST['autoplay_speed'] ) : null,
                'drag_enabled'        => isset( $_POST['drag_enabled'] ) ? wp_unslash( $_POST['drag_enabled'] ) : 0,
                'show_price'          => isset( $_POST['show_price'] ) ? wp_unslash( $_POST['show_price'] ) : 0,
                'title_font'          => isset( $_POST['title_font'] ) ? sanitize_text_field( wp_unslash( $_POST['title_font'] ) ) : '',
                'title_size'          => isset( $_POST['title_size'] ) ? wp_unslash( $_POST['title_size'] ) : null,
                'title_size_mobile'   => isset( $_POST['title_size_mobile'] ) ? wp_unslash( $_POST['title_size_mobile'] ) : null,
                'title_weight'        => isset( $_POST['title_weight'] ) ? wp_unslash( $_POST['title_weight'] ) : null,
                'title_weight_mobile' => isset( $_POST['title_weight_mobile'] ) ? wp_unslash( $_POST['title_weight_mobile'] ) : null,
                'title_align'         => isset( $_POST['title_align'] ) ? sanitize_text_field( wp_unslash( $_POST['title_align'] ) ) : null,
                'title_align_mobile'  => isset( $_POST['title_align_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['title_align_mobile'] ) ) : null,
                'title_color'         => isset( $_POST['title_color'] ) ? sanitize_text_field( wp_unslash( $_POST['title_color'] ) ) : '',
                'price_size'          => isset( $_POST['price_size'] ) ? wp_unslash( $_POST['price_size'] ) : null,
                'price_size_mobile'   => isset( $_POST['price_size_mobile'] ) ? wp_unslash( $_POST['price_size_mobile'] ) : null,
                'price_weight'        => isset( $_POST['price_weight'] ) ? wp_unslash( $_POST['price_weight'] ) : null,
                'price_weight_mobile' => isset( $_POST['price_weight_mobile'] ) ? wp_unslash( $_POST['price_weight_mobile'] ) : null,
                'price_align'         => isset( $_POST['price_align'] ) ? sanitize_text_field( wp_unslash( $_POST['price_align'] ) ) : null,
                'price_align_mobile'  => isset( $_POST['price_align_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['price_align_mobile'] ) ) : null,
                'price_color'         => isset( $_POST['price_color'] ) ? sanitize_text_field( wp_unslash( $_POST['price_color'] ) ) : '',
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

        // Kaydeden kullanıcı sihirbazda DEĞİL, vitrin listesinde bırakılır:
        // "Vitrini Kaydet" sihirbazın son adımıdır, iş bitmiştir. Düzenleme
        // formuna geri dönmek (eski davranış) kullanıcıyı aynı sihirbazın
        // 1. adımında bırakıyor, kaydın gerçekleşip gerçekleşmediği
        // belirsiz kalıyordu. Bildirim listede basılır
        // (bkz. render_vitrin_list() → vitrin_notice()).
        wp_safe_redirect( $this->vitrin_url( array( 'vitrin_msg' => 'kaydedildi' ) ) );
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
