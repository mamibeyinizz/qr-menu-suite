<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Post_Types_Trait {

    public function register_post_types() {
        register_post_type( 'rma_menu_item', [
            'labels'        => [
                'name'          => 'Menü Ürünleri',
                'singular_name' => 'Menü Ürünü',
                'menu_name'     => 'Menü',
                'add_new'       => 'Ürün Ekle',
                'add_new_item'  => 'Ürün Ekle',
                'edit_item'     => 'Ürünü Düzenle',
                'new_item'      => 'Yeni Ürün',
                'view_item'     => 'Ürünü Görüntüle',
                'search_items'  => 'Ürünlerde Ara',
                'not_found'     => 'Ürün bulunamadı',
            ],
            'public'        => true,
            'show_ui'       => true,
            // Suite'in "QR Menü" üst menüsünün altına alınır. Slug sabit string
            // yerine QRMS_Admin::MENU_SLUG'dan okunur (tek kaynak). Suite yoksa
            // eski davranışa dönülür: aksi hâlde var olmayan bir üst menüye
            // bağlanıp menüden tamamen kaybolurdu. menu_position kaldırıldı —
            // yalnızca top-level menüde okunur.
            'show_in_menu'  => class_exists( 'QRMS_Admin' ) ? QRMS_Admin::MENU_SLUG : true,
            'menu_icon'     => 'dashicons-media-document',
            'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'has_archive'   => false,
            'rewrite'       => [ 'slug' => 'menu-item' ],
        ] );

        register_taxonomy( 'rma_category', [ 'rma_menu_item' ], [
            'hierarchical'      => true,
            'labels'            => [
                'name'          => 'Menü Kategorileri',
                'menu_name'     => 'Kategoriler',
                'singular_name' => 'Menü Kategorisi',
                'search_items'  => 'Kategorilerde Ara',
                'all_items'     => 'Tüm Kategoriler',
                'edit_item'     => 'Kategoriyi Düzenle',
                'update_item'   => 'Kategoriyi Güncelle',
                'add_new_item'  => 'Yeni Kategori Ekle',
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'menu-category' ],
        ] );

        // Alerjen taksonomisi — tax_query ile hızlı, indexli filtreleme sağlar
        // (meta_query/serialize yaklaşımına göre performans avantajı).
        register_taxonomy( 'rma_allergen', [ 'rma_menu_item' ], [
            'hierarchical'      => false,
            'labels'            => [
                'name'          => 'Alerjenler',
                'singular_name' => 'Alerjen',
            ],
            'show_ui'           => true,
            'show_admin_column' => false,
            'show_in_quick_edit'=> false,
            'meta_box_cb'       => false, // Özel checklist "Ürün Detayları" kutusunda gösterilir
            'query_var'         => true,
            'rewrite'           => false,
        ] );
    }

    /* -----------------------------------------------------------------
       META BOXES
    ----------------------------------------------------------------- */
    public function add_menu_item_meta_boxes() {
        add_meta_box( 'rma_item_details', 'Ürün Detayları', [ $this, 'render_item_details_meta_box' ], 'rma_menu_item', 'normal', 'high' );
    }

    public function render_item_details_meta_box( $post ) {
        wp_nonce_field( 'rma_save_meta', 'rma_meta_nonce' );

        // Besin değerleri tek ızgarada toplanır; fiyat ayrı ele alınır çünkü
        // ekranın en üstündeki "Temel Bilgiler" kartına taşınır.
        $besin = [
            'rma_calories'  => [ __( 'Kalori', 'qrms' ),           __( 'kcal', 'qrms' ) ],
            'rma_grams'     => [ __( 'Gramaj', 'qrms' ),           __( 'g', 'qrms' ) ],
            'rma_prep_time' => [ __( 'Hazırlanış Süresi', 'qrms' ), __( 'dk', 'qrms' ) ],
            'rma_protein'   => [ __( 'Protein', 'qrms' ),          __( 'g', 'qrms' ) ],
            'rma_carbs'     => [ __( 'Karbonhidrat', 'qrms' ),     __( 'g', 'qrms' ) ],
            'rma_fat'       => [ __( 'Yağ', 'qrms' ),              __( 'g', 'qrms' ) ],
        ];

        $ozellikler = [
            'rma_is_vegan'       => __( 'Vegan', 'qrms' ),
            'rma_is_vegetarian'  => __( 'Vejetaryen', 'qrms' ),
            'rma_is_gluten_free' => __( 'Glütensiz', 'qrms' ),
            'rma_is_sugar_free'  => __( 'Şekersiz', 'qrms' ),
        ];

        $rozetler = [
            'rma_badge_popular'     => [ '⭐',   __( 'Popüler', 'qrms' ) ],
            'rma_badge_new'         => [ '🆕',   __( 'Yeni', 'qrms' ) ],
            'rma_badge_recommended' => [ '👨‍🍳', __( 'Önerilen', 'qrms' ) ],
            'rma_badge_discount'    => [ '🏷',   __( 'İndirim', 'qrms' ) ],
        ];

        $price_val = get_post_meta( $post->ID, 'rma_price', true );
        $spicy_val = (string) get_post_meta( $post->ID, RMA_Filtre::META_ACI, true );
        ?>
        <div class="qrms-pe-groups">

            <?php /* Fiyat — ekranın en üstündeki Temel Bilgiler kartına taşınır. */ ?>
            <div class="qrms-pe-field qrms-pe-field--price" data-qrms-pe-slot="fiyat">
                <label class="qrms-pe-label" for="rma_price"><?php esc_html_e( 'Fiyat', 'qrms' ); ?></label>
                <div class="qrms-pe-affix">
                    <input type="number" id="rma_price" name="rma_price" class="qrms-pe-input" inputmode="decimal"
                           min="0" max="999999.99" step="0.01"
                           value="<?php echo esc_attr( $price_val ); ?>" placeholder="0">
                    <span class="qrms-pe-affix-son" aria-hidden="true">₺</span>
                </div>
            </div>

            <section class="qrms-pe-sec" data-qrms-pe-collapse data-open="1">
                <h3 class="qrms-pe-sec-bas">
                    <button type="button" class="qrms-pe-sec-tetik" aria-expanded="true">
                        <span class="qrms-pe-sec-ikon" aria-hidden="true">🥗</span>
                        <span class="qrms-pe-sec-metin"><?php esc_html_e( 'Özellikler', 'qrms' ); ?></span>
                        <span class="qrms-pe-sec-ok" aria-hidden="true"></span>
                    </button>
                </h3>
                <div class="qrms-pe-sec-govde">
                    <div class="qrms-pe-pills">
                        <?php foreach ( $ozellikler as $id => $label ) : ?>
                        <label class="qrms-pe-pill">
                            <input type="checkbox" name="<?php echo esc_attr( $id ); ?>" value="1"
                                <?php checked( get_post_meta( $post->ID, $id, true ), '1' ); ?>>
                            <span><?php echo esc_html( $label ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="qrms-pe-field qrms-pe-field--yari">
                        <label class="qrms-pe-label" for="<?php echo esc_attr( RMA_Filtre::META_ACI ); ?>"><?php esc_html_e( 'Acılık', 'qrms' ); ?></label>
                        <select id="<?php echo esc_attr( RMA_Filtre::META_ACI ); ?>" name="<?php echo esc_attr( RMA_Filtre::META_ACI ); ?>" class="qrms-pe-input qrms-pe-select">
                            <option value="" <?php selected( $spicy_val, '' ); ?>><?php esc_html_e( 'Belirtilmemiş', 'qrms' ); ?></option>
                            <?php foreach ( RMA_Filtre::aci_seviyeleri() as $seviye => $etiket ) : ?>
                            <option value="<?php echo esc_attr( $seviye ); ?>" <?php selected( $spicy_val, (string) $seviye ); ?>><?php echo esc_html( $etiket ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="qrms-pe-sec" data-qrms-pe-collapse data-open="1">
                <h3 class="qrms-pe-sec-bas">
                    <button type="button" class="qrms-pe-sec-tetik" aria-expanded="true">
                        <span class="qrms-pe-sec-ikon" aria-hidden="true">⭐</span>
                        <span class="qrms-pe-sec-metin"><?php esc_html_e( 'Rozetler', 'qrms' ); ?></span>
                        <span class="qrms-pe-sec-ok" aria-hidden="true"></span>
                    </button>
                </h3>
                <div class="qrms-pe-sec-govde">
                    <div class="qrms-pe-secim"
                         data-qrms-secim="coklu"
                         data-etiket="<?php esc_attr_e( 'Rozet seçin…', 'qrms' ); ?>"
                         data-ara="<?php esc_attr_e( 'Rozet ara…', 'qrms' ); ?>">
                        <div class="qrms-pe-secim-kaynak">
                            <?php foreach ( $rozetler as $id => $rozet ) : ?>
                            <label class="qrms-pe-secenek">
                                <input type="checkbox" name="<?php echo esc_attr( $id ); ?>" value="1"
                                    <?php checked( get_post_meta( $post->ID, $id, true ), '1' ); ?>>
                                <span><span class="qrms-pe-secenek-ikon" aria-hidden="true"><?php echo esc_html( $rozet[0] ); ?></span><?php echo esc_html( $rozet[1] ); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="qrms-pe-sec" data-qrms-pe-collapse data-open="1">
                <h3 class="qrms-pe-sec-bas">
                    <button type="button" class="qrms-pe-sec-tetik" aria-expanded="true">
                        <span class="qrms-pe-sec-ikon" aria-hidden="true">⚠️</span>
                        <span class="qrms-pe-sec-metin"><?php esc_html_e( 'Alerjenler', 'qrms' ); ?></span>
                        <span class="qrms-pe-sec-ok" aria-hidden="true"></span>
                    </button>
                </h3>
                <div class="qrms-pe-sec-govde">
                    <?php
                    $selected_allergens = wp_get_object_terms( $post->ID, 'rma_allergen', [ 'fields' => 'slugs' ] );
                    if ( is_wp_error( $selected_allergens ) ) $selected_allergens = [];
                    ?>
                    <div class="qrms-pe-secim"
                         data-qrms-secim="coklu"
                         data-etiket="<?php esc_attr_e( 'Alerjen seçin…', 'qrms' ); ?>"
                         data-ara="<?php esc_attr_e( 'Alerjen ara…', 'qrms' ); ?>">
                        <div class="qrms-pe-secim-kaynak">
                            <?php foreach ( $this->get_allergen_definitions() as $slug => $def ) : ?>
                            <label class="qrms-pe-secenek">
                                <input type="checkbox" name="rma_allergens[]" value="<?php echo esc_attr( $slug ); ?>"
                                    <?php checked( in_array( $slug, $selected_allergens, true ) ); ?>>
                                <span><span class="qrms-pe-secenek-ikon" aria-hidden="true"><?php echo esc_html( $def['icon'] ); ?></span><?php echo esc_html( $def['label'] ); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="qrms-pe-sec" data-qrms-pe-collapse data-open="0">
                <h3 class="qrms-pe-sec-bas">
                    <button type="button" class="qrms-pe-sec-tetik" aria-expanded="false">
                        <span class="qrms-pe-sec-ikon" aria-hidden="true">📊</span>
                        <span class="qrms-pe-sec-metin"><?php esc_html_e( 'Besin Bilgileri', 'qrms' ); ?></span>
                        <span class="qrms-pe-sec-ok" aria-hidden="true"></span>
                    </button>
                </h3>
                <div class="qrms-pe-sec-govde">
                    <div class="qrms-pe-izgara">
                        <?php foreach ( $besin as $id => $bilgi ) : ?>
                        <div class="qrms-pe-field">
                            <label class="qrms-pe-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $bilgi[0] ); ?></label>
                            <div class="qrms-pe-affix">
                                <input type="<?php echo 'rma_prep_time' === $id ? 'number' : 'text'; ?>"
                                    <?php echo 'rma_prep_time' === $id ? ' step="1" min="0"' : ''; ?>
                                    id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>"
                                    class="qrms-pe-input" inputmode="decimal"
                                    value="<?php echo esc_attr( get_post_meta( $post->ID, $id, true ) ); ?>" placeholder="—">
                                <span class="qrms-pe-affix-son" aria-hidden="true"><?php echo esc_html( $bilgi[1] ); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="qrms-pe-sec" data-qrms-pe-collapse data-open="1">
                <h3 class="qrms-pe-sec-bas">
                    <button type="button" class="qrms-pe-sec-tetik" aria-expanded="true">
                        <span class="qrms-pe-sec-ikon" aria-hidden="true">📦</span>
                        <span class="qrms-pe-sec-metin"><?php esc_html_e( 'Ürün Durumu', 'qrms' ); ?></span>
                        <span class="qrms-pe-sec-ok" aria-hidden="true"></span>
                    </button>
                </h3>
                <div class="qrms-pe-sec-govde">
                    <label class="qrms-pe-anahtar">
                        <input type="checkbox" name="rma_active" value="1" <?php checked( get_post_meta( $post->ID, 'rma_active', true ), '1' ); ?>>
                        <span class="qrms-pe-anahtar-metin">
                            <strong><?php esc_html_e( 'Menüde göster', 'qrms' ); ?></strong>
                            <em><?php esc_html_e( 'Kapatılırsa ürün menüden tamamen kalkar.', 'qrms' ); ?></em>
                        </span>
                    </label>
                    <label class="qrms-pe-anahtar">
                        <input type="checkbox" name="rma_tukendi" value="1" <?php checked( RMA_Tukendi::urun_tukendi( $post->ID ) ); ?>>
                        <span class="qrms-pe-anahtar-metin">
                            <strong><?php esc_html_e( 'Tükendi', 'qrms' ); ?></strong>
                            <em><?php esc_html_e( 'Ürün menüde kalır, "Tükendi" etiketiyle görünür ve sipariş alınmaz.', 'qrms' ); ?></em>
                        </span>
                    </label>
                </div>
            </section>

            <section class="qrms-pe-sec" data-qrms-pe-collapse data-open="0">
                <h3 class="qrms-pe-sec-bas">
                    <button type="button" class="qrms-pe-sec-tetik" aria-expanded="false">
                        <span class="qrms-pe-sec-ikon" aria-hidden="true">📋</span>
                        <span class="qrms-pe-sec-metin"><?php esc_html_e( 'Şeffaf Menü Bilgileri', 'qrms' ); ?></span>
                        <span class="qrms-pe-sec-ok" aria-hidden="true"></span>
                    </button>
                </h3>
                <div class="qrms-pe-sec-govde">
                    <div class="qrms-pe-izgara qrms-pe-izgara--iki">
                        <div class="qrms-pe-field">
                            <label class="qrms-pe-label" for="rma_meat_origin"><?php esc_html_e( 'Et Menşei', 'qrms' ); ?></label>
                            <select id="rma_meat_origin" name="rma_meat_origin" class="qrms-pe-input qrms-pe-select">
                                <?php
                                $meat_val = get_post_meta( $post->ID, 'rma_meat_origin', true );
                                foreach ( $this->get_meat_origin_options() as $val => $label ) :
                                ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $meat_val, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="qrms-pe-field">
                            <span class="qrms-pe-label"><?php esc_html_e( 'Diğer', 'qrms' ); ?></span>
                            <div class="qrms-pe-pills">
                                <label class="qrms-pe-pill">
                                    <input type="checkbox" name="rma_contains_alcohol" value="1" <?php checked( get_post_meta( $post->ID, 'rma_contains_alcohol', true ), '1' ); ?>>
                                    <span><?php esc_html_e( 'Alkol içerir', 'qrms' ); ?></span>
                                </label>
                                <label class="qrms-pe-pill">
                                    <input type="checkbox" name="rma_contains_pork" value="1" <?php checked( get_post_meta( $post->ID, 'rma_contains_pork', true ), '1' ); ?>>
                                    <span><?php esc_html_e( 'Domuz türevi içerir', 'qrms' ); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <p class="qrms-pe-not"><?php esc_html_e( 'Bu bölümdeki bilgiler işletmenin resmi beyanı sayılır. Doğruluğu için uzman/diyetisyen onayı alınması önerilir. (1 Temmuz 2026 Şeffaf Menü Yönetmeliği)', 'qrms' ); ?></p>
                </div>
            </section>

        </div>
        <?php
    }

    public function set_default_active_status( $post_id, $post, $update ) {
        if ( $post->post_type === 'rma_menu_item' && ! $update ) {
            update_post_meta( $post_id, 'rma_active', '1' );
        }
    }

    public function save_menu_item_meta( $post_id ) {
        if ( ! isset( $_POST['rma_meta_nonce'] ) || ! wp_verify_nonce( $_POST['rma_meta_nonce'], 'rma_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Ürün adı boşsa kayıt tamamen engellenir: WordPress çekirdeği başlıksız
        // bir postu (içerik/excerpt de boşsa) auto-draft'a düşürür, ama fiyat
        // gibi meta alanları yine de sessizce yazardı. rma_baslik_gecersiz
        // bayrağı redirect_post_location/admin_notices üzerinden kullanıcıya
        // açık bir hata gösterir (bkz. flag_empty_title_redirect()).
        if ( '' === trim( (string) wp_unslash( $_POST['post_title'] ?? '' ) ) ) {
            $this->rma_baslik_gecersiz = true;
            return;
        }

        $fields     = [ 'rma_calories', 'rma_grams', 'rma_protein', 'rma_carbs', 'rma_fat', 'rma_prep_time' ];
        $checkboxes = [ 'rma_is_vegan', 'rma_is_vegetarian', 'rma_is_gluten_free', 'rma_is_sugar_free', 'rma_badge_popular', 'rma_badge_new', 'rma_badge_recommended', 'rma_badge_discount', 'rma_active', 'rma_contains_alcohol', 'rma_contains_pork' ];

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }

        // Fiyat — negatif/metin/biçimsiz değer KAYDEDİLMEZ, eski değer korunur;
        // kullanıcıya redirect_post_location/admin_notices ile açık hata gösterilir.
        if ( isset( $_POST['rma_price'] ) ) {
            $gecerli_fiyat = $this->sanitize_price_value( wp_unslash( $_POST['rma_price'] ) );
            if ( null === $gecerli_fiyat ) {
                $this->rma_fiyat_gecersiz = true;
            } else {
                update_post_meta( $post_id, 'rma_price', $gecerli_fiyat );
            }
        }

        // Acı seviyesi — beyaz liste. Alan eskiden hiç doğrulanmıyordu; artık
        // tanınmayan bir değer YAZILMAZ, böylece elle/dış araçla girilmiş eski
        // bir kayıt da form kaydında sessizce bozulmaz.
        if ( isset( $_POST[ RMA_Filtre::META_ACI ] ) ) {
            $spicy_posted = sanitize_text_field( $_POST[ RMA_Filtre::META_ACI ] );
            $spicy_allowed = array_map( 'strval', array_keys( RMA_Filtre::aci_seviyeleri() ) );

            if ( '' === $spicy_posted || in_array( $spicy_posted, $spicy_allowed, true ) ) {
                update_post_meta( $post_id, RMA_Filtre::META_ACI, $spicy_posted );
            }
        }
        foreach ( $checkboxes as $cb ) {
            update_post_meta( $post_id, $cb, isset( $_POST[ $cb ] ) ? '1' : '0' );
        }

        // Tükendi, Göster/Gizle'den bağımsız ayrı meta'dır (rma_active ezilmez).
        RMA_Tukendi::kaydet( $post_id, isset( $_POST['rma_tukendi'] ) );

        // Et menşei — whitelist kontrolü
        if ( isset( $_POST['rma_meat_origin'] ) ) {
            $meat_val = sanitize_text_field( $_POST['rma_meat_origin'] );
            if ( array_key_exists( $meat_val, $this->get_meat_origin_options() ) ) {
                update_post_meta( $post_id, 'rma_meat_origin', $meat_val );
            }
        }

        // Alerjenler — taksonomi olarak kaydedilir (hızlı tax_query filtreleme için)
        $allowed_allergens = array_keys( $this->get_allergen_definitions() );
        $posted_allergens   = isset( $_POST['rma_allergens'] ) ? array_map( 'sanitize_text_field', (array) $_POST['rma_allergens'] ) : [];
        $posted_allergens   = array_values( array_intersect( $posted_allergens, $allowed_allergens ) );
        wp_set_object_terms( $post_id, $posted_allergens, 'rma_allergen', false );
    }

    /**
     * Ürün adı boşken WordPress'in post satırını ESKİ hâliyle korur.
     *
     * Mevcut (daha önce başlığı olan) bir ürün için: başlık/içerik/excerpt/
     * durum eskisiyle değiştirilir — kayıt fiilen no-op'a döner, ürün
     * bozulmaz. Henüz hiç kaydedilmemiş (auto-draft) bir ürün içinse durum
     * auto-draft'ta bırakılır — WordPress'in "başlıksız gerçek taslak
     * oluşmaz" kuralıyla tutarlı, sadece içerik/excerpt doluyken de
     * uygulanır (çekirdek yalnızca üçü de boşken bunu yapar).
     *
     * save_menu_item_meta() (generic 'save_post') bu bayrağı görüp meta
     * kaydını da atlar; flag_save_errors_redirect() kullanıcıya açık bir
     * hata gösterir.
     *
     * @param array $data    Kaydedilecek, slashlanmış post alanları.
     * @param array $postarr Ham $_POST tabanlı post dizisi.
     * @return array
     */
    public function block_empty_title_save( $data, $postarr ) {
        if ( 'rma_menu_item' !== ( $data['post_type'] ?? '' ) ) {
            return $data;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $data;
        }
        if ( '' !== trim( wp_strip_all_tags( (string) $data['post_title'] ) ) ) {
            return $data;
        }

        $post_id = ! empty( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
        $eski    = $post_id ? get_post( $post_id ) : null;

        if ( $eski && 'auto-draft' !== $eski->post_status ) {
            $data['post_title']   = $eski->post_title;
            $data['post_content'] = $eski->post_content;
            $data['post_excerpt'] = $eski->post_excerpt;
            $data['post_status']  = $eski->post_status;
            $data['post_name']    = $eski->post_name;
        } else {
            // Henüz kaydedilmemiş (auto-draft) ürün: durum auto-draft'ta kalır
            // VE içerik/excerpt de yazılmaz — aksi hâlde başlıksız gerçek bir
            // taslak oluşmasa da açıklama gibi alanlar görünmez bir auto-draft
            // satırına sızardı (kayıt fiilen kısmen gerçekleşmiş olurdu).
            $data['post_status']  = 'auto-draft';
            $data['post_content'] = '';
            $data['post_excerpt'] = '';
        }

        $this->rma_baslik_gecersiz = true;

        return $data;
    }

    /**
     * Başlık İLE birlikte açıklama ve excerpt de boşken WordPress çekirdeği
     * `wp_insert_post_empty_content` ile kaydı `wp_insert_post_data`'ya HİÇ
     * ULAŞTIRMADAN erkenden durdurur (bkz. wp-includes/post.php). Bu durumda
     * block_empty_title_save() hiç çalışmaz; post satırı zaten dokunulmadan
     * kalır (çekirdek zaten veri kaybını engelliyor), ama redirect_post()
     * yine de "Post published/updated" mesajını basar. Bu kanca yalnızca
     * bayrağı ayarlayıp kullanıcıya doğru geri bildirimi sağlar — çekirdeğin
     * kararını (`$maybe_empty`) DEĞİŞTİRMEZ.
     *
     * @param bool  $maybe_empty Çekirdeğin "içerik boş mu" kararı.
     * @param array $postarr     Ham post dizisi.
     * @return bool Değiştirilmeden geri döner.
     */
    public function flag_empty_content_error( $maybe_empty, $postarr ) {
        if ( $maybe_empty && 'rma_menu_item' === ( $postarr['post_type'] ?? '' )
            && ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        ) {
            $this->rma_baslik_gecersiz = true;
        }
        return $maybe_empty;
    }

    /**
     * post.php'nin standart "kaydedildi" yönlendirmesine, save_menu_item_meta()
     * ve block_empty_title_save() tarafından ayarlanmış hata bayraklarını
     * query parametresi olarak ekler.
     *
     * @param string $location Yönlendirme adresi.
     * @return string
     */
    public function flag_save_errors_redirect( $location ) {
        if ( $this->rma_baslik_gecersiz ) {
            $location = add_query_arg( 'rma_baslik_hata', '1', $location );
        }
        if ( $this->rma_fiyat_gecersiz ) {
            $location = add_query_arg( 'rma_fiyat_hata', '1', $location );
        }
        return $location;
    }

    /**
     * Başlık/fiyat hatası varken WordPress'in "Post updated." vb. yanıltıcı
     * başarı metnini boşaltır; gerçek hata render_save_error_notices() ile
     * ayrı ve net biçimde gösterilir.
     *
     * @param array $messages Çekirdek mesaj dizisi (post_type => [index => metin]).
     * @return array
     */
    public function suppress_success_message_on_error( $messages ) {
        if ( ! isset( $_GET['rma_baslik_hata'] ) && ! isset( $_GET['rma_fiyat_hata'] ) ) {
            return $messages;
        }
        // edit-form-advanced.php yalnızca $messages['rma_menu_item'] TAMAMEN
        // tanımsızsa 'post' dizisine düşer (bkz. wp-admin/edit-form-advanced.php);
        // bu yüzden ilgili indeksi var olsun/olmasın DOĞRUDAN boşaltmak gerekir —
        // aksi hâlde çekirdek "Post published/updated" metnini basmaya devam eder.
        $index = isset( $_GET['message'] ) ? (int) $_GET['message'] : 0;
        $messages['rma_menu_item'][ $index ] = '';
        return $messages;
    }

    /**
     * Ürün ekle/düzenle ekranında başlık/fiyat hatası varsa açık bir uyarı basar.
     *
     * @return void
     */
    public function render_save_error_notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'rma_menu_item' !== $screen->post_type ) {
            return;
        }
        if ( isset( $_GET['rma_baslik_hata'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Ürün adı boş bırakılamaz. Hiçbir bilgi kaydedilmedi; lütfen ürün adını girip tekrar kaydedin.', 'qrms' ) . '</p></div>';
        }
        if ( isset( $_GET['rma_fiyat_hata'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Girilen fiyat geçersiz (negatif veya sayısal olmayan bir değer). Fiyat güncellenmedi, önceki değer korundu.', 'qrms' ) . '</p></div>';
        }
    }
}
