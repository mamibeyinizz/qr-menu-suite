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
            'show_in_menu'  => true,
            'menu_position' => 5,
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

        $fields = [
            'rma_price'       => 'Fiyat (₺)',
            'rma_spicy_level' => 'Acı Seviyesi (0-3)',
            'rma_calories'    => 'Kalori (kcal)',
            'rma_grams'       => 'Gramaj (g)',
            'rma_protein'     => 'Protein (g)',
            'rma_carbs'       => 'Karbonhidrat (g)',
            'rma_fat'         => 'Yağ (g)',
            'rma_prep_time'   => 'Hazırlanış Süresi (dk)',
        ];

        $checkboxes = [
            'rma_is_vegan'          => 'Vegan',
            'rma_is_vegetarian'     => 'Vejetaryen',
            'rma_is_gluten_free'    => 'Glütensiz',
            'rma_badge_popular'     => 'Popüler Rozeti',
            'rma_badge_new'         => 'Yeni Rozeti',
            'rma_badge_recommended' => 'Önerilen Rozeti',
            'rma_badge_discount'    => 'İndirim Rozeti',
            'rma_active'            => 'Aktif Durum (Göster)',
        ];

        echo '<div style="display:flex;flex-wrap:wrap;gap:20px;">';
        foreach ( $fields as $id => $label ) {
            $val  = get_post_meta( $post->ID, $id, true );
            $type = ( $id === 'rma_prep_time' ) ? 'number' : 'text';
            $step = ( $id === 'rma_prep_time' ) ? " step='1' min='0'" : '';
            echo "<div style='flex:1 1 30%;'>
                    <label for='{$id}'><strong>{$label}</strong></label><br>
                    <input type='{$type}'{$step} id='{$id}' name='{$id}' value='" . esc_attr( $val ) . "' style='width:100%;'/>
                  </div>";
        }
        echo '</div><hr><div style="display:flex;flex-wrap:wrap;gap:20px;">';
        foreach ( $checkboxes as $id => $label ) {
            $checked = get_post_meta( $post->ID, $id, true ) === '1' ? 'checked' : '';
            echo "<div style='flex:1 1 20%;'>
                    <label><input type='checkbox' name='{$id}' value='1' {$checked}/> {$label}</label>
                  </div>";
        }
        echo '</div>';

        /* ---- 1 Temmuz 2026 Şeffaf Menü Yönetmeliği Alanları ---- */
        echo '<hr><div style="margin:10px 0 6px;"><strong style="color:#b8860b;">📋 Şeffaf Menü Bilgileri (1 Temmuz 2026 Yönetmeliği)</strong></div>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start;">';

        // Et menşei — tek seçim dropdown
        $meat_val = get_post_meta( $post->ID, 'rma_meat_origin', true );
        echo "<div style='flex:1 1 30%;'>
                <label for='rma_meat_origin'><strong>Et Menşei</strong></label><br>
                <select id='rma_meat_origin' name='rma_meat_origin' style='width:100%;'>";
        foreach ( $this->get_meat_origin_options() as $val => $label ) {
            $sel = selected( $meat_val, $val, false );
            echo "<option value='" . esc_attr( $val ) . "' {$sel}>" . esc_html( $label ) . "</option>";
        }
        echo "</select></div>";

        // Alkol / domuz türevi
        $alcohol_checked = get_post_meta( $post->ID, 'rma_contains_alcohol', true ) === '1' ? 'checked' : '';
        $pork_checked    = get_post_meta( $post->ID, 'rma_contains_pork',    true ) === '1' ? 'checked' : '';
        echo "<div style='flex:1 1 30%;'>
                <label><strong>Diğer</strong></label><br>
                <label style='display:block;margin-top:6px;'><input type='checkbox' name='rma_contains_alcohol' value='1' {$alcohol_checked}/> Alkol İçerir</label>
                <label style='display:block;margin-top:4px;'><input type='checkbox' name='rma_contains_pork' value='1' {$pork_checked}/> Domuz Türevi İçerir</label>
              </div>";

        echo '</div>';

        // Alerjen çoklu seçim — taksonomi tabanlı checklist
        $selected_allergens = wp_get_object_terms( $post->ID, 'rma_allergen', [ 'fields' => 'slugs' ] );
        if ( is_wp_error( $selected_allergens ) ) $selected_allergens = [];

        echo '<div style="margin-top:16px;"><label><strong>Alerjenler</strong></label>
              <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">';
        foreach ( $this->get_allergen_definitions() as $slug => $def ) {
            $chk = in_array( $slug, $selected_allergens, true ) ? 'checked' : '';
            echo "<label style='flex:0 0 auto;border:1px solid #dcdcdc;border-radius:4px;padding:5px 10px;background:#fafafa;'>
                    <input type='checkbox' name='rma_allergens[]' value='" . esc_attr( $slug ) . "' {$chk}/> {$def['icon']} " . esc_html( $def['label'] ) . "
                  </label>";
        }
        echo '</div></div>';
        echo '<p style="color:#777;font-size:12px;margin-top:8px;">Bu bölümdeki bilgiler işletmenin resmi beyanı sayılır. Doğruluğu için uzman/diyetisyen onayı alınması önerilir.</p>';
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

        $fields     = [ 'rma_price', 'rma_spicy_level', 'rma_calories', 'rma_grams', 'rma_protein', 'rma_carbs', 'rma_fat', 'rma_prep_time' ];
        $checkboxes = [ 'rma_is_vegan', 'rma_is_vegetarian', 'rma_is_gluten_free', 'rma_badge_popular', 'rma_badge_new', 'rma_badge_recommended', 'rma_badge_discount', 'rma_active', 'rma_contains_alcohol', 'rma_contains_pork' ];

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
        foreach ( $checkboxes as $cb ) {
            update_post_meta( $post_id, $cb, isset( $_POST[ $cb ] ) ? '1' : '0' );
        }

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
}
