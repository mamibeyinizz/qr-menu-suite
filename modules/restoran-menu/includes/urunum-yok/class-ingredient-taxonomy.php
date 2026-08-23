<?php
/**
 * Malzeme (ingredient) taksonomisi — "Ürünüm Yok" katmanının temel verisi.
 *
 * `rma_category` / `rma_allergen` ile birebir aynı desende, ama TAMAMEN AYRI
 * bir dosyada kurulur: mevcut trait-post-types.php hiç değiştirilmez. Ürün
 * düzenleme ekranında kendi meta kutusunu ekler (mevcut "Ürün Detayları"
 * kutusuna dokunmaz), çoklu seçim checkbox listesi + serbest metinle yeni
 * malzeme ekleme (tag-input benzeri) sağlar.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Ingredient_Taxonomy' ) ) :

class RMA_Ingredient_Taxonomy {

    const TAXONOMY = 'rma_ingredient';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post_rma_menu_item', [ __CLASS__, 'save' ] );
    }

    /**
     * Taksonomi kaydı — `rma_allergen` ile aynı yapı (rma_menu_item'e bağlı,
     * hiyerarşik değil, kendi meta kutusuyla gösterilir).
     */
    public static function register() {
        register_taxonomy( self::TAXONOMY, [ 'rma_menu_item' ], [
            'hierarchical'       => false,
            'labels'             => [
                'name'          => 'Malzemeler',
                'menu_name'     => 'Malzemeler',
                'singular_name' => 'Malzeme',
                'search_items'  => 'Malzemelerde Ara',
                'all_items'     => 'Tüm Malzemeler',
                'edit_item'     => 'Malzemeyi Düzenle',
                'update_item'   => 'Malzemeyi Güncelle',
                'add_new_item'  => 'Yeni Malzeme Ekle',
                'not_found'     => 'Malzeme bulunamadı',
            ],
            'show_ui'            => true,
            'show_admin_column'  => false,
            'show_in_quick_edit' => false,
            'meta_box_cb'        => false, // Kendi meta kutumuzda gösterilir.
            'query_var'          => true,
            'rewrite'            => false,
        ] );
    }

    public static function add_meta_box() {
        add_meta_box(
            'qmo_uy_ingredients',
            'Malzemeler ("Ürünüm Yok")',
            [ __CLASS__, 'render_meta_box' ],
            'rma_menu_item',
            'side',
            'default'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'qmo_uy_ingredients_save', 'qmo_uy_ingredients_nonce' );

        $terms = get_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'orderby' => 'name' ] );
        if ( is_wp_error( $terms ) ) $terms = [];

        $selected = wp_get_object_terms( $post->ID, self::TAXONOMY, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $selected ) ) $selected = [];

        echo '<p class="description">Bu üründe kullanılan malzemeleri işaretleyin. Bir malzeme tükendiğinde "Ürünüm Yok" ekranından bu listeye göre ürünler bulunur.</p>';

        echo '<div id="qmo-uy-liste" style="max-height:220px;overflow:auto;border:1px solid #dcdcde;padding:8px;border-radius:4px;background:#fff;">';
        if ( empty( $terms ) ) {
            echo '<p class="description" id="qmo-uy-bos-mesaj">Henüz malzeme eklenmemiş.</p>';
        }
        foreach ( $terms as $term ) {
            $checked = in_array( $term->term_id, $selected, true ) ? 'checked' : '';
            printf(
                '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="qmo_uy_ingredients[]" value="%1$d" %2$s/> %3$s</label>',
                (int) $term->term_id,
                $checked,
                esc_html( $term->name )
            );
        }
        echo '</div>';

        echo '<p style="margin-top:8px;display:flex;gap:6px;">
                <input type="text" id="qmo-uy-yeni-malzeme" placeholder="Yeni malzeme adı…" style="flex:1;">
                <button type="button" class="button" id="qmo-uy-yeni-malzeme-ekle">Ekle</button>
              </p>';

        // Serbest metinle eklenen isimler kaydedilirken yeni terim olarak
        // oluşturulur (bkz. save()); burada yalnızca listeye görsel checkbox
        // eklenir ve gizli bir input ile isim taşınır.
        ?>
        <script>
        (function () {
            var buton = document.getElementById( 'qmo-uy-yeni-malzeme-ekle' );
            var girdi = document.getElementById( 'qmo-uy-yeni-malzeme' );
            var liste = document.getElementById( 'qmo-uy-liste' );
            if ( ! buton || ! girdi || ! liste ) return;

            buton.addEventListener( 'click', function () {
                var ad = girdi.value.trim();
                if ( '' === ad ) return;

                var bosMesaj = document.getElementById( 'qmo-uy-bos-mesaj' );
                if ( bosMesaj ) bosMesaj.remove();

                var etiket = document.createElement( 'label' );
                etiket.style.display = 'block';
                etiket.style.marginBottom = '4px';

                var kutu = document.createElement( 'input' );
                kutu.type = 'checkbox';
                kutu.checked = true;

                var gizli = document.createElement( 'input' );
                gizli.type = 'hidden';
                gizli.name = 'qmo_uy_yeni_malzemeler[]';
                gizli.value = ad;
                kutu.addEventListener( 'change', function () {
                    gizli.disabled = ! kutu.checked;
                } );

                etiket.appendChild( kutu );
                etiket.appendChild( document.createTextNode( ' ' + ad ) );
                etiket.appendChild( gizli );
                liste.appendChild( etiket );

                girdi.value = '';
                girdi.focus();
            } );

            girdi.addEventListener( 'keydown', function ( e ) {
                if ( 'Enter' === e.key ) {
                    e.preventDefault();
                    buton.click();
                }
            } );
        })();
        </script>
        <?php
    }

    public static function save( $post_id ) {
        if ( ! isset( $_POST['qmo_uy_ingredients_nonce'] ) || ! wp_verify_nonce( $_POST['qmo_uy_ingredients_nonce'], 'qmo_uy_ingredients_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $ids = isset( $_POST['qmo_uy_ingredients'] ) ? array_map( 'intval', (array) $_POST['qmo_uy_ingredients'] ) : [];

        $yeni_adlar = isset( $_POST['qmo_uy_yeni_malzemeler'] ) ? array_map( 'sanitize_text_field', (array) $_POST['qmo_uy_yeni_malzemeler'] ) : [];
        foreach ( $yeni_adlar as $ad ) {
            $ad = trim( $ad );
            if ( '' === $ad ) continue;

            $term = term_exists( $ad, self::TAXONOMY );
            if ( ! $term ) $term = wp_insert_term( $ad, self::TAXONOMY );

            if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
                $ids[] = (int) $term['term_id'];
            }
        }

        wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), self::TAXONOMY, false );
    }
}

endif;
