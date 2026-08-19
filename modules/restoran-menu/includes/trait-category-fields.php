<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Category_Fields_Trait {

    public function add_category_custom_fields() {
        echo '<div class="form-field"><label for="rma_cat_order">Sıra</label><input type="number" name="rma_cat_order" id="rma_cat_order" value="0"></div>';
    }

    public function edit_category_custom_fields( $term ) {
        $o = get_term_meta( $term->term_id, 'rma_cat_order', true );
        echo '<tr class="form-field"><th><label>Sıra</label></th><td><input type="number" name="rma_cat_order" value="' . esc_attr( $o ) . '"></td></tr>';
    }

    public function save_category_custom_fields( $term_id ) {
        if ( isset( $_POST['rma_cat_order'] ) ) {
            update_term_meta( $term_id, 'rma_cat_order', intval( $_POST['rma_cat_order'] ) );
        }
    }
}
