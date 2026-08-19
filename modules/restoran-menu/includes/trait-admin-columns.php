<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Admin_Columns_Trait {

    public function add_admin_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $title ) {
            $new[ $key ] = $title;
            if ( $key === 'title' ) $new['rma_status'] = 'Göster/Gizle';
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
}
