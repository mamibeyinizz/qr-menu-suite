<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Admin_Columns_Trait {

    public function add_admin_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $title ) {
            $new[ $key ] = $title;
            if ( $key === 'title' ) {
                $new['rma_status']  = 'Göster/Gizle';
                $new['rma_tukendi'] = 'Tükendi';
            }
        }
        return $new;
    }

    /**
     * Admin ürün listesini kategoriye göre gruplar.
     * Sıra: Kategori Sıralaması sayfasındaki düzen (rma_cat_order) → kategori adı → ürün adı.
     * Yeni kategori eklenince otomatik senkronize olur (dinamik JOIN).
     * Kullanıcı bir kolon başlığına tıklayıp kendi sıralamasını seçerse müdahale edilmez.
     */
    public function admin_group_by_category( $clauses, $query ) {
        global $pagenow, $wpdb;
        if ( ! is_admin() || $pagenow !== 'edit.php' || ! $query->is_main_query() ) return $clauses;
        if ( $query->get( 'post_type' ) !== 'rma_menu_item' ) return $clauses;
        if ( ! empty( $_GET['orderby'] ) ) return $clauses; // manuel kolon sıralamasını ezme

        $clauses['join'] .= "
            LEFT JOIN {$wpdb->term_relationships} rma_tr ON {$wpdb->posts}.ID = rma_tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} rma_tt ON rma_tr.term_taxonomy_id = rma_tt.term_taxonomy_id AND rma_tt.taxonomy = 'rma_category'
            LEFT JOIN {$wpdb->terms} rma_t ON rma_tt.term_id = rma_t.term_id
            LEFT JOIN {$wpdb->termmeta} rma_tm ON rma_t.term_id = rma_tm.term_id AND rma_tm.meta_key = 'rma_cat_order'
        ";
        $clauses['groupby'] = "{$wpdb->posts}.ID";
        $clauses['orderby'] = "
            ISNULL( MIN( rma_t.name ) ) ASC,
            MIN( COALESCE( CAST( rma_tm.meta_value AS UNSIGNED ), 99999 ) ) ASC,
            MIN( rma_t.name ) ASC,
            {$wpdb->posts}.post_title ASC
        ";
        return $clauses;
    }

    /**
     * Ürün listesini "Tükendi" meta'sına göre daraltır.
     *
     * Genel Bakış analiz şeridindeki "Tükendi Ürün" kutusu `rma_tukendi=1`
     * ile buraya gelir; ayrı bir sayfa değil, filtrelenmiş liste.
     *
     * @param WP_Query $query Ana sorgu.
     * @return void
     */
    public function filter_tukendi_list( $query ) {
        global $pagenow;

        if ( ! is_admin() || $pagenow !== 'edit.php' || ! $query->is_main_query() ) {
            return;
        }
        if ( $query->get( 'post_type' ) !== 'rma_menu_item' ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['rma_tukendi'] ) ) {
            return;
        }

        $query->set( 'meta_key', class_exists( 'RMA_Tukendi' ) ? RMA_Tukendi::META : '_rma_tukendi' );
        $query->set( 'meta_value', '1' );
    }

    /**
     * Ürün listesinin üstüne "Tükendi" görünümü ekler.
     *
     * Sayaç qmo_tukendi_urun_sayisi() ile sol menü rozeti ve Genel Bakış
     * şeridinin aynı kaynağıdır.
     *
     * @param array $views Mevcut görünüm bağlantıları.
     * @return array
     */
    public function tukendi_views( $views ) {
        $sayi = function_exists( 'qmo_tukendi_urun_sayisi' ) ? qmo_tukendi_urun_sayisi() : 0;
        $url  = admin_url( 'edit.php?post_type=rma_menu_item&rma_tukendi=1' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $class = ! empty( $_GET['rma_tukendi'] ) ? 'current' : '';

        $views['rma_tukendi'] = '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">'
            . esc_html__( 'Tükendi', 'qrms' )
            . ' <span class="count">(' . (int) $sayi . ')</span></a>';

        return $views;
    }

    public function render_admin_columns( $column, $post_id ) {
        if ( $column === 'rma_status' ) {
            $status  = get_post_meta( $post_id, 'rma_active', true );
            if ( $status === '' ) $status = '1';
            $checked = $status === '1' ? 'checked' : '';
            echo "<label class='rma-switch'>
                    <input type='checkbox' class='rma-toggle-status' data-id='{$post_id}' {$checked}>
                    <span class='rma-slider round'></span>
                  </label>";
        }

        if ( $column === 'rma_tukendi' ) {
            $checked = RMA_Tukendi::urun_tukendi( $post_id ) ? 'checked' : '';
            echo "<label class='rma-switch rma-switch-tukendi'>
                    <input type='checkbox' class='rma-toggle-tukendi' data-id='{$post_id}' {$checked}>
                    <span class='rma-slider round'></span>
                  </label>";
        }
    }

    /* -----------------------------------------------------------------
       DUPLICATE
    ----------------------------------------------------------------- */
    public function add_duplicate_post_link( $actions, $post ) {
        if ( current_user_can( 'edit_posts' ) && $post->post_type === 'rma_menu_item' ) {
            $nonce = wp_create_nonce( 'rma_duplicate_post_' . $post->ID );
            $url   = admin_url( 'admin.php?action=rma_duplicate_post&post=' . $post->ID . '&nonce=' . $nonce );
            $actions['duplicate'] = '<a href="' . esc_url( $url ) . '" title="Bu ürünü çoğalt">Çoğalt</a>';
        }
        return $actions;
    }

    public function duplicate_post_action() {
        if ( ! isset( $_GET['post'], $_GET['nonce'] ) ) {
            wp_die( 'Güvenlik hatası.' );
        }

        $post_id = absint( wp_unslash( $_GET['post'] ) );
        $nonce   = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );

        if ( $post_id < 1 || ! wp_verify_nonce( $nonce, 'rma_duplicate_post_' . $post_id ) ) {
            wp_die( 'Güvenlik hatası.' );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( 'Yetkiniz yok.' );
        }

        $post = get_post( $post_id );
        if ( ! $post || 'rma_menu_item' !== $post->post_type ) {
            wp_die( 'Ürün bulunamadı.' );
        }

        $new_id = $this->duplicate_menu_item( $post );
        if ( is_wp_error( $new_id ) || (int) $new_id < 1 ) {
            wp_die( 'Çoğaltma başarısız.' );
        }

        wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . (int) $new_id ) );
        exit;
    }

    /**
     * Menü ürününün katalog verisini yeni bir yazıya kopyalar.
     *
     * Kopyalanır: başlık/içerik/özet, ürün meta (fiyat, porsiyon, extra,
     * rozet, kombin ilişkileri…), taksonomiler, öne çıkan görsel (aynı
     * attachment ID — dosya çoğaltılmaz).
     *
     * Kopyalanmaz: düzenleme kilidi, görüntüleme sayacı, thumbnail meta
     * satırının çifti (görsel set_post_thumbnail ile yazılır).
     *
     * Tasarım: kopya menüde görünür (`rma_active=1`) ve stokta kabul edilir.
     *
     * @param WP_Post $post Kaynak ürün.
     * @return int|WP_Error Yeni yazı ID.
     */
    public function duplicate_menu_item( $post ) {
        if ( ! $post || empty( $post->ID ) || 'rma_menu_item' !== $post->post_type ) {
            return new WP_Error( 'rma_duplicate_invalid', 'Ürün bulunamadı.' );
        }

        $new_id = wp_insert_post(
            array(
                'post_title'   => $post->post_title . ' (Kopya)',
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
                'post_status'  => 'publish',
                'post_type'    => 'rma_menu_item',
            ),
            true
        );

        if ( is_wp_error( $new_id ) || (int) $new_id < 1 ) {
            return is_wp_error( $new_id ) ? $new_id : new WP_Error( 'rma_duplicate_insert', 'Çoğaltma başarısız.' );
        }

        $new_id = (int) $new_id;

        foreach ( (array) get_post_custom( $post->ID ) as $key => $values ) {
            if ( $this->duplicate_meta_atlanir( (string) $key ) ) {
                continue;
            }
            foreach ( (array) $values as $value ) {
                add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
            }
        }

        update_post_meta( $new_id, 'rma_active', '1' );
        if ( class_exists( 'RMA_Tukendi' ) ) {
            RMA_Tukendi::kaydet( $new_id, false );
        }

        foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
            $terimler = wp_get_object_terms( $post->ID, $tax, array( 'fields' => 'slugs' ) );
            if ( is_wp_error( $terimler ) ) {
                continue;
            }
            wp_set_object_terms( $new_id, $terimler, $tax, false );
        }

        $thumb = (int) get_post_thumbnail_id( $post->ID );
        if ( $thumb > 0 ) {
            set_post_thumbnail( $new_id, $thumb );
        }

        return $new_id;
    }

    /**
     * Çoğaltmada atlanan meta anahtarları (işlemsel / çekirdek kilitleri).
     *
     * @param string $key Meta anahtarı.
     * @return bool
     */
    private function duplicate_meta_atlanir( $key ) {
        if ( '' === $key ) {
            return true;
        }

        if ( 'rma_views' === $key || '_thumbnail_id' === $key ) {
            return true;
        }

        return ( 0 === strpos( $key, '_edit_' ) || 0 === strpos( $key, '_wp_' ) );
    }

    /* -----------------------------------------------------------------
       HIZLI DÜZENLE — görsel + alerjenler

       WordPress'in hiyerarşik taksonomi kutusu (Menü Kategorileri) aynı
       satırda native gelir; rma_allergen hiyerarşik olmadığı ve
       show_in_quick_edit=false olduğu için çekirdek onu etiket alanı
       olarak basardı. Görsel de çekirdekte yok. İkisi de burada, kategori
       checklist'iyle aynı markup kalıbında eklenir.
    ----------------------------------------------------------------- */

    /**
     * Her ürün satırının gizli #inline_{id} bloğuna görsel ve alerjen
     * verisini yazar. Quick Edit açılınca JS buradan okur (WP'nin kategori
     * ID'lerini .post_category'den okuması ile aynı desen).
     *
     * @param WP_Post $post            Satırdaki yazı.
     * @param mixed   $post_type_object Kullanılmıyor; kanca imzası.
     */
    public function add_quick_edit_inline_data( $post, $post_type_object = null ) {
        if ( ! $post || $post->post_type !== 'rma_menu_item' ) {
            return;
        }

        $thumb_id  = (int) get_post_thumbnail_id( $post->ID );
        $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';
        if ( ! is_string( $thumb_url ) ) {
            $thumb_url = '';
        }

        $allergen_ids = wp_get_object_terms( $post->ID, 'rma_allergen', [ 'fields' => 'ids' ] );
        if ( is_wp_error( $allergen_ids ) ) {
            $allergen_ids = [];
        }
        $allergen_ids = array_map( 'intval', (array) $allergen_ids );

        echo '<div class="rma_thumb_id">' . $thumb_id . '</div>';
        echo '<div class="rma_thumb_url">' . esc_html( $thumb_url ) . '</div>';
        echo '<div class="rma_allergen">' . esc_html( implode( ',', $allergen_ids ) ) . '</div>';
    }

    /**
     * Quick Edit şablonuna görsel seçici ve alerjen checklist'ini basar.
     * Kanca her kolon için tetiklenir; markup bir kez basılır.
     *
     * @param string $column_name Kolon anahtarı.
     * @param string $post_type   Yazı tipi.
     */
    public function render_quick_edit_box( $column_name, $post_type ) {
        if ( $post_type !== 'rma_menu_item' ) {
            return;
        }

        static $printed = false;
        if ( $printed ) {
            return;
        }
        if ( $column_name !== 'rma_status' ) {
            return;
        }
        $printed = true;

        wp_nonce_field( 'rma_quick_edit', 'rma_qe_nonce' );

        $allergen_terms = get_terms( [
            'taxonomy'   => 'rma_allergen',
            'hide_empty' => false,
            'orderby'    => 'name',
        ] );
        if ( is_wp_error( $allergen_terms ) ) {
            $allergen_terms = [];
        }
        ?>
        <fieldset class="inline-edit-col-left rma-qe-fieldset rma-qe-image">
            <div class="inline-edit-col">
                <span class="title"><?php echo esc_html( 'Görsel' ); ?></span>
                <div class="rma-qe-image-controls">
                    <img class="rma-qe-thumb-preview" src="" alt="" width="60" height="60" hidden />
                    <input type="hidden" name="rma_qe_thumbnail_id" class="rma-qe-thumb-id" value="" />
                    <p class="rma-qe-image-buttons">
                        <button type="button" class="button rma-qe-select-image"><?php echo esc_html( 'Görsel Seç' ); ?></button>
                        <button type="button" class="button rma-qe-remove-image" hidden><?php echo esc_html( 'Kaldır' ); ?></button>
                    </p>
                </div>
            </div>
        </fieldset>
        <fieldset class="inline-edit-col-center inline-edit-categories rma-qe-fieldset rma-qe-allergens">
            <div class="inline-edit-col">
                <span class="title inline-edit-categories-label"><?php echo esc_html( 'Alerjenler' ); ?></span>
                <input type="hidden" name="rma_qe_allergens[]" value="0" />
                <ul class="cat-checklist rma_allergen-checklist">
                    <?php foreach ( $allergen_terms as $term ) : ?>
                        <li id="rma_allergen-<?php echo (int) $term->term_id; ?>">
                            <label class="selectit">
                                <input type="checkbox" name="rma_qe_allergens[]" value="<?php echo (int) $term->term_id; ?>" />
                                <?php echo esc_html( $term->name ); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Quick Edit kaydı: featured image ve alerjen terim ID'leri.
     * Tam ürün düzenleme formundan gelmez (nonce yok); o akış
     * save_menu_item_meta() ile slug üzerinden alerjen yazar.
     *
     * @param int $post_id Ürün ID'si.
     */
    public function save_quick_edit_fields( $post_id ) {
        if ( ! isset( $_POST['rma_qe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rma_qe_nonce'] ) ), 'rma_quick_edit' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( isset( $_POST['bulk_edit'] ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( array_key_exists( 'rma_qe_thumbnail_id', $_POST ) ) {
            $thumb_ham = sanitize_text_field( wp_unslash( $_POST['rma_qe_thumbnail_id'] ) );
            // Boş = JS satırı doldurmadı; mevcut görseli koru. "0" = kaldır.
            if ( '' !== $thumb_ham ) {
                $thumb_id = absint( $thumb_ham );
                if ( $thumb_id > 0 ) {
                    if ( 'attachment' === get_post_type( $thumb_id ) ) {
                        set_post_thumbnail( $post_id, $thumb_id );
                    }
                } else {
                    delete_post_thumbnail( $post_id );
                }
            }
        }

        if ( isset( $_POST['rma_qe_allergens'] ) && is_array( $_POST['rma_qe_allergens'] ) ) {
            $term_ids = array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['rma_qe_allergens'] ) ) ) );
            wp_set_object_terms( $post_id, $term_ids, 'rma_allergen', false );
        }
    }
}
