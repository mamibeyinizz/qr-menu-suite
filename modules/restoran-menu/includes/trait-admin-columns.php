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
        if ( ! isset( $_GET['post'], $_GET['nonce'] ) ) wp_die( 'Güvenlik hatası.' );
        $post_id = intval( $_GET['post'] );
        if ( ! wp_verify_nonce( $_GET['nonce'], 'rma_duplicate_post_' . $post_id ) ) wp_die( 'Güvenlik hatası.' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Yetkiniz yok.' );

        $post = get_post( $post_id );
        if ( ! $post ) wp_die( 'Ürün bulunamadı.' );

        $new_id = wp_insert_post( [
            'post_title'   => $post->post_title . ' (Kopya)',
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => 'publish',
            'post_type'    => $post->post_type,
        ] );

        if ( $new_id ) {
            foreach ( get_post_custom( $post_id ) as $key => $values ) {
                foreach ( $values as $value ) add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
            }
            update_post_meta( $new_id, 'rma_active', '1' );
            RMA_Tukendi::kaydet( $new_id, false );
            foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
                wp_set_object_terms( $new_id, wp_get_object_terms( $post_id, $tax, [ 'fields' => 'slugs' ] ), $tax, false );
            }
            $thumb = get_post_thumbnail_id( $post_id );
            if ( $thumb ) set_post_thumbnail( $new_id, $thumb );
            wp_redirect( admin_url( 'edit.php?post_type=rma_menu_item' ) );
            exit;
        }
        wp_die( 'Çoğaltma başarısız.' );
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
                    <input type="hidden" name="rma_qe_thumbnail_id" class="rma-qe-thumb-id" value="0" />
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

        if ( isset( $_POST['rma_qe_thumbnail_id'] ) ) {
            $thumb_id = absint( wp_unslash( $_POST['rma_qe_thumbnail_id'] ) );
            if ( $thumb_id > 0 ) {
                set_post_thumbnail( $post_id, $thumb_id );
            } else {
                delete_post_thumbnail( $post_id );
            }
        }

        $term_ids = [];
        if ( isset( $_POST['rma_qe_allergens'] ) && is_array( $_POST['rma_qe_allergens'] ) ) {
            $term_ids = array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['rma_qe_allergens'] ) ) ) );
        }
        wp_set_object_terms( $post_id, $term_ids, 'rma_allergen', false );
    }
}
