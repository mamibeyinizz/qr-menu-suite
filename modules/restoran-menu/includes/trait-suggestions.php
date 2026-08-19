<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Suggestions_Trait {

    public function ajax_save_suggestions() {
        check_ajax_referer( 'rma_admin_nonce', 'security' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $mode       = sanitize_text_field( $_POST['mode'] ?? 'system' );
        $manual_ids = isset( $_POST['manual_ids'] ) ? array_map( 'intval', (array) $_POST['manual_ids'] ) : [];

        update_option( 'rma_suggestions_settings', [
            'mode'       => $mode,
            'manual_ids' => $manual_ids,
        ] );
        // Öneri bölümü menü çıktısının parçası — önbelleği tazele.
        $this->bump_cache_version();
        wp_send_json_success();
    }

    public function render_suggestions_page() {
        $settings   = get_option( 'rma_suggestions_settings', [] );
        $mode       = $settings['mode']       ?? 'system';
        $manual_ids = $settings['manual_ids'] ?? [];

        /*
         * PERF: Bu liste eskiden ürün başına 3 ek sorgu doğuruyordu
         * (wp_get_post_terms cache'i atlar + öne çıkan görsel + görsel
         * metası). 300 ürünlük bir menüde ~900 sorgu demekti.
         *  - WP_Query ile post/meta/terim cache'leri toplu ısıtılır,
         *  - get_the_terms() cache'ten okur (ek sorgu yok),
         *  - update_post_thumbnail_cache() tüm görselleri tek sorguda alır.
         */
        $items_query = new WP_Query( [
            'post_type'              => 'rma_menu_item',
            'post_status'            => 'publish',
            'posts_per_page'         => (int) apply_filters( 'rma_max_menu_items', 800 ),
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'meta_query'             => [ [ 'key' => 'rma_active', 'value' => '1', 'compare' => '=' ] ],
        ] );

        $all_items = $items_query->posts;
        if ( $all_items && function_exists( 'update_post_thumbnail_cache' ) ) {
            update_post_thumbnail_cache( $items_query );
        }

        $grouped = [];
        foreach ( $all_items as $item ) {
            $terms    = get_the_terms( $item->ID, 'rma_category' );
            $cat_name = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? reset( $terms )->name : 'Kategorisiz';
            $grouped[ $cat_name ][] = $item;
        }
        ksort( $grouped );

        ?>
        <div id="rma-suggestions-saved" class="rma-toast">✔ Ayarlar kaydedildi.</div>

        <div class="rma-card" id="rma-oneriler">
            <h2 class="rma-card-title">Günün Önerileri</h2>
            <p class="rma-card-desc">Menüdeki "Öneriler" bölümünde hangi ürünlerin görüneceğini belirleyin.</p>

            <div class="rma-choice-grid rma-mode-grid">
                <label class="rma-choice rma-choice-inline<?php echo $mode === 'system' ? ' is-selected' : ''; ?>" id="rma-mode-system-wrap">
                    <input type="radio" name="rma_suggestion_mode" value="system" <?php checked( $mode, 'system' ); ?>>
                    <span>
                        <span class="rma-choice-name">Sistem (Otomatik)</span>
                        <span class="rma-choice-sub">Sabah 06–11 "Kahvaltılıklar", akşam 18–23 "Ana Yemekler". Diğer saatlerde "Önerilen" rozetliler.</span>
                    </span>
                </label>
                <label class="rma-choice rma-choice-inline<?php echo $mode === 'manual' ? ' is-selected' : ''; ?>" id="rma-mode-manual-wrap">
                    <input type="radio" name="rma_suggestion_mode" value="manual" <?php checked( $mode, 'manual' ); ?>>
                    <span>
                        <span class="rma-choice-name">Manuel Seç</span>
                        <span class="rma-choice-sub">Önerilecek ürünleri kendiniz belirleyin.</span>
                    </span>
                </label>
            </div>

            <div id="rma-manual-items-wrap"<?php echo $mode === 'manual' ? '' : ' style="display:none;"'; ?>>
                <h3 class="rma-section-title">Önerilecek Ürünleri Seç</h3>
                <?php if ( empty( $grouped ) ) : ?>
                    <p class="rma-empty">Seçilebilecek yayında ürün yok. Önce "Ürün Ekle" sayfasından ürün ekleyin.</p>
                <?php endif; ?>
                <?php foreach ( $grouped as $cat_name => $items ) : ?>
                    <div class="rma-section">
                        <div class="rma-section-title"><?php echo esc_html( $cat_name ); ?></div>
                        <div class="rma-manual-scroll">
                            <?php foreach ( $items as $item ) :
                                $is_checked = in_array( $item->ID, $manual_ids, true );
                                $img        = get_the_post_thumbnail_url( $item->ID, 'thumbnail' ) ?: '';
                                $price      = get_post_meta( $item->ID, 'rma_price', true );
                            ?>
                            <label class="rma-manual-item-row">
                                <input type="checkbox" class="rma-manual-item-cb" value="<?php echo $item->ID; ?>" <?php checked( $is_checked ); ?>>
                                <?php if ( $img ) : ?>
                                    <img src="<?php echo esc_url( $img ); ?>" alt="" class="rma-manual-thumb">
                                <?php else : ?>
                                    <span class="rma-manual-thumb rma-manual-thumb-empty">◆</span>
                                <?php endif; ?>
                                <span class="rma-manual-item-main">
                                    <span class="rma-manual-item-title"><?php echo esc_html( $item->post_title ); ?></span>
                                    <?php if ( $price ) : ?><span class="rma-manual-item-price"><?php echo esc_html( $price ); ?> ₺</span><?php endif; ?>
                                </span>
                                <span class="rma-selected-badge"<?php echo $is_checked ? ' style="display:inline-block;"' : ''; ?>>✔ Seçili</span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="rma-actions">
                <button type="button" id="rma-save-suggestions" class="button button-primary">Ayarları Kaydet</button>
                <span id="rma-save-spinner" class="rma-spinner">Kaydediliyor…</span>
            </div>
        </div>

        <?php
    }

    private function get_suggestion_config() {
        $settings   = get_option( 'rma_suggestions_settings', [] );
        $mode       = $settings['mode']       ?? 'system';
        $manual_ids = $settings['manual_ids'] ?? [];

        if ( $mode === 'manual' ) {
            return [ 'mode' => 'manual', 'manual_ids' => array_map( 'intval', $manual_ids ), 'slug' => '' ];
        }

        $hour = (int) current_time( 'G' );
        if ( $hour >= 6 && $hour < 11 ) {
            $slug = 'kahvaltiliklar';
        } elseif ( $hour >= 18 && $hour < 23 ) {
            $slug = 'ana-yemekler';
        } else {
            $slug = 'populer';
        }

        return [ 'mode' => 'system', 'manual_ids' => [], 'slug' => $slug ];
    }

}
